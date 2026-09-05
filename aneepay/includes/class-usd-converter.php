<?php
/**
 * AneePay fiat -> USD -> token converter.
 *
 * Stablecoins (USDT/USDC/DAI) are pegged to USD, so charging a customer a
 * token amount is equivalent to charging a USD amount. This class converts
 * the shop total (in the store currency) into the token amount to request
 * from the AneePay API.
 *
 * The USD rate is resolved with the following priority:
 *   1. Automatic rate from Frankfurter (ECB reference rates), cached.
 *   2. Merchant-provided manual rate ("1 token = X store currency").
 *   3. If neither is available, an exception is thrown — the amount is never
 *      guessed.
 *
 * @package AneePay_Crypto_Gateway
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class AneePay_USD_Converter
 */
class AneePay_USD_Converter {

	const DEFAULT_RATE_SOURCE = 'auto';

	/**
	 * Gateway settings instance.
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
	 * The store currency code (ISO 4217).
	 *
	 * @return string
	 */
	public function get_store_currency() {
		return (string) get_woocommerce_currency();
	}

	/**
	 * Whether the automatic rate source (Frankfurter/ECB) is enabled.
	 *
	 * @return bool
	 */
	public function is_auto_enabled() {
		return 'manual' !== $this->gateway->get_option( 'rate_source', self::DEFAULT_RATE_SOURCE );
	}

	/**
	 * Merchant-provided manual rate: "1 token (~$1) = X store currency".
	 *
	 * @return float|null Null when not configured.
	 */
	protected function get_manual_rate() {
		$rate = (float) $this->gateway->get_option( 'exchange_rate', 0 );

		return $rate > 0 ? $rate : null;
	}

	/**
	 * Resolve the number of store-currency units per one USD.
	 *
	 * @param string $currency Store currency code.
	 * @param string $source   (out) Resolved source: auto|manual.
	 * @return float|null Units of store currency per 1 USD, or null.
	 */
	public function get_fiat_per_usd( $currency, &$source ) {
		$currency = strtoupper( trim( (string) $currency ) );

		// USD is trivially 1:1 — no external request needed.
		if ( 'USD' === $currency ) {
			$source = 'auto';
			return 1.0;
		}

		if ( $this->is_auto_enabled() ) {
			$rate = $this->fetch_auto_rate( $currency );

			if ( null !== $rate ) {
				$source = 'auto';
				return $rate;
			}
		}

		$manual = $this->get_manual_rate();

		if ( null !== $manual && $manual > 0 ) {
			$source = 'manual';
			return $manual;
		}

		return null;
	}

	/**
	 * Convert a store-total into the token amount to request.
	 *
	 * @param float|string $store_total Order total in the store currency.
	 * @param string       $currency    Store currency code. Defaults to the shop currency.
	 * @return array{token_amount:float, usd_amount:float, fiat_per_usd:float, currency:string, source:string}
	 * @throws RuntimeException When no USD rate can be resolved.
	 */
	public function convert( $store_total, $currency = '' ) {
		if ( '' === $currency ) {
			$currency = $this->get_store_currency();
		}

		$store_total = (float) $store_total;

		if ( $store_total <= 0 ) {
			throw new RuntimeException( __( 'Order total must be greater than zero.', 'aneepay-crypto-gateway' ) );
		}

		$source = 'auto';
		$rate   = $this->get_fiat_per_usd( $currency, $source );

		if ( null === $rate || $rate <= 0 ) {
			throw new RuntimeException( __( 'Unable to resolve a USD exchange rate. Set a manual rate in the AneePay settings or add the manual rate source.', 'aneepay-crypto-gateway' ) );
		}

		$usd_amount  = $store_total / $rate;
		$token_amount = $this->ceil_2( $usd_amount );

		return array(
			'token_amount' => $token_amount,
			'usd_amount'   => $usd_amount,
			'fiat_per_usd' => $rate,
			'currency'     => $currency,
			'source'       => $source,
		);
	}

	/**
	 * Fetch and cache the automatic Frankfurter/ECB rate.
	 *
	 * @param string $currency Store currency code.
	 * @return float|null Units of store currency per 1 USD, or null.
	 */
	protected function fetch_auto_rate( $currency ) {
		$cache_key = 'aneepay_fiat_rate_' . strtolower( $currency );
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) && isset( $cached['rate'] ) ) {
			return (float) $cached['rate'];
		}

		$url = add_query_arg(
			array(
				'from' => 'USD',
				'to'   => $currency,
			),
			'https://api.frankfurter.app/latest'
		);

		$this->log( 'rate_fetch', 'GET ' . $url );

		$response = wp_remote_get( $url, array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) ) {
			$this->log( 'rate_fetch', $response->get_error_message(), 'error' );
			return null;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			$this->log( 'rate_fetch', 'Unexpected status: ' . $code, 'error' );
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		$rate = isset( $data['rates'][ $currency ] ) ? (float) $data['rates'][ $currency ] : 0.0;

		if ( $rate <= 0 ) {
			$this->log( 'rate_fetch', 'No rate for ' . $currency . ' in response', 'error' );
			return null;
		}

		set_transient(
			$cache_key,
			array(
				'rate' => $rate,
				'time' => time(),
			),
			HOUR_IN_SECONDS
		);

		return $rate;
	}

	/**
	 * Round a value up (ceiling) to 2 decimal places, in favour of the merchant.
	 *
	 * @param float $value Value to round.
	 * @return float
	 */
	protected function ceil_2( $value ) {
		return ceil( ( (float) $value * 100 ) ) / 100;
	}

	/**
	 * Log a message via the gateway API handler (only when debug is on).
	 *
	 * @param string $context Context label.
	 * @param mixed  $message Message.
	 * @param string $level   Log level.
	 * @return void
	 */
	protected function log( $context, $message, $level = 'info' ) {
		if ( isset( $this->gateway->api_handler ) && is_callable( array( $this->gateway->api_handler, 'log' ) ) ) {
			$this->gateway->api_handler->log( $context, $message, $level );
		}
	}
}
