/**
 * Cloudflare WAF Dashboard JavaScript
 * 
 * Handles dashboard-specific functionality
 */

(function( $ ) {
    'use strict';

    /**
     * Initialize dashboard
     */
    function initDashboard() {
        // Handle refresh statistics button
        $('#cloudflare-waf-refresh-stats').on('click', refreshStatistics);
        
        // Set up automatic refresh timer if enabled
        if ($('#cloudflare-waf-dashboard-auto-refresh').length && $('#cloudflare-waf-dashboard-auto-refresh').is(':checked')) {
            setAutoRefresh();
        }
    }

    /**
     * Handle refresh statistics
     */
    function refreshStatistics() {
        const $button = $('#cloudflare-waf-refresh-stats');
        
        // Prevent duplicate requests
        if ($button.hasClass('loading')) {
            return;
        }
        
        // Show loading state
        $button.addClass('loading');
        $button.find('.dashicons').addClass('spin');
        $button.find('.cloudflare-waf-button-label').text(cloudflareWafAdmin.strings.refreshing || 'Refreshing...');
        
        // Make AJAX request
        window.CloudflareWAF.ajax({
            action: 'cloudflare_waf_refresh_stats',
            success: function(response) {
                if (response.success) {
                    updateDashboardStats(response.data);
                    window.CloudflareWAF.showNotification(cloudflareWafAdmin.strings.stats_refreshed || 'Statistics refreshed', 'success');
                } else {
                    window.CloudflareWAF.showNotification(response.data.message || cloudflareWafAdmin.strings.error, 'error');
                }
            },
            complete: function() {
                // Reset button state
                $button.removeClass('loading');
                $button.find('.dashicons').removeClass('spin');
                $button.find('.cloudflare-waf-button-label').text(cloudflareWafAdmin.strings.refresh_stats || 'Refresh Statistics');
            }
        });
    }

    /**
     * Update dashboard statistics
     * 
     * @param {Object} data The updated statistics data
     */
    function updateDashboardStats(data) {
        // Update status information
        if (data.status) {
            const status = data.status;
            const $statusBox = $('.cloudflare-waf-status');
            
            // Update status class
            $statusBox.removeClass('active inactive pending issues unknown')
                     .addClass(status.status || 'unknown');
            
            // Update status message
            $statusBox.find('h3').text(getStatusLabel(status.status));
            
            if (status.message) {
                $statusBox.find('p').text(status.message);
            }
            
            // Update status icon
            $statusBox.find('.dashicons')
                     .removeClass('dashicons-shield dashicons-shield-alt dashicons-clock dashicons-warning dashicons-question')
                     .addClass(getStatusIcon(status.status));
        }
        
        // Update statistics
        if (data.stats) {
            const stats = data.stats;
            
            // Update attacks blocked
            if (typeof stats.attacks_blocked !== 'undefined') {
                $('.cloudflare-waf-stat-box:nth-child(1) .cloudflare-waf-stat-value').text(formatNumber(stats.attacks_blocked));
            }
            
            // Update suspicious IPs
            if (typeof stats.suspicious_ips !== 'undefined') {
                $('.cloudflare-waf-stat-box:nth-child(2) .cloudflare-waf-stat-value').text(formatNumber(stats.suspicious_ips));
            }
            
            // Update protection level
            if (typeof stats.protection_level !== 'undefined') {
                $('.cloudflare-waf-stat-box:nth-child(3) .cloudflare-waf-stat-value').text(
                    stats.protection_level.charAt(0).toUpperCase() + stats.protection_level.slice(1)
                );
            }
            
            // Update last attack
            if (typeof stats.last_attack !== 'undefined') {
                $('.cloudflare-waf-stat-box:nth-child(4) .cloudflare-waf-stat-value').text(
                    stats.last_attack > 0 ? formatDateTime(stats.last_attack) : 'None detected'
                );
            }
        }
    }

    /**
     * Get status label based on status code
     * 
     * @param {string} status The status code
     * @return {string} The status label
     */
    function getStatusLabel(status) {
        switch (status) {
            case 'active':
                return 'Active';
            case 'inactive':
                return 'Inactive';
            case 'pending':
                return 'Pending DNS Setup';
            case 'issues':
                return 'Issues Detected';
            default:
                return 'Unknown';
        }
    }

    /**
     * Get status icon based on status code
     * 
     * @param {string} status The status code
     * @return {string} The status icon class
     */
    function getStatusIcon(status) {
        switch (status) {
            case 'active':
                return 'dashicons-shield';
            case 'inactive':
                return 'dashicons-shield-alt';
            case 'pending':
                return 'dashicons-clock';
            case 'issues':
                return 'dashicons-warning';
            default:
                return 'dashicons-question';
        }
    }

    /**
     * Format number for display
     * 
     * @param {number} number The number to format
     * @return {string} Formatted number
     */
    function formatNumber(number) {
        if (number >= 1000000) {
            return Math.round(number / 100000) / 10 + 'M';
        } else if (number >= 1000) {
            return Math.round(number / 100) / 10 + 'K';
        }
        
        return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
    }

    /**
     * Format date/time for display
     * 
     * @param {number} timestamp The timestamp to format
     * @return {string} Formatted date/time
     */
    function formatDateTime(timestamp) {
        const date = new Date(timestamp * 1000);
        return date.toLocaleString();
    }

    /**
     * Set up automatic refresh
     */
    function setAutoRefresh() {
        const refreshInterval = parseInt($('#cloudflare-waf-dashboard-auto-refresh').data('interval')) || 300000; // Default to 5 minutes
        
        // Set interval
        window.cloudflareWafRefreshTimer = setInterval(function() {
            refreshStatistics();
        }, refreshInterval);
        
        // Clear interval when checkbox is unchecked
        $('#cloudflare-waf-dashboard-auto-refresh').on('change', function() {
            if (!$(this).is(':checked') && window.cloudflareWafRefreshTimer) {
                clearInterval(window.cloudflareWafRefreshTimer);
                window.cloudflareWafRefreshTimer = null;
            } else if ($(this).is(':checked') && !window.cloudflareWafRefreshTimer) {
                setAutoRefresh();
            }
        });
    }

    // Run initialization on document ready
    $(document).ready(initDashboard);

})( jQuery );