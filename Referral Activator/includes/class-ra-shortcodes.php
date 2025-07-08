<?php
/**
 * Class responsible for managing shortcodes
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class RA_Shortcodes {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_shortcode('referral_link', array($this, 'referral_link_shortcode'));
        add_shortcode('referral_status', array($this, 'referral_status_shortcode'));
    }

    /**
     * Shortcode to display user's referral link
     */
    public function referral_link_shortcode($atts) {
        if (!is_user_logged_in()) {
            return __('Please log in to view your referral link.', 'referral-activator');
        }

        $user_id = get_current_user_id();
        $referral_code = get_user_meta($user_id, 'ra_referral_code', true);

        if (!$referral_code) {
            return __('Referral code not found.', 'referral-activator');
        }

        $referral_url = add_query_arg('ref', $referral_code, home_url());
        
        ob_start();
        ?>
        <div class="referral-link-wrapper">
            <p class="referral-link-label"><?php _e('Your Referral Link:', 'referral-activator'); ?></p>
            <input type="text" class="referral-link-input" value="<?php echo esc_url($referral_url); ?>" readonly onclick="this.select();" style="width: 100%;">

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Shortcode to display user's referral status
     */
    public function referral_status_shortcode($atts) {
        if (!is_user_logged_in()) {
            return __('Please log in to view your referral status.', 'referral-activator');
        }

        $user_id = get_current_user_id();
        $referral_count = (int) get_user_meta($user_id, 'ra_referral_count', true);
        $threshold = (int) get_option('ra_referral_threshold', 5);
        $remaining = max(0, $threshold - $referral_count);

        $user = new WP_User($user_id);
        $status = $user->has_cap('pending_user') ? 'pending' : 'active';

        ob_start();
        ?>
        <div class="ra-referral-status">
            <div class="ra-status-current">
                <p>
                    <?php 
                    printf(
                        __('Current Status: %s', 'referral-activator'),
                        $status === 'pending' ? __('Pending', 'referral-activator') : __('Active', 'referral-activator')
                    );
                    ?>
                </p>
            </div>
            <div class="ra-status-count">
                <p>
                    <?php
                    printf(
                        __('Your Referrals: %d', 'referral-activator'),
                        $referral_count
                    );
                    ?>
                </p>
            </div>
            <?php if ($status === 'pending'): ?>
            <div class="ra-status-requirement">
                <p>
                    <?php
                    printf(
                        __('Referrals needed for activation: %d', 'referral-activator'),
                        $remaining
                    );
                    ?>
                </p>
            </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}