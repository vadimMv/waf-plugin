<?php
/**
 * Cloudflare Controller for WAF Protection
 *
 * Handles Cloudflare-specific API operations.
 *
 * @package CloudflareWAF
 * @subpackage API
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CloudflareWAF_Cloudflare_Controller {

    /**
     * API client instance
     *
     * @var CloudflareWAF_API_Client
     */
    private $api_client;

    /**
     * Logger instance
     *
     * @var CloudflareWAF_Logger
     */
    private $logger;

    /**
     * Constructor
     */
    public function __construct() {
        $this->api_client = new CloudflareWAF_API_Client();
        $this->logger = new CloudflareWAF_Logger();
    }

    /**
     * Configure WAF protection
     *
     * @param string $protection_level Protection level (low, medium, high)
     * @return array|WP_Error Configuration result or error
     */
    public function configure_waf( $protection_level = 'medium' ) {
        $this->logger->info( 'Configuring WAF protection', [ 'level' => $protection_level ] );
        
        // Validate protection level
        if ( ! in_array( $protection_level, [ 'low', 'medium', 'high' ] ) ) {
            $this->logger->error( 'Invalid protection level', [ 'level' => $protection_level ] );
            return new WP_Error( 'invalid_level', 'Invalid protection level. Valid levels are: low, medium, high.' );
        }
        
        // Make API request to configure WAF
        return $this->api_client->configure_waf( $protection_level );
    }

    /**
     * Get WAF protection status
     *
     * @return array|WP_Error Status or error
     */
    public function get_status() {
        $this->logger->info( 'Getting WAF protection status' );
        
        return $this->api_client->get_waf_status();
    }

    /**
     * Get WAF protection statistics
     *
     * @param string $period Period for statistics (day, week, month)
     * @return array|WP_Error Statistics or error
     */
    public function get_statistics( $period = 'week' ) {
        $this->logger->info( 'Getting WAF statistics', [ 'period' => $period ] );
        
        // Validate period
        if ( ! in_array( $period, [ 'day', 'week', 'month', 'year' ] ) ) {
            $this->logger->error( 'Invalid period', [ 'period' => $period ] );
            return new WP_Error( 'invalid_period', 'Invalid period. Valid periods are: day, week, month, year.' );
        }
        
        return $this->api_client->get_waf_statistics( $period );
    }

    /**
     * Get WAF attack reports
     *
     * @param string $period Period for reports (day, week, month)
     * @param int $limit Number of reports to retrieve
     * @return array|WP_Error Reports or error
     */
    public function get_reports( $period = 'week', $limit = 100 ) {
        $this->logger->info( 'Getting WAF reports', [ 'period' => $period, 'limit' => $limit ] );
        
        // Validate period
        if ( ! in_array( $period, [ 'day', 'week', 'month', 'year' ] ) ) {
            $this->logger->error( 'Invalid period', [ 'period' => $period ] );
            return new WP_Error( 'invalid_period', 'Invalid period. Valid periods are: day, week, month, year.' );
        }
        
        // Validate limit
        if ( ! is_numeric( $limit ) || $limit < 1 || $limit > 1000 ) {
            $this->logger->error( 'Invalid limit', [ 'limit' => $limit ] );
            return new WP_Error( 'invalid_limit', 'Invalid limit. Limit must be between 1 and 1000.' );
        }
        
        return $this->api_client->get_waf_reports( $period, $limit );
    }

    /**
     * Check DNS configuration status
     *
     * @return array|WP_Error DNS status or error
     */
    public function check_dns_status() {
        $this->logger->info( 'Checking DNS status' );
        
        return $this->api_client->check_dns_status();
    }

    /**
     * Get DNS configuration instructions
     *
     * @return array|WP_Error DNS instructions or error
     */
    public function get_dns_instructions() {
        $this->logger->info( 'Getting DNS instructions' );
        
        return $this->api_client->get_dns_instructions();
    }

    /**
     * Update WAF rule settings
     *
     * @param array $rule_settings Rule settings to update
     * @return array|WP_Error Update result or error
     */
    public function update_rule_settings( $rule_settings ) {
        $this->logger->info( 'Updating WAF rule settings' );
        
        // Validate rule settings
        if ( ! is_array( $rule_settings ) ) {
            $this->logger->error( 'Invalid rule settings', [ 'rule_settings' => $rule_settings ] );
            return new WP_Error( 'invalid_settings', 'Invalid rule settings. Rule settings must be an array.' );
        }
        
        // Make custom API request to update rule settings
        $url = $this->get_cloudflare_worker_url() . '/waf/rules';
        $args = [
            'method' => 'PUT',
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->get_auth_token()
            ],
            'body' => json_encode( $rule_settings )
        ];
        
        $response = wp_remote_request( $url, $args );
        
        // Handle errors
        if ( is_wp_error( $response ) ) {
            $this->logger->error( 'Rule settings update failed', [ 'error' => $response->get_error_message() ] );
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        
        if ( $status_code !== 200 ) {
            $error_message = isset( $body['message'] ) ? $body['message'] : 'Unknown error';
            $this->logger->error( 'Rule settings update failed', [ 'status' => $status_code, 'error' => $error_message ] );
            
            return new WP_Error( 'update_failed', 'Failed to update rule settings: ' . $error_message );
        }
        
        return $body;
    }

    /**
     * Get available WAF rule sets
     *
     * @return array|WP_Error Rule sets or error
     */
    public function get_rule_sets() {
        $this->logger->info( 'Getting WAF rule sets' );
        
        // Make custom API request to get rule sets
        $url = $this->get_cloudflare_worker_url() . '/waf/rule-sets';
        $args = [
            'method' => 'GET',
            'timeout' => 30,
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->get_auth_token()
            ]
        ];
        
        $response = wp_remote_request( $url, $args );
        
        // Handle errors
        if ( is_wp_error( $response ) ) {
            $this->logger->error( 'Failed to get rule sets', [ 'error' => $response->get_error_message() ] );
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        
        if ( $status_code !== 200 ) {
            $error_message = isset( $body['message'] ) ? $body['message'] : 'Unknown error';
            $this->logger->error( 'Failed to get rule sets', [ 'status' => $status_code, 'error' => $error_message ] );
            
            return new WP_Error( 'request_failed', 'Failed to get rule sets: ' . $error_message );
        }
        
        return $body;
    }

    /**
     * Get auth token
     *
     * @return string|WP_Error Authentication token or error
     */
    private function get_auth_token() {
        $auth_controller = new CloudflareWAF_Auth_Controller();
        return $auth_controller->get_auth_token();
    }

    /**
     * Get Cloudflare worker URL
     *
     * @return string The Cloudflare worker URL
     */
    private function get_cloudflare_worker_url() {
        return defined( 'CLOUDFLARE_WAF_CF_WORKER_URL' ) 
            ? CLOUDFLARE_WAF_CF_WORKER_URL 
            : 'https://cloudflare-api.your-workers.dev';
    }
}