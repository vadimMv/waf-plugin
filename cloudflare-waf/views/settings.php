<div class="wrap ecom-waf-settings">
    <h1><?php _e('WAF Protection Settings', 'ecommerce-waf'); ?></h1>
    
    <form method="post" action="options.php" id="ecom-waf-settings-form">
        <?php settings_fields('ecom_waf_settings'); ?>
        <?php do_settings_sections('ecom_waf_settings'); ?>
        
        <h2><?php _e('API Authentication', 'ecommerce-waf'); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php _e('Client ID', 'ecommerce-waf'); ?></th>
                <td>
                    <input type="text" id="ecom_waf_client_id" name="ecom_waf_settings[client_id]" value="<?php echo esc_attr(isset($this->settings['client_id']) ? $this->settings['client_id'] : ''); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Client Secret', 'ecommerce-waf'); ?></th>
                <td>
                    <input type="password" id="ecom_waf_client_secret" name="ecom_waf_settings[client_secret]" value="<?php echo esc_attr(isset($this->settings['client_secret']) ? $this->settings['client_secret'] : ''); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Domain', 'ecommerce-waf'); ?></th>
                <td>
                    <input type="text" id="ecom_waf_domain" name="ecom_waf_settings[domain]" value="<?php echo esc_attr(isset($this->settings['domain']) ? $this->settings['domain'] : parse_url(site_url(), PHP_URL_HOST)); ?>" class="regular-text">
                </td>
            </tr>
            <tr>
                <th scope="row"><?php _e('Authentication Token', 'ecommerce-waf'); ?></th>
                <td>
                    <button type="button" id="ecom-waf-fetch-token" class="button button-secondary"><?php _e('Fetch Token', 'ecommerce-waf'); ?></button>
                    <span class="ecom-waf-token-status"></span>
                    <?php if (get_option('ecom_waf_token_expires')): ?>
                        <p class="description">
                            <?php _e('Current token expires:', 'ecommerce-waf'); ?> 
                            <span id="ecom-waf-token-expires"><?php echo esc_html(get_option('ecom_waf_token_expires')); ?></span>
                        </p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        
        <div class="ecom-waf-connection-test">
            <h3><?php _e('Connection Test', 'ecommerce-waf'); ?></h3>
            <button type="button" class="button" id="ecom-waf-test-connection"><?php _e('Test Connection', 'ecommerce-waf'); ?></button>
            <span class="ecom-waf-connection-status"></span>
        </div>
        
        <!-- Rest of your form -->
        
        <?php submit_button(); ?>
    </form>
</div>