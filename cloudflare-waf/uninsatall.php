<?php
// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('cloudflare_waf_settings');
delete_option('cloudflare_waf_protection_status');
delete_option('cloudflare_waf_client_id');
delete_option('cloudflare_waf_client_secret');

// Delete any transients
delete_transient('cloudflare_waf_activation_redirect');

// Drop custom database tables if they exist
global $wpdb;
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}cloudflare_waf_logs");

// Clear scheduled hooks
wp_clear_scheduled_hook('cloudflare_waf_daily_check');