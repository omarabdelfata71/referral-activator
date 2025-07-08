<?php
/**
 * Template for the pending account page content
 * This file contains the content that will be displayed on the pending-account page
 * It includes the referral shortcodes from the Referral Activator plugin
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Get current user
$current_user = wp_get_current_user();
$user_id = $current_user->ID;

// Get referral threshold
$threshold = (int) get_option('ra_referral_threshold', 5);
$referral_count = (int) get_user_meta($user_id, 'ra_referral_count', true);
$remaining = max(0, $threshold - $referral_count);
?>

<div class="pending-account-container">
    <h2><?php _e('Your Account is Pending Activation', 'referral-activator'); ?></h2>
    
    <div class="pending-account-message">
    <p><?php _e('Thank you for registering! Your account is currently pending activation. To activate your account, you need to get 5 friends to register using your referral link.', 'referral-activator'); ?></p>
</div>
    
    <div class="referral-status-container">
        <h3><?php _e('Your Referral Status', 'referral-activator'); ?></h3>
        <?php echo do_shortcode('[referral_status]'); ?>
    </div>
    
    <div class="referral-link-container">
        <h3><?php _e('Your Referral Link', 'referral-activator'); ?></h3>
        <p><?php _e('Share this link with your friends to activate your account:', 'referral-activator'); ?></p>
        <?php echo do_shortcode('[referral_link]'); ?>
        
        <div class="referral-instructions">
            <h4><?php _e('How it works:', 'referral-activator'); ?></h4>
            <ol>
                <li><?php _e('Copy your unique referral link above', 'referral-activator'); ?></li>
                <li><?php printf(__('Share it with %d friends', 'referral-activator'), $threshold); ?></li>
                <li><?php _e('When they register using your link, you get credit', 'referral-activator'); ?></li>
                <li><?php _e('Once you reach the required number of referrals, your account will be automatically activated', 'referral-activator'); ?></li>
            </ol>
        </div>
    </div>
    
    <div class="social-sharing">
        <h3><?php _e('Share via Social Media', 'referral-activator'); ?></h3>
        <?php 
        $referral_code = get_user_meta($user_id, 'ra_referral_code', true);
        $referral_url = add_query_arg('ref', $referral_code, home_url());
        $share_text = urlencode(__('Join me on this website using my referral link:', 'referral-activator'));
        ?>
        
        <div class="social-buttons">
            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($referral_url); ?>" target="_blank" class="social-button facebook">
                <?php _e('Share on Facebook', 'referral-activator'); ?>
            </a>
            
            <a href="https://twitter.com/intent/tweet?text=<?php echo $share_text; ?>&url=<?php echo urlencode($referral_url); ?>" target="_blank" class="social-button twitter">
                <?php _e('Share on Twitter', 'referral-activator'); ?>
            </a>
            
            <a href="https://wa.me/?text=<?php echo $share_text . ' ' . urlencode($referral_url); ?>" target="_blank" class="social-button whatsapp">
                <?php _e('Share on WhatsApp', 'referral-activator'); ?>
            </a>
            
            <a href="mailto:?subject=<?php echo urlencode(__('Join me on this website', 'referral-activator')); ?>&body=<?php echo $share_text . ' ' . urlencode($referral_url); ?>" class="social-button email">
                <?php _e('Share via Email', 'referral-activator'); ?>
            </a>
        </div>
    </div>
</div>

<style>
.pending-account-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
    background-color: #f9f9f9;
    border-radius: 5px;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
}

.pending-account-container h2 {
    color: #333;
    border-bottom: 2px solid #ddd;
    padding-bottom: 10px;
    margin-bottom: 20px;
}

.pending-account-message {
    background-color: #e7f5ff;
    border-left: 4px solid #0073aa;
    padding: 15px;
    margin-bottom: 20px;
}

.referral-status-container,
.referral-link-container {
    margin-bottom: 30px;
    padding: 15px;
    background-color: #fff;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.referral-instructions {
    background-color: #f5f5f5;
    padding: 15px;
    margin-top: 20px;
    border-radius: 4px;
}

.social-sharing {
    margin-top: 30px;
}

.social-buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 15px;
}

.social-button {
    display: inline-block;
    padding: 10px 15px;
    border-radius: 4px;
    color: #fff;
    text-decoration: none;
    font-weight: bold;
    text-align: center;
    min-width: 120px;
}

.social-button.facebook {
    background-color: #3b5998;
}

.social-button.twitter {
    background-color: #1da1f2;
}

.social-button.whatsapp {
    background-color: #25d366;
}

.social-button.email {
    background-color: #777;
}

.social-button:hover {
    opacity: 0.9;
}

.ra-referral-link input {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    margin-bottom: 10px;
}

@media (max-width: 600px) {
    .social-buttons {
        flex-direction: column;
    }
    
    .social-button {
        width: 100%;
    }
}
</style>