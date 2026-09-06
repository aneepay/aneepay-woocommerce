<?php
/**
 * AneePay Crypto Gateway (USDT/USDC/DAI on Polygon + Amoy).
 *
 * @author AneePay
 */
if (!defined('_PS_VERSION_')) {
	exit;
}

require_once _PS_MODULE_DIR_ . 'ps_aneepay/classes/AneePayApi.php';
require_once _PS_MODULE_DIR_ . 'ps_aneepay/classes/AneePayUsdConverter.php';
require_once _PS_MODULE_DIR_ . 'ps_aneepay/classes/AneePayOrder.php';
require_once _PS_MODULE_DIR_ . 'ps_aneepay/classes/AneePayWebhook.php';

class Ps_Aneepay extends PaymentModule {
	public function __construct() {
		$this->name                  = 'ps_aneepay';
		$this->tab                   = 'payments_gateways';
		$this->version               = '1.2.0';
		$this->author                = 'AneePay';
		$this->need_instance         = 0;
		$this->bootstrap             = true;
		$this->ps_versions_compliancy = array('min' => '1.7.0.0', 'max' => _PS_VERSION_);

		parent::__construct();

		$this->displayName    = $this->l('AneePay Crypto Gateway');
		$this->description    = $this->l('Accept stablecoin payments (USDT, USDC, DAI) on Polygon (live) or Amoy (testnet). Non-custodial, 0.5% fee, no KYC.');
		$this->confirmUninstall = $this->l('Are you sure you want to uninstall AneePay?');
	}

	public function install() {
		return parent::install()
			&& $this->registerHook('paymentOptions')
			&& $this->installDb()
			&& $this->installConfig();
	}

	public function uninstall() {
		return $this->uninstallDb() && parent::uninstall();
	}

	public function installDb() {
		$sql = array();

		$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'aneepay_order` (
			`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			`id_order` INT(11) UNSIGNED NOT NULL,
			`id_cart` INT(11) UNSIGNED NOT NULL DEFAULT 0,
			`payment_id` VARCHAR(64) NOT NULL DEFAULT \'\',
			`operation_id` VARCHAR(64) NOT NULL DEFAULT \'\',
			`checkout_url` TEXT NOT NULL,
			`token` VARCHAR(16) NOT NULL DEFAULT \'\',
			`network` VARCHAR(16) NOT NULL DEFAULT \'\',
			`sandbox` TINYINT(1) NOT NULL DEFAULT 0,
			`payment_status` VARCHAR(16) NOT NULL DEFAULT \'pending\',
			`token_amount` VARCHAR(32) NOT NULL DEFAULT \'\',
			`order_currency` VARCHAR(8) NOT NULL DEFAULT \'\',
			`order_total` VARCHAR(32) NOT NULL DEFAULT \'\',
			`usd_amount` VARCHAR(32) NOT NULL DEFAULT \'\',
			`fiat_per_usd` VARCHAR(32) NOT NULL DEFAULT \'\',
			`rate_source` VARCHAR(16) NOT NULL DEFAULT \'\',
			`date_added` DATETIME NOT NULL,
			PRIMARY KEY (`id`),
			KEY `id_order` (`id_order`),
			KEY `payment_id` (`payment_id`),
			KEY `operation_id` (`operation_id`)
		) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci';

		$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'aneepay_log` (
			`log_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			`context` VARCHAR(64) NOT NULL DEFAULT \'\',
			`message` TEXT NOT NULL,
			`level` VARCHAR(16) NOT NULL DEFAULT \'info\',
			`date_added` DATETIME NOT NULL,
			PRIMARY KEY (`log_id`),
			KEY `date_added` (`date_added`)
		) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci';

		foreach ($sql as $stmt) {
			if (!Db::getInstance()->execute($stmt)) {
				return false;
			}
		}

		return true;
	}

	public function uninstallDb() {
		Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'aneepay_order`');
		Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'aneepay_log`');

		return true;
	}

