/**
 * Cloudflare WAF Admin JavaScript
 * 
 * Main admin JavaScript file with shared functionality
 */

(function( $ ) {
    'use strict';

    /**
     * Initialize admin functionality
     */
    function initAdmin() {
        // Initialize tooltips if available
        if (typeof $.fn.tooltip === 'function') {
            $('.cloudflare-waf-tooltip').tooltip();
        }
        
        // Initialize tabs
        initTabs();
        
        // Initialize notification system
        initNotifications();
    }

    /**
     * Initialize tabbed interfaces
     */
    function initTabs() {
        $('.cloudflare-waf-tabs').each(function() {
            const $tabContainer = $(this);
            const $tabs = $tabContainer.find('.cloudflare-waf-tab');
            const $tabContents = $tabContainer.next('.cloudflare-waf-tab-contents').find('.cloudflare-waf-tab-content');
            
            // Set the first tab as active by default
            if (!$tabs.filter('.active').length) {
                $tabs.first().addClass('active');
                $tabContents.first().addClass('active');
            }
            
            // Handle tab clicks
            $tabs.on('click', function(e) {
                e.preventDefault();
                
                const $tab = $(this);
                const tabId = $tab.data('tab');
                
                // Set active tab
                $tabs.removeClass('active');
                $tab.addClass('active');
                
                // Show active content
                $tabContents.removeClass('active');
                $tabContents.filter('[data-tab="' + tabId + '"]').addClass('active');
                
                // Update URL hash if needed
                if ($tabContainer.data('remember')) {
                    window.location.hash = tabId;
                }
            });
            
            // Check URL hash on load
            if ($tabContainer.data('remember') && window.location.hash) {
                const tabId = window.location.hash.substring(1);
                const $hashTab = $tabs.filter('[data-tab="' + tabId + '"]');
                
                if ($hashTab.length) {
                    $hashTab.trigger('click');
                }
            }
        });
    }

    /**
     * Initialize notification system
     */
    function initNotifications() {
        window.CloudflareWAF = window.CloudflareWAF || {};
        
        /**
         * Show a notification
         * 
         * @param {string} message The message to display
         * @param {string} type The type of notification (success, error, warning, info)
         * @param {number} duration How long to show the notification (milliseconds)
         */
        window.CloudflareWAF.showNotification = function(message, type, duration) {
            type = type || 'info';
            duration = duration || 3000;
            
            // Create notification element if it doesn't exist
            let $container = $('.cloudflare-waf-notifications');
            
            if (!$container.length) {
                $container = $('<div class="cloudflare-waf-notifications"></div>');
                $('body').append($container);
            }
            
            // Create notification
            const $notification = $(
                '<div class="cloudflare-waf-notification cloudflare-waf-notification-' + type + '">' +
                '<span class="cloudflare-waf-notification-message">' + message + '</span>' +
                '<button type="button" class="cloudflare-waf-notification-close">&times;</button>' +
                '</div>'
            );
            
            // Add to container
            $container.append($notification);
            
            // Set timeout to remove
            setTimeout(function() {
                $notification.addClass('removing');
                
                setTimeout(function() {
                    $notification.remove();
                }, 300);
            }, duration);
            
            // Handle close button
            $notification.find('.cloudflare-waf-notification-close').on('click', function() {
                $notification.addClass('removing');
                
                setTimeout(function() {
                    $notification.remove();
                }, 300);
            });
        };
    }

    /**
     * Handle AJAX requests with consistent error handling
     * 
     * @param {Object} options jQuery AJAX options
     * @return {jqXHR} The jQuery XHR object
     */
    function handleAjax(options) {
        // Default error handler
        const defaultErrorHandler = function(xhr, textStatus, errorThrown) {
            let errorMessage = cloudflareWafAdmin.strings.error;
            
            if (xhr.responseJSON && xhr.responseJSON.message) {
                errorMessage = xhr.responseJSON.message;
            } else if (errorThrown) {
                errorMessage = errorThrown;
            }
            
            window.CloudflareWAF.showNotification(errorMessage, 'error');
        };
        
        // Set up defaults
        const settings = $.extend({
            url: cloudflareWafAdmin.ajax_url,
            type: 'POST',
            dataType: 'json',
            error: defaultErrorHandler
        }, options);
        
        // Add nonce if not provided
        if (!settings.data) {
            settings.data = {};
        }
        
        if (typeof settings.data === 'object' && !settings.data.nonce) {
            settings.data.nonce = cloudflareWafAdmin.nonce;
        }
        
        // Return the AJAX request
        return $.ajax(settings);
    }

    // Expose the AJAX handler to the global CloudflareWAF object
    window.CloudflareWAF = window.CloudflareWAF || {};
    window.CloudflareWAF.ajax = handleAjax;

    // Run initialization on document ready
    $(document).ready(initAdmin);

})( jQuery );