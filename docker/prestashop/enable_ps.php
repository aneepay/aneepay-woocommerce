<?php
/**
 * Ensure the AneePay module is fully wired for the PrestaShop stand:
 *  - creates the aneepay tables
 *  - registers the paymentOptions hook
 *  - sets module config defaults
 *  - activates the module
 *
 * DB-direct (no Module::install()) to avoid CLI Context issues. Idempotent —
 * safe to run repeatedly.
 */
if (!file_exists('/var/www/html/config/config.inc.php')) {
	exit(1);
}

require_once('/var/www/html/config/config.inc.php');

$db = Db::getInstance();

// Tables (matching the module install()).
$db->execute("CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "aneepay_order` (
	`id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
	`id_order` INT(11) UNSIGNED NOT NULL,
	`id_cart` INT(11) UNSIGNED NOT NULL DEFAULT 0,
	`payment_id` VARCHAR(64) NOT NULL DEFAULT '',
	`operation_id` VARCHAR(64) NOT NULL DEFAULT '',
	`checkout_url` TEXT NOT NULL,
	`token` VARCHAR(16) NOT NULL DEFAULT '',
	`network` VARCHAR(16) NOT NULL DEFAULT '',
	`sandbox` TINYINT(1) NOT NULL DEFAULT 0,
	`payment_status` VARCHAR(16) NOT NULL DEFAULT 'pending',
	`token_amount` VARCHAR(32) NOT NULL DEFAULT '',
	`order_currency` VARCHAR(8) NOT NULL DEFAULT '',
	`order_total` VARCHAR(32) NOT NULL DEFAULT '',
	`usd_amount` VARCHAR(32) NOT NULL DEFAULT '',
	`fiat_per_usd` VARCHAR(32) NOT NULL DEFAULT '',
	`rate_source` VARCHAR(16) NOT NULL DEFAULT '',
	`date_added` DATETIME NOT NULL,
	PRIMARY KEY (`id`),
	KEY `id_order` (`id_order`),
	KEY `payment_id` (`payment_id`),
	KEY `operation_id` (`operation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

$db->execute("CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "aneepay_log` (
	`log_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
	`context` VARCHAR(64) NOT NULL DEFAULT '',
	`message` TEXT NOT NULL,
	`level` VARCHAR(16) NOT NULL DEFAULT 'info',
	`date_added` DATETIME NOT NULL,
	PRIMARY KEY (`log_id`),
	KEY `date_added` (`date_added`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Register the paymentOptions hook (idempotent).
$hook_id  = (int) $db->getValue("SELECT `id_hook` FROM `" . _DB_PREFIX_ . "hook` WHERE `name` = 'paymentOptions'");
$mod_id   = (int) $db->getValue("SELECT `id_module` FROM `" . _DB_PREFIX_ . "module` WHERE `name` = 'ps_aneepay'");

if ($hook_id && $mod_id) {
	$exists = (int) $db->getValue(
		"SELECT COUNT(*) FROM `" . _DB_PREFIX_ . "hook_module`
		 WHERE `id_hook` = " . $hook_id . " AND `id_module` = " . $mod_id . " AND `id_shop` = 1"
	);

	if (!$exists) {
		$db->insert('hook_module', array(
			'id_hook'   => (int) $hook_id,
			'id_module' => (int) $mod_id,
			'id_shop'   => 1,
			'position'  => 0,
		));
	}
}

// Config defaults.
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
	Configuration::updateValue($key, $value);
}

// Ensure the module is active.
$db->execute("UPDATE `" . _DB_PREFIX_ . "module` SET `active` = 1 WHERE `name` = 'ps_aneepay'");

echo "AneePay wired: tables, hook, config, active.\n";
