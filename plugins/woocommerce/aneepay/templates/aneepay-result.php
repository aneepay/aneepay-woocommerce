<?php
/**
 * AneePay checkout result page (success / fail).
 *
 * AneePay redirects the customer browser to SUCCESS_URL / FAIL_URL after the
 * hosted checkout. This template renders the store-side confirmation page.
 *
 * Available variables: $result (success|fail), $order (WC_Order|null),
 * $is_paid (bool).
 *
 * @package AneePay_Crypto_Gateway
 */

defined( 'ABSPATH' ) || exit;

$result  = isset( $result ) ? $result : 'fail';
$is_paid = isset( $is_paid ) ? (bool) $is_paid : false;
$order   = isset( $order ) ? $order : null;

if ( $is_paid ) {
	$result = 'success';
}

$is_success = ( 'success' === $result );

get_header();
?>
<div class="aneepay-result">
	<div class="aneepay-result-card">
		<div class="aneepay-result-icon <?php echo $is_success ? 'is-success' : 'is-fail'; ?>">
			<?php echo $is_success ? '&#10003;' : '&#10005;'; ?>
		</div>

		<?php if ( $is_success ) : ?>
			<h1 class="aneepay-result-title"><?php esc_html_e( 'Payment completed', 'aneepay-crypto-gateway' ); ?></h1>
			<p class="aneepay-result-text"><?php esc_html_e( 'Thank you! Your payment was successful.', 'aneepay-crypto-gateway' ); ?></p>
		<?php else : ?>
			<h1 class="aneepay-result-title"><?php esc_html_e( 'Payment not completed', 'aneepay-crypto-gateway' ); ?></h1>
			<p class="aneepay-result-text"><?php esc_html_e( 'The payment was cancelled or could not be completed. Your order has not been charged.', 'aneepay-crypto-gateway' ); ?></p>
		<?php endif; ?>

		<?php if ( $order ) : ?>
			<div class="aneepay-result-order">
				<p>
					<strong><?php esc_html_e( 'Order', 'aneepay-crypto-gateway' ); ?>:</strong>
					#<?php echo esc_html( $order->get_order_number() ); ?>
				</p>
				<p>
					<strong><?php esc_html_e( 'Total', 'aneepay-crypto-gateway' ); ?>:</strong>
					<?php echo wp_kses_post( $order->get_formatted_order_total() ); ?>
				</p>
				<?php if ( $order->get_meta( '_aneepay_payment_id', true ) ) : ?>
					<p>
						<strong><?php esc_html_e( 'AneePay payment', 'aneepay-crypto-gateway' ); ?>:</strong>
						<code><?php echo esc_html( (string) $order->get_meta( '_aneepay_payment_id', true ) ); ?></code>
					</p>
				<?php endif; ?>
				<?php if ( $order->get_meta( '_aneepay_tx_hash', true ) ) : ?>
					<p>
						<strong><?php esc_html_e( 'Transaction', 'aneepay-crypto-gateway' ); ?>:</strong>
						<code><?php echo esc_html( (string) $order->get_meta( '_aneepay_tx_hash', true ) ); ?></code>
					</p>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<p class="aneepay-result-actions">
			<?php if ( $is_success ) : ?>
				<?php if ( $order ) : ?>
					<a class="button" href="<?php echo esc_url( $order->get_checkout_order_received_url() ); ?>"><?php esc_html_e( 'View order', 'aneepay-crypto-gateway' ); ?></a>
				<?php endif; ?>
				<a class="button" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php esc_html_e( 'Back to shop', 'aneepay-crypto-gateway' ); ?></a>
			<?php else : ?>
				<?php if ( $order ) : ?>
					<a class="button" href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>"><?php esc_html_e( 'Try again', 'aneepay-crypto-gateway' ); ?></a>
				<?php endif; ?>
				<a class="button" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"><?php esc_html_e( 'Back to shop', 'aneepay-crypto-gateway' ); ?></a>
			<?php endif; ?>
		</p>
	</div>
</div>
<?php
get_footer();
