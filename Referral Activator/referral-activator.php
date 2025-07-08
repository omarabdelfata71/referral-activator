<?php
/**
 * Plugin Name: Referral Activator
 * Plugin URI: 
 * Description: Control user account activation based on referrals, integrate with BuddyPress, restrict page access, and provide referral analytics.
 * Version: 1.0.0
 * Author: Omar Helal
 * Author URI: 
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: referral-activator
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('RA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RA_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RA_PLUGIN_VERSION', '1.0.0');

// Include required files
require_once RA_PLUGIN_DIR . 'includes/class-ra-activator.php';
require_once RA_PLUGIN_DIR . 'includes/class-ra-user-management.php';
require_once RA_PLUGIN_DIR . 'includes/class-ra-shortcodes.php';
require_once RA_PLUGIN_DIR . 'includes/class-ra-auth.php';
require_once RA_PLUGIN_DIR . 'includes/class-ra-redirect.php';
require_once RA_PLUGIN_DIR . 'admin/class-ra-admin.php';

class Referral_Activator {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Initialize plugin components
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    public function init() {
        // Load text domain for internationalization
        load_plugin_textdomain('referral-activator', false, dirname(plugin_basename(__FILE__)) . '/languages');

        // Initialize components
        RA_User_Management::get_instance();
        RA_Shortcodes::get_instance();
        RA_Auth::get_instance();
        RA_Redirect::get_instance();

        if (is_admin()) {
            RA_Admin::get_instance();
        }
    }

    public function activate() {
        RA_Activator::activate();
    }

    public function deactivate() {
        RA_Activator::deactivate();
    }
}

// Initialize the plugin
Referral_Activator::get_instance();