<?php
/**
 * AneePay Crypto payment gateway.
 *
 * @package AneePay_Crypto_Gateway
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class WC_Gateway_AneePay_Crypto
 */
class WC_Gateway_AneePay_Crypto extends WC_Payment_Gateway {

	/**
	 * API handler instance.
	 *
	 * @var AneePay_Crypto_API_Handler
	 */
	protected $api_handler;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id                 = ANEEPAY_PAYMENT_GATEWAY_ID;
		$this->icon               = apply_filters( 'aneepay_gateway_icon', '' );
		$this->has_fields         = false;
		$this->method_title       = __( 'AneePay Crypto', 'aneepay-crypto-gateway' );
		$this->method_description = __( 'Accept stablecoin payments (USDT, USDC, DAI) on Polygon and the Amoy testnet through the non-custodial AneePay gateway.', 'aneepay-crypto-gateway' );

		$this->supports = array(
			'products',
		);

		$this->init_form_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );

		$this->api_handler = new AneePay_Crypto_API_Handler( $this );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

		add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'render_payment' ), 10, 1 );
		add_action( 'woocommerce_view_order', array( $this, 'render_payment' ), 10, 1 );
	}

	/**
	 * Payment gateway settings fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'     => array(
				'title'       => __( 'Enable/Disable', 'aneepay-crypto-gateway' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable AneePay crypto payments', 'aneepay-crypto-gateway' ),
				'default'     => 'no',
			),
			'title'       => array(
				'title'       => __( 'Title', 'aneepay-crypto-gateway' ),
				'type'        => 'text',
				'description' => __( 'Payment method title shown to the customer during checkout.', 'aneepay-crypto-gateway' ),
				'default'     => __( 'Pay with Crypto (USDT/USDC/DAI)', 'aneepay-crypto-gateway' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'aneepay-crypto-gateway' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description shown to the customer during checkout.', 'aneepay-crypto-gateway' ),
				'default'     => __( 'Pay instantly with a stablecoin on the Polygon network.', 'aneepay-crypto-gateway' ),
				'desc_tip'    => true,
			),
			'test_mode'   => array(
				'title'       => __( 'Test/Sandbox Mode', 'aneepay-crypto-gateway' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable sandbox mode (Amoy testnet)', 'aneepay-crypto-gateway' ),
				'description' => __( 'When enabled, payments are created on the Polygon Amoy testnet with USDC and the is_safe check is bypassed.', 'aneepay-crypto-gateway' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
			'site_domain' => array(
				'title'       => __( 'Domain', 'aneepay-crypto-gateway' ),
				'type'        => 'text',
				'description' => __( 'Your verified merchant domain. Sent in the Origin header. Leave empty to use your site host automatically.', 'aneepay-crypto-gateway' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'account_id'  => array(
				'title'       => __( 'Account ID', 'aneepay-crypto-gateway' ),
				'type'        => 'text',
				'description' => __( 'Your AneePay account UUID. Required — sent in the X-Account-Id header for every API request.', 'aneepay-crypto-gateway' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'token'       => array(
				'title'       => __( 'Supported Cryptocurrency', 'aneepay-crypto-gateway' ),
				'type'        => 'select',
				'description' => __( 'The stablecoin used to charge customers. Funds go directly to your wallet.', 'aneepay-crypto-gateway' ),
				'default'     => 'usdt',
				'desc_tip'    => true,
				'options'     => array(
					'usdt' => 'USDT (Tether)',
					'usdc' => 'USDC (USD Coin)',
					'dai'  => 'DAI',
				),
			),
			'webhook_secret' => array(
				'title'       => __( 'Webhook Secret', 'aneepay-crypto-gateway' ),
				'type'        => 'password',
				'description' => __( 'Your AneePay webhook secret used to verify the X-AneePay-Signature (HMAC-SHA256) on incoming webhook calls. Strongly recommended.', 'aneepay-crypto-gateway' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'debug'       => array(
				'title'       => __( 'Debug Log', 'aneepay-crypto-gateway' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable logging', 'aneepay-crypto-gateway' ),
				'description' => __( 'Log AneePay API requests and responses. Logs can be found in WooCommerce > Status > Logs (source: aneepay).', 'aneepay-crypto-gateway' ),
				'default'     => 'no',
				'desc_tip'    => true,
			),
		);
	}

	/**
	 * Gateway settings page. Appends the webhook endpoint hint.
	 *
	 * @return void
	 */
	public function admin_options() {
		parent::admin_options();

		$webhook_url = rest_url( 'aneepay/v1/webhook' );
		$success_url = home_url( '/?aneepay=success' );
		$fail_url    = home_url( '/?aneepay=fail' );

		echo '<h2>' . esc_html__( 'AneePay Endpoints', 'aneepay-crypto-gateway' ) . '</h2>';
		echo '<p>' . esc_html__( 'Configure these URLs in the AneePay account panel:', 'aneepay-crypto-gateway' ) . '</p>';

		echo '<p><strong>' . esc_html__( 'STATUS_URL (webhook)', 'aneepay-crypto-gateway' ) . '</strong><br><code>' . esc_url( $webhook_url ) . '</code></p>';
		echo '<p>' . esc_html__( 'AneePay pushes the transaction result here to keep order statuses in sync.', 'aneepay-crypto-gateway' ) . '</p>';

		echo '<p><strong>' . esc_html__( 'SUCCESS_URL', 'aneepay-crypto-gateway' ) . '</strong><br><code>' . esc_url( $success_url ) . '</code></p>';
		echo '<p>' . esc_html__( 'Customers land here after a successful payment.', 'aneepay-crypto-gateway' ) . '</p>';

		echo '<p><strong>' . esc_html__( 'FAIL_URL', 'aneepay-crypto-gateway' ) . '</strong><br><code>' . esc_url( $fail_url ) . '</code></p>';
		echo '<p>' . esc_html__( 'Customers land here when the payment is cancelled or fails.', 'aneepay-crypto-gateway' ) . '</p>';

		echo '<p>' . esc_html__( 'As a fallback the plugin also polls the payment status periodically.', 'aneepay-crypto-gateway' ) . '</p>';
	}

	/**
	 * Process the payment on checkout.
	 *
	 * Creates the payment via the AneePay API and redirects the customer to
	 * the hosted checkout page. SUCCESS_URL / FAIL_URL / STATUS_URL are
	 * configured in the AneePay account panel.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 * @throws Exception When the payment cannot be created.
	 */
	public function process_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			throw new Exception( __( 'Order not found.', 'aneepay-crypto-gateway' ) );
		}

		$amount = $order->get_total();

		if ( $amount <= 0 ) {
			throw new Exception( __( 'Order total must be greater than zero.', 'aneepay-crypto-gateway' ) );
		}

		$created = $this->api_handler->create_payment( $amount, $order );

		$order->add_meta_data( '_aneepay_payment_id', $created['payment_id'], true );
		$order->add_meta_data( '_aneepay_operation_id', $created['operation_id'], true );
		$order->add_meta_data( '_aneepay_token', $this->api_handler->get_token(), true );
		$order->add_meta_data( '_aneepay_network', $this->api_handler->get_network(), true );
		$order->add_meta_data( '_aneepay_sandbox', $this->api_handler->is_sandbox() ? 'yes' : 'no', true );
		$order->add_meta_data( '_aneepay_payment_status', 'pending', true );
		$order->add_meta_data( '_aneepay_checkout_url', $created['checkout_url'], true );

		$order->save();

		$order->update_status(
			'pending',
			sprintf(
				/* translators: %s: payment id */
				__( 'Awaiting crypto payment. AneePay payment ID: %s', 'aneepay-crypto-gateway' ),
				$created['payment_id']
			)
		);

		wc_reduce_stock_levels( $order_id );

		// Track the order so the store-side success/fail pages (configured in
		// the AneePay panel as SUCCESS_URL / FAIL_URL) can identify it.
		wc_setcookie( 'aneepay_order', (string) $order_id, time() + HOUR_IN_SECONDS, is_ssl() );

		return array(
			'result'   => 'success',
			'redirect' => $created['checkout_url'],
		);
	}

	/**
	 * Render the payment status block on the thank-you / view-order page.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function render_payment( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order || $order->get_payment_method() !== $this->id ) {
			return;
		}

		$status = $order->get_meta( '_aneepay_payment_status', true );

		if ( in_array( $status, array( 'success', 'cancelled', 'failed' ), true ) ) {
			return;
		}

		echo '<div class="aneepay-payment" data-order-id="' . esc_attr( $order_id ) . '" data-payment-status="' . esc_attr( (string) $status ) . '">';
		esc_html_e( 'Waiting for the payment to be confirmed on the blockchain. This page updates automatically.', 'aneepay-crypto-gateway' );
		echo '</div>';
	}
}

/**
 * Handle an incoming status notification.
 *
 * AneePay pushes this payload to the configured STATUS_URL after a payment
 * is confirmed on-chain (only for success):
 * {
 *   "operation_id": 1025366559013960798969014288963858567168, // int(payment_id)
 *   "status": "success",
 *   "timestamp": 1755262800.0
 * }
 *
 * The signature (X-AneePay-Signature) is an HMAC-SHA256 hex digest of the
 * raw request body using the webhook_secret. Payloads sent by the legacy
 * dApp confirm endpoint (payment_id / order_id / description / operationId)
 * are still accepted for compatibility.
 *
 * @param WP_REST_Request $request REST request.
 * @return WP_REST_Response
 */
