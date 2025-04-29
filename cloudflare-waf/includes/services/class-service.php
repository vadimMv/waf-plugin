<?php
/**
 * WAF Service for Cloudflare Protection
 *
 * Main service class that provides access to all controllers.
 *
 * @package CloudflareWAF
 * @subpackage Services
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CloudflareWAF_Service {

    /**
     * Auth controller instance
     *
     * @var CloudflareWAF_Auth_Controller
     */
    private $auth;

    /**
     * Cloudflare controller instance
     *
     * @var CloudflareWAF_Cloudflare_Controller
     */
    private $cloudflare;

    /**
     * Payment controller instance
     *
     * @var CloudflareWAF_Payment_Controller
     */
    private $payment;

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
        
        // Initialize controllers
        $this->auth = new CloudflareWAF_Auth_Controller();
        $this->cloudflare = new CloudflareWAF_Cloudflare_Controller();
        $this->payment = new CloudflareWAF_Payment_Controller();
        
        $this->logger->debug( 'WAF Service initialized' );
    }

    /**
     * Get auth controller
     *
     * @return CloudflareWAF_Auth_Controller
     */
    public function auth() {
        return $this->auth;
    }

    /**
     * Get Cloudflare controller
     *
     * @return CloudflareWAF_Cloudflare_Controller
     */
    public function cloudflare() {
        return $this->cloudflare;
    }

    /**
     * Get payment controller
     *
     * @return CloudflareWAF_Payment_Controller
     */
    public function payment() {
        return $this->payment;
    }

    /**
     * Register site
     *
     * @param string $admin_email Admin email
     * @return array|WP_Error Registration result or error
     */
    public function register_site( $admin_email = '' ) {
        $this->logger->info( 'Beginning site registration' );
        
        // Register site with auth service
        $registration = $this->auth->register_site( $admin_email );
        
        if ( is_wp_error( $registration ) ) {
            return $registration;
        }
        
        $this->logger->info( 'Site registered successfully' );
        
        return $registration;
    }

    /**
     * Setup protection with default settings
     *
     * @param string $protection_level Protection level
     * @return array|WP_Error Setup result or error
     */
    public function setup_protection( $protection_level = 'medium' ) {
        $this->logger->info( 'Setting up WAF protection', [ 'level' => $protection_level ] );
        
        // Check if site is registered
        if ( ! $this->auth->is_site_registered() ) {
            $this->logger->error( 'Cannot setup protection - site not registered' );
            return new WP_Error( 'not_registered', 'Site is not registered. Please register first.' );
        }
        
        // Configure WAF protection
        $result = $this->cloudflare->configure_waf( $protection_level );
        
        if ( is_wp_error( $result ) ) {
            return $result;
        }
        
        $this->logger->info( 'WAF protection set up successfully' );
        
        // Get DNS instructions
        $dns_instructions = $this->cloudflare->get_dns_instructions();
        
        if ( is_wp_error( $dns_instructions ) ) {
            // Don't fail if DNS instructions fail, still return success
            $this->logger->warning( 'Failed to get DNS instructions', [
                'error' => $dns_instructions->get_error_message()
            ]);
            
            return [
                'success' => true,
                'message' => 'WAF protection configured successfully, but could not get DNS instructions.',
                'protection_status' => isset( $result['status'] ) ? $result['status'] : 'pending'
            ];
        }
        
        return [
            'success' => true,
            'message' => 'WAF protection configured successfully.',
            'protection_status' => isset( $result['status'] ) ? $result['status'] : 'pending',
            'dns_instructions' => $dns_instructions
        ];
    }

    /**
     * Get protection status with details
     *
     * @return array|WP_Error Status info or error
     */
    public function get_protection_status() {
        $this->logger->debug( 'Getting comprehensive protection status' );
        
        // Check if site is registered
        if ( ! $this->auth->is_site_registered() ) {
            return [
                'registered' => false,
                'status' => 'not_registered',
                'message' => 'Site is not registered'
            ];
        }
        
        // Get WAF status
        $waf_status = $this->cloudflare->get_status();
        
        if ( is_wp_error( $waf_status ) ) {
            return [
                'registered' => true,
                'status' => 'error',
                'message' => 'Error getting WAF status: ' . $waf_status->get_error_message()
            ];
        }
        
        // Get subscription status
        $subscription_status = $this->payment->get_subscription_status();
        $has_active_subscription = $this->payment->has_active_subscription();
        
        // Get DNS status
        $dns_status = $this->cloudflare->check_dns_status();
        $dns_configured = ! is_wp_error( $dns_status ) && 
                          isset( $dns_status['configured'] ) && 
                          $dns_status['configured'] === true;
        
        // Determine overall status
        $overall_status = 'inactive';
        $status_message = 'Protection is not active';
        
        if ( ! $has_active_subscription ) {
            $overall_status = 'no_subscription';
            $status_message = 'No active subscription';
        } elseif ( ! $dns_configured ) {
            $overall_status = 'dns_pending';
            $status_message = 'DNS configuration pending';
        } elseif ( isset( $waf_status['status'] ) && $waf_status['status'] === 'active' ) {
            $overall_status = 'active';
            $status_message = 'Protection is active';
        } elseif ( isset( $waf_status['status'] ) && $waf_status['status'] === 'issues' ) {
            $overall_status = 'issues';
            $status_message = isset( $waf_status['message'] ) ? $waf_status['message'] : 'Protection has issues';
        }
        
        // Compile complete status
        return [
            'registered' => true,
            'status' => $overall_status,
            'message' => $status_message,
            'waf_status' => $waf_status,
            'subscription_status' => $subscription_status,
            'dns_configured' => $dns_configured,
            'last_check' => current_time( 'timestamp' )
        ];
    }

    /**
     * Get protection dashboard data
     *
     * @return array|WP_Error Dashboard data or error
     */
    public function get_dashboard_data() {
        $this->logger->debug( 'Getting dashboard data' );
        
        // Get protection status
        $status = $this->get_protection_status();
        
        if ( isset( $status['status'] ) && $status['status'] === 'active' ) {
            // Get statistics for last week
            $statistics = $this->cloudflare->get_statistics( 'week' );
            
            // Get recent reports (10 most recent)
            $reports = $this->cloudflare->get_reports( 'week', 10 );
            
            // Format data for dashboard
            return [
                'status' => $status,
                'statistics' => is_wp_error( $statistics ) ? [] : $statistics,
                'reports' => is_wp_error( $reports ) ? [] : $reports
            ];
        }
        
        // If protection not active, just return status
        return [
            'status' => $status,
            'statistics' => [],
            'reports' => []
        ];
    }

    /**
     * Complete setup wizard
     *
     * @param array $settings Setup settings
     * @return array|WP_Error Setup result or error
     */
    public function complete_setup( $settings ) {
        $this->logger->info( 'Completing setup wizard', [ 'settings' => array_keys( $settings ) ] );
        
        $default_settings = [
            'email' => '',
            'plan_id' => '',
            'protection_level' => 'medium',
            'complete_registration' => true
        ];
        
        $settings = wp_parse_args( $settings, $default_settings );
        
        // Register site if needed
        if ( $settings['complete_registration'] && ! $this->auth->is_site_registered() ) {
            $registration = $this->register_site( $settings['email'] );
            
            if ( is_wp_error( $registration ) ) {
                return $registration;
            }
        }
        
        // Setup subscription if plan ID provided
        if ( ! empty( $settings['plan_id'] ) ) {
            // Create checkout session
            $checkout = $this->payment->create_checkout_session( $settings['plan_id'] );
            
            if ( is_wp_error( $checkout ) ) {
                return $checkout;
            }
            
            // Setup WAF protection
            $protection = $this->setup_protection( $settings['protection_level'] );
            
            if ( is_wp_error( $protection ) ) {
                return $protection;
            }
            
            return [
                'success' => true,
                'checkout_url' => isset( $checkout['url'] ) ? $checkout['url'] : '',
                'protection' => $protection,
                'setup_complete' => true
            ];
        }
        
        // If no plan ID, just setup WAF protection
        $protection = $this->setup_protection( $settings['protection_level'] );
        
        if ( is_wp_error( $protection ) ) {
            return $protection;
        }
        
        return [
            'success' => true,
            'protection' => $protection,
            'setup_complete' => true
        ];
    }
}