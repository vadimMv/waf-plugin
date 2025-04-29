<?php
/**
 * Admin not registered template
 *
 * @package CloudflareWAF
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="wrap cloudflare-waf-dashboard">
    <h1><?php _e( 'Cloudflare WAF Protection', 'cloudflare-waf' ); ?></h1>
    
    <div class="cloudflare-waf-not-registered">
        <span class="dashicons dashicons-shield-alt" style="font-size: 48px; width: 48px; height: 48px; margin-bottom: 20px; color: #72777c;"></span>
        
        <h2><?php _e( 'Welcome to Cloudflare WAF Protection', 'cloudflare-waf' ); ?></h2>
        
        <p><?php _e( 'Protect your WordPress site from attacks using Cloudflare\'s Web Application Firewall.', 'cloudflare-waf' ); ?></p>
        
        <p><?php _e( 'To get started, you need to complete the setup process to connect your site with the Cloudflare WAF service.', 'cloudflare-waf' ); ?></p>
        
        <p>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=cloudflare-waf-wizard' ) ); ?>" class="button button-primary button-hero">
                <?php _e( 'Start Setup', 'cloudflare-waf' ); ?>
            </a>
        </p>
        
        <p class="description">
            <?php _e( 'The setup wizard will guide you through connecting your site with Cloudflare WAF, selecting a protection plan, and configuring your settings.', 'cloudflare-waf' ); ?>
        </p>
    </div>
</div>