function aneepay_handle_webhook( $request ) {
	$gateway = new WC_Gateway_AneePay_Crypto();
	$secret  = (string) $gateway->get_option( 'webhook_secret' );

	$signature = $request->get_header( 'X-AneePay-Signature' );
	$body      = (string) $request->get_body();

	if ( '' !== $secret ) {
		if ( empty( $signature ) || ! hash_equals( hash_hmac( 'sha256', $body, $secret ), $signature ) ) {
			return new WP_REST_Response( array( 'success' => false ), 401 );
		}
	}

	// JSON_BIGINT_AS_STRING keeps operation_id (a 128-bit integer) exact.
	$params = json_decode( $body, true, 512, JSON_BIGINT_AS_STRING );

	if ( ! is_array( $params ) ) {
		return new WP_REST_Response( array( 'success' => false ), 400 );
	}

	$status = isset( $params['status'] ) ? sanitize_key( $params['status'] ) : '';

	if ( empty( $status ) ) {
		return new WP_REST_Response( array( 'success' => false ), 400 );
	}

	$order = aneepay_resolve_order( $params );

	if ( ! $order ) {
		return new WP_REST_Response( array( 'success' => false ), 404 );
	}

	aneepay_apply_payment_status( $order, $status, $params );

	return new WP_REST_Response(
		array(
			'success' => true,
			'status'  => $status,
		),
		200
	);
}

