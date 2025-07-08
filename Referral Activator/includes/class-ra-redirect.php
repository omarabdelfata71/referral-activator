<?php
/**
 * Class responsible for handling redirections for pending users
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class RA_Redirect {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Handle specific page redirections for pending users
        add_action('template_redirect', array($this, 'handle_specific_redirects'), 30);
    }

    /**
     * Handle specific page redirections for pending users
     */
    public function handle_specific_redirects() {
        // Only check for logged in users
        if (!is_user_logged_in() || is_admin()) {
            return;
        }

        $user = wp_get_current_user();
        if (!$user->has_cap('pending_user')) {
            return;
        }

        // Define restricted pages/sections that should redirect to pending account page
        $restricted_sections = array(
            'activity',
            'members',
            'groups',
            'profile'
        );

        // Get current URL path
        $current_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
        $path_segments = explode('/', $current_path);

        // Check if current path contains any restricted sections
        foreach ($restricted_sections as $restricted) {
            if (in_array($restricted, $path_segments)) {
                $pending_page = get_page_by_path('pending-account');
                if ($pending_page) {
                    wp_safe_redirect(get_permalink($pending_page->ID));
                    exit;
                }
            }
        }
    }
}

// Don't initialize here as it will be initialized in the main plugin file
// RA_Redirect::get_instance();