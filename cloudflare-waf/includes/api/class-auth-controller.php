<?php
/**
 * Auth Controller for WAF Protection
 *
 * Handles authentication-specific API operations.
 *
 * @package CloudflareWAF
 * @subpackage API
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CloudflareWAF_Auth_Controller {

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
        $this->api_client = new CloudflareWAF_API_Client();
        $this->logger = new CloudflareWAF_Logger();
        $this->token_store = new CloudflareWAF_Token_Store();
        $this->credentials_store = new CloudflareWAF_Credentials_Store();
    }

    /**
     * Register the site with the auth service
     *
     * @param string $admin_email Admin email for registration
     * @return array|WP_Error Registration result or error
     */
    public function register_site( $admin_email = '' ) {
        $this->logger->info( 'Attempting to register site' );
        
        // If no email provided, use the admin email
        if ( empty( $admin_email ) ) {
            $admin_email = get_option( 'admin_email' );
        }
        
        // Validate email
        if ( ! is_email( $admin_email ) ) {
            $this->logger->error( 'Invalid email for registration', [ 'email' => $admin_email ] );
            return new WP_Error( 'invalid_email', 'A valid email address is required for registration.' );
        }
        
        // Check if already registered
        if ( $this->credentials_store->has_credentials() ) {
            $this->logger->warning( 'Site is already registered' );
            return new WP_Error( 'already_registered', 'This site is already registered. Please use reset_registration if you want to re-register.' );
        }
        
        // Get site information for registration
        $site_info = $this->get_site_info();
        
        try {
            // Make registration request
            $response = $this->api_client->register_site( $admin_email );
            
            if ( is_wp_error( $response ) ) {
                $this->logger->error( 'Registration failed', [ 'error' => $response->get_error_message() ] );
                return $response;
            }
            
            // Check for required data
            if ( ! isset( $response['clientId'] ) || ! isset( $response['clientSecret'] ) ) {
                $this->logger->error( 'Invalid registration response', [ 'response' => $response ] );
                return new WP_Error( 'invalid_response', 'Invalid response received from registration service.' );
            }
            
            // Store the credentials securely
            $credentials_saved = $this->credentials_store->save_credentials(
                $response['clientId'],
                $response['clientSecret']
            );
            
            if ( ! $credentials_saved ) {
                $this->logger->error( 'Failed to save credentials' );
                return new WP_Error( 'storage_failed', 'Failed to securely store API credentials.' );
            }
            
            $this->logger->info( 'Site registered successfully' );
            
            // Return success response
            return [
                'success' => true,
                'message' => 'Site registered successfully.',
                'status' => isset( $response['status'] ) ? $response['status'] : 'registered'
            ];
        } catch ( Exception $e ) {
            $this->logger->error( 'Exception during registration', [ 'error' => $e->getMessage() ] );
            return new WP_Error( 'registration_failed', 'Registration failed: ' . $e->getMessage() );
        }
    }
    
    /**
     * Get site information for registration
     *
     * @return array Site information
     */
    private function get_site_info() {
        return [
            'domain' => parse_url( home_url(), PHP_URL_HOST ),
            'site_url' => home_url(),
            'site_name' => get_bloginfo( 'name' ),
            'wp_version' => get_bloginfo( 'version' ),
            'plugin_version' => defined( 'CLOUDFLARE_WAF_VERSION' ) ? CLOUDFLARE_WAF_VERSION : '1.0.0',
            'is_multisite' => is_multisite(),
            'locale' => get_locale(),
            'woocommerce_active' => $this->is_woocommerce_active()
        ];
    }
    
    /**
     * Check if WooCommerce is active
     *
     * @return bool Whether WooCommerce is active
     */
    private function is_woocommerce_active() {
        return in_array( 
            'woocommerce/woocommerce.php', 
            apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) 
        );
    }
    
    /**
     * Get a valid authentication token
     *
     * @return string|WP_Error Token or error
     */
    public function get_auth_token() {
        $this->logger->debug( 'Retrieving auth token' );
        
        // Check if we have a valid token
        $token = $this->token_store->get_token();
        if ( $token ) {
            $this->logger->debug( 'Found valid cached token' );
            return $token;
        }
        
        // No valid token, request a new one
        $this->logger->debug( 'No valid token found, requesting new token' );
        
        // Check if we have credentials
        if ( ! $this->credentials_store->has_credentials() ) {
            $this->logger->error( 'No credentials found for token request' );
            return new WP_Error( 'no_credentials', 'No API credentials found. Please register the site first.' );
        }
        
        // Get credentials
        $client_id = $this->credentials_store->get_client_id();
        $client_secret = $this->credentials_store->get_client_secret();
        
        // Prepare token request
        $request_url = $this->get_auth_worker_url() . '/token';
        $request_args = [
            'method' => 'POST',
            'timeout' => 30,
            'redirection' => 5,
            'httpversion' => '1.1',
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ],
            'body' => json_encode([
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'grant_type' => 'client_credentials'
            ])
        ];
        
        // Make token request
        $response = wp_remote_post( $request_url, $request_args );
        
        // Handle errors
        if ( is_wp_error( $response ) ) {
            $this->logger->error( 'Token request failed', [ 'error' => $response->get_error_message() ] );
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        
        // Check for invalid response
        if ( $status_code !== 200 || ! isset( $body['access_token'] ) ) {
            $error_message = isset( $body['message'] ) ? $body['message'] : 'Unknown error';
            $this->logger->error( 'Token request failed', [ 'status' => $status_code, 'error' => $error_message ] );
            
            return new WP_Error( 'token_request_failed', 'Failed to obtain authentication token: ' . $error_message );
        }
        
        // Got valid token, store it
        $token = $body['access_token'];
        $expires_in = isset( $body['expires_in'] ) ? $body['expires_in'] : 3600;
        
        $this->token_store->set_token( $token, $expires_in );
        $this->logger->debug( 'New token acquired and stored', [ 'expires_in' => $expires_in ] );
        
        return $token;
    }
    
    /**
     * Reset site registration (remove credentials)
     *
     * @return bool Whether registration was reset successfully
     */
    public function reset_registration() {
        $this->logger->info( 'Resetting site registration' );
        
        // Clear token
        $this->token_store->clear_token();
        
        // Clear credentials
        return $this->credentials_store->clear_credentials();
    }
    
    /**
     * Check if site is registered
     *
     * @return bool Whether site is registered
     */
    public function is_site_registered() {
        return $this->credentials_store->has_credentials();
    }
    
    /**
     * Verify authentication is working
     *
     * @return bool|WP_Error True if working, error if not
     */
    public function verify_authentication() {
        $this->logger->info( 'Verifying authentication' );
        
        // Get token
        $token = $this->get_auth_token();
        
        if ( is_wp_error( $token ) ) {
            return $token;
        }
        
        // If we got a token, authentication is working
        return true;
    }
    
    /**
     * Get auth worker URL
     *
     * @return string The auth worker URL
     */
    private function get_auth_worker_url() {
        return defined( 'CLOUDFLARE_WAF_AUTH_WORKER_URL' ) 
            ? CLOUDFLARE_WAF_AUTH_WORKER_URL 
            : 'https://auth.your-workers.dev';
    }