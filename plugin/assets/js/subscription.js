/**
 * WAF Protection Subscription JavaScript
 */
(function($) {
    'use strict';
    
    // Initialize when document is ready
    $(document).ready(function() {
        // Checkout button click handler
        $('.ecom-waf-checkout-button').on('click', function(e) {
            e.preventDefault();
            
            const plan = $(this).data('plan');
            const button = $(this);
            
            // Disable button and show loading state
            button.prop('disabled', true).text(ecomWAF.strings.processing);
            
            // Create checkout session
            $.ajax({
                url: ecomWAF.ajax_url,
                type: 'POST',
                data: {
                    action: 'ecom_waf_create_checkout_session',
                    plan: plan,
                    nonce: ecomWAF.nonce
                },
                success: function(response) {
                    if (response.success && response.data.checkout_url) {
                        // Redirect to Stripe Checkout
                        window.location.href = response.data.checkout_url;
                    } else {
                        alert(response.data.message || ecomWAF.strings.error_message);
                        button.prop('disabled', false).text(plan === 'monthly' ? 
                            ecomWAF.strings.subscribe_monthly : 
                            ecomWAF.strings.subscribe_annual);
                    }
                },
                error: function() {
                    alert(ecomWAF.strings.error_message);
                    button.prop('disabled', false).text(plan === 'monthly' ? 
                        ecomWAF.strings.subscribe_monthly : 
                        ecomWAF.strings.subscribe_annual);
                }
            });
        });
        
        // Handle accordion for FAQs
        $('.ecom-waf-accordion-header').on('click', function() {
            const item = $(this).parent('.ecom-waf-accordion-item');
            
            if (item.hasClass('active')) {
                item.removeClass('active');
            } else {
                $('.ecom-waf-accordion-item').removeClass('active');
                item.addClass('active');
            }
        });
        
        // Manage subscription button
        $('.ecom-waf-manage-subscription').on('click', function(e) {
            e.preventDefault();
            // This would typically open a customer portal or similar
            alert('Subscription management will be implemented in a future version.');
        });
        
        // Auto-refresh status for configuring state
        if ($('.ecom-waf-setup-progress').length) {
            setTimeout(function() {
                window.location.reload();
            }, 30000); // Refresh every 30 seconds
        }
    });
})(jQuery);