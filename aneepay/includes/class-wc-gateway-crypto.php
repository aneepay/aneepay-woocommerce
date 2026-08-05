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
				'description' => __( 'When enabled, payments are created on the Polygon Amoy testnet and the is_safe check is bypassed.', 'aneepay-crypto-gateway' ),
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
				'description' => __( 'Your AneePay account UUID. Recommended together with the Origin header.', 'aneepay-crypto-gateway' ),
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
				'description' => __( 'Optional shared secret used to authenticate incoming webhook calls to the endpoint below. Leave empty to accept unsigned requests (not recommended).', 'aneepay-crypto-gateway' ),
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

		echo '<h2>' . esc_html__( 'Webhook Endpoint', 'aneepay-crypto-gateway' ) . '</h2>';
		echo '<p>' . esc_html__( 'Configure this URL as your STATUS_URL (webhook) in the AneePay account panel. AneePay pushes the transaction result here to keep order statuses in sync.', 'aneepay-crypto-gateway' ) . '</p>';
		echo '<p><code>' . esc_url( $webhook_url ) . '</code></p>';
		echo '<p>' . esc_html__( 'Configure SUCCESS_URL / FAIL_URL in the same panel to return customers to the store after the hosted checkout page.', 'aneepay-crypto-gateway' ) . '</p>';
		echo '<p>' . esc_html__( 'As a fallback the plugin also polls the payment status periodically.', 'aneepay-crypto-gateway' ) . '</p>';
	}

	/**
	 * Process the payment on checkout.
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

		if ( ! empty( $created['checkout_url'] ) ) {
			// Preferred flow: redirect to the hosted checkout page on aneepay.com.
			// success/fail redirects and the status_url webhook are configured
			// in the AneePay account panel.
			$order->add_meta_data( '_aneepay_checkout_url', $created['checkout_url'], true );
		} else {
			// Fallback: keep the returned HTML to render on the thank-you page.
			$order->add_meta_data( '_aneepay_payment_html', $created['html'], true );
		}

		$order->save();

		$order->update_status(
			'pending',
			sprintf(
				/* translators: %s: payment id */
				__( 'Awaiting crypto payment. AneePay payment ID: %s', 'aneepay-crypto-gateway' ),
				$created['payment_id'] ? $created['payment_id'] : __( 'assigned by AneePay', 'aneepay-crypto-gateway' )
			)
		);

		wc_reduce_stock_levels( $order_id );

		if ( ! empty( $created['checkout_url'] ) ) {
			return array(
				'result'   => 'success',
				'redirect' => $created['checkout_url'],
			);
		}

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order ),
		);
	}

	/**
	 * Render the hosted AneePay checkout page on the thank-you / view-order page.
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

		if ( 'success' === $status || 'cancelled' === $status || 'failed' === $status ) {
			return;
		}

		$html = $order->get_meta( '_aneepay_payment_html', true );

		echo '<div class="aneepay-payment" data-order-id="' . esc_attr( $order_id ) . '" data-payment-status="' . esc_attr( (string) $status ) . '">';

		if ( ! empty( $html ) ) {
			// The API returns a fully rendered, self-contained checkout page (QR code + MetaMask deeplink).
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sanitised by AneePay.
		} else {
			esc_html_e( 'Your payment page is being prepared. Please wait...', 'aneepay-crypto-gateway' );
		}

		echo '</div>';
	}
}

/**
 * Handle an incoming status notification.
 *
 * The AneePay dApp calls this endpoint (its STATUS_URL) with the following
 * JSON payload after a transaction completes:
 * {
 *   "operationId": 123,          // int payment id
 *   "description": "Order #123", // order reference passed at creation
 *   "amount": "19.50",
 *   "token": "0x...",            // token contract address
 *   "recipient": "0x...",
 *   "status": "success|failed",
 *   "timestamp": "...",
 *   "chain_id": 137 | 20697,
 *   "txHash": "0x...",
 *   "fee": "...", "netAmount": "...", "blockNumber": 123, "gasUsed": "...",
 *   "error": "...", "code": "..."
 * }
 *
 * The endpoint also accepts "payment_id" (payment UUID) and/or "order_id"
 * as an alternative lookup, for maximum compatibility.
 *
 * @param WP_REST_Request $request REST request.
 * @return WP_REST_Response
 */
function aneepay_handle_webhook( $request ) {
	$gateway = new WC_Gateway_AneePay_Crypto();
	$secret  = (string) $gateway->get_option( 'webhook_secret' );

	$signature = $request->get_header( 'X-AneePay-Signature' );

	if ( '' !== $secret ) {
		$payload = (string) $request->get_body();

		if ( empty( $signature ) || ! hash_equals( hash_hmac( 'sha256', $payload, $secret ), $signature ) ) {
			return new WP_REST_Response( array( 'success' => false ), 401 );
		}
	}

	$params = $request->get_json_params();

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
 * 3. operationId (int) matching _aneepay_operation_id
 * 4. description "Order #N" parsing
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

	if ( isset( $params['operationId'] ) ) {
		$operation_id = sanitize_text_field( $params['operationId'] );
		$found        = aneepay_find_order_by_meta( '_aneepay_operation_id', $operation_id );
		if ( $found ) {
			return $found;
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

				if ( ! empty( $data['txHash'] ) ) {
					$note .= ' ' . sprintf(
						/* translators: %s: transaction hash */
						__( 'Tx: %s', 'aneepay-crypto-gateway' ),
						sanitize_text_field( $data['txHash'] )
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
