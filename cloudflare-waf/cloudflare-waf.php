<?php
/**
 * Plugin Name: Cloudflare WAF Protection
 * Plugin URI: https://example.com/cloudflare-waf
 * Description: Protect your WordPress site from attacks using Cloudflare's Web Application Firewall (WAF)
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://example.com
 * Text Domain: cloudflare-waf
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'CLOUDFLARE_WAF_VERSION', '1.0.0' );
define( 'CLOUDFLARE_WAF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CLOUDFLARE_WAF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CLOUDFLARE_WAF_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// API worker URLs - Replace these with your actual worker URLs
define( 'CLOUDFLARE_WAF_AUTH_WORKER_URL', 'https://auth.your-workers.dev' );
define( 'CLOUDFLARE_WAF_CF_WORKER_URL', 'https://cloudflare-api.your-workers.dev' );
define( 'CLOUDFLARE_WAF_PAYMENT_WORKER_URL', 'https://payment.your-workers.dev' );

/**
 * The code that runs during plugin activation.
 */
function activate_cloudflare_waf() {
    // Create required directories
    $directories = [
        'includes/core',
        'includes/api',
        'includes/db',
        'includes/admin',
        'includes/services',
        'assets/css',
        'assets/js',
        'assets/images',
        'templates/admin',
        'templates/wizard',
        'languages'
    ];
    
    foreach ( $directories as $dir ) {
        $full_path = CLOUDFLARE_WAF_PLUGIN_DIR . $dir;
        
        if ( ! file_exists( $full_path ) ) {
            wp_mkdir_p( $full_path );
        }
    }
    
    // Set activation flag to redirect to setup wizard
    set_transient( 'cloudflare_waf_activation_redirect', true, 30 );
    
    // Initialize logger if available
    if ( class_exists( 'CloudflareWAF_Logger' ) ) {
        $logger = new CloudflareWAF_Logger();
        $logger->info( 'Plugin activated' );
    }
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_cloudflare_waf() {
    // Clean up schedules
    wp_clear_scheduled_hook( 'cloudflare_waf_daily_check' );
    
    // Initialize logger if available
    if ( class_exists( 'CloudflareWAF_Logger' ) ) {
        $logger = new CloudflareWAF_Logger();
        $logger->info( 'Plugin deactivated' );
    }
}

register_activation_hook( __FILE__, 'activate_cloudflare_waf' );
register_deactivation_hook( __FILE__, 'deactivate_cloudflare_waf' );

/**
 * Autoload classes
 */
// spl_autoload_register( function( $class ) {
//     // Only load our plugin classes
//     if ( strpos( $class, 'CloudflareWAF_' ) !== 0 ) {
//         return;
//     }
    
//     // Convert class name to file path
//     $class_name = str_replace( 'CloudflareWAF_', '', $class );
//     $class_name = str_replace( '_', '-', $class_name );
//     $class_file = 'class-' . strtolower( $class_name ) . '.php';
    
//     // Check in each directory for the class file
//     $directories = [
//         'core',
//         'api',
//         'db',
//         'admin',
//         'services'
//     ];
    
//     foreach ( $directories as $dir ) {
//         $file_path = CLOUDFLARE_WAF_PLUGIN_DIR . 'includes/' . $dir . '/' . $class_file;
        
//         if ( file_exists( $file_path ) ) {
//             require_once $file_path;
//             return;
//         }
//     }
// } );
// Modify your spl_autoload_register function to include logging
spl_autoload_register( function( $class ) {
    // Create a log file in your plugin directory
    $log_file = CLOUDFLARE_WAF_PLUGIN_DIR . 'autoload_log.txt';
    
    // Log the class being requested
    file_put_contents($log_file, "Attempting to load class: $class\n", FILE_APPEND);
    
    // Only load our plugin classes 
    if ( strpos( $class, 'CloudflareWAF' ) !== 0 ) {
        file_put_contents($log_file, "Skipping: $class (not a CloudflareWAF class)\n", FILE_APPEND);
        return;
    }
    
    // Convert class name to file path
    $class_name = str_replace( 'CloudflareWAF_', '', $class );
    $class_name = str_replace( '_', '-', $class_name );
    $class_file = 'class-' . strtolower( $class_name ) . '.php';
    
    file_put_contents($log_file, "Looking for file: $class_file\n", FILE_APPEND);
    
    // Check in each directory for the class file
    $directories = [ 'core', 'api', 'db', 'admin', 'services' ];
    foreach ( $directories as $dir ) {
        $file_path = CLOUDFLARE_WAF_PLUGIN_DIR . 'includes/' . $dir . '/' . $class_file;
        
        file_put_contents($log_file, "Checking: $file_path\n", FILE_APPEND);
        
        if ( file_exists( $file_path ) ) {
            file_put_contents($log_file, "FOUND: $file_path\n", FILE_APPEND);
            
            // Log before require_once
            file_put_contents($log_file, "Requiring: $file_path\n", FILE_APPEND);
            
            require_once $file_path;
            
            // Log after require_once (if it succeeds)
            file_put_contents($log_file, "Successfully required: $file_path\n\n", FILE_APPEND);
            
            return;
        }
    }
    
    // If we get here, the file wasn't found
    file_put_contents($log_file, "ERROR: Could not find any file for class $class\n\n", FILE_APPEND);
} );
/**
 * Begins execution of the plugin.
 */
function run_cloudflare_waf() {
    // Schedule daily WAF status check if not already scheduled
    if ( ! wp_next_scheduled( 'cloudflare_waf_daily_check' ) ) {
        wp_schedule_event( time(), 'daily', 'cloudflare_waf_daily_check' );
    }
    
    // Initialize the plugin
    $plugin = new CloudflareWAF_Plugin();
    $plugin->run();
    
    return $plugin;
}

// Initialize the plugin
$GLOBALS['cloudflare_waf'] = run_cloudflare_waf();