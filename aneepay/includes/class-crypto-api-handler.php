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
	 * Build a full API URL for the given path, appending the sandbox
	 * query parameter when test mode is enabled.
	 *
	 * @param string $path Path (e.g. "payments/").
	 * @return string
	 */
	protected function api_url( $path ) {
		$url = trailingslashit( ANEEPAY_API_BASE ) . ltrim( $path, '/' );

		if ( $this->is_sandbox() ) {
			$url = add_query_arg( 'sandbox', 'yes', $url );
		}

		return $url;
	}

	/**
	 * Network name expected by the API (polygon|amoy).
	 *
	 * Test/sandbox mode always forces the Amoy testnet.
	 *
	 * @return string
	 */
	public function get_network() {
		if ( $this->is_sandbox() ) {
			return 'amoy';
		}

		$network = strtolower( (string) $this->gateway->get_option( 'network', 'polygon' ) );

		return in_array( $network, array( 'polygon', 'amoy' ), true ) ? $network : 'polygon';
	}

	/**
	 * Token symbol expected by the API (usdt|usdc|dai).
	 *
	 * In sandbox mode the API forces the token to usdc.
	 *
	 * @return string
	 */
	public function get_token() {
		if ( $this->is_sandbox() ) {
			return 'usdc';
		}

		$token = strtolower( (string) $this->gateway->get_option( 'token', 'usdt' ) );
		return in_array( $token, array( 'usdt', 'usdc', 'dai' ), true ) ? $token : 'usdt';
	}

	/**
	 * Standard request args for AneePay API calls.
	 *
	 * @param array|null $body Optional JSON payload.
	 * @return array
	 */
	protected function request_args( $body = null ) {
		$args = array(
			'headers' => array(
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
				'X-Account-Id' => $this->get_account_id(),
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
	 * Create a standard payment and return the hosted checkout URL.
	 *
	 * @param float|string $amount Order amount.
	 * @param WC_Order     $order  Order object.
	 * @return array{payment_id:string, operation_id:string, checkout_url:string, status:string}
	 * @throws RuntimeException When the API request fails or the Account ID is missing.
	 */
	public function create_payment( $amount, $order ) {
		$account_id = $this->get_account_id();

		if ( '' === $account_id ) {
			throw new RuntimeException( __( 'AneePay Account ID is not configured. Please set it in the gateway settings.', 'aneepay-crypto-gateway' ) );
		}

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

		$url  = $this->api_url( 'payments/' );
		$args = $this->request_args( $payload );

		$this->log( 'create_payment', 'POST ' . $url . ' body=' . wp_json_encode( $payload ) );

		$response = wp_remote_post( $url, $args );

		return $this->parse_create_response( $response );
	}

	/**
	 * Parse the create payment response.
	 *
	 * The API always answers with JSON:
	 * { "id", "operation_id", "status", "checkout_url" }.
	 *
	 * JSON_BIGINT_AS_STRING keeps operation_id (a 128-bit integer) exact.
	 *
	 * @param array|WP_Error $response wp_remote_post result.
	 * @return array{payment_id:string, operation_id:string, checkout_url:string, status:string}
	 * @throws RuntimeException When the request failed or returned an error status.
	 */
	protected function parse_create_response( $response ) {
		if ( is_wp_error( $response ) ) {
			$this->log( 'create_payment', $response->get_error_message(), 'error' );
			throw new RuntimeException( __( 'Unable to connect to the AneePay payment service.', 'aneepay-crypto-gateway' ) );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		$this->log( 'create_payment', 'HTTP ' . $code );

		if ( 200 !== $code && 201 !== $code ) {
			$this->log( 'create_payment', 'Unexpected status, body: ' . $body, 'error' );
			throw new RuntimeException( $this->extract_error_message( $code, $body ) );
		}

		$json = json_decode( $body, true, 512, JSON_BIGINT_AS_STRING );

		if ( ! is_array( $json ) ) {
			$this->log( 'create_payment', 'Invalid JSON response: ' . $body, 'error' );
			throw new RuntimeException( __( 'AneePay returned an invalid response.', 'aneepay-crypto-gateway' ) );
		}

		$payment_id   = isset( $json['id'] ) ? sanitize_text_field( (string) $json['id'] ) : '';
		$operation_id = isset( $json['operation_id'] ) ? sanitize_text_field( (string) $json['operation_id'] ) : '';
		$checkout_url = isset( $json['checkout_url'] ) ? esc_url_raw( (string) $json['checkout_url'] ) : '';
		$status       = isset( $json['status'] ) ? sanitize_key( (string) $json['status'] ) : '';

		if ( empty( $payment_id ) || empty( $operation_id ) || empty( $checkout_url ) ) {
			$this->log( 'create_payment', 'Missing required fields in response: ' . $body, 'error' );
			throw new RuntimeException( __( 'AneePay did not return a valid checkout URL.', 'aneepay-crypto-gateway' ) );
		}

		return array(
			'payment_id'   => $payment_id,
			'operation_id' => $operation_id,
			'checkout_url' => $checkout_url,
			'status'       => $status,
		);
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

		if ( is_array( $data ) ) {
			if ( isset( $data['detail'] ) ) {
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

			if ( ! empty( $data['code'] ) ) {
				$error_code = sanitize_text_field( (string) $data['code'] );

				if ( ! empty( $data['message'] ) ) {
					return $error_code . ': ' . sanitize_text_field( (string) $data['message'] );
				}

				if ( ! empty( $data['error'] ) ) {
					return $error_code . ': ' . sanitize_text_field( (string) $data['error'] );
				}

				return $error_code;
			}

			if ( ! empty( $data['message'] ) ) {
				return sanitize_text_field( (string) $data['message'] );
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
		$url  = $this->api_url( 'payments/' . rawurlencode( $payment_id ) );
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

		$data = json_decode( wp_remote_retrieve_body( $response ), true, 512, JSON_BIGINT_AS_STRING );

		if ( ! is_array( $data ) ) {
			$this->log( 'get_payment', 'Invalid JSON response', 'error' );
			return null;
		}

		return $data;
	}
}
