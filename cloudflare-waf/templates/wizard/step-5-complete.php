<?php
/**
 * Setup wizard completion step template
 *
 * @package CloudflareWAF
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$is_active = isset( $status['status'] ) && 'active' === $status['status'];
?>

<div class="cloudflare-waf-complete-step">
    <div class="step-header">
        <h2><?php _e( 'Setup Complete!', 'cloudflare-waf' ); ?></h2>
        <p class="intro"><?php _e( 'Congratulations! Your Cloudflare WAF protection is now set up.', 'cloudflare-waf' ); ?></p>
    </div>

    <div class="step-content">
        <div class="completion-graphic">
            <img src="<?php echo CLOUDFLARE_WAF_PLUGIN_URL; ?>assets/images/illustrations/complete.svg" alt="<?php _e( 'Setup Complete', 'cloudflare-waf' ); ?>">
        </div>
        
        <div class="completion-message">
            <?php if ( $is_active ) : ?>
                <div class="status-badge status-active">
                    <span class="dashicons dashicons-shield"></span>
                    <?php _e( 'Protection Active', 'cloudflare-waf' ); ?>
                </div>
                <p><?php _e( 'Your website is now protected by Cloudflare WAF. You can monitor protection status and view attack statistics from your dashboard.', 'cloudflare-waf' ); ?></p>
            <?php else : ?>
                <div class="status-badge status-pending">
                    <span class="dashicons dashicons-clock"></span>
                    <?php _e( 'Protection Pending', 'cloudflare-waf' ); ?>
                </div>
                <p><?php _e( 'Your protection is being set up and will be active soon. This process can take up to 24 hours as DNS changes propagate.', 'cloudflare-waf' ); ?></p>
                
                <div class="dns-check">
                    <button id="check-dns-propagation" class="button button-secondary">
                        <span class="dashicons dashicons-update"></span>
                        <?php _e( 'Check DNS Status', 'cloudflare-waf' ); ?>
                    </button>
                    <span class="dns-status"></span>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="next-steps">
            <h3><?php _e( 'Next Steps', 'cloudflare-waf' ); ?></h3>
            <ul>
                <li>
                    <span class="dashicons dashicons-dashboard"></span>
                    <div>
                        <h4><?php _e( 'Explore Your Dashboard', 'cloudflare-waf' ); ?></h4>
                        <p><?php _e( 'View protection status, attack statistics, and activity reports.', 'cloudflare-waf' ); ?></p>
                    </div>
                </li>
                <li>
                    <span class="dashicons dashicons-admin-settings"></span>
                    <div>
                        <h4><?php _e( 'Customize Settings', 'cloudflare-waf' ); ?></h4>
                        <p><?php _e( 'Adjust protection levels and configure advanced settings.', 'cloudflare-waf' ); ?></p>
                    </div>
                </li>
                <li>
                    <span class="dashicons dashicons-testimonial"></span>
                    <div>
                        <h4><?php _e( 'Set Up Notifications', 'cloudflare-waf' ); ?></h4>
                        <p><?php _e( 'Configure email alerts for important security events.', 'cloudflare-waf' ); ?></p>
                    </div>
                </li>
            </ul>
        </div>
    </div>

    <div class="step-footer">
        <a href="<?php echo esc_url( admin_url( 'admin.php?page=cloudflare-waf-dashboard' ) ); ?>" class="button button-primary button-hero">
            <?php _e( 'Go to Dashboard', 'cloudflare-waf' ); ?>
            <span class="dashicons dashicons-arrow-right-alt"></span>
        </a>
    </div>
</div>