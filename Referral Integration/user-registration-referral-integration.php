<?php
/**
 * Plugin Name: User Registration Referral Integration
 * Plugin URI: 
 * Description: Integrates User Registration Pro with Referral Activator to make new accounts pending and require referrals for activation.
 * Version: 1.0.0
 * Author: Omar Helal
 * Author URI: 
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: user-registration-referral-integration
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('URRI_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('URRI_PLUGIN_URL', plugin_dir_url(__FILE__));
define('URRI_PLUGIN_VERSION', '1.0.0');

/**
 * Check if required plugins and components are available
 */
function urri_check_dependencies() {
    $missing_plugins = array();
    
    if (!class_exists('UR_Form_Handler')) {
        $missing_plugins[] = 'User Registration Pro';
    }
    
    if (!class_exists('Referral_Activator')) {
        $missing_plugins[] = 'Referral Activator';
    }
    
    if (!empty($missing_plugins)) {
        add_action('admin_notices', function() use ($missing_plugins) {
            echo '<div class="notice notice-error"><p>';
            echo sprintf(
                __('User Registration Referral Integration requires the following plugins: %s. Please install and activate them.', 'user-registration-referral-integration'),
                '<strong>' . implode(', ', $missing_plugins) . '</strong>'
            );
            echo '</p></div>';
        });
        return false;
    }
    return true;
}

/**
 * Initialize plugin components
 */
function urri_init() {
    if (!urri_check_dependencies()) {
        return;
    }

    try {
        // Load required files
        $required_files = array(
            'referral-cookie-handler.php' => 'Referral_Cookie_Handler',
        );

        foreach ($required_files as $file => $class) {
            $file_path = URRI_PLUGIN_DIR . $file;
            if (!file_exists($file_path)) {
                throw new Exception("Required component file {$file} is missing.");
            }
            require_once $file_path;
            
            if (!class_exists($class)) {
                throw new Exception("{$class} class not found after including {$file}.");
            }
        }

        // Initialize components
        new Referral_Cookie_Handler();
        new User_Registration_Referral_Integration();

        // Load the pending page updater
        if (file_exists(URRI_PLUGIN_DIR . 'update-pending-page.php')) {
            require_once URRI_PLUGIN_DIR . 'update-pending-page.php';
            if (function_exists('update_pending_account_page')) {
                add_action('init', 'update_pending_account_page');
            }
        }
    } catch (Exception $e) {
        error_log('User Registration Referral Integration Error: ' . $e->getMessage());
        add_action('admin_notices', function() use ($e) {
            echo '<div class="notice notice-error"><p>';
            echo 'User Registration Referral Integration Error: ' . esc_html($e->getMessage());
            echo '</p></div>';
        });
    }
}

// Initialize the plugin
add_action('plugins_loaded', 'urri_init');

/**
 * Class User_Registration_Referral_Integration
 */
class User_Registration_Referral_Integration {
    /**
     * Constructor
     */
    public function __construct() {
        // Hook into User Registration Pro's registration process
        add_action('user_registration_after_register_user_action', array($this, 'set_user_as_pending'), 9, 3);
        
        // Override the redirect URL after registration to point to the pending account page
        add_filter('user_registration_form_redirect_url', array($this, 'redirect_to_referral_page'), 10, 2);
        
        // Prevent auto-login after registration
        add_filter('user_registration_auto_login_after_registration', array($this, 'prevent_auto_login'), 10, 2);
        
        // Handle referral links and redirect to registration page
        // Using priority 1 to ensure this runs before other redirects
        add_action('template_redirect', array($this, 'handle_referral_links'), 1);
    }
    
    /**
     * Set user as pending after registration
     *
     * @param array $form_data Form data.
     * @param int $form_id Form ID.
     * @param int $user_id User ID.
     */
    public function set_user_as_pending($form_data, $form_id, $user_id) {
        // Get the user object
        $user = new WP_User($user_id);
        
        // Set the user role to pending_user
        $user->set_role('pending_user');
        
        // Generate a referral code for the user if not already set by Referral Activator
        if (!get_user_meta($user_id, 'ra_referral_code', true)) {
            $referral_code = $this->generate_referral_code($user_id);
            update_user_meta($user_id, 'ra_referral_code', $referral_code);
            update_user_meta($user_id, 'ra_referral_count', 0);
        }
        
        // Check if user was referred - check multiple sources
        $referrer_code = '';
        
        // First check cookie
        if (isset($_COOKIE['ra_referral'])) {
            $referrer_code = sanitize_text_field($_COOKIE['ra_referral']);
        }
        
        // If not in cookie, check session
        if (empty($referrer_code) && isset($_SESSION['ra_referral'])) {
            $referrer_code = sanitize_text_field($_SESSION['ra_referral']);
        }
        
        // Check URL parameter as a last resort
        if (empty($referrer_code) && isset($_GET['ref'])) {
            $referrer_code = sanitize_text_field($_GET['ref']);
        }
        
        // Process the referral if we found a code
        if (!empty($referrer_code)) {
            $referrer_id = $this->get_user_by_referral_code($referrer_code);
            
            if ($referrer_id) {
                update_user_meta($user_id, 'ra_referred_by', $referrer_id);
                $this->increment_referral_count($referrer_id);
                
                // Log successful referral for debugging
                error_log('Referral processed: User ' . $user_id . ' was referred by user ' . $referrer_id . ' with code ' . $referrer_code);
            }
        }
    }
    
