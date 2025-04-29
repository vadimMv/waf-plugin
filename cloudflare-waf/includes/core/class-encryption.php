<?php
/**
 * Encryption for WAF Protection
 *
 * Handles encryption and decryption of sensitive data.
 *
 * @package CloudflareWAF
 * @subpackage Core
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CloudflareWAF_Encryption {

    /**
     * Option name for encryption key
     *
     * @var string
     */
    private $key_option = 'cloudflare_waf_encryption_key';

    /**
     * Logger instance
     *
     * @var CloudflareWAF_Logger
     */
    private $logger;

    /**
     * Encryption method
     *
     * @var string
     */
    private $cipher = 'aes-256-cbc';

    /**
     * Constructor
     */
    public function __construct() {
        if ( class_exists( 'CloudflareWAF_Logger' ) ) {
            $this->logger = new CloudflareWAF_Logger();
        }
        
        // Ensure encryption key exists
        $this->ensure_encryption_key();
    }

    /**
     * Encrypt data
     *
     * @param string $data Data to encrypt
     * @return string Encrypted data
     * @throws Exception If encryption fails
     */
    public function encrypt( $data ) {
        if ( empty( $data ) ) {
            return '';
        }
        
        $key = $this->get_encryption_key();
        
        // Generate initialization vector
        $iv_size = openssl_cipher_iv_length( $this->cipher );
        $iv = openssl_random_pseudo_bytes( $iv_size );
        
        // Encrypt the data
        $encrypted = openssl_encrypt(
            $data,
            $this->cipher,
            $key,
            0,
            $iv
        );
        
        if ( $encrypted === false ) {
            $this->log_error( 'Encryption failed' );
            throw new Exception( 'Encryption failed' );
        }
        
        // Combine IV and encrypted data
        $result = base64_encode( $iv . $encrypted );
        
        return $result;
    }

    /**
     * Decrypt data
     *
     * @param string $data Encrypted data to decrypt
     * @return string Decrypted data
     * @throws Exception If decryption fails
     */
    public function decrypt( $data ) {
        if ( empty( $data ) ) {
            return '';
        }
        
        $key = $this->get_encryption_key();
        
        // Decode the combined data
        $decoded = base64_decode( $data );
        
        // Extract IV and encrypted data
        $iv_size = openssl_cipher_iv_length( $this->cipher );
        $iv = substr( $decoded, 0, $iv_size );
        $encrypted = substr( $decoded, $iv_size );
        
        // Decrypt the data
        $decrypted = openssl_decrypt(
            $encrypted,
            $this->cipher,
            $key,
            0,
            $iv
        );
        
        if ( $decrypted === false ) {
            $this->log_error( 'Decryption failed' );
            throw new Exception( 'Decryption failed' );
        }
        
        return $decrypted;
    }

    /**
     * Ensure an encryption key exists
     *
     * @return bool Whether key already existed or was created
     */
    private function ensure_encryption_key() {
        $key = get_option( $this->key_option );
        
        if ( ! $key ) {
            // Generate a new secure key
            $key = $this->generate_encryption_key();
            
            // Store the key
            $key_added = add_option( $this->key_option, $key, '', 'no' ); // No autoload
            
            if ( ! $key_added ) {
                $this->log_error( 'Failed to store encryption key' );
                return false;
            }
            
            return true;
        }
        
        return true;
    }

    /**
     * Get the encryption key
     *
     * @return string The encryption key
     * @throws Exception If no encryption key exists
     */
    private function get_encryption_key() {
        $key = get_option( $this->key_option );
        
        if ( ! $key ) {
            $this->log_error( 'Encryption key not found' );
            throw new Exception( 'Encryption key not found' );
        }
        
        return $key;
    }

    /**
     * Generate a secure encryption key
     *
     * @return string The generated key
     */
    private function generate_encryption_key() {
        // Use WordPress salt if available
        if ( defined( 'AUTH_KEY' ) && AUTH_KEY ) {
            $base = AUTH_KEY;
        } else {
            $base = wp_generate_password( 64, true, true );
        }
        
        // Add some site-specific data
        $site_url = site_url();
        $db_prefix = wp_salt();
        
        // Generate key
        return substr( hash( 'sha256', $base . $site_url . $db_prefix ), 0, 32 );
    }

    /**
     * Log error message if logger is available
     *
     * @param string $message Error message
     */
    private function log_error( $message ) {
        if ( $this->logger instanceof CloudflareWAF_Logger ) {
            $this->logger->error( $message );
        }
    }
}