<?php
/**
 * Class responsible for plugin activation and deactivation
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class RA_Activator {
    /**
     * Plugin activation hook
     */
    public static function activate() {
        self::create_roles();
        self::create_pages();
        self::set_default_options();
    }

    /**
     * Plugin deactivation hook
     */
    public static function deactivate() {
        self::remove_roles();
    }

    /**
     * Create custom roles
     */
    private static function create_roles() {
        add_role(
            'pending_user',
            __('Pending User', 'referral-activator'),
            array(
                'read' => true,
                'level_0' => true
            )
        );
    }

    /**
     * Remove custom roles
     */
    private static function remove_roles() {
        remove_role('pending_user');
    }

    /**
     * Create required pages
     */
    private static function create_pages() {
        // Create pending account page if it doesn't exist
        $pending_page = get_page_by_path('pending-account');
        if (!$pending_page) {
            wp_insert_post(array(
                'post_title' => __('Pending Account', 'referral-activator'),
                'post_content' => __('Your account is pending activation. You need more referrals to activate your account.', 'referral-activator'),
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_name' => 'pending-account'
            ));
        }
    }

    /**
     * Set default plugin options
     */
    private static function set_default_options() {
        $default_options = array(
            'referral_threshold' => 5,
            'auto_activation' => true,
            'activation_email_subject' => __('Your account has been activated!', 'referral-activator'),
            'activation_email_message' => __('Congratulations! Your account has been activated. You can now access all features of our website.', 'referral-activator')
        );

        foreach ($default_options as $option => $value) {
            if (get_option('ra_' . $option) === false) {
                add_option('ra_' . $option, $value);
            }
        }
    }
}