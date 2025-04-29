<?php
/**
 * Setup wizard plan selection step template
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

<div class="cloudflare-waf-plan-step">
    <div class="step-header">
        <h2><?php _e( 'Choose Your Protection Plan', 'cloudflare-waf' ); ?></h2>
        <p class="intro"><?php _e( 'Select the level of protection that best suits your website\'s needs.', 'cloudflare-waf' ); ?></p>
    </div>

    <?php if ( 'no_plan_selected' === $error ) : ?>
        <div class="notice notice-error">
            <p><?php _e( 'Please select a protection plan to continue.', 'cloudflare-waf' ); ?></p>
        </div>
    <?php elseif ( 'invalid_plan' === $error ) : ?>
        <div class="notice notice-error">
            <p><?php _e( 'The selected plan is invalid. Please choose a valid plan.', 'cloudflare-waf' ); ?></p>
        </div>
    <?php endif; ?>

    <div class="step-content">
        <form method="post" id="plan-selection-form">
            <?php wp_nonce_field( 'cloudflare_waf_wizard_plan', 'cloudflare_waf_wizard_nonce' ); ?>
            
            <div class="plan-options">
                <?php if ( empty( $plans ) ) : ?>
                    <div class="notice notice-error">
                        <p><?php _e( 'Error loading plan options. Please try again later.', 'cloudflare-waf' ); ?></p>
                    </div>
                <?php else : ?>
                    <?php foreach ( $plans as $plan_id => $plan ) : ?>
                        <div class="plan-option">
                            <input type="radio" name="plan_id" id="plan-<?php echo esc_attr( $plan_id ); ?>" value="<?php echo esc_attr( $plan_id ); ?>" <?php checked( isset( $plan['recommended'] ) && $plan['recommended'] ); ?>>
                            <label for="plan-<?php echo esc_attr( $plan_id ); ?>">
                                <div class="plan-header">
                                    <h3><?php echo esc_html( $plan['name'] ); ?></h3>
                                    <?php if ( isset( $plan['recommended'] ) && $plan['recommended'] ) : ?>
                                        <span class="recommended-badge"><?php _e( 'Recommended', 'cloudflare-waf' ); ?></span>
                                    <?php endif; ?>
                                    <div class="plan-price">
                                        <span class="price"><?php echo esc_html( $plan['price_display'] ); ?></span>
                                        <span class="billing-cycle"><?php echo esc_html( $plan['billing_cycle'] ); ?></span>
                                    </div>
                                </div>
                                <div class="plan-features">
                                    <ul>
                                        <?php foreach ( $plan['features'] as $feature ) : ?>
                                            <li>
                                                <span class="dashicons dashicons-yes"></span>
                                                <?php echo esc_html( $feature ); ?>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </label>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="step-footer">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=cloudflare-waf-wizard&step=welcome' ) ); ?>" class="button button-secondary">
                    <span class="dashicons dashicons-arrow-left-alt"></span>
                    <?php _e( 'Back', 'cloudflare-waf' ); ?>
                </a>
                <button type="submit" class="button button-primary">
                    <?php _e( 'Continue', 'cloudflare-waf' ); ?>
                    <span class="dashicons dashicons-arrow-right-alt"></span>
                </button>
            </div>
        </form>
    </div>
</div>