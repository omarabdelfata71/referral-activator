jQuery(document).ready(function($) {
    // Function to update referral status
    function updateReferralStatus() {
        const statusContainer = $('.referral-status');
        if (!statusContainer.length) return;

        const userId = statusContainer.data('user-id');
        
        // Use WordPress AJAX
        wp.ajax.post('check_referral_status', {
            user_id: userId,
            _ajax_nonce: referralSystem.nonce
        }).done(function(response) {
            if (response.status === 'pending') {
                statusContainer.find('p:first').html(
                    'Your account is pending activation. You need ' + 
                    response.remaining + ' more referral(s) to activate your account.'
                );
            } else {
                statusContainer.html(
                    '<h3>Your Referral Status</h3>' +
                    '<p>Your account is active! Thank you for your referrals.</p>' +
                    '<p>Total successful referrals: ' + response.referral_count + '</p>'
                );
                
                // Reload page if status changed to active
                if (window.location.href.indexOf('activation=pending') > -1) {
                    window.location.href = '/';
                }
            }
        }).fail(function(error) {
            console.error('Failed to update referral status:', error);
        });
    }

    // Update status every 30 seconds if user is pending
    if ($('.referral-status').length && window.location.href.indexOf('activation=pending') > -1) {
        setInterval(updateReferralStatus, 30000);
    }

    // Copy referral link to clipboard using modern Clipboard API
    $('.copy-referral-link').on('click', async function() {
        const button = $(this);
        const referralLink = button.data('referral-link');
        
        try {
            await navigator.clipboard.writeText(referralLink);
            const originalText = button.text();
            button.text('Copied!');
            
            // Show success feedback
            button.css('background-color', '#4CAF50').css('color', 'white');
            
            setTimeout(function() {
                button.text(originalText);
                button.css('background-color', '').css('color', '');
            }, 2000);
        } catch (err) {
            console.error('Failed to copy:', err);
            button.text('Failed to copy');
            button.css('background-color', '#f44336').css('color', 'white');
            
            setTimeout(function() {
                button.text('Copy Link');
                button.css('background-color', '').css('color', '');
            }, 2000);
        }
    });
});