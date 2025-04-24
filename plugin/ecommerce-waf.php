<?php
/**
 * Plugin Name: E-Commerce WAF Protection
 * Plugin URI: https://yourwebsite.com/waf-protection
 * Description: Web Application Firewall (WAF) protection for WordPress and WooCommerce stores.
 * Version: 0.1.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * Text Domain: ecommerce-waf
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ECOM_WAF_VERSION', '0.1.0');
define('ECOM_WAF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ECOM_WAF_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ECOM_WAF_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('ECOM_WAF_API_URL', 'https://your-waf-api.com/api/v1');
define('ECOM_WAF_API_TIMEOUT', 15); // seconds

// Include authentication functions
require_once ECOM_WAF_PLUGIN_DIR . 'includes/auth-functions.php';


function ecom_waf_load_dependencies() {
    // Load Proxy API class
    require_once ECOM_WAF_PLUGIN_DIR . 'includes/class-waf-proxy-api.php';
    
    // Load Subscription Manager class
    require_once ECOM_WAF_PLUGIN_DIR . 'includes/class-waf-subscription-manager.php';
    
    // Initialize Subscription Manager
    new WAF_Subscription_Manager();
}
add_action('plugins_loaded', 'ecom_waf_load_dependencies', 15);

// Main plugin class
class EcommerceWAF {
    /**
     * Instance of this class
     */
    private static $instance = null;

    /**
     * Plugin settings
     */
    private $settings = [];

    /**
     * Return an instance of this class
     */
    public static function get_instance() {
        if (null == self::$instance) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Load settings
        $this->settings = get_option('ecom_waf_settings', [
            'api_key' => '',
            'domain' => '',
            'protection_status' => 'inactive',
            'protection_level' => 'medium',
            'last_check' => 0,
            'client_id' => '',
            'client_secret' => ''
        ]);

        // Initialize hooks
        $this->init_hooks();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        // Plugin activation and deactivation
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);

        // Admin menu
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);

        // AJAX handlers
        add_action('wp_ajax_ecom_waf_check_connection', [$this, 'ajax_check_connection']);
        add_action('wp_ajax_ecom_waf_update_protection', [$this, 'ajax_update_protection']);

        // Check for WooCommerce
        add_action('plugins_loaded', [$this, 'check_woocommerce']);

        // Add settings link to plugins page
        add_filter('plugin_action_links_' . ECOM_WAF_PLUGIN_BASENAME, [$this, 'add_settings_link']);

        // Schedule daily check
        if (!wp_next_scheduled('ecom_waf_daily_check')) {
            wp_schedule_event(time(), 'daily', 'ecom_waf_daily_check');
        }
        add_action('ecom_waf_daily_check', [$this, 'daily_check']);
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create necessary database tables or options
        // For POC, we'll just use options API
        
        // Set default settings if they don't exist
        if (!get_option('ecom_waf_settings')) {
            update_option('ecom_waf_settings', $this->settings);
        }
        
        // Log activation
        $this->log('Plugin activated');
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up schedules
        wp_clear_scheduled_hook('ecom_waf_daily_check');
        
        // Log deactivation
        $this->log('Plugin deactivated');
    }

    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        add_menu_page(
            __('E-Commerce WAF', 'ecommerce-waf'),
            __('WAF Protection', 'ecommerce-waf'),
            'manage_options',
            'ecom-waf-dashboard',
            [$this, 'render_dashboard_page'],
            'dashicons-shield',
            100
        );
        
        add_submenu_page(
            'ecom-waf-dashboard',
            __('WAF Dashboard', 'ecommerce-waf'),
            __('Dashboard', 'ecommerce-waf'),
            'manage_options',
            'ecom-waf-dashboard'
        );
        
        add_submenu_page(
            'ecom-waf-dashboard',
            __('WAF Settings', 'ecommerce-waf'),
            __('Settings', 'ecommerce-waf'),
            'manage_options',
            'ecom-waf-settings',
            [$this, 'render_settings_page']
        );
        
        add_submenu_page(
            'ecom-waf-dashboard',
            __('WAF Reports', 'ecommerce-waf'),
            __('Reports', 'ecommerce-waf'),
            'manage_options',
            'ecom-waf-reports',
            [$this, 'render_reports_page']
        );
    }

    /**
     * Register plugin settings
     */
    public function register_settings() {
        register_setting('ecom_waf_settings', 'ecom_waf_settings');
        
        add_settings_section(
            'ecom_waf_general_section',
            __('General Settings', 'ecommerce-waf'),
            [$this, 'render_general_section'],
            'ecom_waf_settings'
        );
        
        add_settings_field(
            'ecom_waf_api_key',
            __('API Key', 'ecommerce-waf'),
            [$this, 'render_api_key_field'],
            'ecom_waf_settings',
            'ecom_waf_general_section'
        );
        
        add_settings_field(
            'ecom_waf_domain',
            __('Domain', 'ecommerce-waf'),
            [$this, 'render_domain_field'],
            'ecom_waf_settings',
            'ecom_waf_general_section'
        );
        
        add_settings_field(
            'ecom_waf_protection_level',
            __('Protection Level', 'ecommerce-waf'),
            [$this, 'render_protection_level_field'],
            'ecom_waf_settings',
            'ecom_waf_general_section'
        );
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_admin_scripts($hook) {
        // Only enqueue on our plugin pages
        if (strpos($hook, 'ecom-waf') !== false) {
            wp_enqueue_style('ecom-waf-admin-css', ECOM_WAF_PLUGIN_URL . 'assets/css/admin.css', [], ECOM_WAF_VERSION);
            wp_enqueue_script('ecom-waf-admin-js', ECOM_WAF_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], ECOM_WAF_VERSION, true);
            
            wp_localize_script('ecom-waf-admin-js', 'ecomWAF', [
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('ecom_waf_nonce'),
                'strings' => [
                    'checking' => __('Checking connection...', 'ecommerce-waf'),
                    'connected' => __('Connected successfully!', 'ecommerce-waf'),
                    'connection_error' => __('Connection error', 'ecommerce-waf'),
                    'saving' => __('Saving settings...', 'ecommerce-waf'),
                    'saved' => __('Settings saved!', 'ecommerce-waf'),
                    'error' => __('An error occurred', 'ecommerce-waf')
                ]
            ]);
        }   
    }

    /**
     * Add settings link to plugins page
     */
    public function add_settings_link($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=ecom-waf-settings') . '">' . __('Settings', 'ecommerce-waf') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Check if WooCommerce is active
     */
    public function check_woocommerce() {
        if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            // WooCommerce specific features
            add_action('woocommerce_checkout_process', [$this, 'check_checkout_security']);
        }
    }

    /**
     * Render dashboard page
     */
    public function render_dashboard_page() {
        // Get statistics and status
        $protection_status = $this->settings['protection_status'];
        $stats = $this->get_protection_stats();
        
        include ECOM_WAF_PLUGIN_DIR . 'views/dashboard.php';
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        include ECOM_WAF_PLUGIN_DIR . 'views/settings.php';
    }

    /**
     * Render reports page
     */
    public function render_reports_page() {
        // Get report data
        $reports = $this->get_reports();
        
        include ECOM_WAF_PLUGIN_DIR . 'views/reports.php';
    }

    /**
     * Render general section description
     */
    public function render_general_section() {
        echo '<p>' . __('Configure your WAF protection settings.', 'ecommerce-waf') . '</p>';
    }

    /**
     * Render API key field
     */
    public function render_api_key_field() {
        $api_key = isset($this->settings['api_key']) ? $this->settings['api_key'] : '';
        
        echo '<input type="text" name="ecom_waf_settings[api_key]" value="' . esc_attr($api_key) . '" class="regular-text" />';
        echo '<p class="description">' . __('Enter your WAF API key. You can find this in your account dashboard.', 'ecommerce-waf') . '</p>';
    }

    /**
     * Render domain field
     */
    public function render_domain_field() {
        $domain = isset($this->settings['domain']) ? $this->settings['domain'] : '';
        if (empty($domain)) {
            $domain = parse_url(home_url(), PHP_URL_HOST);
        }
        
        echo '<input type="text" name="ecom_waf_settings[domain]" value="' . esc_attr($domain) . '" class="regular-text" />';
        echo '<p class="description">' . __('Your website domain name.', 'ecommerce-waf') . '</p>';
    }

    /**
     * Render protection level field
     */
    public function render_protection_level_field() {
        $protection_level = isset($this->settings['protection_level']) ? $this->settings['protection_level'] : 'medium';
        
        echo '<select name="ecom_waf_settings[protection_level]">';
        echo '<option value="low" ' . selected($protection_level, 'low', false) . '>' . __('Low - Block only the most critical threats', 'ecommerce-waf') . '</option>';
        echo '<option value="medium" ' . selected($protection_level, 'medium', false) . '>' . __('Medium - Balanced protection (recommended)', 'ecommerce-waf') . '</option>';
        echo '<option value="high" ' . selected($protection_level, 'high', false) . '>' . __('High - Maximum security (may affect performance)', 'ecommerce-waf') . '</option>';
        echo '</select>';
        echo '<p class="description">' . __('Select the level of protection for your website.', 'ecommerce-waf') . '</p>';
    }

    /**
     * AJAX handler for checking connection
     */
    public function ajax_check_connection() {
        // Verify nonce
        check_ajax_referer('ecom_waf_nonce', 'nonce');
        
        // Get settings from POST
        $api_key = isset($_POST['api_key']) ? sanitize_text_field($_POST['api_key']) : '';
        $domain = isset($_POST['domain']) ? sanitize_text_field($_POST['domain']) : '';
        
        // Check connection with API
        $result = $this->check_api_connection($api_key, $domain);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX handler for updating protection
     */
    public function ajax_update_protection() {
        // Verify nonce
        check_ajax_referer('ecom_waf_nonce', 'nonce');
        
        // Get settings from POST
        $protection_level = isset($_POST['protection_level']) ? sanitize_text_field($_POST['protection_level']) : 'medium';
        
        // Update protection level with API
        $result = $this->update_protection_level($protection_level);
        
        if ($result['success']) {
            // Update settings
            $this->settings['protection_level'] = $protection_level;
            update_option('ecom_waf_settings', $this->settings);
            
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * Daily check for protection status
     */
    public function daily_check() {
        // Skip if not configured
        if (empty($this->settings['api_key']) || empty($this->settings['domain'])) {
            return;
        }
        
        // Check status with API
        $result = $this->check_protection_status();
        
        // Update last check time
        $this->settings['last_check'] = time();
        
        // Update protection status based on result
        if (isset($result['status'])) {
            $this->settings['protection_status'] = $result['status'];
        }
        
        // Save settings
        update_option('ecom_waf_settings', $this->settings);
        
        // Log check
        $this->log('Daily check completed. Status: ' . $this->settings['protection_status']);
    }

    /**
     * Check WooCommerce checkout for security issues
     */
    public function check_checkout_security() {
        // This would integrate with your WAF API to check for suspicious checkout activity
        // For the POC, we'll just log the checkout
        $this->log('WooCommerce checkout initiated');
    }

    /**
     * Get protection statistics
     */
    private function get_protection_stats() {
        // This would fetch real stats from your WAF API
        // For POC, return dummy data
        return [
            'attacks_blocked' => rand(10, 100),
            'suspicious_ips' => rand(5, 20),
            'last_attack' => date('Y-m-d H:i:s', time() - rand(0, 86400)),
            'protection_level' => $this->settings['protection_level'],
            'status' => $this->settings['protection_status']
        ];
    }

    /**
     * Get protection reports
     */
    private function get_reports() {
        // This would fetch real reports from your WAF API
        // For POC, return dummy data
        $reports = [];
        for ($i = 0; $i < 10; $i++) {
            $reports[] = [
                'date' => date('Y-m-d H:i:s', time() - (86400 * $i)),
                'ip' => rand(1, 255) . '.' . rand(1, 255) . '.' . rand(1, 255) . '.' . rand(1, 255),
                'type' => ['SQL Injection', 'XSS Attack', 'Form Spam', 'Bot Traffic', 'Rate Limiting'][rand(0, 4)],
                'url' => ['/checkout/', '/my-account/', '/product/sample-product/', '/cart/'][rand(0, 3)],
                'status' => ['blocked', 'logged'][rand(0, 1)]
            ];
        }
        return $reports;
    }

    /**
     * Check API connection
     */
    private function check_api_connection($api_key, $domain) {
        // This would make a real API call to your WAF service
        // For POC, simulate API response
        
        // Simulate API call delay
        sleep(1);
        
        // For demo, succeed if API key is not empty
        if (!empty($api_key) && !empty($domain)) {
            return [
                'success' => true,
                'message' => 'Connection successful',
                'connection_id' => 'waf_' . md5($domain . time())
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Invalid API key or domain'
            ];
        }
    }

    /**
     * Update protection level
     */
    private function update_protection_level($level) {
        // This would make a real API call to your WAF service
        // For POC, simulate API response
        
        // Simulate API call delay
        sleep(1);
        
        // Validate level
        if (in_array($level, ['low', 'medium', 'high'])) {
            return [
                'success' => true,
                'message' => 'Protection level updated to ' . $level,
                'level' => $level
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Invalid protection level'
            ];
        }
    }

    /**
     * Check protection status
     */
    private function check_protection_status() {
        // This would make a real API call to your WAF service
        // For POC, simulate API response
        
        // For demo, randomly return active or issues
        $statuses = ['active', 'active', 'active', 'issues'];
        return [
            'status' => $statuses[array_rand($statuses)],
            'message' => 'Protection status checked'
        ];
    }

    /**
     * Log information
     */
    private function log($message) {
        // For POC, just write to WordPress debug log if enabled
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG === true) {
                error_log('E-Commerce WAF: ' . $message);
            }
        }
    }
}

