<?php
/**
 * Payment entry point: validates the cart into an order, creates the AneePay
 * payment and redirects the buyer to the hosted checkout.
 */
class Ps_AneepayPaymentModuleFrontController extends ModuleFrontController {
	public $ssl = true;

	public function initContent() {
		parent::initContent();

		$cart     = $this->context->cart;
		$customer = $this->context->customer;

		$home = $this->context->link->getPageLink('index');

		if (!$this->module->active || !$cart->id || $cart->id_customer != (int) $customer->id) {
			Tools::redirect($home);
		}

		$order_model = new AneePayOrder();

		// Idempotency gate: reuse an existing pending hosted payment.
		$existing = $order_model->getByCart((int) $cart->id);

		if ($existing && !empty($existing['checkout_url']) && in_array((string) $existing['payment_status'], array('', 'pending'), true)) {
			$this->context->cookie->__set('aneepay_order_id', (int) $existing['id_order'], time() + 3600);

			Tools::redirect((string) $existing['checkout_url']);
		}

		$total    = (float) $cart->getOrderTotal(true, Cart::BOTH);
		$currency = new Currency((int) $cart->id_currency);

		if ($total <= 0) {
			Tools::redirect($home);
		}

		$api = $this->module->getApi();
		$api->set_config('store_rate', $this->module->getStoreRate((string) $currency->iso_code));

		try {
			$converter = new AneePayUsdConverter($api);
			$converted = $converter->convert($total, (string) $currency->iso_code);
			$created   = $api->create_payment($converted['token_amount'], 'Order #' . (int) $cart->id);
		} catch (Exception $e) {
			$this->errors[] = $e->getMessage();
			$this->redirectWithNotice($home, 'error', $e->getMessage());

			return;
		}

		// Create the order (pending) and capture its id/reference.
		$pendingState = (int) Configuration::get('ANEEPAY_PENDING_STATE');

		$this->module->validateOrder(
			(int) $cart->id,
			$pendingState,
			$total,
			$this->module->displayName,
			null,
			array(),
			null,
			false,
			$cart->secure_key
		);

		$id_order = (int) $this->module->currentOrder;

		$order_model->saveMapping($id_order, array(
			'id_cart'        => (int) $cart->id,
			'payment_id'     => $created['payment_id'],
			'operation_id'   => $created['operation_id'],
			'checkout_url'   => $created['checkout_url'],
			'token'          => $api->get_token(),
			'network'        => $api->get_network(),
			'sandbox'        => $api->is_sandbox() ? 1 : 0,
			'payment_status' => 'pending',
			'token_amount'   => number_format((float) $converted['token_amount'], 2, '.', ''),
			'order_currency' => (string) $currency->iso_code,
			'order_total'    => (string) $total,
			'usd_amount'     => number_format((float) $converted['usd_amount'], 2, '.', ''),
			'fiat_per_usd'   => (string) $converted['fiat_per_usd'],
			'rate_source'    => (string) $converted['source'],
		));

		$this->context->cookie->__set('aneepay_order_id', $id_order, time() + 3600);

		Tools::redirect((string) $created['checkout_url']);
	}

	protected function redirectWithNotice($url, $type, $message) {
		$this->context->cookie->__set('aneepay_error', $message, time() + 600);

		Tools::redirect($url);
	}
}
