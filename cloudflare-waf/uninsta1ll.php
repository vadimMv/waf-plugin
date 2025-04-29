<?php
/**
 * Uninstall E-Commerce WAF Protection Plugin
 *
 * @package EcommerceWAF
 */

// If uninstall is not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete options
delete_option('ecom_waf_settings');

// Remove scheduled events
wp_clear_scheduled_hook('ecom_waf_daily_check');

// Remove any capabilities
$admin = get_role('administrator');
if ($admin) {
    $admin->remove_cap('manage_waf_protection');
}

// Optional: Remove any database tables if you created them
global $wpdb;
// $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}ecom_waf_logs");