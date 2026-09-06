<?php
/**
 * Enable the AneePay payment method for the OpenCart stand and create its tables.
 * Runs in the `setup` container.
 */
$db = mysqli_connect(getenv('DB_HOST'), getenv('DB_USER'), getenv('DB_PASS'), getenv('DB_NAME'), (int) getenv('DB_PORT'));

if (!$db) {
	fwrite(STDERR, 'DB connection failed: ' . mysqli_connect_error() . "\n");
	exit(1);
}

$prefix = 'oc_';

function q($db, $sql) {
	if (!mysqli_query($db, $sql)) {
		fwrite(STDERR, 'SQL error: ' . mysqli_error($db) . "\n  in: " . $sql . "\n");
		exit(1);
	}
}

// Tables created by the extension's install() (admin model).
q($db, "CREATE TABLE IF NOT EXISTS `{$prefix}aneepay_order` (
	`id` INT(11) NOT NULL AUTO_INCREMENT,
	`order_id` INT(11) NOT NULL,
	`store_id` INT(11) NOT NULL DEFAULT 0,
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
	KEY `order_id` (`order_id`),
	KEY `payment_id` (`payment_id`),
	KEY `operation_id` (`operation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

q($db, "CREATE TABLE IF NOT EXISTS `{$prefix}aneepay_log` (
	`log_id` INT(11) NOT NULL AUTO_INCREMENT,
	`context` VARCHAR(64) NOT NULL DEFAULT '',
	`message` TEXT NOT NULL,
	`level` VARCHAR(16) NOT NULL DEFAULT 'info',
	`date_added` DATETIME NOT NULL,
	PRIMARY KEY (`log_id`),
	KEY `date_added` (`date_added`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

// Register the extension (oc_extension: type='payment', code='aneepay').
$r = mysqli_query($db, "SELECT COUNT(*) AS c FROM `{$prefix}extension` WHERE `type` = 'payment' AND `code` = 'aneepay'");
$row = $r ? mysqli_fetch_assoc($r) : array('c' => 0);

if (empty($row['c'])) {
	q($db, "INSERT INTO `{$prefix}extension` (`type`, `code`) VALUES ('payment', 'aneepay')");
}

// Store-level defaults so the method is enabled out of the box.
$defaults = array(
	'payment_aneepay_status'      => '1',
	'payment_aneepay_title'       => 'Pay with Crypto (USDT/USDC/DAI)',
	'payment_aneepay_description' => 'Pay instantly with a stablecoin on the Polygon network.',
	'payment_aneepay_token'       => 'usdt',
	'payment_aneepay_network'     => 'polygon',
	'payment_aneepay_rate_source' => 'auto',
	'payment_aneepay_test_mode'   => '1',
	'payment_aneepay_pending_status_id'   => '1',
	'payment_aneepay_paid_status_id'      => '2',
	'payment_aneepay_failed_status_id'    => '8',
	'payment_aneepay_cancelled_status_id' => '7',
);

foreach ($defaults as $key => $value) {
	q($db, "DELETE FROM `{$prefix}setting` WHERE `code` = 'payment_aneepay' AND `key` = '" . $key . "'");
	q($db, "INSERT INTO `{$prefix}setting` (`store_id`, `code`, `key`, `value`, `serialized`) VALUES (0, 'payment_aneepay', '" . $key . "', '" . $value . "', 0)");
}

echo "AneePay enabled.\n";