	public function installConfig() {
		$defaults = array(
			'ANEEPAY_ACCOUNT_ID'      => '',
			'ANEEPAY_WEBHOOK_SECRET'  => '',
			'ANEEPAY_SITE_DOMAIN'     => '',
			'ANEEPAY_TEST_MODE'       => '1',
			'ANEEPAY_TOKEN'           => 'usdt',
			'ANEEPAY_NETWORK'         => 'polygon',
			'ANEEPAY_RATE_SOURCE'     => 'auto',
			'ANEEPAY_EXCHANGE_RATE'   => '',
			'ANEEPAY_PENDING_STATE'   => (string) Configuration::get('PS_OS_CHEQUE'),
			'ANEEPAY_PAID_STATE'      => (string) Configuration::get('PS_OS_PAYMENT'),
			'ANEEPAY_FAILED_STATE'    => (string) Configuration::get('PS_OS_ERROR'),
			'ANEEPAY_CANCELLED_STATE' => (string) Configuration::get('PS_OS_CANCELED'),
		);

		foreach ($defaults as $key => $value) {
			if (!Configuration::updateValue($key, $value)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Build the API client from the current configuration.
	 *
	 * @param string $host Origin host (fallback when site_domain empty).
	 * @return AneePayApi
	 */
	public function getApi($host = '') {
		return new AneePayApi(array(
			'account_id'     => (string) Configuration::get('ANEEPAY_ACCOUNT_ID'),
			'webhook_secret' => (string) Configuration::get('ANEEPAY_WEBHOOK_SECRET'),
			'site_domain'    => (string) Configuration::get('ANEEPAY_SITE_DOMAIN'),
			'host'           => (string) $host,
			'token'          => (string) Configuration::get('ANEEPAY_TOKEN', 'usdt'),
			'network'        => (string) Configuration::get('ANEEPAY_NETWORK', 'polygon'),
			'test_mode'      => (bool) Configuration::get('ANEEPAY_TEST_MODE'),
			'rate_source'    => (string) Configuration::get('ANEEPAY_RATE_SOURCE', 'auto'),
			'exchange_rate'  => (string) Configuration::get('ANEEPAY_EXCHANGE_RATE', ''),
		), Db::getInstance());
	}

	/**
	 * Map payment statuses to PrestaShop order states.
	 *
	 * @return array
	 */
	public function getStateMap() {
		return array(
			'pending'   => (int) Configuration::get('ANEEPAY_PENDING_STATE'),
			'success'   => (int) Configuration::get('ANEEPAY_PAID_STATE'),
			'failed'    => (int) Configuration::get('ANEEPAY_FAILED_STATE'),
			'cancelled' => (int) Configuration::get('ANEEPAY_CANCELLED_STATE'),
		);
	}

	/**
	 * Store-currency units per 1 USD, resolved from the PrestaShop Currency table.
	 *
	 * @param string $currencyIso ISO-4217 code.
	 * @return float
	 */
	public function getStoreRate($currencyIso) {
		$currencyIso = strtoupper((string) $currencyIso);

		if ('USD' === $currencyIso) {
			return 1.0;
		}

		$currency = Currency::getCurrencyInstance((int) Currency::getIdByIsoCode($currencyIso));

		if (!$currency || !(float) $currency->conversion_rate) {
			return 0.0;
		}

		// conversion_rate = 1 EUR? PS stores the rate of the currency relative to
		// the default shop currency. To get USD per 1 unit, we compare with USD's rate.
		$usd = Currency::getCurrencyInstance((int) Currency::getIdByIsoCode('USD'));

		if (!$usd || !(float) $usd->conversion_rate) {
			return 0.0;
		}

		$usd_per_fiat = (float) $currency->conversion_rate / (float) $usd->conversion_rate;

		return $usd_per_fiat > 0 ? 1 / $usd_per_fiat : 0.0;
	}

	/**
	 * checkout PaymentOption.
	 *
	 * @param array $params
	 * @return array
	 */
	public function hookPaymentOptions($params) {
		if (!$this->active) {
			return array();
		}

		$links = $this->context->link;

		$option = new PaymentOption();
		$option->setModuleName($this->name)
			->setCallToActionText($this->l('Pay with crypto (USDT/USDC/DAI)'))
			->setAction($links->getModuleLink($this->name, 'payment', array(), true));

		return array($option);
	}

	/**
	 * Admin configuration page.
	 *
	 * @return string
	 */
	public function getContent() {
		$output = '';

		if (Tools::isSubmit('submitAneepay')) {
			$output .= $this->postProcess();
		}

		return $output . $this->renderForm();
	}

	protected function postProcess() {
		$output = '';

		if (Tools::isSubmit('submitAneepay')) {
			$valueMap = array(
				'ANEEPAY_ACCOUNT_ID'      => Tools::getValue('ANEEPAY_ACCOUNT_ID'),
				'ANEEPAY_WEBHOOK_SECRET'  => Tools::getValue('ANEEPAY_WEBHOOK_SECRET'),
				'ANEEPAY_SITE_DOMAIN'     => Tools::getValue('ANEEPAY_SITE_DOMAIN'),
				'ANEEPAY_TOKEN'           => Tools::getValue('ANEEPAY_TOKEN'),
				'ANEEPAY_NETWORK'         => Tools::getValue('ANEEPAY_NETWORK'),
				'ANEEPAY_RATE_SOURCE'     => Tools::getValue('ANEEPAY_RATE_SOURCE'),
				'ANEEPAY_EXCHANGE_RATE'   => Tools::getValue('ANEEPAY_EXCHANGE_RATE'),
			);

			// Empty webhook secret on re-save keeps the previously stored value.
			$secret = (string) $valueMap['ANEEPAY_WEBHOOK_SECRET'];
			if ('' === $secret) {
				$valueMap['ANEEPAY_WEBHOOK_SECRET'] = (string) Configuration::get('ANEEPAY_WEBHOOK_SECRET');
			}

			$valueMap['ANEEPAY_TEST_MODE'] = Tools::getValue('ANEEPAY_TEST_MODE') ? '1' : '0';

			foreach ($valueMap as $key => $value) {
				Configuration::updateValue($key, $value);
			}

			return $this->displayConfirmation($this->l('Settings updated.'));
		}

		return $output;
	}

	protected function renderForm() {
		$fields = array();

		// Build the form using the PrestaShop 1.7 helper form structure.
		$fields[0]['form'] = array(
			'legend' => array('title' => $this->l('Connection')),
			'input'  => array(
				array(
					'type'     => 'text',
					'label'    => $this->l('Account ID'),
					'name'     => 'ANEEPAY_ACCOUNT_ID',
					'desc'     => $this->l('Your AneePay account UUID. Sent in the X-Account-Id header.'),
					'required' => true,
				),
				array(
					'type'     => 'password',
					'label'    => $this->l('Webhook Secret'),
					'name'     => 'ANEEPAY_WEBHOOK_SECRET',
					'desc'     => $this->l('Used to verify the X-AneePay-Signature (HMAC-SHA256).'),
				),
				array(
					'type'     => 'text',
					'label'    => $this->l('Domain'),
					'name'     => 'ANEEPAY_SITE_DOMAIN',
					'desc'     => $this->l('Your verified merchant domain (Origin header). Empty = use your shop host.'),
				),
				array(
					'type'     => 'switch',
					'label'    => $this->l('Test / Sandbox Mode'),
					'name'     => 'ANEEPAY_TEST_MODE',
					'is_bool'  => true,
					'values'   => array(
						array('id' => 'active_on', 'value' => 1, 'label' => $this->l('Enabled')),
						array('id' => 'active_off', 'value' => 0, 'label' => $this->l('Disabled')),
					),
					'desc'     => $this->l('When enabled, payments are created on the Amoy testnet with USDC.'),
				),
			),
			'submit' => array('title' => $this->l('Save')),
		);

		$fields[1]['form'] = array(
			'legend' => array('title' => $this->l('Coin & Blockchain')),
			'input'  => array(
				array(
					'type'     => 'select',
					'label'    => $this->l('Cryptocurrency'),
					'name'     => 'ANEEPAY_TOKEN',
					'options'  => array(
						'query' => array(
							array('id' => 'usdt', 'name' => 'USDT (Tether)'),
							array('id' => 'usdc', 'name' => 'USDC (USD Coin)'),
							array('id' => 'dai',  'name' => 'DAI'),
						),
						'id'   => 'id',
						'name' => 'name',
					),
				),
				array(
					'type'     => 'select',
					'label'    => $this->l('Blockchain'),
					'name'     => 'ANEEPAY_NETWORK',
					'options'  => array(
						'query' => array(
							array('id' => 'polygon', 'name' => 'Polygon (Mainnet)'),
							array('id' => 'amoy',    'name' => 'Amoy (Testnet)'),
						),
						'id'   => 'id',
						'name' => 'name',
					),
				),
				array(
					'type'     => 'select',
					'label'    => $this->l('Exchange Rate Source'),
					'name'     => 'ANEEPAY_RATE_SOURCE',
					'options'  => array(
						'query' => array(
							array('id' => 'auto',   'name' => 'Auto (Frankfurter/ECB) + manual fallback'),
							array('id' => 'manual', 'name' => 'Manual rate only'),
						),
						'id'   => 'id',
						'name' => 'name',
					),
				),
				array(
					'type' => 'text',
					'label' => $this->l('Manual Exchange Rate'),
					'name'  => 'ANEEPAY_EXCHANGE_RATE',
					'desc'  => $this->l('1 token (≈1 USD) = X store currency. Used when the auto rate is unavailable.'),
				),
			),
			'submit' => array('title' => $this->l('Save')),
		);

		$helper = new HelperForm();
		$helper->module = $this;
		$helper->name_controller = 'ps_aneepay';
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->currentIndex = AdminController::$currentIndex;
		$helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');
		$helper->allow_employee_form_lang = (int) Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
		$helper->title = $this->displayName;
		$helper->submit_action = 'submitAneepay';

		$helper->fields_value = array(
			'ANEEPAY_ACCOUNT_ID'     => Tools::getValue('ANEEPAY_ACCOUNT_ID', (string) Configuration::get('ANEEPAY_ACCOUNT_ID')),
			'ANEEPAY_TEST_MODE'      => (bool) Configuration::get('ANEEPAY_TEST_MODE'),
			'ANEEPAY_TOKEN'          => (string) Configuration::get('ANEEPAY_TOKEN', 'usdt'),
			'ANEEPAY_NETWORK'        => (string) Configuration::get('ANEEPAY_NETWORK', 'polygon'),
			'ANEEPAY_RATE_SOURCE'    => (string) Configuration::get('ANEEPAY_RATE_SOURCE', 'auto'),
			'ANEEPAY_EXCHANGE_RATE'  => (string) Configuration::get('ANEEPAY_EXCHANGE_RATE', ''),
			'ANEEPAY_WEBHOOK_SECRET' => '',
		);

		$output = $helper->generateForm($fields);

		// Endpoints + connection hint.
		$output .= '<div class="panel"><div class="panel-heading"><i class="icon-cogs"></i> ' . $this->l('Set these URLs in the AneePay account panel:') . '</div><div class="panel-body">';
		$output .= '<p><strong>' . $this->l('STATUS_URL (webhook)') . '</strong><br><code>' . $this->moduleLink('webhook') . '</code></p>';
		$output .= '<p><strong>' . $this->l('SUCCESS_URL') . '</strong><br><code>' . $this->moduleLink('success') . '</code></p>';
		$output .= '<p><strong>' . $this->l('FAIL_URL') . '</strong><br><code>' . $this->moduleLink('fail') . '</code></p>';
		$output .= '<p><strong>' . $this->l('Cron URL') . '</strong><br><code>' . $this->moduleLink('cron') . '</code></p>';
		$output .= '</div></div>';

		return $output;
	}

	/**
	 * Full storefront URL to a front controller of this module.
	 *
	 * @param string $controller Controller name.
	 * @return string
	 */
	public function moduleLink($controller) {
		return $this->context->link->getModuleLink($this->name, $controller, array(), true);
	}

	/**
	 * Data for the success/fail result page.
	 *
	 * @param bool $is_success Whether it is the success page.
	 * @return array
	 */
	public function resultData($is_success) {
		$order_id = (int) $this->context->cookie->aneepay_order_id;

		$data = array(
			'is_success'   => (bool) $is_success,
			'order_id'     => $order_id,
			'token'        => '',
			'payment_id'   => '',
			'tx_hash'      => '',
			'checkout_url' => '',
			'retry_url'    => $this->moduleLink('payment'),
			'check_url'    => $this->moduleLink('check'),
			'shop_url'     => $this->context->link->getPageLink('index'),
			'pay_now'      => '',
		);

		if ($order_id) {
			$order = new Order((int) $order_id);

			if (Validate::isLoadedObject($order)) {
				$data['order_total'] = (string) $order->total_paid;
				$data['currency']    = (string) (new Currency((int) $order->id_currency))->iso_code;
			}

			$row = (new AneePayOrder())->getByOrder((int) $order_id);

			if ($row) {
				$data['token']        = strtoupper((string) $row['token']);
				$data['payment_id']   = (string) $row['payment_id'];
				$data['checkout_url'] = (string) $row['checkout_url'];
			}
		}

		return $data;
	}
}
