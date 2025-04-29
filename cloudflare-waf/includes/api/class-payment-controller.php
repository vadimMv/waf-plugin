<?php
/**
 * Payment Controller for WAF Protection
 *
 * Handles payment and subscription operations.
 *
 * @package CloudflareWAF
 * @subpackage API
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CloudflareWAF_Payment_Controller {

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
     * Option name for subscription data
     *
     * @var string
     */
    private $subscription_option = 'cloudflare_waf_subscription';

    /**
     * Constructor
     */
    public function __construct() {
        $this->api_client = new CloudflareWAF_API_Client();
        $this->logger = new CloudflareWAF_Logger();
    }

    /**
     * Get available subscription plans
     *
     * @return array|WP_Error Plans or error
     */
    public function get_available_plans() {
        $this->logger->info( 'Getting available subscription plans' );
        
        // Make custom API request to get plans
        $url = $this->get_payment_worker_url() . '/plans';
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
            $this->logger->error( 'Failed to get plans', [ 'error' => $response->get_error_message() ] );
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        
        if ( $status_code !== 200 ) {
            $error_message = isset( $body['message'] ) ? $body['message'] : 'Unknown error';
            $this->logger->error( 'Failed to get plans', [ 'status' => $status_code, 'error' => $error_message ] );
            
            return new WP_Error( 'request_failed', 'Failed to get plans: ' . $error_message );
        }
        
        return $body;
    }

    /**
     * Create checkout session for subscription
     *
     * @param string $plan_id Plan ID
     * @param array $customer_info Optional customer information
     * @return array|WP_Error Checkout session or error
     */
    public function create_checkout_session( $plan_id, $customer_info = [] ) {
        $this->logger->info( 'Creating checkout session', [ 'plan_id' => $plan_id ] );
        
        // Generate success and cancel URLs
        $success_url = admin_url( 'admin.php?page=cloudflare-waf-wizard&step=complete&session_id={CHECKOUT_SESSION_ID}' );
        $cancel_url = admin_url( 'admin.php?page=cloudflare-waf-wizard&step=plan&cancel=true' );
        
        // Make API request to create checkout session
        return $this->api_client->create_checkout_session( 
            $plan_id, 
            $success_url, 
            $cancel_url 
        );
    }

    /**
     * Get subscription details
     *
     * @param bool $force_refresh Whether to force refresh from API
     * @return array|WP_Error Subscription details or error
     */
    public function get_subscription( $force_refresh = false ) {
        // Check if we have cached subscription data
        $subscription = get_option( $this->subscription_option );
        
        // If data exists and we're not forcing refresh, return it
        if ( $subscription && ! $force_refresh ) {
            // Check if data is stale (older than 1 hour)
            if ( isset( $subscription['updated_at'] ) && ( time() - $subscription['updated_at'] < 3600 ) ) {
                return $subscription;
            }
        }
        
        // Get fresh data from API
        $this->logger->info( 'Getting subscription details from API' );
        
        $response = $this->api_client->get_subscription();
        
        if ( is_wp_error( $response ) ) {
            $this->logger->error( 'Failed to get subscription', [ 'error' => $response->get_error_message() ] );
            
            // If we have cached data, return it even if it's stale
            if ( $subscription ) {
                return $subscription;
            }
            
            return $response;
        }
        
        // Add updated timestamp
        $response['updated_at'] = time();
        
        // Cache the response
        update_option( $this->subscription_option, $response, false );
        
        return $response;
    }

    /**
     * Update subscription plan
     *
     * @param string $plan_id New plan ID
     * @return array|WP_Error Update result or error
     */
    public function update_subscription( $plan_id ) {
        $this->logger->info( 'Updating subscription plan', [ 'plan_id' => $plan_id ] );
        
        $response = $this->api_client->update_subscription( $plan_id );
        
        if ( ! is_wp_error( $response ) ) {
            // Update local cache
            $this->get_subscription( true );
        }
        
        return $response;
    }

    /**
     * Cancel subscription
     *
     * @return array|WP_Error Cancel result or error
     */
    public function cancel_subscription() {
        $this->logger->info( 'Canceling subscription' );
        
        $response = $this->api_client->cancel_subscription();
        
        if ( ! is_wp_error( $response ) ) {
            // Update local cache
            $this->get_subscription( true );
        }
        
        return $response;
    }

    /**
     * Handle webhook event
     *
     * @param array $event Webhook event data
     * @return bool Whether event was handled successfully
     */
    public function handle_webhook_event( $event ) {
        if ( ! isset( $event['type'] ) ) {
            $this->logger->error( 'Invalid webhook event', [ 'event' => $event ] );
            return false;
        }
        
        $this->logger->info( 'Handling webhook event', [ 'type' => $event['type'] ] );
        
        switch ( $event['type'] ) {
            case 'subscription_created':
            case 'subscription_updated':
            case 'subscription_deleted':
            case 'payment_succeeded':
            case 'payment_failed':
                // Force refresh subscription data
                $this->get_subscription( true );
                
                // Trigger action for this event
                do_action( 'cloudflare_waf_' . $event['type'], $event );
                return true;
            
            default:
                $this->logger->warning( 'Unknown webhook event type', [ 'type' => $event['type'] ] );
                return false;
        }
    }

    /**
     * Get customer portal URL
     *
     * @return string|WP_Error Portal URL or error
     */
    public function get_customer_portal_url() {
        $this->logger->info( 'Getting customer portal URL' );
        
        // Make custom API request to get portal URL
        $url = $this->get_payment_worker_url() . '/customer/portal';
        $args = [
            'method' => 'POST',
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => 'Bearer ' . $this->get_auth_token()
            ],
            'body' => json_encode([
                'return_url' => admin_url( 'admin.php?page=cloudflare-waf-settings' )
            ])
        ];
        
        $response = wp_remote_request( $url, $args );
        
        // Handle errors
        if ( is_wp_error( $response ) ) {
            $this->logger->error( 'Failed to get portal URL', [ 'error' => $response->get_error_message() ] );
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        
        if ( $status_code !== 200 || ! isset( $body['url'] ) ) {
            $error_message = isset( $body['message'] ) ? $body['message'] : 'Unknown error';
            $this->logger->error( 'Failed to get portal URL', [ 'status' => $status_code, 'error' => $error_message ] );
            
            return new WP_Error( 'request_failed', 'Failed to get customer portal URL: ' . $error_message );
        }
        
        return $body['url'];
    }

    /**
     * Check if site has active subscription
     *
     * @return bool Whether site has active subscription
     */
    public function has_active_subscription() {
        $subscription = $this->get_subscription();
        
        if ( is_wp_error( $subscription ) ) {
            return false;
        }
        
        return isset( $subscription['status'] ) && $subscription['status'] === 'active';
    }

    /**
     * Get subscription status
     *
     * @return string|bool Subscription status or false if error
     */
    public function get_subscription_status() {
        $subscription = $this->get_subscription();
        
        if ( is_wp_error( $subscription ) ) {
            return false;
        }
        
        return isset( $subscription['status'] ) ? $subscription['status'] : false;
    }

    /**
     * Get current plan ID
     *
     * @return string|bool Plan ID or false if error
     */
    public function get_current_plan_id() {
        $subscription = $this->get_subscription();
        
        if ( is_wp_error( $subscription ) ) {
            return false;
        }
        
        return isset( $subscription['plan_id'] ) ? $subscription['plan_id'] : false;
    }

    /**
     * Get subscription expiration date
     *
     * @return string|bool Expiration date or false if error
     */
    public function get_subscription_expiration() {
        $subscription = $this->get_subscription();
        
        if ( is_wp_error( $subscription ) ) {
            return false;
        }
        
        return isset( $subscription['current_period_end'] ) ? $subscription['current_period_end'] : false;
    }

    /**
     * Clear local subscription cache
     *
     * @return bool Whether cache was cleared
     */
    public function clear_subscription_cache() {
        $this->logger->debug( 'Clearing subscription cache' );
        return delete_option( $this->subscription_option );
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
     * Get payment worker URL
     *
     * @return string The payment worker URL
     */
    private function get_payment_worker_url() {
        return defined( 'CLOUDFLARE_WAF_PAYMENT_WORKER_URL' ) 
            ? CLOUDFLARE_WAF_PAYMENT_WORKER_URL 
            : 'https://payment.your-workers.dev';
    }