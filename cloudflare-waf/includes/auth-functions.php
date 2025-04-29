<?php
/**
 * Function to fetch WAF authentication token
 * 
 * @param string $client_id The client ID for authentication
 * @param string $client_secret The client secret for authentication
 * @param string $domain The domain for authentication
 * @return array Response with token or error details
 */
function ecom_waf_fetch_token($client_id, $client_secret, $domain) {
    // Validate parameters
    if (empty($client_id) || empty($client_secret) || empty($domain)) {
        return array(
            'success' => false,
            'message' => 'Missing required parameters'
        );
    }
    
    // Prepare the request
    $api_url = 'https://waf-proxy-service.mukovozov88-vadim.workers.dev/auth/token';
    
    $request_args = array(
        'method'  => 'POST',
        'timeout' => 30,
        'headers' => array(
            'Content-Type' => 'application/json',
        ),
        'body'    => json_encode(array(
            'clientId'     => $client_id,
            'clientSecret' => $client_secret,
            'domain'       => $domain,
            'apiKey'       => 123467
        ))
    );
    
    // Make the request
    $response = wp_remote_post($api_url, $request_args);
    
    // Check for errors
    if (is_wp_error($response)) {
        return array(
            'success' => false,
            'message' => $response->get_error_message()
        );
    }
    
    // Get response code
    $response_code = wp_remote_retrieve_response_code($response);
    
    // Get response body
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
   // var_dump($data);

    // Check if response was successful
    if ($response_code !== 200) {
        return array(
            'success' => false,
            'message' => isset($data['message']) ? $data['message'] : 'Unknown error occurred',
            'code'    => $response_code
        );
    }
    
    // Check if token exists in response
    if (!isset($data['token'])) {
        return array(
            'success' => false,
            'message' => 'Token not found in response'
        );
    }
    
    // Return successful response with token
    return array(
        'success' => true,
        'token'   => $data['token'],
        'expires' => isset($data['expires']) ? $data['expires'] : null
    );
}

/**
 * AJAX handler to fetch token
 */
function ecom_waf_ajax_fetch_token() {
    // Check nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'ecom_waf_nonce')) {
        wp_send_json_error(array('message' => 'Security check failed'));
    }
    
    // Check user capabilities
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Permission denied'));
    }
    
    // Get parameters from form submission or saved options
    $client_id = isset($_POST['client_id']) ? sanitize_text_field($_POST['client_id']) : get_option('ecom_waf_client_id', '');
    $client_secret = isset($_POST['client_secret']) ? sanitize_text_field($_POST['client_secret']) : get_option('ecom_waf_client_secret', '');
    $domain = isset($_POST['domain']) ? sanitize_text_field($_POST['domain']) : parse_url(site_url(), PHP_URL_HOST);
    
    // Fetch token
    $result = ecom_waf_fetch_token($client_id, $client_secret, $domain);
    
    if ($result['success']) {
        // Store token in options (consider encrypting for production)
        update_option('ecom_waf_token', $result['token']);
        update_option('ecom_waf_token_expires', $result['expires']);
        update_option('ecom_waf_token_created', time());
        
        wp_send_json_success(array(
            'message' => 'Token retrieved successfully!',
            'expires' => $result['expires']
        ));
    } else {
        wp_send_json_error(array(
            'message' => $result['message']
        ));
    }
}
add_action('wp_ajax_ecom_waf_fetch_token', 'ecom_waf_ajax_fetch_token');

/**
 * Get valid token (fetches new one if expired)
 * 
 * @return string|false The token if valid, false on failure
 */
function ecom_waf_get_valid_token() {
    $token = get_option('ecom_waf_token', '');
    $expires = get_option('ecom_waf_token_expires', 0);
    $created = get_option('ecom_waf_token_created', 0);
    
    // Check if token exists and is not expired
    // If expires is timestamp, check directly; if it's duration in seconds, calculate expiry
    $is_expired = false;
    
    if (empty($token)) {
        $is_expired = true;
    } elseif (is_numeric($expires) && $expires > 0) {
        // Assume expires is a timestamp if it's greater than current time
        if ($expires > time()) {
            $expiry_time = $expires;
        } else {
            // Assume expires is duration in seconds
            $expiry_time = $created + $expires;
        }
        
        $is_expired = time() >= $expiry_time;
    }
    
    // If token is expired or doesn't exist, get a new one
    if ($is_expired) {
        $client_id = get_option('ecom_waf_client_id', '');
        $client_secret = get_option('ecom_waf_client_secret', '');
        $domain = parse_url(site_url(), PHP_URL_HOST);
        
        if (empty($client_id) || empty($client_secret)) {
            return false;
        }
        
        $result = ecom_waf_fetch_token($client_id, $client_secret, $domain);
        
        if ($result['success']) {
            $token = $result['token'];
            update_option('ecom_waf_token', $token);
            update_option('ecom_waf_token_expires', $result['expires']);
            update_option('ecom_waf_token_created', time());
        } else {
            return false;
        }
    }
    
    return $token;
}