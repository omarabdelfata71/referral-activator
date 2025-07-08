<?php
/**
 * Class responsible for admin interface and functionality
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class RA_Admin {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_ra_update_user_status', array($this, 'ajax_update_user_status'));
    }

    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        add_users_page(
            __('Referral Activator', 'referral-activator'),
            __('Referral Activator', 'referral-activator'),
            'manage_options',
            'referral-activator',
            array($this, 'render_admin_page')
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('ra_settings', 'ra_referral_threshold');
        register_setting('ra_settings', 'ra_auto_activation');
        register_setting('ra_settings', 'ra_activation_email_subject');
        register_setting('ra_settings', 'ra_activation_email_message');
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        if ('users_page_referral-activator' !== $hook) {
            return;
        }

        wp_enqueue_style('ra-admin-css', RA_PLUGIN_URL . 'admin/css/admin.css', array(), RA_PLUGIN_VERSION);
        wp_enqueue_script('ra-admin-js', RA_PLUGIN_URL . 'admin/js/admin.js', array('jquery'), RA_PLUGIN_VERSION, true);
        
        wp_localize_script('ra-admin-js', 'raAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ra_admin_nonce')
        ));
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'referrals';
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <h2 class="nav-tab-wrapper">
                <a href="?page=referral-activator&tab=referrals" class="nav-tab <?php echo $active_tab == 'referrals' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Referrals', 'referral-activator'); ?>
                </a>
                <a href="?page=referral-activator&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Settings', 'referral-activator'); ?>
                </a>
                <a href="?page=referral-activator&tab=analytics" class="nav-tab <?php echo $active_tab == 'analytics' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Analytics', 'referral-activator'); ?>
                </a>
            </h2>

            <div class="tab-content">
                <?php
                switch ($active_tab) {
                    case 'referrals':
                        $this->render_referrals_tab();
                        break;
                    case 'settings':
                        $this->render_settings_tab();
                        break;
                    case 'analytics':
                        $this->render_analytics_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render referrals management tab
     */
    private function render_referrals_tab() {
        $users = get_users(array(
            'role__in' => array('pending_user', 'subscriber')
        ));
        ?>
        <div class="ra-referrals-table-wrap">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Username', 'referral-activator'); ?></th>
                        <th><?php _e('Referral Code', 'referral-activator'); ?></th>
                        <th><?php _e('Referral Count', 'referral-activator'); ?></th>
                        <th><?php _e('Referred Users', 'referral-activator'); ?></th>
                        <th><?php _e('Status', 'referral-activator'); ?></th>
                        <th><?php _e('Actions', 'referral-activator'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <?php
                        $referral_code = get_user_meta($user->ID, 'ra_referral_code', true);
                        $referral_count = (int) get_user_meta($user->ID, 'ra_referral_count', true);
                        $is_pending = in_array('pending_user', $user->roles);
                        ?>
                        <tr>
                            <td><?php echo esc_html($user->user_login); ?></td>
                            <td><?php echo esc_html($referral_code); ?></td>
                            <td><?php echo esc_html($referral_count); ?></td>
                            <td>
                                <?php
                                $referred_users = get_users(array(
                                    'meta_key' => 'ra_referred_by',
                                    'meta_value' => $user->ID
                                ));
                                if (!empty($referred_users)) {
                                    foreach ($referred_users as $referred_user) {
                                        echo esc_html($referred_user->user_login) . '<br>';
                                    }
                                } else {
                                    _e('None', 'referral-activator');
                                }
                                ?>
                            </td>
                            <td>
                                <select class="ra-user-status" data-user-id="<?php echo esc_attr($user->ID); ?>">
                                    <option value="pending" <?php selected($is_pending, true); ?>>
                                        <?php _e('Pending', 'referral-activator'); ?>
                                    </option>
                                    <option value="active" <?php selected($is_pending, false); ?>>
                                        <?php _e('Active', 'referral-activator'); ?>
                                    </option>
                                </select>
                            </td>
                            <td>
                                <button class="button ra-activate-user" data-user-id="<?php echo esc_attr($user->ID); ?>">
                                    <?php _e('Update Status', 'referral-activator'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render settings tab
     */
    private function render_settings_tab() {
        ?>
        <form method="post" action="options.php">
            <?php
            settings_fields('ra_settings');
            ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Referral Threshold', 'referral-activator'); ?></th>
                    <td>
                        <input type="number" name="ra_referral_threshold" value="<?php echo esc_attr(get_option('ra_referral_threshold', 5)); ?>" min="1">
                        <p class="description"><?php _e('Number of referrals needed for account activation', 'referral-activator'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Auto Activation', 'referral-activator'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="ra_auto_activation" value="1" <?php checked(get_option('ra_auto_activation', true)); ?>>
                            <?php _e('Automatically activate accounts when threshold is reached', 'referral-activator'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Activation Email Subject', 'referral-activator'); ?></th>
                    <td>
                        <input type="text" name="ra_activation_email_subject" value="<?php echo esc_attr(get_option('ra_activation_email_subject')); ?>" class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Activation Email Message', 'referral-activator'); ?></th>
                    <td>
                        <textarea name="ra_activation_email_message" rows="5" class="large-text"><?php echo esc_textarea(get_option('ra_activation_email_message')); ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Available Shortcodes', 'referral-activator'); ?></th>
                    <td>
                        <p><code>[referral_link]</code> - <?php _e('Displays the user\'s referral link', 'referral-activator'); ?></p>
                        <p><code>[referral_status]</code> - <?php _e('Displays the user\'s referral status and count', 'referral-activator'); ?></p>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        <?php
    }

    /**
     * Render analytics tab
     */
    private function render_analytics_tab() {
        $total_users = count_users();
        $pending_users = count_users()['avail_roles']['pending_user'] ?? 0;
        $active_users = count_users()['avail_roles']['subscriber'] ?? 0;

        // Get top referrers
        $users = get_users(array(
            'meta_key' => 'ra_referral_count',
            'orderby' => 'meta_value_num',
            'order' => 'DESC',
            'number' => 10
        ));
        ?>
        <div class="ra-analytics-wrap">
            <div class="ra-analytics-cards">
                <div class="ra-analytics-card">
                    <h3><?php _e('Total Users', 'referral-activator'); ?></h3>
                    <p class="ra-big-number"><?php echo esc_html($total_users['total_users']); ?></p>
                </div>
                <div class="ra-analytics-card">
                    <h3><?php _e('Pending Users', 'referral-activator'); ?></h3>
                    <p class="ra-big-number"><?php echo esc_html($pending_users); ?></p>
                </div>
                <div class="ra-analytics-card">
                    <h3><?php _e('Active Users', 'referral-activator'); ?></h3>
                    <p class="ra-big-number"><?php echo esc_html($active_users); ?></p>
                </div>
            </div>

            <div class="ra-leaderboard">
                <h3><?php _e('Top Referrers', 'referral-activator'); ?></h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Username', 'referral-activator'); ?></th>
                            <th><?php _e('Referral Count', 'referral-activator'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <?php $referral_count = get_user_meta($user->ID, 'ra_referral_count', true); ?>
                            <?php if ($referral_count > 0): ?>
                                <tr>
                                    <td><?php echo esc_html($user->user_login); ?></td>
                                    <td><?php echo esc_html($referral_count); ?></td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler for updating user status
     */
    public function ajax_update_user_status() {
        check_ajax_referer('ra_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'referral-activator'));
        }

        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

        if (!$user_id || !in_array($status, array('pending', 'active'))) {
            wp_send_json_error(__('Invalid parameters', 'referral-activator'));
        }

        $user = new WP_User($user_id);
        $new_role = $status === 'active' ? 'subscriber' : 'pending_user';
        $user->set_role($new_role);

        if ($status === 'active') {
            RA_User_Management::get_instance()->activate_user($user_id);
        }

        wp_send_json_success();
    }
}