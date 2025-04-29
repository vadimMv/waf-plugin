<?php
/**
 * Setup wizard payment step template
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

<div class="cloudflare-waf-payment-step">
    <div class="step-header">
        <h2><?php _e( 'Payment Information', 'cloudflare-waf' ); ?></h2>
        <p class="intro"><?php _e( 'Enter your payment details to activate WAF protection.', 'cloudflare-waf' ); ?></p>
    </div>

    <?php if ( 'payment_failed' === $error ) : ?>
        <div class="notice notice-error">
            <p><?php _e( 'Payment processing failed. Please try again.', 'cloudflare-waf' ); ?></p>
        </div>
    <?php elseif ( 'payment_processing_failed' === $error ) : ?>
        <div class="notice notice-error">
            <p><?php _e( 'There was an error processing your payment. Please try again.', 'cloudflare-waf' ); ?></p>
        </div>
    <?php elseif ( 'subscription_failed' === $error ) : ?>
        <div class="notice notice-error">
            <p><?php _e( 'Subscription creation failed. Please try again or contact support.', 'cloudflare-waf' ); ?></p>
        </div>
    <?php elseif ( 'registration_failed' === $error ) : ?>
        <div class="notice notice-error">
            <p><?php _e( 'Site registration failed. Please try again or contact support.', 'cloudflare-waf' ); ?></p>
        </div>
    <?php endif; ?>

    <div class="step-content">
        <div class="plan-summary">
            <h3><?php _e( 'Selected Plan', 'cloudflare-waf' ); ?></h3>
            <div class="plan-details">
                <div class="plan-name"><?php echo esc_html( $selected_plan['name'] ); ?></div>
                <div class="plan-price">
                    <span class="price"><?php echo esc_html( $selected_plan['price_display'] ); ?></span>
                    <span class="billing-cycle"><?php echo esc_html( $selected_plan['billing_cycle'] ); ?></span>
                </div>
            </div>
        </div>

        <div class="payment-form-container">
            <form method="post" id="payment-form">
                <?php wp_nonce_field( 'cloudflare_waf_wizard_payment', 'cloudflare_waf_wizard_nonce' ); ?>
                <input type="hidden" name="payment_token" id="payment-token">
                
                <div class="card-element-container">
                    <label for="card-element"><?php _e( 'Credit or Debit Card', 'cloudflare-waf' ); ?></label>
                    <div id="card-element">
                        <!-- Stripe.js will insert card element here -->
                    </div>
                    <div id="card-errors" class="payment-errors" role="alert"></div>
                </div>
                
                <div class="customer-info">
                    <div class="form-row">
                        <label for="customer-name"><?php _e( 'Name on Card', 'cloudflare-waf' ); ?></label>
                        <input type="text" id="customer-name" name="customer_name" required>
                    </div>
                    
                    <div class="form-row">
                        <label for="customer-email"><?php _e( 'Email Address', 'cloudflare-waf' ); ?></label>
                        <input type="email" id="customer-email" name="customer_email" value="<?php echo esc_attr( get_option( 'admin_email' ) ); ?>" required>
                    </div>
                </div>
                
                <div class="payment-agreement">
                    <p>
                        <?php _e( 'By proceeding, you authorize us to charge your card for this payment and future payments according to our terms.', 'cloudflare-waf' ); ?>
                    </p>
                </div>
                
                <div class="step-footer">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=cloudflare-waf-wizard&step=plan' ) ); ?>" class="button button-secondary">
                        <span class="dashicons dashicons-arrow-left-alt"></span>
                        <?php _e( 'Back', 'cloudflare-waf' ); ?>
                    </a>
                    <button type="submit" id="submit-payment" class="button button-primary">
                        <?php _e( 'Submit Payment', 'cloudflare-waf' ); ?>
                        <span class="dashicons dashicons-arrow-right-alt"></span>
                    </button>
                </div>
                
                <div class="payment-processing-overlay" style="display: none;">
                    <div class="spinner"></div>
                    <p><?php _e( 'Processing payment...', 'cloudflare-waf' ); ?></p>
                </div>
            </form>
        </div>
        
        <div class="payment-security">
            <p>
                <span class="dashicons dashicons-lock"></span>
                <?php _e( 'Your payment information is secure. We use industry-standard encryption to protect your data.', 'cloudflare-waf' ); ?>
            </p>
        </div>
    </div>
</div>