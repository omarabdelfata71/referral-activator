jQuery(document).ready(function($) {
    // Handle user status update
    $('.ra-activate-user').on('click', function(e) {
        e.preventDefault();
        
        const button = $(this);
        const userId = button.data('user-id');
        const status = button.closest('tr').find('.ra-user-status').val();
        
        button.prop('disabled', true);
        
        $.ajax({
            url: raAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'ra_update_user_status',
                nonce: raAjax.nonce,
                user_id: userId,
                status: status
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    const notice = $('<div class="notice notice-success is-dismissible"><p>' + 
                        (status === 'active' ? 'User activated successfully.' : 'User set to pending status.') + 
                        '</p></div>');
                    $('.wrap h1').after(notice);
                    
                    // Auto-dismiss notice after 3 seconds
                    setTimeout(function() {
                        notice.fadeOut(function() {
                            $(this).remove();
                        });
                    }, 3000);
                } else {
                    alert('Error updating user status: ' + response.data);
                }
            },
            error: function() {
                alert('Error updating user status. Please try again.');
            },
            complete: function() {
                button.prop('disabled', false);
            }
        });
    });
});