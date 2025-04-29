<?php
/**
 * Token Store for WAF Protection
 *
 * Handles storage and retrieval of authentication tokens.
 *
 * @package CloudflareWAF
 * @subpackage DB
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CloudflareWAF_Token_Store {

    /**
     * Option name for token storage
     *
     * @var string
     */
    private $token_option = 'cloudflare_waf_auth_token';

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
        $this->logger = new CloudflareWAF_Logger();
    }

    /**
     * Get the stored authentication token
     *
     * @return string|false The token or false if no valid token exists
     */
    public function get_token() {
        $token_data = get_option( $this->token_option );
        
        // If no token exists
        if ( ! $token_data ) {
            return false;
        }
        
        // Check if token exists and is valid
        if ( 
            is_array( $token_data ) && 
            isset( $token_data['token'] ) && 
            isset( $token_data['expires_at'] ) &&
            time() < $token_data['expires_at']
        ) {
            return $token_data['token'];
        }
        
        // Token is expired
        $this->logger->debug( 'Token has expired, clearing' );
        $this->clear_token();
        
        return false;
    }

    /**
     * Store an authentication token with expiration
     *
     * @param string $token The token to store
     * @param int $expires_in Seconds until token expires
     * @return bool Whether the token was stored successfully
     */
    public function set_token( $token, $expires_in = 3600 ) {
        // Add a buffer to ensure we refresh before expiration (5 minutes)
        $buffer = 300;
        $expires_at = time() + $expires_in - $buffer;
        
        $token_data = [
            'token' => $token,
            'expires_at' => $expires_at,
            'created_at' => time()
        ];
        
        $this->logger->debug( 'Setting new token with expiration', [
            'expires_in' => $expires_in,
            'expires_at' => date( 'Y-m-d H:i:s', $expires_at )
        ]);
        
        return update_option( $this->token_option, $token_data, false );
    }

    /**
     * Clear the stored token
     *
     * @return bool Whether the token was cleared successfully
     */
    public function clear_token() {
        $this->logger->debug( 'Clearing authentication token' );
        return delete_option( $this->token_option );
    }

    /**
     * Check if a token exists and is valid
     *
     * @return bool Whether a valid token exists
     */
    public function has_valid_token() {
        return (bool) $this->get_token();
    }

    /**
     * Get token expiration timestamp
     *
     * @return int|false Expiration timestamp or false if no token exists
     */
    public function get_token_expiration() {
        $token_data = get_option( $this->token_option );
        
        if ( 
            is_array( $token_data ) && 
            isset( $token_data['expires_at'] )
        ) {
            return $token_data['expires_at'];
        }
        
        return false;
    }

    /**
     * Get time remaining until token expiration
     *
     * @return int Seconds until expiration, 0 if expired
     */
    public function get_token_remaining_time() {
        $expiration = $this->get_token_expiration();
        
        if ( ! $expiration ) {
            return 0;
        }
        
        $remaining = $expiration - time();
        
        return max( 0, $remaining );
    }
}