/**
 * Resolve a WC_Order from a webhook payload.
 *
 * Lookup order:
 * 1. explicit order_id
 * 2. payment_id (UUID) matching _aneepay_payment_id
 * 3. operation_id (int(payment_id)) matching _aneepay_operation_id
 * 4. operation_id decoded back to the payment UUID
 * 5. description "Order #N" parsing
 *
 * @param array $params Webhook payload.
 * @return WC_Order|null
 */
function aneepay_resolve_order( $params ) {
	if ( isset( $params['order_id'] ) ) {
		$candidate = wc_get_order( absint( $params['order_id'] ) );
		if ( $candidate && $candidate->get_payment_method() === ANEEPAY_PAYMENT_GATEWAY_ID ) {
			return $candidate;
		}
	}

	if ( isset( $params['payment_id'] ) ) {
		$payment_id = sanitize_text_field( $params['payment_id'] );
		$found      = aneepay_find_order_by_meta( '_aneepay_payment_id', $payment_id );
		if ( $found ) {
			return $found;
		}
	}

	$operation_id = '';

	if ( isset( $params['operation_id'] ) ) {
		$operation_id = sanitize_text_field( (string) $params['operation_id'] );
	} elseif ( isset( $params['operationId'] ) ) {
		$operation_id = sanitize_text_field( (string) $params['operationId'] );
	}

	if ( '' !== $operation_id ) {
		$found = aneepay_find_order_by_meta( '_aneepay_operation_id', $operation_id );
		if ( $found ) {
			return $found;
		}

		$uuid = aneepay_operation_id_to_uuid( $operation_id );

		if ( null !== $uuid ) {
			$found = aneepay_find_order_by_meta( '_aneepay_payment_id', $uuid );
			if ( $found ) {
				return $found;
			}
		}
	}

	if ( ! empty( $params['description'] ) ) {
		$description = sanitize_text_field( $params['description'] );

		if ( preg_match( '/#\s*(\d+)/', $description, $matches ) ) {
			$candidate = wc_get_order( absint( $matches[1] ) );
			if ( $candidate && $candidate->get_payment_method() === ANEEPAY_PAYMENT_GATEWAY_ID ) {
				return $candidate;
			}
		}
	}

	return null;
}

