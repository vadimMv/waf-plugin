<?php
/**
 * Proxy API Communication class
 *
 * Handles all communication with the proxy service
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WAF_Proxy_API {
    /**
     * Proxy API URL
     */
    private $api_url;
    
    /**
     * Client ID from proxy service
     */
    private $client_id;
    
    /**
     * Client secret from proxy service
     */
    private $client_secret;
    
    /**
     * JWT token for authenticated requests
     */
    private $token;
    
    /**
     * Token expiration timestamp
     */
    private $token_expires;
    
    /**
     * Constructor
     */
    public function __construct() {
        $settings = get_option('ecom_waf_settings', []);
        
        // $this->api_url = defined('ECOM_WAF_API_URL') ? ECOM_WAF_API_URL : 'https://your-waf-api.com/api/v1';
        //  $this->api_url = 'https://waf-subscription.mukovozov88-vadim.workers.dev';
        $this->api_url =  'http://host.docker.internal:8787';
        $this->client_id = isset($settings['client_id']) ? $settings['client_id'] : '';
        $this->client_secret = isset($settings['client_secret']) ? $settings['client_secret'] : '';
        $this->token = isset($settings['auth_token']) ? $settings['auth_token'] : '';
        $this->token_expires = isset($settings['token_expires']) ? $settings['token_expires'] : 0;
    }
    
    /**
     * Create checkout session with proxy service
     *
     * @param string $plan Subscription plan (monthly/annual)
     * @return array Response from proxy with checkout URL or error
     */
    public function create_checkout_session($plan) {
        $site_url = home_url();
        $admin_url = admin_url();
        $success_url = add_query_arg('waf_checkout', 'success', admin_url('admin.php?page=ecom-waf-subscription'));
        $cancel_url = add_query_arg('waf_checkout', 'cancel', admin_url('admin.php?page=ecom-waf-subscription'));
        
        $current_user = wp_get_current_user();
        
        $data = [
            'plan' => $plan,
            'site_url' => $site_url,
            'success_url' => $success_url, 
            'cancel_url' => $cancel_url,
            'customer_email' => $current_user->user_email,
            'customer_name' => $current_user->display_name,
        ];
        
        return $this->request('POST', '/checkout/create', $data);
    }
    
    /**
     * Verify a checkout session and get credentials
     *
     * @param string $session_id Stripe session ID
     * @param string $checkout_token One-time token from checkout success
     * @return array Response with client credentials or error
     */
    public function verify_checkout_session($session_id, $checkout_token) {
        $data = [
            'session_id' => $session_id,
            'checkout_token' => $checkout_token,
            'site_url' => home_url()
        ];
        
        return $this->request('POST', '/checkout/verify', $data);
    }
    
    /**
     * Get WAF status from proxy
     *
     * @return array WAF status details
     */
    public function get_waf_status() {
        return $this->authenticated_request('GET', '/waf/status');
    }
    
    /**
     * Configure WAF with proxy
     *
     * @param array $config WAF configuration options
     * @return array Response from proxy
     */
    public function configure_waf($config) {
        return $this->authenticated_request('POST', '/waf/configure', $config);
    }
    
    /**
     * Get WAF statistics and reports
     *
     * @param int $days Number of days to get stats for
     * @return array WAF statistics
     */
    public function get_waf_stats($days = 7) {
        return $this->authenticated_request('GET', '/waf/stats', ['days' => $days]);
    }
    
    /**
     * Make an authenticated request to the proxy API
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @return array Response from API
     */
    private function authenticated_request($method, $endpoint, $data = []) {
        // Check if token is expired or missing
        if (empty($this->token) || $this->token_expires < time()) {
            $token_result = $this->get_auth_token();
            if (!$token_result['success']) {
                return $token_result;
            }
        }
        
        // Add authorization header
        $headers = [
            'Authorization' => 'Bearer ' . $this->token
        ];
        
        return $this->request($method, $endpoint, $data, $headers);
    }
    
    /**
     * Get a new authentication token
     *
     * @return array Result of token request
     */
    private function get_auth_token() {
        // Check if we have credentials
        if (empty($this->client_id) || empty($this->client_secret)) {
            return [
                'success' => false,
                'message' => 'Missing client credentials'
            ];
        }
        
        $data = [
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'grant_type' => 'client_credentials'
        ];
        
        $response = $this->request('POST', '/oauth/token', $data);
        
        if ($response['success'] && isset($response['data']['access_token'])) {
            $this->token = $response['data']['access_token'];
            $this->token_expires = time() + ($response['data']['expires_in'] ?? 3600);
            
            // Save the token
            $settings = get_option('ecom_waf_settings', []);
            $settings['auth_token'] = $this->token;
            $settings['token_expires'] = $this->token_expires;
            update_option('ecom_waf_settings', $settings);
            
            return [
                'success' => true,
                'message' => 'Token refreshed successfully'
            ];
        }
        
        return $response;
    }
    
    /**
     * Make a request to the proxy API
     *
     * @param string $method HTTP method
     * @param string $endpoint API endpoint
     * @param array $data Request data
     * @param array $headers Additional headers
     * @return array Response from API
     */
    private function request($method, $endpoint, $data = [], $headers = []) {
        $url = rtrim($this->api_url, '/') . '/' . ltrim($endpoint, '/');
        
        $args = [
            'method' => $method,
            'timeout' => 30,
            'redirection' => 5,
            'httpversion' => '1.1',
            'headers' => array_merge([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'WordPress WAF Plugin/' . ECOM_WAF_VERSION
            ], $headers)
        ];
        //  var_dump($url);
        // die();
        if (!empty($data)) {
            if ($method === 'GET') {
                $url = add_query_arg($data, $url);
            } else {
                $args['body'] = json_encode($data);
            }
        }
        
        $response = wp_remote_request($url, $args);
        //  var_dump($response);
        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => $response->get_error_message()
            ];
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $parsed_body = json_decode($response_body, true);
        
        if ($response_code >= 200 && $response_code < 300) {
            return [
                'success' => true,
                'data' => $parsed_body
            ];
        }
        
        return [
            'success' => false,
            'message' => isset($parsed_body['message']) ? $parsed_body['message'] : 'Unknown error',
            'code' => $response_code
        ];
    }
    
    /**
     * Store client credentials in WordPress
     *
     * @param string $client_id Client ID from proxy
     * @param string $client_secret Client secret from proxy
     * @return bool Success status
     */
    public function store_credentials($client_id, $client_secret) {
        if (empty($client_id) || empty($client_secret)) {
            return false;
        }
        
        $this->client_id = $client_id;
        $this->client_secret = $client_secret;
        
        $settings = get_option('ecom_waf_settings', []);
        $settings['client_id'] = $client_id;
        $settings['client_secret'] = $client_secret;
        
        // Clear existing token since we have new credentials
        $settings['auth_token'] = '';
        $settings['token_expires'] = 0;
        $this->token = '';
        $this->token_expires = 0;
        
        return update_option('ecom_waf_settings', $settings);
    }
}