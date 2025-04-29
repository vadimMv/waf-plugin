<div class="wrap">
    <h1><?php _e('WAF Protection Subscription', 'ecommerce-waf'); ?></h1>
    
    <?php if (!empty($message)): ?>
        <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
            <p><?php echo esc_html($message); ?></p>
        </div>
    <?php endif; ?>
    
    <?php if ($has_subscription): ?>
        <!-- Active subscription information -->
        <div class="ecom-waf-subscription-card">
            <h2><?php _e('Current Subscription', 'ecommerce-waf'); ?></h2>
            
            <table class="form-table">
                <tr>
                    <th><?php _e('Plan', 'ecommerce-waf'); ?></th>
                    <td>
                        <strong>
                            <?php 
                            $plan = $this->settings['subscription_plan'] ?? 'unknown';
                            echo $plan === 'monthly' 
                                ? __('Monthly Protection', 'ecommerce-waf') 
                                : __('Annual Protection', 'ecommerce-waf'); 
                            ?>
                        </strong>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Status', 'ecommerce-waf'); ?></th>
                    <td>
                        <?php 
                        $status_class = 'ecom-waf-status-' . $subscription_status;
                        echo '<span class="' . esc_attr($status_class) . '">' . esc_html(ucfirst($subscription_status)) . '</span>';
                        ?>
                    </td>
                </tr>
                <?php if (!empty($subscription_data) && isset($subscription_data['current_period'])): ?>
                    <tr>
                        <th><?php _e('Current Period', 'ecommerce-waf'); ?></th>
                        <td>
                            <?php 
                            echo esc_html(date_i18n(get_option('date_format'), $subscription_data['current_period']['start']));
                            echo ' - ';
                            echo esc_html(date_i18n(get_option('date_format'), $subscription_data['current_period']['end']));
                            ?>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php if (!empty($subscription_data) && isset($subscription_data['amount'])): ?>
                    <tr>
                        <th><?php _e('Amount', 'ecommerce-waf'); ?></th>
                        <td>
                            <?php 
                            echo '$' . number_format($subscription_data['amount'], 2) . ' ' . strtoupper($subscription_data['currency'] ?? 'USD');
                            ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </table>
            
            <div class="ecom-waf-subscription-actions">
                <?php if ($subscription_status === 'active'): ?>
                    <a href="#" class="button ecom-waf-manage-subscription">
                        <?php _e('Manage Subscription', 'ecommerce-waf'); ?>
                    </a>
                <?php elseif ($subscription_status === 'canceled'): ?>
                    <a href="#" class="button button-primary ecom-waf-reactivate-subscription">
                        <?php _e('Reactivate Subscription', 'ecommerce-waf'); ?>
                    </a>
                <?php endif; ?>
                
                <a href="<?php echo admin_url('admin.php?page=ecom-waf-dashboard'); ?>" class="button">
                    <?php _e('View Protection Status', 'ecommerce-waf'); ?>
                </a>
            </div>
            
            <div class="ecom-waf-faq">
                <h3><?php _e('Frequently Asked Questions', 'ecommerce-waf'); ?></h3>
                
                <div class="ecom-waf-accordion">
                    <div class="ecom-waf-accordion-item">
                        <div class="ecom-waf-accordion-header"><?php _e('How does the WAF protection work?', 'ecommerce-waf'); ?></div>
                        <div class="ecom-waf-accordion-content">
                            <p><?php _e('Our WAF protection works by routing your website traffic through Cloudflare\'s global network. All incoming requests are inspected before reaching your server, blocking malicious traffic at the edge.', 'ecommerce-waf'); ?></p>
                        </div>
                    </div>
                    
                    <div class="ecom-waf-accordion-item">
                        <div class="ecom-waf-accordion-header"><?php _e('Will this slow down my website?', 'ecommerce-waf'); ?></div>
                        <div class="ecom-waf-accordion-content">
                            <p><?php _e('No, in most cases WAF protection will actually improve your website performance. Traffic is routed through Cloudflare\'s global CDN, reducing load times for visitors worldwide.', 'ecommerce-waf'); ?></p>
                        </div>
                    </div>
                    
                    <div class="ecom-waf-accordion-item">
                        <div class="ecom-waf-accordion-header"><?php _e('Can I cancel my subscription?', 'ecommerce-waf'); ?></div>
                        <div class="ecom-waf-accordion-content">
                            <p><?php _e('Yes, you can cancel your subscription at any time. Your protection will remain active until the end of your current billing period.', 'ecommerce-waf'); ?></p>
                        </div>
                    </div>
                    
                    <div class="ecom-waf-accordion-item">
                        <div class="ecom-waf-accordion-header"><?php _e('Do I need technical knowledge to use this?', 'ecommerce-waf'); ?></div>
                        <div class="ecom-waf-accordion-content">
                            <p><?php _e('No technical knowledge required. The plugin handles all the configuration automatically, and our setup wizard guides you through any necessary steps.', 'ecommerce-waf'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- WAF Protection Status -->
        <div class="ecom-waf-status-card">
            <h2><?php _e('Protection Status', 'ecommerce-waf'); ?></h2>
            
            <?php if ($protection_status === 'configuring'): ?>
                <div class="ecom-waf-setup-progress">
                    <span class="dashicons dashicons-update-alt"></span>
                    <p><?php _e('Setting up WAF protection...', 'ecommerce-waf'); ?></p>
                    <div class="ecom-waf-progress-bar">
                        <div class="ecom-waf-progress-inner"></div>
                    </div>
                    <p class="description"><?php _e('This may take a few minutes. Your protection will be active shortly.', 'ecommerce-waf'); ?></p>
                </div>
            <?php elseif ($protection_status === 'active'): ?>
                <div class="ecom-waf-status-active">
                    <span class="dashicons dashicons-shield"></span>
                    <div class="ecom-waf-status-content">
                        <h3><?php _e('WAF Protection Active', 'ecommerce-waf'); ?></h3>
                        <p><?php _e('Your website is being protected by our Web Application Firewall.', 'ecommerce-waf'); ?></p>
                    </div>
                </div>
                <?php if (!empty($subscription_data) && isset($subscription_data['stats'])): ?>
                    <div class="ecom-waf-stats">
                        <div class="ecom-waf-stat">
                            <span class="ecom-waf-stat-value"><?php echo esc_html($subscription_data['stats']['attacks_blocked']); ?></span>
                            <span class="ecom-waf-stat-label"><?php _e('Attacks Blocked', 'ecommerce-waf'); ?></span>
                        </div>
                        <div class="ecom-waf-stat">
                            <span class="ecom-waf-stat-value"><?php echo esc_html($subscription_data['stats']['threats_detected']); ?></span>
                            <span class="ecom-waf-stat-label"><?php _e('Threats Detected', 'ecommerce-waf'); ?></span>
                        </div>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="ecom-waf-status-inactive">
                    <span class="dashicons dashicons-shield-alt"></span>
                    <div class="ecom-waf-status-content">
                        <h3><?php _e('WAF Protection Inactive', 'ecommerce-waf'); ?></h3>
                        <p><?php _e('Your protection is currently inactive. Please check your subscription status.', 'ecommerce-waf'); ?></p>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($protection_status === 'active'): ?>
                <a href="<?php echo admin_url('admin.php?page=ecom-waf-reports'); ?>" class="button button-primary">
                    <?php _e('View Detailed Reports', 'ecommerce-waf'); ?>
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- No subscription yet -->
        <div class="ecom-waf-subscription-card">
            <h2><?php _e('Subscribe to WAF Protection', 'ecommerce-waf'); ?></h2>
            <p><?php _e('Choose a subscription plan to activate WAF protection for your website.', 'ecommerce-waf'); ?></p>
            
            <div class="ecom-waf-plan-selector">
                <div class="ecom-waf-plan-card">
                    <h3><?php _e('Monthly Protection', 'ecommerce-waf'); ?></h3>
                    <div class="ecom-waf-plan-price">
                        <span class="ecom-waf-plan-amount">$19.99</span>
                        <span class="ecom-waf-plan-period"><?php _e('per month', 'ecommerce-waf'); ?></span>
                    </div>
                    <ul class="ecom-waf-plan-features">
                        <li><?php _e('Complete WAF Protection', 'ecommerce-waf'); ?></li>
                        <li><?php _e('Real-time Threat Monitoring', 'ecommerce-waf'); ?></li>
                        <li><?php _e('WordPress-specific Rules', 'ecommerce-waf'); ?></li>
                        <li><?php _e('Monthly Billing', 'ecommerce-waf'); ?></li>
                    </ul>
                    <button type="button" class="button button-primary ecom-waf-checkout-button" data-plan="monthly">
                        <?php _e('Subscribe Monthly', 'ecommerce-waf'); ?>
                    </button>
                </div>
                
                <div class="ecom-waf-plan-card ecom-waf-plan-featured">
                    <div class="ecom-waf-plan-badge"><?php _e('Best Value', 'ecommerce-waf'); ?></div>
                    <h3><?php _e('Annual Protection', 'ecommerce-waf'); ?></h3>
                    <div class="ecom-waf-plan-price">
                        <span class="ecom-waf-plan-amount">$199.99</span>
                        <span class="ecom-waf-plan-period"><?php _e('per year', 'ecommerce-waf'); ?></span>
                    </div>
                    <div class="ecom-waf-plan-savings">
                        <?php _e('Save 17% compared to monthly', 'ecommerce-waf'); ?>
                    </div>
                    <ul class="ecom-waf-plan-features">
                        <li><?php _e('Complete WAF Protection', 'ecommerce-waf'); ?></li>
                        <li><?php _e('Real-time Threat Monitoring', 'ecommerce-waf'); ?></li>
                        <li><?php _e('WordPress-specific Rules', 'ecommerce-waf'); ?></li>
                        <li><?php _e('Annual Billing', 'ecommerce-waf'); ?></li>
                    </ul>
                    <button type="button" class="button button-primary ecom-waf-checkout-button" data-plan="annual">
                        <?php _e('Subscribe Annually', 'ecommerce-waf'); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- WAF Protection Benefits -->
        <div class="ecom-waf-benefits-card">
            <h2><?php _e('Why Use WAF Protection?', 'ecommerce-waf'); ?></h2>
            
            <div class="ecom-waf-benefits-grid">
                <div class="ecom-waf-benefit">
                    <span class="dashicons dashicons-shield"></span>
                    <h3><?php _e('Block Malicious Traffic', 'ecommerce-waf'); ?></h3>
                    <p><?php _e('Stop attacks before they reach your WordPress site with advanced firewall rules.', 'ecommerce-waf'); ?></p>
                </div>
                
                <div class="ecom-waf-benefit">
                    <span class="dashicons dashicons-performance"></span>
                    <h3><?php _e('Improve Performance', 'ecommerce-waf'); ?></h3>
                    <p><?php _e('Reduce server load by blocking malicious bots and traffic at the edge.', 'ecommerce-waf'); ?></p>
                </div>
                
                <div class="ecom-waf-benefit">
                    <span class="dashicons dashicons-admin-users"></span>
                    <h3><?php _e('Protect Your Users', 'ecommerce-waf'); ?></h3>
                    <p><?php _e('Keep your customers\' data safe from theft or exposure by preventing breaches.', 'ecommerce-waf'); ?></p>
                </div>
                
                <div class="ecom-waf-benefit">
                    <span class="dashicons dashicons-update"></span>
                    <h3><?php _e('Always Updated', 'ecommerce-waf'); ?></h3>
                    <p><?php _e('Protection rules are automatically updated to defend against new threats.', 'ecommerce-waf'); ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>