/**
 * Decode an AneePay operation_id back to the payment UUID.
 *
 * operation_id is the payment UUID encoded as a 128-bit unsigned integer
 * (int(payment.id)); the mapping is bijective:
 * uuid.UUID(int=operation_id) === payment_id.
 *
 * @param string|int $operation_id Decimal operation id.
 * @return string|null Payment UUID, or null when not decodable.
 */
function aneepay_operation_id_to_uuid( $operation_id ) {
	$decimal = trim( (string) $operation_id );

	if ( '' === $decimal || ! preg_match( '/^\d+$/', $decimal ) ) {
		return null;
	}

	$hex = '';
	$num = ltrim( $decimal, '0' );

	if ( '' === $num ) {
		$num = '0';
	}

	while ( '0' !== $num ) {
		$remainder = 0;
		$quotient  = '';

		for ( $i = 0, $len = strlen( $num ); $i < $len; $i++ ) {
			$current   = ( $remainder * 10 ) + (int) $num[ $i ];
			$remainder = $current % 16;
			$quotient .= (int) ( $current / 16 );
		}

		$hex = dechex( $remainder ) . $hex;
		$num = ltrim( $quotient, '0' );

		if ( '' === $num ) {
			$num = '0';
		}
	}

	$hex = str_pad( $hex, 32, '0', STR_PAD_LEFT );

	return sprintf(
		'%s-%s-%s-%s-%s',
		substr( $hex, 0, 8 ),
		substr( $hex, 8, 4 ),
		substr( $hex, 12, 4 ),
		substr( $hex, 16, 4 ),
		substr( $hex, 20, 12 )
	);
}

/**
 * Find an order by meta key/value (HPOS compatible).
 *
 * @param string $meta_key   Meta key.
 * @param string $meta_value Meta value.
 * @return WC_Order|null
 */
function aneepay_find_order_by_meta( $meta_key, $meta_value ) {
	$query = wc_get_orders(
		array(
			'limit'      => 1,
			'meta_key'   => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value' => $meta_value, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'return'     => 'ids',
		)
	);

	if ( empty( $query ) ) {
		return null;
	}

	$candidate = wc_get_order( reset( $query ) );

	if ( $candidate && $candidate->get_payment_method() === ANEEPAY_PAYMENT_GATEWAY_ID ) {
		return $candidate;
	}

	return null;
}

/**
 * AJAX handler: poll the AneePay API for a single order and update it.
 *
 * @return void
 */
