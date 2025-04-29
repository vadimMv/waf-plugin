<?php
/**
 * Credentials Store for WAF Protection
 *
 * Handles secure storage and retrieval of API credentials.
 *
 * @package CloudflareWAF
 * @subpackage DB
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CloudflareWAF_Credentials_Store {

    /**
     * Option name for client ID
     *
     * @var string
     */
    private $client_id_option = 'cloudflare_waf_client_id';

    /**
     * Option name for client secret
     *
     * @var string
     */
    private $client_secret_option = 'cloudflare_waf_client_secret';

    /**
     * Logger instance
     *
     * @var CloudflareWAF_Logger
     */
    private $logger;

    /**
     * Encryption instance
     *
     * @var CloudflareWAF_Encryption
     */
    private $encryption;

    /**
     * Constructor
     */
    public function __construct() {
        $this->logger = new CloudflareWAF_Logger();
        $this->encryption = new CloudflareWAF_Encryption();
    }

    /**
     * Save client credentials
     *
     * @param string $client_id The client ID
     * @param string $client_secret The client secret
     * @return bool Whether credentials were saved successfully
     */
    public function save_credentials( $client_id, $client_secret ) {
        $this->logger->info( 'Saving client credentials' );
        
        // Store client ID (not encrypted)
        $id_saved = update_option( $this->client_id_option, $client_id, false );
        
        // Encrypt and store client secret
        $encrypted_secret = $this->encryption->encrypt( $client_secret );
        $secret_saved = update_option( $this->client_secret_option, $encrypted_secret, false );
        
        return $id_saved && $secret_saved;
    }

    /**
     * Get client ID
     *
     * @return string|false Client ID or false if not set
     */
    public function get_client_id() {
        return get_option( $this->client_id_option, false );
    }

    /**
     * Get client secret
     *
     * @return string|false Decrypted client secret or false if not set or decryption fails
     */
    public function get_client_secret() {
        $encrypted_secret = get_option( $this->client_secret_option, false );
        
        if ( ! $encrypted_secret ) {
            return false;
        }
        
        try {
            return $this->encryption->decrypt( $encrypted_secret );
        } catch ( Exception $e ) {
            $this->logger->error( 'Failed to decrypt client secret: ' . $e->getMessage() );
            return false;
        }
    }

    /**
     * Check if credentials exist
     *
     * @return bool Whether both client ID and secret exist
     */
    public function has_credentials() {
        return ( $this->get_client_id() && $this->get_client_secret() );
    }

    /**
     * Clear stored credentials
     *
     * @return bool Whether credentials were cleared successfully
     */
    public function clear_credentials() {
        $this->logger->info( 'Clearing client credentials' );
        
        $id_deleted = delete_option( $this->client_id_option );
        $secret_deleted = delete_option( $this->client_secret_option );
        
        return $id_deleted && $secret_deleted;
    }

    /**
     * Rotate client secret
     *
     * @param string $new_client_secret The new client secret
     * @return bool Whether secret was rotated successfully
     */
    public function rotate_client_secret( $new_client_secret ) {
        $this->logger->info( 'Rotating client secret' );
        
        // Encrypt and store new client secret
        $encrypted_secret = $this->encryption->encrypt( $new_client_secret );
        return update_option( $this->client_secret_option, $encrypted_secret, false );
    }
}