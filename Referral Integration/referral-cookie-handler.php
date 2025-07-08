<?php
/**
 * Referral System Handler
 * This file handles the referral system functionality including registration and activation
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class Referral_Cookie_Handler {
    private $referral_table;
    private $required_referrals = 3;

    public function __construct() {
        if (!function_exists('is_plugin_active')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }

        // Check for required plugins
        if (!is_plugin_active('user-registration-pro/user-registration.php')) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>The Referral System requires User Registration Pro plugin to be active.</p></div>';
            });
            return;
        }

        global $wpdb;
        $this->referral_table = $wpdb->prefix . 'user_referrals';

        // Create tables immediately to avoid race conditions
        $this->create_tables();

        // Initialize core functionality
        add_action('init', array($this, 'init_referral_system'), 1);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'), 99);
        
        // User management hooks
        add_action('user_register', array($this, 'handle_user_registration'), 10, 1);
        add_action('wp_login', array($this, 'check_user_activation'), 10, 2);
        add_action('template_redirect', array($this, 'restrict_pending_users'));
        
        // Frontend features
        add_shortcode('referral_status', array($this, 'referral_status_shortcode'));
        add_shortcode('referral_count', array($this, 'referral_count_shortcode'));
        
        // AJAX handlers
        add_action('wp_ajax_check_referral_status', array($this, 'check_referral_status'));
        add_action('wp_ajax_nopriv_check_referral_status', array($this, 'check_referral_status'));
        add_action('wp_ajax_get_referral_count', array($this, 'ajax_get_referral_count'));
        add_action('wp_ajax_nopriv_get_referral_count', array($this, 'ajax_get_referral_count'));
    }
    
    public function enqueue_scripts() {
        try {
            // Only enqueue on frontend
            if (is_admin()) {
                return;
            }

            // Check if script file exists
            $script_path = plugin_dir_path(__FILE__) . 'js/referral-system.js';
            if (!file_exists($script_path)) {
                error_log('Referral system script not found: ' . $script_path);
                return;
            }

            // Get file modification time for versioning
            $version = filemtime($script_path);

            // Enqueue core dependencies
            wp_enqueue_script('jquery');
            wp_enqueue_script('wp-util');

            // Enqueue our referral system script with proper dependencies
            wp_enqueue_script(
                'referral-system',
                plugins_url('js/referral-system.js', __FILE__),
                array('jquery', 'wp-util'),
                $version,
                true
            );

            // Localize script with necessary data
            wp_localize_script('referral-system', 'referralSystem', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('referral-system-nonce'),
                'homeUrl' => home_url(),
                'isUserLoggedIn' => is_user_logged_in(),
                'requiredReferrals' => $this->required_referrals
            ));

        } catch (Exception $e) {
            error_log('Error enqueueing referral system scripts: ' . $e->getMessage());
        }
    }

    private function create_tables() {
        try {
            global $wpdb;
            
            if (!function_exists('dbDelta')) {
                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            }
            
            $charset_collate = $wpdb->get_charset_collate();
            
            // Create the referrals table
            $sql = "CREATE TABLE IF NOT EXISTS $this->referral_table (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                referral_code varchar(32) NOT NULL,
                referrer_id bigint(20) DEFAULT NULL,
                status varchar(20) DEFAULT 'pending',
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY referral_code (referral_code),
                KEY user_id (user_id),
                KEY referrer_id (referrer_id),
                KEY status (status)
            ) $charset_collate;";
            
            // Execute table creation and capture any errors
            $results = dbDelta($sql);
            
            // Verify table exists
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$this->referral_table'") === $this->referral_table;
            
            if (!$table_exists) {
                throw new Exception('Failed to create referral system database table.');
            }
            
            // Log table creation results
            error_log('Referral system table creation results: ' . print_r($results, true));
            
        } catch (Exception $e) {
            error_log('Error creating referral system tables: ' . $e->getMessage());
            throw $e; // Re-throw to be caught by the initialization handler
        }
    }

    public function init_referral_system() {
        // Check if we're on a referral URL path
        $request_uri = $_SERVER['REQUEST_URI'];
        if (preg_match('#^/sign-up/([^/]+)/?$#', $request_uri, $matches)) {
            $username = sanitize_text_field($matches[1]);
            error_log('REFERRAL SYSTEM: Processing username from URL: ' . $username);
            
            // Get referral code for username
            global $wpdb;
            $referral_code = $wpdb->get_var($wpdb->prepare(
                "SELECT referral_code FROM $this->referral_table 
                 INNER JOIN {$wpdb->users} ON {$wpdb->users}.ID = $this->referral_table.user_id 
                 WHERE {$wpdb->users}.user_login = %s",
                $username
            ));
            
            if ($referral_code) {
                error_log('REFERRAL SYSTEM: Found referral code: ' . $referral_code);
                
                // Store referral code in cookie with secure settings
                $secure = true;
                $expiry = time() + (30 * DAY_IN_SECONDS);
                setcookie('ra_referral', $referral_code, [
                    'expires' => $expiry,
                    'path' => COOKIEPATH,
                    'domain' => COOKIE_DOMAIN,
                    'secure' => $secure,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]);
                
                // Redirect to the registration form page
                $registration_url = home_url('/sign-up/');
                error_log('REFERRAL SYSTEM: Redirecting to registration form: ' . $registration_url);
                wp_redirect($registration_url);
                exit();
            } else {
                error_log('REFERRAL SYSTEM: No referral code found for username: ' . $username);
                wp_redirect(home_url('/sign-up/'));
                exit();
            }
        }
        
        // Handle legacy ref parameter for backward compatibility
        $ref_param = filter_input(INPUT_GET, 'ref', FILTER_SANITIZE_STRING);
        if (!empty($ref_param)) {
            $referral_code = sanitize_text_field($ref_param);
            error_log('REFERRAL SYSTEM: Processing referral code from query param: ' . $referral_code);
            
            // Store referral code in cookie
            setcookie('ra_referral', $referral_code, [
                'expires' => time() + (30 * DAY_IN_SECONDS),
                'path' => COOKIEPATH,
                'domain' => COOKIE_DOMAIN,
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
    }

    public function handle_user_registration($user_id) {
        global $wpdb;
        
        // Generate unique referral code for new user
        $referral_code = $this->generate_referral_code();
        
        // Check if user was referred
        $referrer_code = isset($_COOKIE['ra_referral']) ? sanitize_text_field($_COOKIE['ra_referral']) : '';
        $referrer_id = null;
        
        if (!empty($referrer_code)) {
            $referrer = $wpdb->get_row($wpdb->prepare(
                "SELECT user_id FROM $this->referral_table WHERE referral_code = %s",
                $referrer_code
            ));
            
            if ($referrer) {
                $referrer_id = $referrer->user_id;
                
                // Insert new user's referral record with confirmed status
                $wpdb->insert(
                    $this->referral_table,
                    array(
                        'user_id' => $user_id,
                        'referral_code' => $referral_code,
                        'referrer_id' => $referrer_id,
                        'status' => 'confirmed'
                    ),
                    array('%d', '%s', '%d', '%s')
                );
                
                // Update referrer's count and check if they should be activated
                $referral_count = $this->update_referral_count($referrer_id);
                if ($referral_count >= $this->required_referrals) {
                    update_user_meta($referrer_id, 'account_status', 'active');
                    
                    // Send email notification to referrer
                    $referrer_user = get_user_by('id', $referrer_id);
                    if ($referrer_user) {
                        $subject = 'Your account has been activated!';
                        $message = 'Congratulations! You have successfully referred ' . $this->required_referrals . ' users. Your account is now active.';
                        wp_mail($referrer_user->user_email, $subject, $message);
                    }
                }
            } else {
                // If no referrer found, create normal record
                $wpdb->insert(
                    $this->referral_table,
                    array(
                        'user_id' => $user_id,
                        'referral_code' => $referral_code,
                        'status' => 'pending'
                    ),
                    array('%d', '%s', '%s')
                );
            }
        } else {
            // If no referral code, create normal record
            $wpdb->insert(
                $this->referral_table,
                array(
                    'user_id' => $user_id,
                    'referral_code' => $referral_code,
                    'status' => 'pending'
                ),
                array('%d', '%s', '%s')
            );
        }
        
        // Set new user as pending until they get enough referrals
        update_user_meta($user_id, 'account_status', 'pending');
    }

    private function update_referral_count($user_id) {
        global $wpdb;
        
        // Count confirmed referrals from the database
        $referral_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $this->referral_table WHERE referrer_id = %d AND status = 'confirmed'",
            $user_id
        ));
        
        // Update the user meta with the current count
        update_user_meta($user_id, 'referral_count', $referral_count);
        
        return (int)$referral_count;
    }

    private function generate_referral_code($length = 8) {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = '';
        
        for ($i = 0; $i < $length; $i++) {
            $code .= $characters[rand(0, strlen($characters) - 1)];
        }
        
        return $code;
    }

    private function is_registration_page() {
        // Get current URL with protocol and host
        $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $current_path = parse_url($current_url, PHP_URL_PATH);
        
        // Normalize the path
        $current_path = trim($current_path, '/');
        
        // Direct path comparison (case-insensitive)
        $registration_paths = array('sign-up', 'signup', 'register', 'registration');
        foreach ($registration_paths as $path) {
            if (stripos($current_path, $path) !== false) {
                error_log('REGISTRATION PAGE: Detected via path match: ' . $current_path);
                return true;
            }
        }
        
        // Check if we're already on tartariasports.com/sign-up/
        if (stripos($current_url, 'tartariasports.com/sign-up') !== false) {
            error_log('REGISTRATION PAGE: Detected tartariasports.com/sign-up');
            return true;
        }
        
        // Check WordPress page detection
        global $post;
        if ($post instanceof WP_Post) {
            $slug = $post->post_name;
            if (in_array($slug, $registration_paths)) {
                error_log('REGISTRATION PAGE: Detected via post slug: ' . $slug);
                return true;
            }
        }
        
        // Check User Registration plugin page
        $ur_page_id = get_option('user_registration_registration_page_id');
        if ($ur_page_id && is_page($ur_page_id)) {
            error_log('REGISTRATION PAGE: Detected via User Registration plugin page ID: ' . $ur_page_id);
            return true;
        }
        
        error_log('REGISTRATION PAGE: Not on registration page. Current URL: ' . $current_url);
        return false;
    }

    public function check_referral_status() {
        check_ajax_referer('referral-system-nonce');
        
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        if (!$user_id) {
            wp_send_json_error(new WP_Error('invalid_user', 'Invalid user ID'));
            return;
        }
        
        $referral_count = $this->update_referral_count($user_id);
        $status = get_user_meta($user_id, 'account_status', true);
        
        $response = array(
            'status' => $status ?: 'pending',
            'referral_count' => $referral_count
        );
        
        if ($status === 'pending') {
            $response['remaining'] = max(0, $this->required_referrals - $referral_count);
        }
        
        wp_send_json($response);
    }
    
    public function check_user_activation($user_login, $user) {
        $status = get_user_meta($user->ID, 'account_status', true);
        
        if ($status === 'pending') {
            wp_logout();
            wp_redirect(home_url('/sign-up/?activation=pending'));
            exit();
        }
    }
    
    public function restrict_pending_users() {
        if (!is_user_logged_in()) {
            return;
        }
        
        $user = wp_get_current_user();
        $status = get_user_meta($user->ID, 'account_status', true);
        
        // Get current URL to check for activation parameter
        $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $is_activation_page = strpos($current_url, 'activation=pending') !== false;
        
        if ($status === 'pending' && !$this->is_registration_page() && !$is_activation_page) {
            wp_redirect(home_url('/sign-up/?activation=pending'));
            exit();
        }
    }
    
    public function referral_status_shortcode() {
        if (!is_user_logged_in()) {
            return '<p>Please log in to view your referral status.</p>';
        }
        
        $user_id = get_current_user_id();
        $user_info = get_userdata($user_id);
        $username = $user_info->user_login;
        $status = get_user_meta($user_id, 'account_status', true);
        $referral_count = $this->update_referral_count($user_id);
        $remaining = max(0, $this->required_referrals - $referral_count);
        
        ob_start();
        ?>
        <div class="referral-status" data-user-id="<?php echo esc_attr($user_id); ?>">
            <h3>Your Referral Status</h3>
            <?php if ($status === 'pending'): ?>
                <p>Your account is pending activation. You need <?php echo $remaining; ?> more referral(s) to activate your account.</p>
                <p>Share your referral link: <strong><?php echo esc_url('https://tartariasports.com/sign-up/' . $username); ?></strong></p>
                <button class="copy-referral-link" data-referral-link="<?php echo esc_url('https://tartariasports.com/sign-up/' . $username); ?>">Copy Link</button>
            <?php else: ?>
                <p>Your account is active! Thank you for your referrals.</p>
                <p>Total successful referrals: <?php echo $referral_count; ?></p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private function get_user_referral_code($user_id) {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare(
            "SELECT referral_code FROM $this->referral_table WHERE user_id = %d",
            $user_id
        ));
    }
    /**
     * Check if a referral code is valid
     *
     * @param string $code Referral code
     * @return bool
     */
    private function is_valid_referral_code($code) {
        if (empty($code)) {
            error_log('Empty referral code provided');
            return false;
        }

        error_log('Validating referral code: ' . $code);

        // Look up the referral code in the database
        $users = get_users(array(
            'meta_key' => 'ra_referral_code',
            'meta_value' => $code,
            'number' => 1
        ));

        if (!empty($users)) {
            error_log('Valid referral code found for code: ' . $code . ', user ID: ' . $users[0]->ID);
            return true;
        } else {
            error_log('No users found with referral code: ' . $code);
            // For debugging, check if any users have referral codes at all
            $sample_users = get_users(array(
                'meta_key' => 'ra_referral_code',
                'number' => 3
            ));
            
            if (!empty($sample_users)) {
                error_log('Sample valid referral codes exist in the system');
            } else {
                error_log('No users with any referral codes found in the system');
            }
            
            return false;
        }
    }
    
    /**
     * Check if we're currently on the registration page
     */
    private function is_on_registration_page() {
        // Check current URL path
        $current_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $current_path = parse_url($current_url, PHP_URL_PATH);
        
        // Check if path matches sign-up page
        if (strpos($current_path, '/sign-up') !== false) {
            return true;
        }
        
        // Check WordPress page detection
        if (is_page('sign-up') || is_page('signup') || is_page('register') || is_page('registration')) {
            return true;
        }
        
        // Check User Registration plugin page
        $ur_page_id = get_option('user_registration_registration_page_id');
        if ($ur_page_id && is_page($ur_page_id)) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Redirect to registration page with referral code
     * 
     * @param string $referral_code The referral code to include in the URL
     */
    private function redirect_to_registration_page($referral_code) {
        // Use hardcoded URL to the sign-up page
        $registration_url = 'https://tartariasports.com/sign-up/';
        $redirect_url = add_query_arg('ref', $referral_code, $registration_url);
        
        error_log('REDIRECT: Attempting redirect to: ' . $redirect_url);
        
        // Use wp_redirect for WordPress compatibility
        if (!headers_sent()) {
            wp_redirect($redirect_url, 302);
            exit();
        }
        
        // Fallback JavaScript redirect
        echo "<script type='text/javascript'>window.location.replace('" . esc_js($redirect_url) . "');</script>";
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . esc_url($redirect_url) . '" /></noscript>';
        exit();
    }
}

// Initialize the cookie handler
function init_referral_cookie_handler() {
    try {
        new Referral_Cookie_Handler();
    } catch (Exception $e) {
        error_log('Failed to initialize Referral Cookie Handler: ' . $e->getMessage());
        add_action('admin_notices', function() use ($e) {
            echo '<div class="error"><p>Failed to initialize Referral System: ' . esc_html($e->getMessage()) . '</p></div>';
        });
    }
}

add_action('plugins_loaded', 'init_referral_cookie_handler', 20);