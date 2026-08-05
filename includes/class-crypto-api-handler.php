<?php
/**
 * AneePay API handler.
 *
 * Wraps all outbound HTTP requests to the AneePay API
 * (https://api.aneepay.com) and centralises logging.
 *
 * @package AneePay_Crypto_Gateway
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class AneePay_Crypto_API_Handler
 */
class AneePay_Crypto_API_Handler {

	/**
	 * Gateway settings instance (WC_Gateway_AneePay_Crypto).
	 *
	 * @var WC_Gateway_AneePay_Crypto
	 */
	protected $gateway;

	/**
	 * Constructor.
	 *
	 * @param WC_Gateway_AneePay_Crypto $gateway Gateway instance.
	 */
	public function __construct( $gateway ) {
		$this->gateway = $gateway;
	}

	/**
	 * Whether debug logging is enabled.
	 *
	 * @return bool
	 */
	protected function is_debug() {
		return 'yes' === $this->gateway->get_option( 'debug' );
	}

	/**
	 * Log a message to the WC logger (only when debug mode is enabled).
	 *
	 * @param string $context Context label.
	 * @param mixed  $message Message.
	 * @param string $level   Log level (info|error|notice).
	 * @return void
	 */
	public function log( $context, $message, $level = 'info' ) {
		if ( ! $this->is_debug() ) {
			return;
		}

		$logger = wc_get_logger();
		$logger->log( $level, sprintf( '[%s] %s', $context, $message ), array( 'source' => 'aneepay' ) );
	}

	/**
	 * The merchant's domain, sent in the Origin header.
	 *
	 * @return string
	 */
	public function get_origin() {
		$configured = $this->gateway->get_option( 'site_domain' );

		if ( ! empty( $configured ) ) {
			return $configured;
		}

		$domain = wp_parse_url( home_url(), PHP_URL_HOST );

		return (string) $domain;
	}

	/**
	 * Account UUID from settings (may be empty when only the Origin header is used).
	 *
	 * @return string
	 */
	public function get_account_id() {
		return (string) $this->gateway->get_option( 'account_id' );
	}

	/**
	 * Whether the gateway runs in sandbox/test mode.
	 *
	 * @return bool
	 */
	public function is_sandbox() {
		return 'yes' === $this->gateway->get_option( 'test_mode' );
	}

	/**
	 * Build the API base URL with an optional sandbox query parameter.
	 *
	 * @return string
	 */
	protected function api_base_url() {
		$url = trailingslashit( ANEEPAY_API_BASE );

		if ( $this->is_sandbox() ) {
			$url = add_query_arg( 'sandbox', 'yes', $url );
		}

		return $url;
	}

	/**
	 * Network name expected by the API (polygon|amoy).
	 *
	 * @return string
	 */
	public function get_network() {
		return $this->is_sandbox() ? 'amoy' : 'polygon';
	}

	/**
	 * Token symbol expected by the API (usdt|usdc|dai).
	 *
	 * @return string
	 */
	public function get_token() {
		$token = strtolower( (string) $this->gateway->get_option( 'token', 'usdt' ) );
		return in_array( $token, array( 'usdt', 'usdc', 'dai' ), true ) ? $token : 'usdt';
	}

	/**
	 * Standard request args for AneePay API calls.
	 *
	 * @return array
	 */
	protected function request_args( $body = null ) {
		$args = array(
			'headers' => array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
				'Origin'       => $this->get_origin(),
			),
			'timeout' => 30,
		);

		if ( null !== $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		return $args;
	}