function aneepay_ajax_check_status() {
	check_ajax_referer( 'aneepay_status', 'nonce' );

	$order_id = isset( $_POST['orderId'] ) ? absint( $_POST['orderId'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
	$order    = wc_get_order( $order_id );

	if ( ! $order || $order->get_payment_method() !== ANEEPAY_PAYMENT_GATEWAY_ID ) {
		wp_send_json_error( array( 'message' => 'Invalid order' ) );
	}

	$gateway = new WC_Gateway_AneePay_Crypto();

	if ( 'success' === $order->get_meta( '_aneepay_payment_status', true ) ) {
		wp_send_json_success( array( 'status' => 'success' ) );
	}

	$payment_id = (string) $order->get_meta( '_aneepay_payment_id', true );

	if ( empty( $payment_id ) ) {
		wp_send_json_success( array( 'status' => $order->get_meta( '_aneepay_payment_status', true ) ) );
	}

	$data = $gateway->api_handler->get_payment( $payment_id );

	if ( null === $data || empty( $data['status'] ) ) {
		wp_send_json_success( array( 'status' => $order->get_meta( '_aneepay_payment_status', true ) ) );
	}

	aneepay_apply_payment_status( $order, $data['status'] );

	wp_send_json_success( array( 'status' => $order->get_meta( '_aneepay_payment_status', true ) ) );
}

/**
 * WP-Cron handler: sync all pending AneePay orders.
 *
 * @return void
 */
function aneepay_cron_sync_pending_orders() {
	$query = wc_get_orders(
		array(
			'limit'        => 20,
			'status'       => array( 'pending', 'on-hold' ),
			'payment_method' => ANEEPAY_PAYMENT_GATEWAY_ID,
			'return'       => 'ids',
		)
	);

	foreach ( $query as $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			continue;
		}

		$status = $order->get_meta( '_aneepay_payment_status', true );

		if ( in_array( $status, array( 'success', 'failed', 'cancelled' ), true ) ) {
			continue;
		}

		$payment_id = (string) $order->get_meta( '_aneepay_payment_id', true );

		if ( empty( $payment_id ) ) {
			continue;
		}

		$gateway = new WC_Gateway_AneePay_Crypto();
		$data    = $gateway->api_handler->get_payment( $payment_id );

		if ( null === $data || empty( $data['status'] ) ) {
			continue;
		}

		aneepay_apply_payment_status( $order, $data['status'] );
	}
}
add_action( 'aneepay_sync_pending_orders', 'aneepay_cron_sync_pending_orders' );

/**
 * Update an order based on a known payment status.
 *
 * @param string $payment_id AneePay payment UUID.
 * @param string $status     New payment status.
 * @param int    $order_id   Optional order ID for faster lookup.
 * @return bool
 */
function aneepay_update_order_by_status( $payment_id, $status, $order_id = 0 ) {
	$params = array();

	if ( ! empty( $order_id ) ) {
		$params['order_id'] = $order_id;
	}

	if ( ! empty( $payment_id ) ) {
		$params['payment_id'] = $payment_id;
	}

	$order = aneepay_resolve_order( $params );

	if ( ! $order ) {
		return false;
	}

	aneepay_apply_payment_status( $order, $status );

	return true;
}

/**
 * Apply a payment status to an order and mark it in meta.
 *
 * @param WC_Order $order  Order object.
 * @param string   $status Payment status (success|failed|cancelled|pending).
 * @param array    $data   Optional webhook payload for extra context.
 * @return void
 */
function aneepay_apply_payment_status( $order, $status, $data = array() ) {
	$status  = sanitize_key( $status );
	$current = $order->get_meta( '_aneepay_payment_status', true );

	if ( $status === $current ) {
		return;
	}

	switch ( $status ) {
		case 'success':
			if ( ! $order->is_paid() ) {
				$note = __( 'AneePay: payment confirmed.', 'aneepay-crypto-gateway' );

				if ( ! empty( $data['operation_id'] ) ) {
					$note .= ' ' . sprintf(
						/* translators: %s: AneePay operation id */
						__( 'Operation ID: %s', 'aneepay-crypto-gateway' ),
						sanitize_text_field( (string) $data['operation_id'] )
					);
				}

				$order->payment_complete();
				$order->add_order_note( $note );
			}
			break;

		case 'failed':
			$note = __( 'AneePay: payment failed.', 'aneepay-crypto-gateway' );

			if ( ! empty( $data['error'] ) ) {
				$note .= ' ' . sprintf(
					/* translators: %s: error message */
					__( 'Reason: %s', 'aneepay-crypto-gateway' ),
					sanitize_text_field( $data['error'] )
				);
			}

			$order->update_status( 'failed', $note );
			break;

		case 'cancelled':
			$order->update_status( 'cancelled', __( 'AneePay: payment cancelled by the customer.', 'aneepay-crypto-gateway' ) );
			break;

		case 'pending':
		default:
			$order->update_status( 'pending', __( 'AneePay: awaiting payment.', 'aneepay-crypto-gateway' ) );
			break;
	}

	$order->update_meta_data( '_aneepay_payment_status', $status );
	$order->save();
}
