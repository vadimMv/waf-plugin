<?php
/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks. It also maintains the unique identifier of this plugin
 * as well as the current version.
 *
 * @package CloudflareWAF
 * @subpackage Core
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Add this before the run_cloudflare_waf() function

class CloudflareWAF_Plugin {

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @var CloudflareWAF_Loader $loader Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @var string $plugin_name The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @var string $version The current version of the plugin.
     */
    protected $version;

    /**
     * Logger instance
     *
     * @var CloudflareWAF_Logger
     */
    private $logger;

    /**
     * WAF service instance
     *
     * @var CloudflareWAF_Service
     */
    private $waf_service;

    /**
     * Define the core functionality of the plugin.
     */
    public function __construct() {
        $this->version = CLOUDFLARE_WAF_VERSION;
        $this->plugin_name = 'cloudflare-waf';
        
        // Initialize logger
        $this->logger = new CloudflareWAF_Logger();
        $this->logger->info( 'Plugin initializing, version ' . $this->version );
        // Initialize WAF service
        $this->waf_service = new CloudflareWAF_Service();
        
        $this->load_dependencies();
        $this->define_admin_hooks();
        $this->define_api_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Create an instance of the loader which will be used to register the hooks
     * with WordPress.
     */
    private function load_dependencies() {
        $this->loader = new CloudflareWAF_Loader();
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     */
    private function define_admin_hooks() {
        // Main admin class for shared functionality
        $admin = new CloudflareWAF_Admin( $this->get_plugin_name(), $this->get_version(), $this->waf_service );
        
        // Register admin menu
        $this->loader->add_action( 'admin_menu', $admin, 'register_admin_menu' );
        
        // Register admin assets
        $this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_styles' );
        $this->loader->add_action( 'admin_enqueue_scripts', $admin, 'enqueue_scripts' );
        
        // Add settings link to plugins page
        $this->loader->add_filter( 'plugin_action_links_' . CLOUDFLARE_WAF_PLUGIN_BASENAME, $admin, 'add_settings_link' );
        
        // Handle activation redirect
        $this->loader->add_action( 'admin_init', $admin, 'activation_redirect' );
        
        // Dashboard-specific hooks
        $dashboard = new CloudflareWAF_Dashboard( $this->get_plugin_name(), $this->get_version(), $this->waf_service );
        $this->loader->add_action( 'wp_ajax_cloudflare_waf_refresh_stats', $dashboard, 'ajax_refresh_stats' );
        
        // // Settings-specific hooks
        // $settings = new CloudflareWAF_Settings( $this->get_plugin_name(), $this->get_version(), $this->waf_service );
        // $this->loader->add_action( 'wp_ajax_cloudflare_waf_save_settings', $settings, 'ajax_save_settings' );
        
        // Setup wizard hooks
        $wizard = new CloudflareWAF_Wizard( $this->get_plugin_name(), $this->get_version(), $this->waf_service );
        $this->loader->add_action( 'wp_ajax_cloudflare_waf_wizard_step', $wizard, 'ajax_process_step' );
    }

    /**
     * Register all of the hooks related to the API functionality
     * of the plugin.
     */
    private function define_api_hooks() {
        // Register REST API endpoints
        $api = new CloudflareWAF_API_Client( $this->get_plugin_name(), $this->get_version(), $this->waf_service );
        $this->loader->add_action( 'rest_api_init', $api, 'register_rest_routes' );
        
        // Schedule daily WAF status check
        $this->loader->add_action( 'cloudflare_waf_daily_check', $this->waf_service, 'check_protection_status' );
        
        // Handle webhook requests
        // $this->loader->add_action( 'init', $api, 'handle_webhook_request' );
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @return string The name of the plugin.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @return CloudflareWAF_Loader Orchestrates the hooks of the plugin.
     */
    public function get_loader() {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @return string The version number of the plugin.
     */
    public function get_version() {
        return $this->version;
    }

    /**
     * Get WAF service instance
     *
     * @return CloudflareWAF_Service The WAF service
     */
    public function get_service() {
        return $this->waf_service;
    }
}