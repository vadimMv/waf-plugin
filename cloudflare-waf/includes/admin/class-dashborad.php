<?php
/**
 * The dashboard-specific functionality of the plugin.
 *
 * @package CloudflareWAF
 * @subpackage Admin
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CloudflareWAF_Dashboard {

    /**
     * The ID of this plugin.
     *
     * @var string $plugin_name The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @var string $version The current version of this plugin.
     */
    private $version;

    /**
     * WAF service instance
     *
     * @var CloudflareWAF_Service
     */
    private $waf_service;

    /**
     * Logger instance
     *
     * @var CloudflareWAF_Logger
     */
    private $logger;

    /**
     * Initialize the class and set its properties.
     *
     * @param string $plugin_name The name of this plugin.
     * @param string $version The version of this plugin.
     * @param CloudflareWAF_Service $waf_service The WAF service instance.
     */
    public function __construct( $plugin_name, $version, $waf_service ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->waf_service = $waf_service;
        $this->logger = new CloudflareWAF_Logger();
    }

    /**
     * Display the dashboard page
     */
    public function display_page() {
        // Check if site is registered
        $is_registered = $this->waf_service->auth()->is_site_registered();
        
        if ( ! $is_registered ) {
            $this->display_not_registered();
            return;
        }

        // Get protection status
        $status = $this->waf_service->get_protection_status();
        
        // Get protection statistics
        $stats = $this->waf_service->get_statistics();
        
        // Get subscription details
        $subscription = $this->waf_service->payment()->get_subscription();
        
        // Load the dashboard template
        include CLOUDFLARE_WAF_PLUGIN_DIR . 'templates/admin/dashboard.php';
    }

    /**
     * Display the not registered message
     */
    private function display_not_registered() {
        include CLOUDFLARE_WAF_PLUGIN_DIR . 'templates/admin/not-registered.php';
    }

    /**
     * Ajax handler for refreshing statistics
     */
    public function ajax_refresh_stats() {
        // Check nonce
        if ( ! check_ajax_referer( 'cloudflare_waf_nonce', 'nonce', false ) ) {
            wp_send_json_error( array(
                'message' => __( 'Security check failed.', 'cloudflare-waf' )
            ));
        }

        // Check if site is registered
        if ( ! $this->waf_service->auth()->is_site_registered() ) {
            wp_send_json_error( array(
                'message' => __( 'Site not registered.', 'cloudflare-waf' )
            ));
        }

        // Get fresh status
        $status = $this->waf_service->get_protection_status( true );
        
        // Get fresh statistics
        $stats = $this->waf_service->get_statistics( true );
        
        // Return the results
        wp_send_json_success( array(
            'status' => $status,
            'stats' => $stats
        ));
    }

    /**
     * Get the protection status label and class
     *
     * @param array $status The protection status array
     * @return array Label and CSS class
     */
    public function get_status_info( $status ) {
        $status_value = isset( $status['status'] ) ? $status['status'] : 'unknown';
        
        switch ( $status_value ) {
            case 'active':
                return array(
                    'label' => __( 'Active', 'cloudflare-waf' ),
                    'class' => 'active',
                    'icon' => 'dashicons-shield',
                );
            
            case 'inactive':
                return array(
                    'label' => __( 'Inactive', 'cloudflare-waf' ),
                    'class' => 'inactive',
                    'icon' => 'dashicons-shield-alt',
                );
            
            case 'pending':
                return array(
                    'label' => __( 'Pending DNS Setup', 'cloudflare-waf' ),
                    'class' => 'pending',
                    'icon' => 'dashicons-clock',
                );
            
            case 'issues':
                return array(
                    'label' => __( 'Issues Detected', 'cloudflare-waf' ),
                    'class' => 'issues',
                    'icon' => 'dashicons-warning',
                );
            
            default:
                return array(
                    'label' => __( 'Unknown', 'cloudflare-waf' ),
                    'class' => 'unknown',
                    'icon' => 'dashicons-question',
                );
        }
    }

    /**
     * Format number for display
     *
     * @param int $number The number to format
     * @return string Formatted number
     */
    public function format_number( $number ) {
        if ( $number >= 1000000 ) {
            return round( $number / 1000000, 1 ) . 'M';
        } elseif ( $number >= 1000 ) {
            return round( $number / 1000, 1 ) . 'K';
        }
        
        return number_format( $number );
    }

    /**
     * Format timestamp for display
     *
     * @param int $timestamp Unix timestamp
     * @return string Formatted date/time
     */
    public function format_datetime( $timestamp ) {
        return date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $timestamp );
    }
}