// Initialize the plugin
function ecom_waf_init() {
    // Load plugin text domain for translations
    load_plugin_textdomain('ecommerce-waf', false, dirname(plugin_basename(__FILE__)) . '/languages');
    
    // Initialize main plugin class
    EcommerceWAF::get_instance();
}
add_action('plugins_loaded', 'ecom_waf_init');

// Create directory structure and files on plugin activation
register_activation_hook(__FILE__, 'ecom_waf_activation');
function ecom_waf_activation() {
    // Create necessary files
    ecom_waf_create_files();
    
    // Add capability
    $admin = get_role('administrator');
    if ($admin) {
        $admin->add_cap('manage_waf_protection');
    }
    
    // Maybe add a welcome message or redirect to the settings page
    set_transient('ecom_waf_activation_redirect', true, 30);
}

// Redirect after activation
add_action('admin_init', 'ecom_waf_activation_redirect');
function ecom_waf_activation_redirect() {
    if (get_transient('ecom_waf_activation_redirect')) {
        delete_transient('ecom_waf_activation_redirect');
        if (!isset($_GET['activate-multi'])) {
            wp_redirect(admin_url('admin.php?page=ecom-waf-dashboard'));
            exit;
        }
    }
}

// Create directory structure and files on plugin activation
function ecom_waf_create_files() {
    // Create directories
    if (!file_exists(ECOM_WAF_PLUGIN_DIR . 'views')) {
        mkdir(ECOM_WAF_PLUGIN_DIR . 'views', 0755, true);
    }
    if (!file_exists(ECOM_WAF_PLUGIN_DIR . 'assets/css')) {
        mkdir(ECOM_WAF_PLUGIN_DIR . 'assets/css', 0755, true);
    }
    if (!file_exists(ECOM_WAF_PLUGIN_DIR . 'assets/js')) {
        mkdir(ECOM_WAF_PLUGIN_DIR . 'assets/js', 0755, true);
    }
    if (!file_exists(ECOM_WAF_PLUGIN_DIR . 'languages')) {
        mkdir(ECOM_WAF_PLUGIN_DIR . 'languages', 0755, true);
    }
    
    // Create dashboard view file
    if (!file_exists(ECOM_WAF_PLUGIN_DIR . 'views/dashboard.php')) {
        $dashboard_content = <<<EOT
<div class="wrap ecom-waf-dashboard">
    <h1><?php _e('WAF Protection Dashboard', 'ecommerce-waf'); ?></h1>
    
    <div class="ecom-waf-status-box">
        <h2><?php _e('Protection Status', 'ecommerce-waf'); ?></h2>
        <div class="ecom-waf-status <?php echo esc_attr(\$protection_status); ?>">
            <?php if (\$protection_status === 'active'): ?>
                <span class="dashicons dashicons-shield"></span>
                <p><?php _e('Your site is protected by WAF', 'ecommerce-waf'); ?></p>
            <?php elseif (\$protection_status === 'inactive'): ?>
                <span class="dashicons dashicons-shield-alt"></span>
                <p><?php _e('WAF protection is not active', 'ecommerce-waf'); ?></p>
                <a href="<?php echo admin_url('admin.php?page=ecom-waf-settings'); ?>" class="button button-primary"><?php _e('Activate Protection', 'ecommerce-waf'); ?></a>
            <?php elseif (\$protection_status === 'issues'): ?>
                <span class="dashicons dashicons-warning"></span>
                <p><?php _e('There are issues with your WAF protection', 'ecommerce-waf'); ?></p>
                <a href="<?php echo admin_url('admin.php?page=ecom-waf-settings'); ?>" class="button button-primary"><?php _e('Resolve Issues', 'ecommerce-waf'); ?></a>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="ecom-waf-stats-grid">
        <div class="ecom-waf-stat-box">
            <h3><?php _e('Attacks Blocked', 'ecommerce-waf'); ?></h3>
            <div class="ecom-waf-stat-value"><?php echo esc_html(\$stats['attacks_blocked']); ?></div>
        </div>
        
        <div class="ecom-waf-stat-box">
            <h3><?php _e('Suspicious IPs', 'ecommerce-waf'); ?></h3>
            <div class="ecom-waf-stat-value"><?php echo esc_html(\$stats['suspicious_ips']); ?></div>
        </div>
        
        <div class="ecom-waf-stat-box">
            <h3><?php _e('Last Attack', 'ecommerce-waf'); ?></h3>
            <div class="ecom-waf-stat-value"><?php echo esc_html(\$stats['last_attack']); ?></div>
        </div>
        
        <div class="ecom-waf-stat-box">
            <h3><?php _e('Protection Level', 'ecommerce-waf'); ?></h3>
            <div class="ecom-waf-stat-value"><?php echo esc_html(ucfirst(\$stats['protection_level'])); ?></div>
        </div>
    </div>
    
    <div class="ecom-waf-quick-links">
        <a href="<?php echo admin_url('admin.php?page=ecom-waf-reports'); ?>" class="button"><?php _e('View Detailed Reports', 'ecommerce-waf'); ?></a>
        <a href="<?php echo admin_url('admin.php?page=ecom-waf-settings'); ?>" class="button"><?php _e('Adjust Settings', 'ecommerce-waf'); ?></a>
    </div>
</div>
EOT;
        file_put_contents(ECOM_WAF_PLUGIN_DIR . 'views/dashboard.php', $dashboard_content);
    }
    
    // Create settings view file
    if (!file_exists(ECOM_WAF_PLUGIN_DIR . 'views/settings.php')) {
        $settings_content = <<<EOT
<div class="wrap ecom-waf-settings">
    <h1><?php _e('WAF Protection Settings', 'ecommerce-waf'); ?></h1>
    
    <form method="post" action="options.php" id="ecom-waf-settings-form">
        <?php settings_fields('ecom_waf_settings'); ?>
        <?php do_settings_sections('ecom_waf_settings'); ?>
        
        <div class="ecom-waf-connection-test">
            <h3><?php _e('Connection Test', 'ecommerce-waf'); ?></h3>
            <button type="button" class="button" id="ecom-waf-test-connection"><?php _e('Test Connection', 'ecommerce-waf'); ?></button>
            <span class="ecom-waf-connection-status"></span>
        </div>
        
        <div class="ecom-waf-dns-settings" style="display:none;">
            <h3><?php _e('DNS Settings', 'ecommerce-waf'); ?></h3>
            <p><?php _e('To activate WAF protection, update your DNS settings with the following information:', 'ecommerce-waf'); ?></p>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><?php _e('Current A Record', 'ecommerce-waf'); ?></th>
                    <td><code id="ecom-waf-current-ip">0.0.0.0</code></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('WAF DNS Server 1', 'ecommerce-waf'); ?></th>
                    <td><code>ns1.ecom-waf.example.com</code></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('WAF DNS Server 2', 'ecommerce-waf'); ?></th>
                    <td><code>ns2.ecom-waf.example.com</code></td>
                </tr>
            </table>
            <p class="description"><?php _e('After updating your DNS settings, it may take up to 24 hours for changes to propagate.', 'ecommerce-waf'); ?></p>
        </div>
        
        <?php submit_button(); ?>
    </form>
</div>
EOT;
        file_put_contents(ECOM_WAF_PLUGIN_DIR . 'views/settings.php', $settings_content);
    }
    
    // Create reports view file
    if (!file_exists(ECOM_WAF_PLUGIN_DIR . 'views/reports.php')) {
        $reports_content = <<<EOT
<div class="wrap ecom-waf-reports">
    <h1><?php _e('WAF Protection Reports', 'ecommerce-waf'); ?></h1>
    
    <div class="ecom-waf-report-filters">
        <form method="get" action="">
            <input type="hidden" name="page" value="ecom-waf-reports" />
            <select name="report_type">
                <option value="all"><?php _e('All Events', 'ecommerce-waf'); ?></option>
                <option value="blocked"><?php _e('Blocked Attacks', 'ecommerce-waf'); ?></option>
                <option value="logged"><?php _e('Logged Warnings', 'ecommerce-waf'); ?></option>
            </select>
            <input type="date" name="date_from" placeholder="<?php _e('From Date', 'ecommerce-waf'); ?>" />
            <input type="date" name="date_to" placeholder="<?php _e('To Date', 'ecommerce-waf'); ?>" />
            <button type="submit" class="button"><?php _e('Filter', 'ecommerce-waf'); ?></button>
        </form>
    </div>
    
    <table class="wp-list-table widefat fixed striped ecom-waf-reports-table">
        <thead>
            <tr>
                <th scope="col"><?php _e('Date', 'ecommerce-waf'); ?></th>
                <th scope="col"><?php _e('IP Address', 'ecommerce-waf'); ?></th>
                <th scope="col"><?php _e('Attack Type', 'ecommerce-waf'); ?></th>
                <th scope="col"><?php _e('URL', 'ecommerce-waf'); ?></th>
                <th scope="col"><?php _e('Status', 'ecommerce-waf'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty(\$reports)): ?>
                <tr>
                    <td colspan="5"><?php _e('No reports found.', 'ecommerce-waf'); ?></td>
                </tr>
            <?php else: ?>
                <?php foreach (\$reports as \$report): ?>
                    <tr>
                        <td><?php echo esc_html(\$report['date']); ?></td>
                        <td><?php echo esc_html(\$report['ip']); ?></td>
                        <td><?php echo esc_html(\$report['type']); ?></td>
                        <td><?php echo esc_html(\$report['url']); ?></td>
                        <td>
                            <span class="ecom-waf-status-<?php echo esc_attr(\$report['status']); ?>">
                                <?php echo esc_html(ucfirst(\$report['status'])); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <div class="ecom-waf-export-actions">
        <a href="#" class="button"><?php _e('Export CSV', 'ecommerce-waf'); ?></a>
        <a href="#" class="button"><?php _e('Print Report', 'ecommerce-waf'); ?></a>
    </div>
</div>
EOT;
        file_put_contents(ECOM_WAF_PLUGIN_DIR . 'views/reports.php', $reports_content);
    }
    
    // Create CSS file
    if (!file_exists(ECOM_WAF_PLUGIN_DIR . 'assets/css/admin.css')) {
        $css_content = <<<EOT
/* E-Commerce WAF Plugin Admin Styles */

.ecom-waf-dashboard {
    max-width: 1200px;
}

.ecom-waf-status-box {
    background: #fff;
    border: 1px solid #ccc;
    padding: 20px;
    margin-bottom: 20px;
    border-radius: 5px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.ecom-waf-status {
    display: flex;
    align-items: center;
    padding: 15px;
    border-radius: 4px;
}

.ecom-waf-status.active {
    background-color: #f0f7ed;
    border-left: 4px solid #46b450;
}

.ecom-waf-status.inactive {
    background-color: #fef8ee;
    border-left: 4px solid #ffb900;
}

.ecom-waf-status.issues {
    background-color: #fef1f1;
    border-left: 4px solid #dc3232;
}

.ecom-waf-status .dashicons {
    font-size: 30px;
    width: 30px;
    height: 30px;
    margin-right: 15px;
}

.ecom-waf-status.active .dashicons {
    color: #46b450;
}

.ecom-waf-status.inactive .dashicons {
    color: #ffb900;
}

.ecom-waf-status.issues .dashicons {
    color: #dc3232;
}

.ecom-waf-status p {
    margin: 0;
    font-size: 16px;
}

.ecom-waf-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.ecom-waf-stat-box {
    background: #fff;
    border: 1px solid #ccc;
    padding: 15px;
    border-radius: 5px;
    text-align: center;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.ecom-waf-stat-box h3 {
    margin-top: 0;
    color: #23282d;
    font-size: 14px;
}

.ecom-waf-stat-value {
    font-size: 24px;
    font-weight: 600;
    color: #0073aa;
}

.ecom-waf-quick-links {
    margin-top: 20px;
}

.ecom-waf-connection-test {
    margin: 20px 0;
    padding: 15px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.ecom-waf-connection-status {
    display: inline-block;
    margin-left: 10px;
    font-weight: bold;
}

.ecom-waf-connection-status.success {
    color: #46b450;
}

.ecom-waf-connection-status.error {
    color: #dc3232;
}

.ecom-waf-dns-settings {
    margin: 20px 0;
    padding: 15px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.ecom-waf-reports-table {
    margin-top: 20px;
}

.ecom-waf-status-blocked {
    color: #dc3232;
    font-weight: bold;
}

.ecom-waf-status-logged {
    color: #ffb900;
}

.ecom-waf-report-filters {
    margin-bottom: 20px;
    padding: 15px;
    background: #f9f9f9;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.ecom-waf-export-actions {
    margin-top: 20px;
}

@media screen and (max-width: 782px) {
    .ecom-waf-stats-grid {
        grid-template-columns: 1fr;
    }
}
EOT;
        file_put_contents(ECOM_WAF_PLUGIN_DIR . 'assets/css/admin.css', $css_content);
    }
}