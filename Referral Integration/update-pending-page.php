<?php
/**
 * Update Pending Account Page
 * This script updates the pending-account page with the custom content
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Update the pending account page with custom content
 */
function update_pending_account_page() {
    // Get the pending account page
    $pending_page = get_page_by_path('pending-account');
    
    if (!$pending_page) {
        // Create the page if it doesn't exist
        $page_id = wp_insert_post(array(
            'post_title'    => __('Pending Account', 'referral-activator'),
            'post_content'  => '',
            'post_status'   => 'publish',
            'post_type'     => 'page',
            'post_name'     => 'pending-account'
        ));
    } else {
        $page_id = $pending_page->ID;
    }
    
    // Get the content from our template file
    $content = file_get_contents(dirname(__FILE__) . '/pending-account-content.php');
    
    // Update the page with our content
    wp_update_post(array(
        'ID'           => $page_id,
        'post_content' => $content
    ));
    
    return $page_id;
}

// Run the update function
add_action('init', 'update_pending_account_page');