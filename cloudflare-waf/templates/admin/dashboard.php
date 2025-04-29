<?php
/**
 * Admin dashboard template
 *
 * @package CloudflareWAF
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Get status information
$status_info = $this->get_status_info( $status );
?>

<div class="wrap cloudflare-waf-dashboard">
    <h1><?php _e( 'Cloudflare WAF Protection', 'cloudflare-waf' ); ?></h1>
    
    <div class="cloudflare-waf-status-box">
        <h2><?php _e( 'Protection Status', 'cloudflare-waf' ); ?></h2>
        <div class="cloudflare-waf-status <?php echo esc_attr( $status_info['class'] ); ?>">
            <span class="dashicons <?php echo esc_attr( $status_info['icon'] ); ?>"></span>
            <div class="cloudflare-waf-status-content">
                <h3><?php echo esc_html( $status_info['label'] ); ?></h3>
                <p><?php echo isset( $status['message'] ) ? esc_html( $status['message'] ) : ''; ?></p>
                
                <?php if ( isset( $status['status'] ) && $status['status'] === 'pending' ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=cloudflare-waf-settings' ) ); ?>" class="button button-primary">
                        <?php _e( 'Complete Setup', 'cloudflare-waf' ); ?>
                    </a>
                <?php endif; ?>
                
                <?php if ( isset( $status['status'] ) && $status['status'] === 'issues' ) : ?>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=cloudflare-waf-settings' ) ); ?>" class="button button-primary">
                        <?php _e( 'Resolve Issues', 'cloudflare-waf' ); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="cloudflare-waf-stats-grid">
        <div class="cloudflare-waf-stat-box">
            <h3><?php _e( 'Attacks Blocked', 'cloudflare-waf' ); ?></h3>
            <div class="cloudflare-waf-stat-value">
                <?php echo esc_html( $this->format_number( isset( $stats['attacks_blocked'] ) ? $stats['attacks_blocked'] : 0 ) ); ?>
            </div>
            <div class="cloudflare-waf-stat-period">
                <?php _e( 'Last 30 days', 'cloudflare-waf' ); ?>
            </div>
        </div>
        
        <div class="cloudflare-waf-stat-box">
            <h3><?php _e( 'Suspicious IPs', 'cloudflare-waf' ); ?></h3>
            <div class="cloudflare-waf-stat-value">
                <?php echo esc_html( $this->format_number( isset( $stats['suspicious_ips'] ) ? $stats['suspicious_ips'] : 0 ) ); ?>
            </div>
            <div class="cloudflare-waf-stat-period">
                <?php _e( 'Currently blocked', 'cloudflare-waf' ); ?>
            </div>
        </div>
        
        <div class="cloudflare-waf-stat-box">
            <h3><?php _e( 'Protection Level', 'cloudflare-waf' ); ?></h3>
            <div class="cloudflare-waf-stat-value protection-level">
                <?php 
                $protection_level = isset( $stats['protection_level'] ) ? $stats['protection_level'] : 'medium';
                echo esc_html( ucfirst( $protection_level ) ); 
                ?>
            </div>
            <div class="cloudflare-waf-stat-period">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=cloudflare-waf-settings' ) ); ?>">
                    <?php _e( 'Adjust settings', 'cloudflare-waf' ); ?>
                </a>
            </div>
        </div>
        
        <div class="cloudflare-waf-stat-box">
            <h3><?php _e( 'Last Attack', 'cloudflare-waf' ); ?></h3>
            <div class="cloudflare-waf-stat-value last-attack">
                <?php 
                if ( isset( $stats['last_attack'] ) && $stats['last_attack'] > 0 ) {
                    echo esc_html( $this->format_datetime( $stats['last_attack'] ) );
                } else {
                    _e( 'None detected', 'cloudflare-waf' );
                }
                ?>
            </div>
            <div class="cloudflare-waf-stat-period">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=cloudflare-waf-reports' ) ); ?>">
                    <?php _e( 'View reports', 'cloudflare-waf' ); ?>
                </a>
            </div>
        </div>
    </div>
    
    <?php if ( isset( $subscription ) && is_array( $subscription ) ) : ?>
    <div class="cloudflare-waf-subscription-box">
        <h2><?php _e( 'Subscription', 'cloudflare-waf' ); ?></h2>
        <div class="cloudflare-waf-subscription-info">
            <div class="cloudflare-waf-subscription-plan">
                <strong><?php _e( 'Current Plan:', 'cloudflare-waf' ); ?></strong>
                <?php echo isset( $subscription['plan_name'] ) ? esc_html( $subscription['plan_name'] ) : __( 'Unknown', 'cloudflare-waf' ); ?>
            </div>
            
            <div class="cloudflare-waf-subscription-status">
                <strong><?php _e( 'Status:', 'cloudflare-waf' ); ?></strong>
                <?php 
                $subscription_status = isset( $subscription['status'] ) ? $subscription['status'] : 'unknown';
                echo esc_html( ucfirst( $subscription_status ) ); 
                ?>
            </div>
            
            <?php if ( isset( $subscription['next_payment'] ) && $subscription['next_payment'] > 0 ) : ?>
            <div class="cloudflare-waf-subscription-renewal">
                <strong><?php _e( 'Next Payment:', 'cloudflare-waf' ); ?></strong>
                <?php echo esc_html( $this->format_datetime( $subscription['next_payment'] ) ); ?>
            </div>
            <?php endif; ?>
            
            <div class="cloudflare-waf-subscription-actions">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=cloudflare-waf-settings&tab=subscription' ) ); ?>" class="button">
                    <?php _e( 'Manage Subscription', 'cloudflare-waf' ); ?>
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="cloudflare-waf-quick-actions">
        <h2><?php _e( 'Quick Actions', 'cloudflare-waf' ); ?></h2>
        <div class="cloudflare-waf-button-grid">
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=cloudflare-waf-settings' ) ); ?>" class="cloudflare-waf-button">
                <span class="dashicons dashicons-admin-settings"></span>
                <span class="cloudflare-waf-button-label"><?php _e( 'Configure Settings', 'cloudflare-waf' ); ?></span>
            </a>
            
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=cloudflare-waf-reports' ) ); ?>" class="cloudflare-waf-button">
                <span class="dashicons dashicons-chart-bar"></span>
                <span class="cloudflare-waf-button-label"><?php _e( 'View Reports', 'cloudflare-waf' ); ?></span>
            </a>
            
            <button type="button" id="cloudflare-waf-refresh-stats" class="cloudflare-waf-button">
                <span class="dashicons dashicons-image-rotate"></span>
                <span class="cloudflare-waf-button-label"><?php _e( 'Refresh Statistics', 'cloudflare-waf' ); ?></span>
            </button>
            
            <a href="https://dash.cloudflare.com" target="_blank" class="cloudflare-waf-button">
                <span class="dashicons dashicons-external"></span>
                <span class="cloudflare-waf-button-label"><?php _e( 'Cloudflare Dashboard', 'cloudflare-waf' ); ?></span>
            </a>
        </div>
    </div>
</div>