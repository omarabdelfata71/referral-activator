<?php
/**
 * Uninstall script for Referral Activator plugin
 *
 * This file will be called automatically when the plugin is deleted through the WordPress admin
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove all plugin options
$options = array(
    'ra_referral_threshold',
    'ra_auto_activation',
    'ra_activation_email_subject',
    'ra_activation_email_message'
);

foreach ($options as $option) {
    delete_option($option);
}

// Remove user meta for all users
$users = get_users(array(
    'fields' => 'ID'
));

foreach ($users as $user_id) {
    delete_user_meta($user_id, 'ra_referral_code');
    delete_user_meta($user_id, 'ra_referral_count');
    delete_user_meta($user_id, 'ra_referred_by');
}

// Remove custom role
$role = get_role('pending_user');
if ($role) {
    remove_role('pending_user');
}

// Remove pending-account page
$pending_page = get_page_by_path('pending-account');
if ($pending_page) {
    wp_delete_post($pending_page->ID, true);
}