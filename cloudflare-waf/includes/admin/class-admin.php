<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @package CloudflareWAF
 * @subpackage Admin
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CloudflareWAF_Admin {

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
    }

    /**
     * Register the stylesheets for the admin area.
     */
    public function enqueue_styles( $hook ) {
        // Only enqueue on our plugin pages
        if ( strpos( $hook, 'cloudflare-waf' ) === false ) {
            return;
        }

        wp_enqueue_style( 
            $this->plugin_name, 
            CLOUDFLARE_WAF_PLUGIN_URL . 'assets/css/admin.css', 
            array(), 
            $this->version, 
            'all' 
        );
    }

    /**
     * Register the JavaScript for the admin area.
     */
    public function enqueue_scripts( $hook ) {
        // Only enqueue on our plugin pages
        if ( strpos( $hook, 'cloudflare-waf' ) === false ) {
            return;
        }

        wp_enqueue_script( 
            $this->plugin_name, 
            CLOUDFLARE_WAF_PLUGIN_URL . 'assets/js/admin.js', 
            array( 'jquery' ), 
            $this->version, 
            true 
        );

        // Localize the script with necessary data
        wp_localize_script( 
            $this->plugin_name, 
            'cloudflareWafAdmin', 
            array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce' => wp_create_nonce( 'cloudflare_waf_nonce' ),
                'is_registered' => $this->waf_service->auth()->is_site_registered() ? 'yes' : 'no',
                'strings' => array(
                    'saving' => __( 'Saving...', 'cloudflare-waf' ),
                    'saved' => __( 'Settings saved!', 'cloudflare-waf' ),
                    'error' => __( 'An error occurred', 'cloudflare-waf' ),
                    'confirm_cancel' => __( 'Are you sure you want to cancel your subscription? This will disable WAF protection.', 'cloudflare-waf' )
                )
            )
        );

        // Enqueue page-specific scripts based on the current admin page
        if ( strpos( $hook, 'cloudflare-waf-dashboard' ) !== false ) {
            wp_enqueue_script( 
                $this->plugin_name . '-dashboard', 
                CLOUDFLARE_WAF_PLUGIN_URL . 'assets/js/dashboard.js', 
                array( 'jquery', $this->plugin_name ), 
                $this->version, 
                true 
            );
        } elseif ( strpos( $hook, 'cloudflare-waf-settings' ) !== false ) {
            wp_enqueue_script( 
                $this->plugin_name . '-settings', 
                CLOUDFLARE_WAF_PLUGIN_URL . 'assets/js/settings.js', 
                array( 'jquery', $this->plugin_name ), 
                $this->version, 
                true 
            );
        } elseif ( strpos( $hook, 'cloudflare-waf-wizard' ) !== false ) {
            wp_enqueue_script( 
                $this->plugin_name . '-wizard', 
                CLOUDFLARE_WAF_PLUGIN_URL . 'assets/js/wizard.js', 
                array( 'jquery', $this->plugin_name ), 
                $this->version, 
                true 
            );
        }
    }

    /**
     * Register admin menu items
     */
    public function register_admin_menu() {
        // Main menu
        add_menu_page(
            __( 'Cloudflare WAF', 'cloudflare-waf' ),
            __( 'Cloudflare WAF', 'cloudflare-waf' ),
            'manage_options',
            'cloudflare-waf-dashboard',
            array( $this, 'display_dashboard_page' ),
            'dashicons-shield',
            81
        );

        // Dashboard submenu (same as main menu)
        add_submenu_page(
            'cloudflare-waf-dashboard',
            __( 'Dashboard', 'cloudflare-waf' ),
            __( 'Dashboard', 'cloudflare-waf' ),
            'manage_options',
            'cloudflare-waf-dashboard',
            array( $this, 'display_dashboard_page' )
        );

        // Settings page
        add_submenu_page(
            'cloudflare-waf-dashboard',
            __( 'Settings', 'cloudflare-waf' ),
            __( 'Settings', 'cloudflare-waf' ),
            'manage_options',
            'cloudflare-waf-settings',
            array( $this, 'display_settings_page' )
        );

        // Reports page
        add_submenu_page(
            'cloudflare-waf-dashboard',
            __( 'Reports', 'cloudflare-waf' ),
            __( 'Reports', 'cloudflare-waf' ),
            'manage_options',
            'cloudflare-waf-reports',
            array( $this, 'display_reports_page' )
        );

        // Hidden wizard page
        add_submenu_page(
            null,
            __( 'Setup Wizard', 'cloudflare-waf' ),
            __( 'Setup Wizard', 'cloudflare-waf' ),
            'manage_options',
            'cloudflare-waf-wizard',
            array( $this, 'display_wizard_page' )
        );
    }

    /**
     * Display the dashboard page
     */
    public function display_dashboard_page() {
        $dashboard = new CloudflareWAF_Dashboard( $this->plugin_name, $this->version, $this->waf_service );
        $dashboard->display_page();
    }

    /**
     * Display the settings page
     */
    public function display_settings_page() {
        $settings = new CloudflareWAF_Settings( $this->plugin_name, $this->version, $this->waf_service );
        $settings->display_page();
    }

    /**
     * Display the reports page
     */
    public function display_reports_page() {
        $reports = new CloudflareWAF_Reports( $this->plugin_name, $this->version, $this->waf_service );
        $reports->display_page();
    }

    /**
     * Display the setup wizard page
     */
    public function display_wizard_page() {
        $wizard = new CloudflareWAF_Wizard( $this->plugin_name, $this->version, $this->waf_service );
        $wizard->display_wizard();
    }

    /**
     * Add settings link to the plugins page
     *
     * @param array $links Plugin action links
     * @return array Modified plugin action links
     */
    public function add_settings_link( $links ) {
        $settings_link = '<a href="' . admin_url( 'admin.php?page=cloudflare-waf-dashboard' ) . '">' . 
                        __( 'Dashboard', 'cloudflare-waf' ) . '</a>';
        array_unshift( $links, $settings_link );
        
        return $links;
    }

    /**
     * Handle redirection after activation
     */
    public function activation_redirect() {
        // Check if we should redirect
        if ( get_transient( 'cloudflare_waf_activation_redirect' ) ) {
            delete_transient( 'cloudflare_waf_activation_redirect' );
            
            // Only redirect if not network admin and not bulk activation
            if ( ! is_network_admin() && ! isset( $_GET['activate-multi'] ) ) {
                wp_redirect( admin_url( 'admin.php?page=cloudflare-waf-wizard' ) );
                exit;
            }
        }
    }
}