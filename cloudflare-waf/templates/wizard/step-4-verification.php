<?php
/**
 * Setup wizard domain verification step template
 *
 * @package CloudflareWAF
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Check for error
$error = isset( $_GET['error'] ) ? sanitize_text_field( $_GET['error'] ) : '';
?>

<div class="cloudflare-waf-verification-step">
    <div class="step-header">
        <h2><?php _e( 'Domain Verification', 'cloudflare-waf' ); ?></h2>
        <p class="intro"><?php _e( 'Let\'s verify your domain to set up WAF protection.', 'cloudflare-waf' ); ?></p>
    </div>

    <?php if ( 'verification_failed' === $error ) : ?>
        <div class="notice notice-error">
            <p><?php _e( 'Domain verification failed. Please check your DNS settings and try again.', 'cloudflare-waf' ); ?></p>
        </div>
    <?php endif; ?>

    <div class="step-content">
        <div class="domain-info">
            <h3><?php _e( 'Your Domain', 'cloudflare-waf' ); ?></h3>
            <p class="domain-name"><?php echo esc_html( $domain ); ?></p>
        </div>

        <div class="verification-methods">
            <h3><?php _e( 'Verification Method', 'cloudflare-waf' ); ?></h3>
            
            <div class="verification-tabs">
                <div class="verification-tab active" data-method="dns">
                    <?php _e( 'DNS Record', 'cloudflare-waf' ); ?>
                </div>
                <div class="verification-tab" data-method="html">
                    <?php _e( 'HTML File', 'cloudflare-waf' ); ?>
                </div>
            </div>
            
            <div class="verification-content active" id="dns-verification">
                <p><?php _e( 'Add the following DNS TXT record to your domain to verify ownership:', 'cloudflare-waf' ); ?></p>
                
                <div class="dns-record-info">
                    <div class="record-row">
                        <div class="record-label"><?php _e( 'Record Type:', 'cloudflare-waf' ); ?></div>
                        <div class="record-value">TXT</div>
                    </div>
                    <div class="record-row">
                        <div class="record-label"><?php _e( 'Host:', 'cloudflare-waf' ); ?></div>
                        <div class="record-value"><?php echo esc_html( $verification['dns']['host'] ); ?></div>
                        <button class="copy-button" data-clipboard-text="<?php echo esc_attr( $verification['dns']['host'] ); ?>">
                            <span class="dashicons dashicons-clipboard"></span>
                        </button>
                    </div>
                    <div class="record-row">
                        <div class="record-label"><?php _e( 'Value:', 'cloudflare-waf' ); ?></div>
                        <div class="record-value"><?php echo esc_html( $verification['dns']['value'] ); ?></div>
                        <button class="copy-button" data-clipboard-text="<?php echo esc_attr( $verification['dns']['value'] ); ?>">
                            <span class="dashicons dashicons-clipboard"></span>
                        </button>
                    </div>
                </div>
                
                <div class="dns-help">
                    <h4><?php _e( 'Where to add this record?', 'cloudflare-waf' ); ?></h4>
                    <p><?php _e( 'Log in to your domain registrar or DNS provider\'s website and locate the DNS settings for your domain.', 'cloudflare-waf' ); ?></p>
                    <p><?php _e( 'Common DNS providers:', 'cloudflare-waf' ); ?></p>
                    <ul class="dns-providers">
                        <li><a href="https://support.cloudflare.com/hc/en-us/articles/360019093151" target="_blank">Cloudflare</a></li>
                        <li><a href="https://docs.aws.amazon.com/Route53/latest/DeveloperGuide/ResourceRecordTypes.html" target="_blank">AWS Route 53</a></li>
                        <li><a href="https://support.google.com/domains/answer/3290350?hl=en" target="_blank">Google Domains</a></li>
                        <li><a href="https://docs.godaddy.com/en/domains/managing-your-domain-names/create-or-edit-a-txt-dns-record" target="_blank">GoDaddy</a></li>
                        <li><a href="https://help.dreamhost.com/hc/en-us/articles/215414867-Adding-custom-DNS-records" target="_blank">DreamHost</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="verification-content" id="html-verification" style="display:none;">
                <p><?php _e( 'Download the HTML verification file and upload it to your website:', 'cloudflare-waf' ); ?></p>
                
                <div class="html-verification-info">
                    <div class="file-info">
                        <div class="file-name"><?php echo esc_html( $verification['html']['filename'] ); ?></div>
                        <a href="<?php echo esc_url( $verification['html']['download_url'] ); ?>" class="button button-secondary download-button">
                            <span class="dashicons dashicons-download"></span>
                            <?php _e( 'Download File', 'cloudflare-waf' ); ?>
                        </a>
                    </div>
                    
                    <div class="file-path">
                        <p><?php _e( 'Upload the file to:', 'cloudflare-waf' ); ?></p>
                        <code><?php echo esc_html( 'https://' . $domain . '/' . $verification['html']['path'] ); ?></code>
                    </div>
                </div>
                
                <div class="html-help">
                    <h4><?php _e( 'How to upload the file', 'cloudflare-waf' ); ?></h4>
                    <p><?php _e( 'You can upload the file using FTP, your hosting control panel file manager, or WordPress Media Library.', 'cloudflare-waf' ); ?></p>
                    <p>
                        <button id="auto-upload-file" class="button">
                            <?php _e( 'Try Automatic Upload', 'cloudflare-waf' ); ?>
                        </button>
                        <span class="upload-status"></span>
                    </p>
                </div>
            </div>
            
            <div class="verification-check">
                <h3><?php _e( 'Verify Domain Ownership', 'cloudflare-waf' ); ?></h3>
                <p><?php _e( 'After adding the verification record, click the button below to verify your domain.', 'cloudflare-waf' ); ?></p>
                
                <div class="check-button-container">
                    <button id="check-verification" class="button button-secondary">
                        <span class="dashicons dashicons-search"></span>
                        <?php _e( 'Check Verification', 'cloudflare-waf' ); ?>
                    </button>
                    <span class="verification-status"></span>
                </div>
            </div>
        </div>
        
        <form method="post" id="verification-form">
            <?php wp_nonce_field( 'cloudflare_waf_wizard_verification', 'cloudflare_waf_wizard_nonce' ); ?>
            <input type="hidden" name="verification_complete" id="verification-complete" value="0">
            
            <div class="step-footer">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=cloudflare-waf-wizard&step=payment' ) ); ?>" class="button button-secondary">
                    <span class="dashicons dashicons-arrow-left-alt"></span>
                    <?php _e( 'Back', 'cloudflare-waf' ); ?>
                </a>
                <button type="submit" class="button button-primary" id="continue-button" disabled>
                    <?php _e( 'Continue', 'cloudflare-waf' ); ?>
                    <span class="dashicons dashicons-arrow-right-alt"></span>
                </button>
            </div>
        </form>
    </div>
</div>