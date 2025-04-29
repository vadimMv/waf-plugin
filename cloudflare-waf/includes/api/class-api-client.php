<?php
/**
 * API Client for WAF Protection
 *
 * Handles all API communication with the proxy service.
 *
 * @package CloudflareWAF
 * @subpackage API
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CloudflareWAF_API_Client {

    /**
     * Base URL for the auth worker
     *
     * @var string
     */
    private $auth_worker_url;

    /**
     * Base URL for the Cloudflare worker
     *
     * @var string
     */
    private $cloudflare_worker_url;

    /**
     * Base URL for the payment worker
     *
     * @var string
     */
    private $payment_worker_url;

    /**
     * Logger instance
     *
     * @var CloudflareWAF_Logger
     */
    private $logger;

    /**
     * Token store instance
     *
     * @var CloudflareWAF_Token_Store
     */
    private $token_store;

    /**
     * Credentials store instance
     *
     * @var CloudflareWAF_Credentials_Store
     */
    private $credentials_store;

    /**
     * Constructor
     */
    public function __construct() {
        // Load worker URLs from settings
        $this->auth_worker_url = defined( 'CLOUDFLARE_WAF_AUTH_WORKER_URL' ) 
            ? CLOUDFLARE_WAF_AUTH_WORKER_URL 
            : 'https://auth.your-workers.dev';
            
        $this->cloudflare_worker_url = defined( 'CLOUDFLARE_WAF_CF_WORKER_URL' ) 
            ? CLOUDFLARE_WAF_CF_WORKER_URL 
            : 'https://cloudflare-api.your-workers.dev';
            
        $this->payment_worker_url = defined( 'CLOUDFLARE_WAF_PAYMENT_WORKER_URL' ) 
            ? CLOUDFLARE_WAF_PAYMENT_WORKER_URL 
            : 'https://payment.your-workers.dev';

        // Initialize dependencies
        $this->logger = new CloudflareWAF_Logger();
        $this->token_store = new CloudflareWAF_Token_Store();
        $this->credentials_store = new CloudflareWAF_Credentials_Store();
    }

    /**
     * Register the site with the auth worker
     *
     * @param string $email Admin email
     * @return array|WP_Error Registration response or error
     */
    public function register_site( $email ) {
        $domain = parse_url( home_url(), PHP_URL_HOST );
        
        $this->logger->info( 'Registering site: ' . $domain );
        
        $response = $this->make_request(
            'auth',
            '/register',
            'POST',
            [
                'domain' => $domain,
                'email' => $email,
                'site_url' => home_url(),
                'wp_version' => get_bloginfo( 'version' ),
                'plugin_version' => CLOUDFLARE_WAF_VERSION
            ],
            false // No auth token needed for registration
        );
        
        if ( ! is_wp_error( $response ) && isset( $response['clientId'] ) && isset( $response['clientSecret'] ) ) {
            $this->logger->info( 'Site registered successfully' );
            
            // Store credentials securely
            $this->credentials_store->save_credentials(
                $response['clientId'],
                $response['clientSecret']
            );
            
            return $response;
        }
        
        if ( is_wp_error( $response ) ) {
            $this->logger->error( 'Registration failed: ' . $response->get_error_message() );
            return $response;
        }
        
        $this->logger->error( 'Registration failed: Invalid response' );
        return new WP_Error( 'invalid_response', 'Invalid response from registration endpoint' );
    }

    /**
     * Configure WAF protection
     *
     * @param string $protection_level The protection level (low, medium, high)
     * @return array|WP_Error Configuration response or error
     */
    public function configure_waf( $protection_level ) {
        $domain = parse_url( home_url(), PHP_URL_HOST );
        
        $this->logger->info( 'Configuring WAF protection: ' . $protection_level );
        
        return $this->make_request(
            'cloudflare',
            '/waf/configure',
            'POST',
            [
                'domain' => $domain,
                'protection_level' => $protection_level
            ]
        );
    }

    /**
     * Get WAF protection status
     *
     * @return array|WP_Error Status response or error
     */
    public function get_waf_status() {
        $this->logger->info( 'Getting WAF protection status' );
        
        return $this->make_request(
            'cloudflare',
            '/waf/status',
            'GET'
        );
    }

    /**
     * Get WAF protection statistics
     *
     * @param string $period Period for statistics (day, week, month)
     * @return array|WP_Error Statistics response or error
     */
    public function get_waf_statistics( $period = 'week' ) {
        $this->logger->info( 'Getting WAF statistics for period: ' . $period );
        
        return $this->make_request(
            'cloudflare',
            '/waf/statistics',
            'GET',
            [
                'period' => $period
            ]
        );
    }

    /**
     * Get WAF attack reports
     *
     * @param string $period Period for reports (day, week, month)
     * @param int $limit Number of reports to retrieve
     * @return array|WP_Error Reports response or error
     */
    public function get_waf_reports( $period = 'week', $limit = 100 ) {
        $this->logger->info( 'Getting WAF reports for period: ' . $period );
        
        return $this->make_request(
            'cloudflare',
            '/waf/reports',
            'GET',
            [
                'period' => $period,
                'limit' => $limit
            ]
        );
    }

    /**
     * Create a checkout session for subscription
     *
     * @param string $plan_id The plan ID
     * @param string $success_url Success redirect URL
     * @param string $cancel_url Cancel redirect URL
     * @return array|WP_Error Checkout session response or error
     */
    public function create_checkout_session( $plan_id, $success_url, $cancel_url ) {
        $domain = parse_url( home_url(), PHP_URL_HOST );
        $admin_email = get_option( 'admin_email' );
        
        $this->logger->info( 'Creating checkout session for plan: ' . $plan_id );
        
        return $this->make_request(
            'payment',
            '/checkout/create',
            'POST',
            [
                'domain' => $domain,
                'email' => $admin_email,
                'plan_id' => $plan_id,
                'success_url' => $success_url,
                'cancel_url' => $cancel_url
            ]
        );
    }

    /**
     * Get subscription details
     *
     * @return array|WP_Error Subscription details or error
     */
    public function get_subscription() {
        $this->logger->info( 'Getting subscription details' );
        
        return $this->make_request(
            'payment',
            '/subscription',
            'GET'
        );
    }

    /**
     * Update subscription plan
     *
     * @param string $plan_id The new plan ID
     * @return array|WP_Error Update response or error
     */
    public function update_subscription( $plan_id ) {
        $this->logger->info( 'Updating subscription to plan: ' . $plan_id );
        
        return $this->make_request(
            'payment',
            '/subscription/update',
            'POST',
            [
                'plan_id' => $plan_id
            ]
        );
    }

    /**
     * Cancel subscription
     *
     * @return array|WP_Error Cancel response or error
     */
    public function cancel_subscription() {
        $this->logger->info( 'Canceling subscription' );
        
        return $this->make_request(
            'payment',
            '/subscription/cancel',
            'POST'
        );
    }

    /**
     * Check DNS configuration status
     *
     * @return array|WP_Error DNS status or error
     */
    public function check_dns_status() {
        $domain = parse_url( home_url(), PHP_URL_HOST );
        
        $this->logger->info( 'Checking DNS status for: ' . $domain );
        
        return $this->make_request(
            'cloudflare',
            '/waf/dns/status',
            'GET',
            [
                'domain' => $domain
            ]
        );
    }

    /**
     * Get DNS configuration instructions
     *
     * @return array|WP_Error DNS instructions or error
     */
    public function get_dns_instructions() {
        $domain = parse_url( home_url(), PHP_URL_HOST );
        
        $this->logger->info( 'Getting DNS instructions for: ' . $domain );
        
        return $this->make_request(
            'cloudflare',
            '/waf/dns/instructions',
            'GET',
            [
                'domain' => $domain
            ]
        );
    }

    /**
     * Make a request to the API
     *
     * @param string $worker_type The worker type (auth, cloudflare, payment)
     * @param string $endpoint The API endpoint
     * @param string $method The HTTP method
     * @param array $data The request data
     * @param bool $auth Whether authentication is required
     * @param int $retry_count Current retry count for this request
     * @return array|WP_Error Response data or error
     */
    private function make_request( $worker_type, $endpoint, $method = 'GET', $data = [], $auth = true, $retry_count = 0 ) {
        $url = $this->get_worker_url( $worker_type ) . $endpoint;
        $max_retries = 3;
        
        // Prepare request arguments
        $args = [
            'method' => $method,
            'timeout' => 30,
            'redirection' => 5,
            'httpversion' => '1.1',
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ]
        ];
        
        // Add authentication token if required
        if ( $auth ) {
            $token = $this->get_valid_token();
            
            if ( is_wp_error( $token ) ) {
                return $token;
            }
            
            $args['headers']['Authorization'] = 'Bearer ' . $token;
        }
        
        // Add request body for non-GET requests
        if ( $method !== 'GET' && ! empty( $data ) ) {
            $args['body'] = json_encode( $data );
        }
        
        // Add query parameters for GET requests
        if ( $method === 'GET' && ! empty( $data ) ) {
            $url = add_query_arg( $data, $url );
        }
        
        $this->logger->debug( 'Making API request to: ' . $url );
        
        // Make the request
        $response = wp_remote_request( $url, $args );
        
        // Check for HTTP errors
        if ( is_wp_error( $response ) ) {
            $this->logger->error( 'HTTP error: ' . $response->get_error_message() );
            
            // Retry on network errors
            if ( $retry_count < $max_retries ) {
                $this->logger->info( 'Retrying request (attempt ' . ( $retry_count + 1 ) . ' of ' . $max_retries . ')' );
                
                // Exponential backoff
                sleep( pow( 2, $retry_count ) );
                
                return $this->make_request( $worker_type, $endpoint, $method, $data, $auth, $retry_count + 1 );
            }
            
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        
        // Parse JSON response
        $parsed_body = json_decode( $body, true );
        
        if ( $parsed_body === null && ! empty( $body ) ) {
            $this->logger->error( 'Invalid JSON response' );
            return new WP_Error( 'invalid_json', 'Invalid JSON response from API' );
        }
        
        // Handle authentication errors
        if ( $status_code === 401 && $auth && $retry_count === 0 ) {
            $this->logger->info( 'Authentication failed, refreshing token and retrying' );
            
            // Force token refresh
            $this->token_store->clear_token();
            
            // Retry the request once with a new token
            return $this->make_request( $worker_type, $endpoint, $method, $data, $auth, $retry_count + 1 );
        }
        
        // Handle other error responses
        if ( $status_code >= 400 ) {
            $error_message = isset( $parsed_body['message'] ) ? $parsed_body['message'] : 'Unknown error';
            $error_code = isset( $parsed_body['error'] ) ? $parsed_body['error'] : 'api_error';
            
            $this->logger->error( 'API error: [' . $status_code . '] ' . $error_message );
            
            return new WP_Error( $error_code, $error_message, [
                'status' => $status_code,
                'response' => $parsed_body
            ]);
        }
        
        // Return the parsed response
        return $parsed_body;
    }

    /**
     * Get the base URL for the specified worker type
     *
     * @param string $worker_type The worker type (auth, cloudflare, payment)
     * @return string The worker URL
     */
    private function get_worker_url( $worker_type ) {
        switch ( $worker_type ) {
            case 'auth':
                return $this->auth_worker_url;
            case 'cloudflare':
                return $this->cloudflare_worker_url;
            case 'payment':
                return $this->payment_worker_url;
            default:
                return $this->auth_worker_url;
        }
    }

    /**
     * Get a valid authentication token
     *
     * @return string|WP_Error The token or an error
     */
    private function get_valid_token() {
        // Check if we have a valid token
        $token = $this->token_store->get_token();
        
        if ( $token ) {
            return $token;
        }
        
        // We need to request a new token
        $client_id = $this->credentials_store->get_client_id();
        $client_secret = $this->credentials_store->get_client_secret();
        
        if ( ! $client_id || ! $client_secret ) {
            $this->logger->error( 'No client credentials found' );
            return new WP_Error( 'no_credentials', 'No client credentials found. Please register the site first.' );
        }
        
        $this->logger->info( 'Requesting new auth token' );
        
        $response = wp_remote_post( 
            $this->auth_worker_url . '/token', 
            [
                'timeout' => 30,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ],
                'body' => json_encode([
                    'client_id' => $client_id,
                    'client_secret' => $client_secret,
                    'grant_type' => 'client_credentials'
                ])
            ]
        );
        
        if ( is_wp_error( $response ) ) {
            $this->logger->error( 'Token request failed: ' . $response->get_error_message() );
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        
        if ( $status_code !== 200 || ! isset( $body['access_token'] ) ) {
            $error_message = isset( $body['message'] ) ? $body['message'] : 'Unknown error';
            $this->logger->error( 'Token request failed: ' . $error_message );
            
            return new WP_Error( 'token_request_failed', $error_message );
        }
        
        // Store the token
        $token = $body['access_token'];
        $expires_in = isset( $body['expires_in'] ) ? $body['expires_in'] : 3600;
        
        $this->token_store->set_token( $token, $expires_in );
        
        return $token;
    }
}