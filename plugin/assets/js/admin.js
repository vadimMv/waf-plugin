jQuery(document).ready(function($) {
    // Handle fetch token button click
    $('#ecom-waf-fetch-token').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var statusEl = $('.ecom-waf-token-status');
        
        // Disable button and show loading state
        button.attr('disabled', true);
        statusEl.html('Fetching token...');
        
        // Get form values
        var clientId = $('#ecom_waf_client_id').val();
        var clientSecret = $('#ecom_waf_client_secret').val();
        var domain = $('#ecom_waf_domain').val() || window.location.hostname;
        
        // Make AJAX request
        $.ajax({
            url: ecomWAF.ajaxurl,
            type: 'POST',
            data: {
                action: 'ecom_waf_fetch_token',
                nonce: ecomWAF.nonce,
                client_id: clientId,
                client_secret: clientSecret,
                domain: domain
            },
            success: function(response) {
                if (response.success) {
                    statusEl.html('<span class="success">Token retrieved successfully!</span>');
                    
                    // Update token status display if exists
                    if ($('#ecom-waf-token-expires').length) {
                        $('#ecom-waf-token-expires').text(response.data.expires || 'Unknown');
                    }
                    
                    // Show success message
                    showNotice('Token retrieved and saved successfully!', 'success');
                } else {
                    statusEl.html('<span class="error">Failed: ' + response.data.message + '</span>');
                    showNotice('Token retrieval failed: ' + response.data.message, 'error');
                }
            },
            error: function(xhr, status, error) {
                statusEl.html('<span class="error">Error: ' + error + '</span>');
                showNotice('Connection error occurred', 'error');
            },
            complete: function() {
                // Re-enable button
                button.attr('disabled', false);
            }
        });
    });
    
    // Helper function to show admin notice
    function showNotice(message, type) {
        var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
        var notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');
        
        // Add notice at the top of the form
        $('.wrap .ecom-waf-settings h1').after(notice);
        
        // Make the notice dismissible
        if (wp.notices && wp.notices.removeDismissed) {
            wp.notices.removeDismissed();
        }
        
        // Auto-remove after 5 seconds
        setTimeout(function() {
            notice.fadeOut(400, function() { 
                $(this).remove();
            });
        }, 5000);
    }
});