	/**
	 * Create a standard payment and return the hosted checkout HTML.
	 *
	 * @param float|string $amount       Order amount.
	 * @param WC_Order     $order        Order object.
	 * @return array{html:string, payment_id:?string}
	 * @throws RuntimeException When the API request fails.
	 */
	public function create_payment( $amount, $order ) {
		$amount = round( (float) $amount, 2 );
		$amount = number_format( $amount, 2, '.', '' );

		$payload = array(
			'amount'      => $amount,
			'token'       => $this->get_token(),
			'network'     => $this->get_network(),
			'description' => sprintf(
				/* translators: %s: order number */
				__( 'Order #%s', 'aneepay-crypto-gateway' ),
				$order->get_order_number()
			),
		);

		$account_id = $this->get_account_id();
		if ( ! empty( $account_id ) ) {
			$payload['account_id'] = $account_id;
		}

		$url  = $this->api_base_url() . 'payments/';
		$args = $this->request_args( $payload );
		$args['headers']['Accept'] = 'text/html';

		$this->log( 'create_payment', 'POST ' . $url . ' body=' . wp_json_encode( $payload ) );

		$response = wp_remote_post( $url, $args );

		return $this->parse_create_response( $response );
	}

	/**
	 * Parse the create payment response (text/html page).
	 *
	 * @param array|WP_Error $response wp_remote_post result.
	 * @return array{html:string, payment_id:?string}
	 * @throws RuntimeException When the request failed or returned an error status.
	 */
	protected function parse_create_response( $response ) {
		if ( is_wp_error( $response ) ) {
			$this->log( 'create_payment', $response->get_error_message(), 'error' );
			throw new RuntimeException( __( 'Unable to connect to the AneePay payment service.', 'aneepay-crypto-gateway' ) );
		}

		$code   = (int) wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );
		$headers = wp_remote_retrieve_headers( $response );

		$this->log( 'create_payment', 'HTTP ' . $code );

		if ( 200 !== $code && 201 !== $code ) {
			$this->log( 'create_payment', 'Unexpected status, body: ' . $body, 'error' );
			throw new RuntimeException( $this->extract_error_message( $code, $body ) );
		}

		$payment_id = $this->extract_payment_id( $headers );

		return array(
			'html'       => (string) $body,
			'payment_id' => $payment_id,
		);
	}

	/**
	 * Try to extract the payment UUID from response headers.
	 *
	 * @param array $headers Response headers.
	 * @return string|null
	 */
	protected function extract_payment_id( $headers ) {
		$candidates = array( 'x-payment-id', 'location' );

		foreach ( $candidates as $candidate ) {
			$value = isset( $headers[ $candidate ] ) ? $headers[ $candidate ] : '';

			if ( is_array( $value ) ) {
				$value = reset( $value );
			}

			$value = (string) $value;

			if ( preg_match( '/([0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12})/i', $value, $matches ) ) {
				return $matches[1];
			}
		}

		return null;
	}

	/**
	 * Build a user-facing error message from a failed API response.
	 *
	 * @param int    $code HTTP status code.
	 * @param string $body Response body.
	 * @return string
	 */
	protected function extract_error_message( $code, $body ) {
		$data = json_decode( (string) $body, true );

		if ( is_array( $data ) && isset( $data['detail'] ) ) {
			if ( is_string( $data['detail'] ) ) {
				return $data['detail'];
			}

			if ( is_array( $data['detail'] ) ) {
				$messages = array();
				foreach ( $data['detail'] as $item ) {
					if ( is_array( $item ) && isset( $item['msg'] ) ) {
						$messages[] = $item['msg'];
					}
				}
				if ( ! empty( $messages ) ) {
					return implode( '; ', $messages );
				}
			}
		}

		return sprintf(
			/* translators: %d: HTTP status code */
			__( 'AneePay returned an unexpected response (HTTP %d).', 'aneepay-crypto-gateway' ),
			$code
		);
	}

	/**
	 * Fetch payment details from the API.
	 *
	 * @param string $payment_id Payment UUID.
	 * @return array|null Payment data, or null on failure.
	 */
	public function get_payment( $payment_id ) {
		$url  = $this->api_base_url() . 'payments/' . rawurlencode( $payment_id );
		$args = $this->request_args();

		$this->log( 'get_payment', 'GET ' . $url );

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->log( 'get_payment', $response->get_error_message(), 'error' );
			return null;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			$this->log( 'get_payment', 'Unexpected status: ' . $code . ' body: ' . wp_remote_retrieve_body( $response ), 'error' );
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $data ) ) {
			$this->log( 'get_payment', 'Invalid JSON response', 'error' );
			return null;
		}

		return $data;
	}
}
