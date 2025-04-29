<?php
/**
 * Logger for WAF Protection
 *
 * Handles logging of plugin events and errors.
 *
 * @package CloudflareWAF
 * @subpackage Core
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CloudflareWAF_Logger {

    /**
     * Log levels
     */
    const DEBUG = 'debug';
    const INFO = 'info';
    const WARNING = 'warning';
    const ERROR = 'error';

    /**
     * Option name for debug mode
     *
     * @var string
     */
    private $debug_option = 'cloudflare_waf_debug_mode';

    /**
     * Option name for log retention
     *
     * @var string
     */
    private $retention_option = 'cloudflare_waf_log_retention';

    /**
     * Log file path
     *
     * @var string
     */
    private $log_file;

    /**
     * Constructor
     */
    public function __construct() {
        $upload_dir = wp_upload_dir();
        $this->log_file = trailingslashit( $upload_dir['basedir'] ) . 'cloudflare-waf-logs.log';
    }

    /**
     * Log a debug message
     *
     * @param string $message The message to log
     * @param array $context Additional context data
     */
    public function debug( $message, $context = [] ) {
        // Only log debug messages if debug mode is enabled
        if ( $this->is_debug_mode() ) {
            $this->log( self::DEBUG, $message, $context );
        }
    }

    /**
     * Log an info message
     *
     * @param string $message The message to log
     * @param array $context Additional context data
     */
    public function info( $message, $context = [] ) {
        $this->log( self::INFO, $message, $context );
    }

    /**
     * Log a warning message
     *
     * @param string $message The message to log
     * @param array $context Additional context data
     */
    public function warning( $message, $context = [] ) {
        $this->log( self::WARNING, $message, $context );
    }

    /**
     * Log an error message
     *
     * @param string $message The message to log
     * @param array $context Additional context data
     */
    public function error( $message, $context = [] ) {
        $this->log( self::ERROR, $message, $context );
    }

    /**
     * Log a message with a specific level
     *
     * @param string $level The log level
     * @param string $message The message to log
     * @param array $context Additional context data
     */
    private function log( $level, $message, $context = [] ) {
        // Check if logging is enabled
        if ( ! $this->is_logging_enabled() ) {
            return;
        }
        
        // Format log entry
        $timestamp = current_time( 'mysql' );
        $formatted_context = empty( $context ) ? '' : ' ' . $this->format_context( $context );
        $log_entry = sprintf( "[%s] [%s] %s%s\n", $timestamp, strtoupper( $level ), $message, $formatted_context );
        
        // Write to log file
        $this->write_to_log( $log_entry );
        
        // For errors, also write to WordPress error log
        if ( $level === self::ERROR && defined( 'WP_DEBUG' ) && WP_DEBUG === true ) {
            error_log( 'CloudflareWAF: ' . $message . $formatted_context );
        }
    }

    /**
     * Format context data for logging
     *
     * @param array $context The context data
     * @return string Formatted context
     */
    private function format_context( $context ) {
        // Filter sensitive data
        $context = $this->filter_sensitive_data( $context );
        
        return json_encode( $context );
    }

    /**
     * Filter sensitive data from context
     *
     * @param array $context The context data
     * @return array Filtered context
     */
    private function filter_sensitive_data( $context ) {
        $sensitive_keys = [
            'client_secret',
            'token',
            'api_key',
            'password',
            'secret',
            'key'
        ];
        
        foreach ( $context as $key => $value ) {
            // Check for sensitive keys
            foreach ( $sensitive_keys as $sensitive_key ) {
                if ( stripos( $key, $sensitive_key ) !== false ) {
                    $context[$key] = '[REDACTED]';
                    break;
                }
            }
            
            // Recursively filter nested arrays
            if ( is_array( $value ) ) {
                $context[$key] = $this->filter_sensitive_data( $value );
            }
        }
        
        return $context;
    }

    /**
     * Write entry to log file
     *
     * @param string $entry The formatted log entry
     */
    private function write_to_log( $entry ) {
        // Check log file size and rotate if necessary
        $this->maybe_rotate_logs();
        
        // Append to log file
        $file_handle = @fopen( $this->log_file, 'a' );
        if ( $file_handle ) {
            @fwrite( $file_handle, $entry );
            @fclose( $file_handle );
        }
    }

    /**
     * Rotate logs if they exceed the maximum size
     */
    private function maybe_rotate_logs() {
        $max_size = 5 * 1024 * 1024; // 5 MB by default
        
        // Check if log file exists and exceeds max size
        if ( file_exists( $this->log_file ) && filesize( $this->log_file ) > $max_size ) {
            $backup_file = $this->log_file . '.' . date( 'Y-m-d-H-i-s' );
            @rename( $this->log_file, $backup_file );
            
            // Keep only a limited number of backups
            $this->cleanup_old_logs();
        }
    }

    /**
     * Clean up old log files
     */
    private function cleanup_old_logs() {
        $retention_days = $this->get_log_retention();
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'];
        
        // Find log files older than retention period
        $files = glob( $log_dir . '/cloudflare-waf-logs.log.*' );
        if ( ! empty( $files ) ) {
            foreach ( $files as $file ) {
                // Extract date from filename
                $date_part = basename( $file );
                $date_part = str_replace( 'cloudflare-waf-logs.log.', '', $date_part );
                
                // Check if file is older than retention period
                $file_time = strtotime( $date_part );
                if ( $file_time && ( time() - $file_time ) > ( $retention_days * 86400 ) ) {
                    @unlink( $file );
                }
            }
        }
    }

    /**
     * Check if debug mode is enabled
     *
     * @return bool Whether debug mode is enabled
     */
    private function is_debug_mode() {
        return get_option( $this->debug_option, false );
    }

    /**
     * Check if logging is enabled
     *
     * @return bool Whether logging is enabled
     */
    private function is_logging_enabled() {
        // Always enable logging for now
        return true;
    }

    /**
     * Get log retention period in days
     *
     * @return int Retention period in days
     */
    private function get_log_retention() {
        return (int) get_option( $this->retention_option, 7 );
    }

    /**
     * Get log entries with optional filtering
     *
     * @param string $level Filter by log level
     * @param int $limit Maximum number of entries to return
     * @return array Log entries
     */
    public function get_log_entries( $level = null, $limit = 100 ) {
        if ( ! file_exists( $this->log_file ) ) {
            return [];
        }
        
        $entries = [];
        $file_handle = @fopen( $this->log_file, 'r' );
        
        if ( $file_handle ) {
            // Read log file from end to get the most recent entries
            $lines = [];
            $position = -1;
            $line = '';
            $count = 0;
            
            // Read the file backwards until we reach the limit
            while ( -1 !== fseek( $file_handle, $position, SEEK_END ) && $count < $limit ) {
                $char = fgetc( $file_handle );
                
                if ( $char === "\n" ) {
                    // When we reach a new line, store the line and reset
                    if ( ! empty( $line ) ) {
                        $lines[] = strrev( $line );
                        $line = '';
                        $count++;
                    }
                } else {
                    // Add character to current line
                    $line .= $char;
                }
                
                $position--;
            }
            
            // Add the last line if any
            if ( ! empty( $line ) ) {
                $lines[] = strrev( $line );
            }
            
            // Close the file
            @fclose( $file_handle );
            
            // Reverse the lines to get chronological order
            $lines = array_reverse( $lines );
            
            // Parse and filter log entries
            foreach ( $lines as $log_line ) {
                // Parse log entry
                if ( preg_match( '/\[(.*?)\] \[(.*?)\] (.*?)(?:\s(\{.*\}))?$/', $log_line, $matches ) ) {
                    $entry_level = strtolower( $matches[2] );
                    
                    // Filter by level if specified
                    if ( $level && $entry_level !== strtolower( $level ) ) {
                        continue;
                    }
                    
                    $entry = [
                        'timestamp' => $matches[1],
                        'level' => $entry_level,
                        'message' => $matches[3],
                        'context' => isset( $matches[4] ) ? json_decode( $matches[4], true ) : []
                    ];
                    
                    $entries[] = $entry;
                }
            }
        }
        
        return $entries;
    }

    /**
     * Clear log file
     *
     * @return bool Whether log was cleared successfully
     */
    public function clear_logs() {
        if ( file_exists( $this->log_file ) ) {
            return @unlink( $this->log_file );
        }
        
        return true;
    }
}