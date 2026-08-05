<?php
/**
 * Plugin Name: AneePay Crypto Gateway for WooCommerce
 * Plugin URI:  https://aneepay.com/
 * Description: Accept stablecoin crypto payments (USDT, USDC, DAI) on Polygon & Amoy testnet via the non-custodial AneePay gateway. Compatible with HPOS (High-Performance Order Storage).
 * Version:     1.0.0
 * Author:      AneePay
 * Author URI:  https://aneepay.com/
 * License:     MIT
 * License URI: https://opensource.org/licenses/MIT
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 9.4
 * Text Domain: aneepay-crypto-gateway
 * Domain Path: /languages
 *
 * @package AneePay_Crypto_Gateway
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'ANEEPAY_VERSION' ) ) {
	define( 'ANEEPAY_VERSION', '1.0.0' );
}

if ( ! defined( 'ANEEPAY_PLUGIN_FILE' ) ) {
	define( 'ANEEPAY_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'ANEEPAY_PLUGIN_URL' ) ) {
	define( 'ANEEPAY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

if ( ! defined( 'ANEEPAY_PLUGIN_DIR' ) ) {
	define( 'ANEEPAY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'ANEEPAY_API_BASE' ) ) {
	define( 'ANEEPAY_API_BASE', 'https://api.aneepay.com' );
}

if ( ! defined( 'ANEEPAY_PAYMENT_GATEWAY_ID' ) ) {
	define( 'ANEEPAY_PAYMENT_GATEWAY_ID', 'aneepay_crypto' );
}

require_once ANEEPAY_PLUGIN_DIR . 'includes/class-crypto-api-handler.php';
require_once ANEEPAY_PLUGIN_DIR . 'includes/class-wc-gateway-crypto.php';

/**
 * Declare compatibility with High-Performance Order Storage (HPOS).
 *
 * @return void
 */
function aneepay_declare_hpos_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', ANEEPAY_PLUGIN_FILE, true );
	}
}
add_action( 'before_woocommerce_init', 'aneepay_declare_hpos_compatibility' );

/**
 * Register the gateway with WooCommerce.
 *
 * @param array $methods Registered payment gateway class names.
 * @return array
 */
function aneepay_add_gateway( $methods ) {
	$methods[] = 'WC_Gateway_AneePay_Crypto';
	return $methods;
}
add_filter( 'woocommerce_payment_gateways', 'aneepay_add_gateway' );

/**
 * Show an admin notice if WooCommerce is not active.
 *
 * @return void
 */
function aneepay_missing_wc_notice() {
	if ( class_exists( 'WooCommerce' ) ) {
		return;
	}

	$message = sprintf(
		/* translators: %s: plugin name */
		__( 'AneePay Crypto Gateway requires WooCommerce to be installed and activated. Please install and activate WooCommerce to use the %s plugin.', 'aneepay-crypto-gateway' ),
		'<strong>AneePay Crypto Gateway for WooCommerce</strong>'
	);

	echo '<div class="notice notice-error"><p>' . wp_kses_post( $message ) . '</p></div>';
}
add_action( 'admin_notices', 'aneepay_missing_wc_notice' );

/**
 * Activation hook.
 *
 * @return void
 */
function aneepay_activate_plugin() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		wp_die( esc_html__( 'AneePay Crypto Gateway requires WooCommerce to be installed and activated. Plugin deactivated.', 'aneepay-crypto-gateway' ) );
	}

	wp_clear_scheduled_hook( 'aneepay_sync_pending_orders' );
	wp_schedule_event( time() + MINUTE_IN_SECONDS, 'every_five_minutes', 'aneepay_sync_pending_orders' );
}
register_activation_hook( __FILE__, 'aneepay_activate_plugin' );

/**
 * Deactivation hook.
 *
 * @return void
 */
function aneepay_deactivate_plugin() {
	wp_clear_scheduled_hook( 'aneepay_sync_pending_orders' );
}
register_deactivation_hook( __FILE__, 'aneepay_deactivate_plugin' );

/**
 * Register a custom cron interval.
 *
 * @param array $schedules Cron schedule intervals.
 * @return array
 */
function aneepay_add_cron_interval( $schedules ) {
	$schedules['every_five_minutes'] = array(
		'interval' => 5 * MINUTE_IN_SECONDS,
		'display'  => __( 'Every five minutes', 'aneepay-crypto-gateway' ),
	);
	return $schedules;
}
add_filter( 'cron_schedules', 'aneepay_add_cron_interval' );

/**
 * Enqueue frontend script used to poll the payment status.
 *
 * @return void
 */
function aneepay_enqueue_frontend_scripts() {
	if ( ! is_checkout() && ! is_wc_endpoint_url( 'order-pay' ) ) {
		return;
	}

	wp_enqueue_script(
		'aneepay-checkout',
		ANEEPAY_PLUGIN_URL . 'assets/js/aneepay-checkout.js',
		array(),
		ANEEPAY_VERSION,
		true
	);

	wp_localize_script(
		'aneepay-checkout',
		'aneepayParams',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'aneepay_status' ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'aneepay_enqueue_frontend_scripts' );

/**
 * Register the webhook REST route and the status polling AJAX actions.
 *
 * @return void
 */
function aneepay_register_endpoints() {
	register_rest_route(
		'aneepay/v1',
		'/webhook',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'aneepay_handle_webhook',
			'permission_callback' => '__return_true',
		)
	);

	add_action( 'wp_ajax_aneepay_check_status', 'aneepay_ajax_check_status' );
	add_action( 'wp_ajax_nopriv_aneepay_check_status', 'aneepay_ajax_check_status' );
}
add_action( 'rest_api_init', 'aneepay_register_endpoints' );
