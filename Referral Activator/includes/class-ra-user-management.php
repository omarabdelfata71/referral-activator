<?php
/**
 * Class responsible for user management and referral tracking
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class RA_User_Management {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('user_register', array($this, 'handle_user_registration'), 10, 1);
        add_action('init', array($this, 'check_page_access'));
        add_action('template_redirect', array($this, 'handle_pending_user_access'), 10);
    }

    /**
     * Handle new user registration
     */
    public function handle_user_registration($user_id) {
        // Set initial role as pending_user
        $user = new WP_User($user_id);
        $user->set_role('pending_user');

        // Generate and save referral code with retry mechanism
        $max_attempts = 3;
        $attempt = 0;
        $referral_code = false;

        while (!$referral_code && $attempt < $max_attempts) {
            $generated_code = $this->generate_referral_code($user_id);
            if ($generated_code && !$this->referral_code_exists($generated_code)) {
                $referral_code = $generated_code;
            }
            $attempt++;
        }

        if (!$referral_code) {
            // Fallback to a guaranteed unique code
            $referral_code = 'user-' . $user_id . '-' . substr(md5(time() . wp_rand()), 0, 8);
        }

        update_user_meta($user_id, 'ra_referral_code', $referral_code);
        update_user_meta($user_id, 'ra_referral_count', 0);

        // Check if user was referred - check both cookie and session
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
            }
        }
    }

    /**
     * Generate unique referral code
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
     * Check if a referral code already exists
     */
    private function referral_code_exists($code) {
        return (bool) $this->get_user_by_referral_code($code);
    }

    /**
     * Increment user's referral count
     */
    private function increment_referral_count($user_id) {
        $current_count = (int) get_user_meta($user_id, 'ra_referral_count', true);
        $new_count = $current_count + 1;
        update_user_meta($user_id, 'ra_referral_count', $new_count);

        // Check for auto-activation
        if (get_option('ra_auto_activation', true)) {
            $threshold = get_option('ra_referral_threshold', 5);
            if ($new_count >= $threshold) {
                $this->activate_user($user_id);
            }
        }
    }

    /**
     * Activate user account
     */
    public function activate_user($user_id) {
        $user = new WP_User($user_id);
        if ($user->has_cap('pending_user')) {
            $user->set_role('subscriber');
            $this->send_activation_email($user_id);
            
            // Redirect to activity page after activation
            if (!is_admin()) {
                wp_redirect('https://tartariasports.com/activity/');
                exit;
            }
        }
    }

    /**
     * Send activation email
     */
    private function send_activation_email($user_id) {
        $user = get_user_by('id', $user_id);
        $subject = get_option('ra_activation_email_subject');
        $message = get_option('ra_activation_email_message');

        wp_mail($user->user_email, $subject, $message);
    }

    /**
     * Check page access for pending users
     */
    public function check_page_access() {
        if (!is_user_logged_in()) {
            return;
        }

        $user = wp_get_current_user();
        if ($user->has_cap('pending_user')) {
            add_filter('the_content', array($this, 'restrict_content'));
        }
    }

    /**
     * Handle access control for pending users
     */
    public function handle_pending_user_access() {
        if (!is_user_logged_in() || is_admin()) {
            return;
        }

        $user = wp_get_current_user();
        if (!$user->has_cap('pending_user')) {
            return;
        }

        // Get the pending account page
        $pending_page = get_page_by_path('pending-account');
        if (!$pending_page) {
            return;
        }

        // Allow access to pending account page and logout
        if (is_page($pending_page->ID) || is_page('logout') || strpos($_SERVER['REQUEST_URI'], 'logout') !== false) {
            return;
        }

        // Allow access to assets and AJAX requests
        if (wp_doing_ajax() || preg_match('/\.(css|js|png|jpg|jpeg|gif|ico|svg)$/i', $_SERVER['REQUEST_URI'])) {
            return;
        }

        // Redirect to pending account page for all other pages
        wp_safe_redirect(get_permalink($pending_page->ID));
        exit;
    }

    /**
     * Restrict content for pending users
     */
    public function restrict_content($content) {
        $pending_page = get_page_by_path('pending-account');
        if (is_page() && get_the_ID() === $pending_page->ID) {
            return $content;
        }
        return __('Your account is pending activation. Please check the pending account page for more information.', 'referral-activator');
    }
}