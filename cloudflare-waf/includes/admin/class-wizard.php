<?php
/**
 * The setup wizard functionality of the plugin.
 *
 * @package CloudflareWAF
 * @subpackage Admin
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CloudflareWAF_Wizard {

    /**
     * The ID of this plugin.
     *
     * @var string $plugin_name The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @var string $version The current version of this plugin.
     */
    private $version;

    /**
     * WAF service instance
     *
     * @var CloudflareWAF_Service
     */
    private $waf_service;

    /**
     * Logger instance
     *
     * @var CloudflareWAF_Logger
     */
    private $logger;

    /**
     * Wizard steps
     *
     * @var array
     */
    private $steps = array();

    /**
     * Current step
     *
     * @var string
     */
    private $current_step = '';

    /**
     * Initialize the class and set its properties.
     *
     * @param string $plugin_name The name of this plugin.
     * @param string $version The version of this plugin.
     * @param CloudflareWAF_Service $waf_service The WAF service instance.
     */
    public function __construct( $plugin_name, $version, $waf_service ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->waf_service = $waf_service;
        $this->logger = new CloudflareWAF_Logger();
        
        // Define wizard steps
        $this->steps = array(
            'welcome'      => array(
                'name'    => __( 'Welcome', 'cloudflare-waf' ),
                'view'    => array( $this, 'welcome_step' ),
                'handler' => array( $this, 'welcome_handler' ),
            ),
            'plan'         => array(
                'name'    => __( 'Protection Plan', 'cloudflare-waf' ),
                'view'    => array( $this, 'plan_step' ),
                'handler' => array( $this, 'plan_handler' ),
            ),
            'payment'      => array(
                'name'    => __( 'Payment', 'cloudflare-waf' ),
                'view'    => array( $this, 'payment_step' ),
                'handler' => array( $this, 'payment_handler' ),
            ),
            'verification' => array(
                'name'    => __( 'Domain Verification', 'cloudflare-waf' ),
                'view'    => array( $this, 'verification_step' ),
                'handler' => array( $this, 'verification_handler' ),
            ),
            'complete'     => array(
                'name'    => __( 'Complete', 'cloudflare-waf' ),
                'view'    => array( $this, 'complete_step' ),
                'handler' => '',
            ),
        );
        
        // Set the current step
        $this->current_step = isset( $_GET['step'] ) ? sanitize_key( $_GET['step'] ) : 'welcome';
        
        // Register admin hooks
        $this->register_hooks();
    }

    /**
     * Register wizard hooks
     */
    private function register_hooks() {
        // Add admin menu item
        add_action( 'admin_menu', array( $this, 'add_wizard_menu' ) );
        
        // Hide wizard menu item
        add_action( 'admin_head', array( $this, 'hide_wizard_menu' ) );
        
        // Enqueue wizard assets
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_wizard_assets' ) );
        
        // Process form submissions
        add_action( 'admin_init', array( $this, 'process_wizard_step' ) );
        
        // Handle wizard redirection after activation
        add_action( 'admin_init', array( $this, 'maybe_redirect_to_wizard' ) );
    }

    /**
     * Add wizard menu item
     */
    public function add_wizard_menu() {
        add_submenu_page(
            'cloudflare-waf-dashboard',
            __( 'Setup Wizard', 'cloudflare-waf' ),
            __( 'Setup Wizard', 'cloudflare-waf' ),
            'manage_options',
            'cloudflare-waf-wizard',
            array( $this, 'display_wizard' )
        );
    }

    /**
     * Hide wizard menu item from admin menu
     */
    public function hide_wizard_menu() {
        // Hide the wizard menu item while keeping it accessible via URL
        echo '<style>
            #adminmenu .wp-submenu a[href="admin.php?page=cloudflare-waf-wizard"] {
                display: none;
            }
        </style>';
    }
    
    /**
     * Redirect to wizard after plugin activation
     */
    public function maybe_redirect_to_wizard() {
        // Check for activation redirect
        if ( get_transient( 'cloudflare_waf_activation_redirect' ) ) {
            delete_transient( 'cloudflare_waf_activation_redirect' );
            
            // Only redirect if not already registered
            if ( ! $this->waf_service->auth()->is_site_registered() ) {
                // Don't redirect for network admin or bulk activations
                if ( ! is_network_admin() && ! isset( $_GET['activate-multi'] ) ) {
                    wp_safe_redirect( admin_url( 'admin.php?page=cloudflare-waf-wizard' ) );
                    exit;
                }
            }
        }
    }

    /**
     * Enqueue wizard styles and scripts
     */
    public function enqueue_wizard_assets( $hook ) {
        if ( 'cloudflare-waf_page_cloudflare-waf-wizard' !== $hook ) {
            return;
        }
        
        // Enqueue wizard CSS
        wp_enqueue_style( 
            'cloudflare-waf-wizard', 
            CLOUDFLARE_WAF_PLUGIN_URL . 'assets/css/wizard.css', 
            array(), 
            $this->version 
        );
        
        // Enqueue wizard JS
        wp_enqueue_script( 
            'cloudflare-waf-wizard', 
            CLOUDFLARE_WAF_PLUGIN_URL . 'assets/js/wizard.js', 
            array( 'jquery' ), 
            $this->version, 
            true 
        );
        
        // Localize script
        wp_localize_script( 
            'cloudflare-waf-wizard', 
            'cloudflareWAFWizard', 
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'cloudflare_waf_nonce' ),
                'strings' => array(
                    'verifying'     => __( 'Verifying domain...', 'cloudflare-waf' ),
                    'verified'      => __( 'Domain verified!', 'cloudflare-waf' ),
                    'error'         => __( 'Error:', 'cloudflare-waf' ),
                    'checking_dns'  => __( 'Checking DNS records...', 'cloudflare-waf' ),
                    'dns_correct'   => __( 'DNS records found!', 'cloudflare-waf' ),
                    'dns_incorrect' => __( 'DNS records not found or not propagated yet.', 'cloudflare-waf' ),
                )
            ) 
        );
        
        // If on payment step, maybe enqueue Stripe JS
        if ( 'payment' === $this->current_step ) {
            wp_enqueue_script( 
                'stripe-js', 
                'https://js.stripe.com/v3/', 
                array(), 
                null, 
                true 
            );
        }
    }

    /**
     * Process wizard step form submission
     */
    public function process_wizard_step() {
        // Only process on wizard page
        if ( ! isset( $_GET['page'] ) || 'cloudflare-waf-wizard' !== $_GET['page'] ) {
            return;
        }
        
        // Check if a form was submitted
        if ( ! isset( $_POST['cloudflare_waf_wizard_nonce'] ) ) {
            return;
        }
        
        // Verify nonce
        if ( ! wp_verify_nonce( $_POST['cloudflare_waf_wizard_nonce'], 'cloudflare_waf_wizard_' . $this->current_step ) ) {
            wp_die( __( 'Security check failed. Please try again.', 'cloudflare-waf' ) );
        }
        
        // Check if current step has a handler
        if ( isset( $this->steps[ $this->current_step ]['handler'] ) ) {
            // Call the step handler
            call_user_func( $this->steps[ $this->current_step ]['handler'] );
        }
    }

    /**
     * Display the setup wizard
     */
    public function display_wizard() {
        // Check user capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        
        // Get the current step
        $current_step = $this->current_step;
        
        // Check if step exists
        if ( ! isset( $this->steps[ $current_step ] ) ) {
            $current_step = 'welcome';
        }
        
        // Start output
        ?>
        <div class="wrap cloudflare-waf-wizard-wrap">
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <?php $this->wizard_steps_header(); ?>
            
            <div class="cloudflare-waf-wizard-content">
                <?php call_user_func( $this->steps[ $current_step ]['view'] ); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Display wizard steps header
     */
    private function wizard_steps_header() {
        ?>
        <div class="cloudflare-waf-wizard-steps">
            <ul>
                <?php foreach ( $this->steps as $step_key => $step ) : ?>
                    <?php
                    $classes = array();
                    
                    if ( $step_key === $this->current_step ) {
                        $classes[] = 'active';
                    } elseif ( $this->is_step_completed( $step_key ) ) {
                        $classes[] = 'completed';
                    }
                    
                    $class = ! empty( $classes ) ? ' class="' . implode( ' ', $classes ) . '"' : '';
                    ?>
                    <li<?php echo $class; ?>>
                        <div class="step-number"><?php echo esc_html( $this->get_step_number( $step_key ) ); ?></div>
                        <div class="step-name"><?php echo esc_html( $step['name'] ); ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    }

    /**
     * Get the step number based on its position
     *
     * @param string $step_key The step key
     * @return int Step number
     */
    private function get_step_number( $step_key ) {
        return array_search( $step_key, array_keys( $this->steps ) ) + 1;
    }

    /**
     * Check if a step is already completed
     *
     * @param string $step The step to check
     * @return bool
     */
    private function is_step_completed( $step ) {
        // Get completed steps
        $completed_steps = get_option( 'cloudflare_waf_completed_steps', array() );
        
        // Check if step is in completed steps
        return in_array( $step, $completed_steps, true );
    }

    /**
     * Mark a step as completed
     *
     * @param string $step The step to mark as completed
     */
    private function mark_step_completed( $step ) {
        // Get completed steps
        $completed_steps = get_option( 'cloudflare_waf_completed_steps', array() );
        
        // Add step if not already completed
        if ( ! in_array( $step, $completed_steps, true ) ) {
            $completed_steps[] = $step;
            update_option( 'cloudflare_waf_completed_steps', $completed_steps );
        }
    }

    /**
     * Get next step URL
     *
     * @param string $current_step The current step
     * @return string URL to the next step
     */
    private function get_next_step_url( $current_step ) {
        // Get current step index
        $current_index = array_search( $current_step, array_keys( $this->steps ) );
        
        // If not found or last step, return dashboard
        if ( false === $current_index || count( $this->steps ) <= $current_index + 1 ) {
            return admin_url( 'admin.php?page=cloudflare-waf-dashboard' );
        }
        
        // Get next step key
        $next_step = array_keys( $this->steps )[ $current_index + 1 ];
        
        // Return URL to next step
        return add_query_arg( 'step', $next_step, admin_url( 'admin.php?page=cloudflare-waf-wizard' ) );
    }

    /**
     * Welcome step view
     */
    public function welcome_step() {
        include CLOUDFLARE_WAF_PLUGIN_DIR . 'templates/wizard/step-1-welcome.php';
    }

    /**
     * Welcome step handler
     */
    public function welcome_handler() {
        // Mark step as completed
        $this->mark_step_completed( 'welcome' );
        
        // Redirect to next step
        wp_safe_redirect( $this->get_next_step_url( 'welcome' ) );
        exit;
    }

    /**
     * Plan selection step view
     */
    public function plan_step() {
        // Get available plans
        $plans = $this->waf_service->payment()->get_available_plans();
        
        include CLOUDFLARE_WAF_PLUGIN_DIR . 'templates/wizard/step-2-plan.php';
    }

    /**
     * Plan selection step handler
     */
    public function plan_handler() {
        // Validate plan selection
        if ( ! isset( $_POST['plan_id'] ) ) {
            // Redirect back with error
            wp_safe_redirect( add_query_arg( 'error', 'no_plan_selected', admin_url( 'admin.php?page=cloudflare-waf-wizard&step=plan' ) ) );
            exit;
        }
        
        // Get selected plan
        $plan_id = sanitize_text_field( $_POST['plan_id'] );
        
        // Validate plan exists
        $plans = $this->waf_service->payment()->get_available_plans();
        if ( ! isset( $plans[ $plan_id ] ) ) {
            // Redirect back with error
            wp_safe_redirect( add_query_arg( 'error', 'invalid_plan', admin_url( 'admin.php?page=cloudflare-waf-wizard&step=plan' ) ) );
            exit;
        }
        
        // Store selected plan in transient
        set_transient( 'cloudflare_waf_selected_plan', $plan_id, HOUR_IN_SECONDS );
        
        // Mark step as completed
        $this->mark_step_completed( 'plan' );
        
        // Redirect to next step
        wp_safe_redirect( $this->get_next_step_url( 'plan' ) );
        exit;
    }

    /**
     * Payment step view
     */
    public function payment_step() {
        // Get selected plan
        $selected_plan_id = get_transient( 'cloudflare_waf_selected_plan' );
        
        // If no plan selected, redirect to plan step
        if ( ! $selected_plan_id ) {
            wp_safe_redirect( admin_url( 'admin.php?page=cloudflare-waf-wizard&step=plan' ) );
            exit;
        }
        
        // Get plan details
        $plans = $this->waf_service->payment()->get_available_plans();
        $selected_plan = isset( $plans[ $selected_plan_id ] ) ? $plans[ $selected_plan_id ] : null;
        
        // If plan not found, redirect to plan step
        if ( ! $selected_plan ) {
            wp_safe_redirect( admin_url( 'admin.php?page=cloudflare-waf-wizard&step=plan' ) );
            exit;
        }
        
        // Get payment client token/key
        $payment_key = $this->waf_service->payment()->get_payment_client_key();
        
        include CLOUDFLARE_WAF_PLUGIN_DIR . 'templates/wizard/step-3-payment.php';
    }

    /**
     * Payment step handler
     */
    public function payment_handler() {
        // Validate payment token
        if ( ! isset( $_POST['payment_token'] ) ) {
            // Redirect back with error
            wp_safe_redirect( add_query_arg( 'error', 'payment_failed', admin_url( 'admin.php?page=cloudflare-waf-wizard&step=payment' ) ) );
            exit;
        }
        
        // Get payment token
        $payment_token = sanitize_text_field( $_POST['payment_token'] );
        
        // Get selected plan
        $selected_plan_id = get_transient( 'cloudflare_waf_selected_plan' );
        
        // Get site info
        $site_url = site_url();
        $admin_email = get_option( 'admin_email' );
        $site_name = get_bloginfo( 'name' );
        
        try {
            // Process payment and create subscription
            $subscription = $this->waf_service->payment()->create_subscription(
                $payment_token,
                $selected_plan_id,
                array(
                    'email'     => $admin_email,
                    'site_name' => $site_name,
                    'site_url'  => $site_url,
                )
            );
            
            // Register site with WAF service if subscription created
            if ( $subscription && isset( $subscription['id'] ) ) {
                $registration = $this->waf_service->auth()->register_site(
                    array(
                        'domain'         => parse_url( $site_url, PHP_URL_HOST ),
                        'email'          => $admin_email,
                        'site_name'      => $site_name,
                        'subscription_id' => $subscription['id'],
                    )
                );
                
                // If registration failed, log the error
                if ( ! $registration || is_wp_error( $registration ) ) {
                    $error_message = is_wp_error( $registration ) ? $registration->get_error_message() : 'Unknown registration error';
                    $this->logger->error( 'Site registration failed: ' . $error_message );
                    
                    // Redirect with error
                    wp_safe_redirect( add_query_arg( 'error', 'registration_failed', admin_url( 'admin.php?page=cloudflare-waf-wizard&step=payment' ) ) );
                    exit;
                }
            } else {
                // Subscription creation failed
                $this->logger->error( 'Subscription creation failed' );
                
                // Redirect with error
                wp_safe_redirect( add_query_arg( 'error', 'subscription_failed', admin_url( 'admin.php?page=cloudflare-waf-wizard&step=payment' ) ) );
                exit;
            }
        } catch ( Exception $e ) {
            // Log the error
            $this->logger->error( 'Payment processing error: ' . $e->getMessage() );
            
            // Redirect with error
            wp_safe_redirect( add_query_arg( 'error', 'payment_processing_failed', admin_url( 'admin.php?page=cloudflare-waf-wizard&step=payment' ) ) );
            exit;
        }
        
        // Mark step as completed
        $this->mark_step_completed( 'payment' );
        
        // Redirect to next step
        wp_safe_redirect( $this->get_next_step_url( 'payment' ) );
        exit;
    }

    /**
     * Verification step view
     */
    public function verification_step() {
        // Check if site is registered
        if ( ! $this->waf_service->auth()->is_site_registered() ) {
            wp_safe_redirect( admin_url( 'admin.php?page=cloudflare-waf-wizard&step=welcome' ) );
            exit;
        }
        
        // Get domain
        $domain = parse_url( site_url(), PHP_URL_HOST );
        
        // Get verification instructions
        $verification = $this->waf_service->get_domain_verification_info();
        
        include CLOUDFLARE_WAF_PLUGIN_DIR . 'templates/wizard/step-4-verification.php';
    }

    /**
     * Verification step handler
     */
    public function verification_handler() {
        // Check verification status
        $verification_status = $this->waf_service->verify_domain();
        
        // If verification failed, redirect with error
        if ( ! $verification_status || is_wp_error( $verification_status ) ) {
            $error_message = is_wp_error( $verification_status ) ? $verification_status->get_error_message() : 'Domain verification failed';
            $this->logger->error( 'Domain verification failed: ' . $error_message );
            
            // Redirect with error
            wp_safe_redirect( add_query_arg( 'error', 'verification_failed', admin_url( 'admin.php?page=cloudflare-waf-wizard&step=verification' ) ) );
            exit;
        }
        
        // Mark step as completed
        $this->mark_step_completed( 'verification' );
        
        // Redirect to next step
        wp_safe_redirect( $this->get_next_step_url( 'verification' ) );
        exit;
    }

    /**
     * Complete step view
     */
    public function complete_step() {
        // Check all previous steps are completed
        foreach ( array_keys( $this->steps ) as $step ) {
            // Skip 'complete' step
            if ( 'complete' === $step ) {
                continue;
            }
            
            // If step not completed, redirect to that step
            if ( ! $this->is_step_completed( $step ) ) {
                wp_safe_redirect( add_query_arg( 'step', $step, admin_url( 'admin.php?page=cloudflare-waf-wizard' ) ) );
                exit;
            }
        }
        
        // Get protection status
        $status = $this->waf_service->get_protection_status();
        
        include CLOUDFLARE_WAF_PLUGIN_DIR . 'templates/wizard/step-5-complete.php';
    }

    /**
     * Ajax handler for checking domain verification
     */
    public function ajax_check_domain_verification() {
        // Check nonce
        if ( ! check_ajax_referer( 'cloudflare_waf_nonce', 'nonce', false ) ) {
            wp_send_json_error( array(
                'message' => __( 'Security check failed.', 'cloudflare-waf' )
            ));
        }
        
        // Check verification status
        $verification_status = $this->waf_service->verify_domain();
        
        if ( $verification_status && ! is_wp_error( $verification_status ) ) {
            wp_send_json_success( array(
                'verified' => true,
                'message' => __( 'Domain verified successfully!', 'cloudflare-waf' )
            ));
        } else {
            $error_message = is_wp_error( $verification_status ) ? $verification_status->get_error_message() : __( 'Domain verification failed.', 'cloudflare-waf' );
            
            wp_send_json_error( array(
                'verified' => false,
                'message' => $error_message
            ));
        }
    }

    /**
     * Ajax handler for checking DNS propagation
     */
    public function ajax_check_dns_propagation() {
        // Check nonce
        if ( ! check_ajax_referer( 'cloudflare_waf_nonce', 'nonce', false ) ) {
            wp_send_json_error( array(
                'message' => __( 'Security check failed.', 'cloudflare-waf' )
            ));
        }
        
        // Check DNS propagation
        $dns_status = $this->waf_service->check_dns_propagation();
        
        if ( $dns_status && ! is_wp_error( $dns_status ) ) {
            wp_send_json_success( array(
                'propagated' => true,
                'message' => __( 'DNS records have propagated!', 'cloudflare-waf' )
            ));
        } else {
            $error_message = is_wp_error( $dns_status ) ? $dns_status->get_error_message() : __( 'DNS records have not propagated yet.', 'cloudflare-waf' );
            
            wp_send_json_error( array(
                'propagated' => false,
                'message' => $error_message
            ));
        }
    }
}