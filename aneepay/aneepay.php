<?php
/**
 * Plugin Name: AneePay Crypto Gateway for WooCommerce
 * Plugin URI:  https://aneepay.com/
 * Description: Accept stablecoin crypto payments (USDT, USDC, DAI) on Polygon & Amoy testnet via the non-custodial AneePay gateway. Compatible with HPOS (High-Performance Order Storage).
 * Version:     1.1.0
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
	define( 'ANEEPAY_VERSION', '1.1.0' );
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

/**
 * Register the success/fail query variable used by the store-side
 * confirmation pages.
 *
 * @param array $vars Public query variables.
 * @return array
 */
function aneepay_add_query_vars( $vars ) {
	$vars[] = 'aneepay';
	return $vars;
}
add_filter( 'query_vars', 'aneepay_add_query_vars' );

/**
 * Render the store-side success/fail confirmation pages.
 *
 * AneePay redirects the customer browser to the account-level SUCCESS_URL /
 * FAIL_URL configured in the AneePay panel. The plugin exposes two static
 * endpoints for that:
 *  - /?aneepay=success
 *  - /?aneepay=fail
 *
 * The relevant order is tracked in a cookie set when the customer was sent
 * to the hosted checkout, so the page can show per-order context.
 *
 * @return void
 */
function aneepay_render_result_page() {
	global $wp;

	$result = isset( $wp->query_vars['aneepay'] ) ? sanitize_key( $wp->query_vars['aneepay'] ) : '';

	if ( ! in_array( $result, array( 'success', 'fail' ), true ) ) {
		return;
	}

	$order_id = isset( $_COOKIE['aneepay_order'] ) ? absint( wp_unslash( $_COOKIE['aneepay_order'] ) ) : 0; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$order    = $order_id ? wc_get_order( $order_id ) : null;

	if ( $order && ANEEPAY_PAYMENT_GATEWAY_ID === $order->get_payment_method() ) {
		aneepay_sync_order_from_api( $order );
		$status = $order->get_meta( '_aneepay_payment_status', true );
	} else {
		$status = '';
	}

	aneepay_render_result_html( $result, $order, ( 'success' === $status ) );

	exit;
}
add_action( 'template_redirect', 'aneepay_render_result_page' );

/**
 * Sync an order's payment status from the AneePay API before rendering a
 * confirmation page (the webhook may still be in flight when the customer
 * returns to the store).
 *
 * @param WC_Order $order Order object.
 * @return void
 */
function aneepay_sync_order_from_api( $order ) {
	if ( 'success' === $order->get_meta( '_aneepay_payment_status', true ) ) {
		return;
	}

	$payment_id = (string) $order->get_meta( '_aneepay_payment_id', true );

	if ( '' === $payment_id ) {
		return;
	}

	$gateway = new WC_Gateway_AneePay_Crypto();
	$data    = $gateway->api_handler->get_payment( $payment_id );

	if ( is_array( $data ) && ! empty( $data['status'] ) ) {
		aneepay_apply_payment_status( $order, sanitize_key( $data['status'] ) );
	}
}

/**
 * Output the store-side success/fail confirmation page.
 *
 * @param string        $result  success|fail.
 * @param WC_Order|null $order   Order object when known.
 * @param bool          $is_paid Whether the payment is already confirmed.
 * @return void
 */
function aneepay_render_result_html( $result, $order, $is_paid ) {
	// A webhook may have confirmed the payment right after AneePay redirected
	// the customer; always reflect the real state.
	if ( $is_paid ) {
		$result = 'success';
	}

	get_header();

	echo '<div class="aneepay-result">';

	if ( 'success' === $result ) {
		echo '<h1>' . esc_html__( 'Payment completed', 'aneepay-crypto-gateway' ) . '</h1>';
		echo '<p>' . esc_html__( 'Thank you! Your payment was successful.', 'aneepay-crypto-gateway' ) . '</p>';

		if ( $order ) {
			echo '<p><a class="button" href="' . esc_url( $order->get_checkout_order_received_url() ) . '">' . esc_html__( 'View order', 'aneepay-crypto-gateway' ) . '</a></p>';
		} else {
			echo '<p><a class="button" href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">' . esc_html__( 'Back to shop', 'aneepay-crypto-gateway' ) . '</a></p>';
		}
	} else {
		echo '<h1>' . esc_html__( 'Payment not completed', 'aneepay-crypto-gateway' ) . '</h1>';
		echo '<p>' . esc_html__( 'The payment was cancelled or could not be completed. Your order has not been charged.', 'aneepay-crypto-gateway' ) . '</p>';

		if ( $order ) {
			echo '<p><a class="button" href="' . esc_url( $order->get_checkout_payment_url() ) . '">' . esc_html__( 'Try again', 'aneepay-crypto-gateway' ) . '</a></p>';
		} else {
			echo '<p><a class="button" href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">' . esc_html__( 'Back to shop', 'aneepay-crypto-gateway' ) . '</a></p>';
		}
	}

	echo '</div>';

	get_footer();
}