    /**
     * Redirect to the pending account page after registration
     * while preserving the referral parameter
     *
     * @param string $redirect_url The redirect URL.
     * @param int $form_id The form ID.
     * @return string
     */
    public function redirect_to_referral_page($redirect_url, $form_id) {
        // Get the pending account page
        $pending_page = get_page_by_path('pending-account');
        
        if ($pending_page) {
            $pending_url = get_permalink($pending_page->ID);
            
            // Check if there's a referral in the cookie or session
            $referral_code = '';
            if (isset($_COOKIE['ra_referral'])) {
                $referral_code = sanitize_text_field($_COOKIE['ra_referral']);
            } elseif (isset($_SESSION['ra_referral'])) {
                $referral_code = sanitize_text_field($_SESSION['ra_referral']);
            }
            
            // Append the referral code to the redirect URL if it exists
            if (!empty($referral_code)) {
                $pending_url = add_query_arg('ref', $referral_code, $pending_url);
            }
            
            return $pending_url;
        }
        
        return $redirect_url;
    }
    
    /**
     * Prevent auto-login after registration
     *
     * @param bool $auto_login Whether to auto-login.
     * @param int $form_id The form ID.
     * @return bool
     */
    public function prevent_auto_login($auto_login, $form_id) {
        // Always prevent auto-login
        return false;
    }
    
    /**
     * Generate unique referral code
     *
     * @param int $user_id User ID.
     * @return string
     */
    private function generate_referral_code($user_id) {
        $user = get_user_by('id', $user_id);
        if (!$user) {
            return false;
        }
        $base = sanitize_title($user->user_login);
        $unique_hash = substr(md5($user_id . time() . wp_rand()), 0, 8);
        return $base . '-' . $unique_hash;
    }
    
    /**
     * Get user by referral code
     *
     * @param string $code Referral code.
     * @return int|bool
     */
    private function get_user_by_referral_code($code) {
        $users = get_users(array(
            'meta_key' => 'ra_referral_code',
            'meta_value' => $code,
            'number' => 1
        ));

        return !empty($users) ? $users[0]->ID : false;
    }
    
    /**
     * Increment user's referral count
     *
     * @param int $user_id User ID.
     */
    private function increment_referral_count($user_id) {
        $current_count = (int) get_user_meta($user_id, 'ra_referral_count', true);
        $new_count = $current_count + 1;
        update_user_meta($user_id, 'ra_referral_count', $new_count);

        // Check for auto-activation
        // Always use 5 as the threshold, regardless of the option value
        $threshold = 5;
        if ($new_count >= $threshold) {
            $this->activate_user($user_id);
        }
    }
    
    /**
     * Activate user account
     *
     * @param int $user_id User ID.
     */
    private function activate_user($user_id) {
        $user = new WP_User($user_id);
        if ($user->has_cap('pending_user')) {
            $user->set_role('subscriber');
            $this->send_activation_email($user_id);
        }
    }
    
    /**
     * Send activation email
     *
     * @param int $user_id User ID.
     */
    private function send_activation_email($user_id) {
        $user = get_user_by('id', $user_id);
        $subject = get_option('ra_activation_email_subject', 'Your account has been activated!');
        $message = get_option('ra_activation_email_message', 'Congratulations! Your account has been activated. You can now access all features of our website.');

        wp_mail($user->user_email, $subject, $message);
    }
    
    /**
     * Handle referral links and redirect to registration page
     */
    public function handle_referral_links() {
        // Check if this is a referral link (has 'ref' parameter)
        if (isset($_GET['ref']) && !empty($_GET['ref'])) {
            // Store the referral code in cookie and session for later use
            $referral_code = sanitize_text_field($_GET['ref']);
            
            // Set cookie with HTTP only flag and secure flag if HTTPS
            $secure = is_ssl();
            $expiry = time() + (30 * DAY_IN_SECONDS);
            
            // Use the traditional format for better compatibility with older PHP versions
            setcookie('ra_referral', $referral_code, $expiry, COOKIEPATH, COOKIE_DOMAIN, $secure, true);
            
            // Log cookie setting
            error_log('Setting referral cookie with expiry: ' . date('Y-m-d H:i:s', $expiry));
            
            if (!session_id()) {
                session_start();
            }
            $_SESSION['ra_referral'] = $referral_code;
            
            // Log referral code for debugging
            error_log('Referral link detected with code: ' . $referral_code);
            
            // Only redirect if user is not logged in
            if (!is_user_logged_in()) {
                // Get the registration page URL from User Registration settings
                $registration_page_id = get_option('user_registration_registration_page_id');
                $registration_url = '';
                
                if ($registration_page_id) {
                    $registration_url = get_permalink($registration_page_id);
                    error_log('Found registration page ID: ' . $registration_page_id . ' with URL: ' . $registration_url);
                } else {
                    // Try to find the registration page by title or slug
                    $registration_page = get_page_by_path('register');
                    if (!$registration_page) {
                        $registration_page = get_page_by_title('Register');
                    }
                    
                    if ($registration_page) {
                        $registration_url = get_permalink($registration_page->ID);
                        error_log('Found registration page by path/title with URL: ' . $registration_url);
                    } else {
                        // Fallback to default registration page
                        $registration_url = home_url('/register/');
                        error_log('Using fallback registration URL: ' . $registration_url);
                    }
                }
                
                // Add the referral code to the registration URL
                $redirect_url = add_query_arg('ref', $referral_code, $registration_url);
                
                // Log the redirect attempt
                error_log('Redirecting to registration page: ' . $redirect_url);
                
                // Perform redirect with status code 302 (temporary redirect)
                wp_redirect($redirect_url, 302);
                exit;
            }
        }
    }
}

// The plugin initialization is now handled by the loader plugin
// This prevents duplicate initialization when both plugins are active
// Do not remove this file as it contains the main integration class