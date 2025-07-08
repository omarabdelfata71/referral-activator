<?php
/**
 * Referral Threshold Manager
 * This file ensures that the referral threshold is set to 5 and manages user activation based on this threshold
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class Referral_Threshold_Manager
 */
class Referral_Threshold_Manager {
    /**
     * Constructor
     */
    public function __construct() {
        // Set the referral threshold to 5 on plugin activation
        add_action('init', array($this, 'set_referral_threshold'));
        
        // Hook into the referral count update process
        add_action('updated_user_meta', array($this, 'check_referral_count'), 10, 4);
    }
    
    /**
     * Set the referral threshold to 5
     */
    public function set_referral_threshold() {
        // Always set the threshold to 5, regardless of current value
        update_option('ra_referral_threshold', 5);
        
        // Ensure auto-activation is enabled
        update_option('ra_auto_activation', true);
        
        // Log that the threshold has been set
        error_log('Referral threshold has been set to 5 referrals');
    }
    
    /**
     * Check if a user has reached the referral threshold and activate their account if needed
     *
     * @param int    $meta_id    ID of the meta data field
     * @param int    $user_id    User ID
     * @param string $meta_key   Meta key
     * @param mixed  $meta_value Meta value
     */
    public function check_referral_count($meta_id, $user_id, $meta_key, $meta_value) {
        // Only proceed if this is the referral count meta key
        if ($meta_key !== 'ra_referral_count') {
            return;
        }
        
        // Check if the user has reached the threshold
        if ((int) $meta_value >= 5) {
            $user = new WP_User($user_id);
            
            // Only activate if the user is still pending
            if ($user->has_cap('pending_user')) {
                // Change role to subscriber
                $user->set_role('subscriber');
                
                // Send activation email
                $this->send_activation_email($user_id);
            }
        }
    }
    
    /**
     * Send activation email to user
     *
     * @param int $user_id User ID
     */
    private function send_activation_email($user_id) {
        $user = get_user_by('id', $user_id);
        $subject = get_option('ra_activation_email_subject', 'Your account has been activated!');
        $message = get_option('ra_activation_email_message', 'Congratulations! Your account has been activated after successfully referring 5 friends. You can now access all features of our website.');

        wp_mail($user->user_email, $subject, $message);
    }
}

// Initialize the threshold manager
add_action('plugins_loaded', function() {
    new Referral_Threshold_Manager();
});