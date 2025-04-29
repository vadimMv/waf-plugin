<?php
/**
 * Setup wizard welcome step template
 *
 * @package CloudflareWAF
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>

<div class="cloudflare-waf-welcome-step">
    <div class="step-header">
        <h2><?php _e( 'Welcome to Cloudflare WAF Protection', 'cloudflare-waf' ); ?></h2>
        <p class="intro"><?php _e( 'This wizard will guide you through setting up Cloudflare WAF protection for your WordPress site.', 'cloudflare-waf' ); ?></p>
    </div>

    <div class="step-content">
        <div class="welcome-graphic">
            <img src="<?php echo CLOUDFLARE_WAF_PLUGIN_URL; ?>assets/images/illustrations/welcome.svg" alt="<?php _e( 'Welcome Illustration', 'cloudflare-waf' ); ?>">
        </div>

        <div class="features-list">
            <h3><?php _e( 'Here\'s what you\'ll get with Cloudflare WAF Protection:', 'cloudflare-waf' ); ?></h3>
            
            <ul>
                <li>
                    <span class="dashicons dashicons-shield"></span>
                    <div>
                        <h4><?php _e( 'Advanced Firewall Protection', 'cloudflare-waf' ); ?></h4>
                        <p><?php _e( 'Block malicious traffic, SQL injections, XSS attacks, and more.', 'cloudflare-waf' ); ?></p>
                    </div>
                </li>
                <li>
                    <span class="dashicons dashicons-performance"></span>
                    <div>
                        <h4><?php _e( 'DDoS Mitigation', 'cloudflare-waf' ); ?></h4>
                        <p><?php _e( 'Protect your site from distributed denial of service attacks.', 'cloudflare-waf' ); ?></p>
                    </div>
                </li>
                <li>
                    <span class="dashicons dashicons-admin-site-alt3"></span>
                    <div>
                        <h4><?php _e( 'WordPress-Specific Rules', 'cloudflare-waf' ); ?></h4>
                        <p><?php _e( 'Custom rules designed specifically to protect WordPress sites.', 'cloudflare-waf' ); ?></p>
                    </div>
                </li>
                <li>
                    <span class="dashicons dashicons-chart-bar"></span>
                    <div>
                        <h4><?php _e( 'Real-time Monitoring', 'cloudflare-waf' ); ?></h4>
                        <p><?php _e( 'View attack statistics and threat analytics directly in your dashboard.', 'cloudflare-waf' ); ?></p>
                    </div>
                </li>
            </ul>
        </div>

        <div class="setup-info">
            <h3><?php _e( 'What you\'ll need to complete setup:', 'cloudflare-waf' ); ?></h3>
            <ul>
                <li><?php _e( 'Access to your domain\'s DNS settings', 'cloudflare-waf' ); ?></li>
                <li><?php _e( 'A payment method for subscription', 'cloudflare-waf' ); ?></li>
                <li><?php _e( 'About 10 minutes of your time', 'cloudflare-waf' ); ?></li>
            </ul>
        </div>
    </div>

    <div class="step-footer">
        <form method="post">
            <?php wp_nonce_field( 'cloudflare_waf_wizard_welcome', 'cloudflare_waf_wizard_nonce' ); ?>
            <button type="submit" class="button button-primary button-hero">
                <?php _e( 'Let\'s Get Started', 'cloudflare-waf' ); ?>
                <span class="dashicons dashicons-arrow-right-alt"></span>
            </button>
        </form>
    </div>
</div>