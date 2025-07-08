<?php
/**
 * Class responsible for handling user authentication and login restrictions
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class RA_Auth {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_filter('login_redirect', array($this, 'handle_login_redirect'), 100, 3);
        add_filter('authenticate', array($this, 'check_user_status'), 30, 3);
    }

    /**
     * Handle login redirection for pending users
     */
    public function handle_login_redirect($redirect_to, $requested_redirect_to, $user) {
        if (!$user || is_wp_error($user)) {
            return $redirect_to;
        }

        if ($user->has_cap('pending_user')) {
            $pending_page = get_page_by_path('pending-account');
            if ($pending_page) {
                return get_permalink($pending_page->ID);
            }
        }

        return $redirect_to;
    }

    /**
     * Check user status during authentication
     */
    public function check_user_status($user, $username, $password) {
        if (!$username || !$password) {
            return $user;
        }

        if (is_wp_error($user)) {
            return $user;
        }

        // Add custom error message for pending users
        if ($user && $user->has_cap('pending_user')) {
            $pending_page = get_page_by_path('pending-account');
            if ($pending_page) {
                $message = sprintf(
                    __('Your account is pending activation. You will be redirected to %s after login.', 'referral-activator'),
                    get_the_title($pending_page->ID)
                );
                add_filter('login_message', function() use ($message) {
                    return '<div class="message">' . esc_html($message) . '</div>';
                });
            }
        }

        return $user;
    }
}