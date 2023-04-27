<?php

/**
 * Database transactions class
 */
class Woocommerce_Yow_Db {

	/**
	 * Transactions table name
	 *
	 * @var string
	 */
	const TABLE_NAME = 'yow_transactions';

	/**
	 * Create table for transactions
	 *
	 * @return void
	 */
	public function createTables() {
		global $wpdb;
		$tableName = self::getTableName();
		$charsetCollate = $wpdb->get_charset_collate();
		$sql = "CREATE TABLE IF NOT EXISTS $tableName (
			id int NOT NULL AUTO_INCREMENT,
			order_id INT NOT NULL,
			transaction_id INT NOT NULL,
			transaction_code TINYTEXT,
			price DECIMAL(13,2) NOT NULL,
			currency TINYTEXT NOT NULL,
			status TINYINT NOT NULL,
			created_at DATETIME DEFAULT '0000-00-00 00:00:00' NOT NULL,
			updated_at DATETIME,
			paid DECIMAL(13,2) NOT NULL,
			paid_currency TINYTEXT NOT NULL,
			sender_iban TINYTEXT,
			sender_swift TINYTEXT,
			sender_account_holder TINYTEXT,
			PRIMARY KEY  (id),
			KEY order_id (order_id),
			KEY transaction_id (transaction_id),
			KEY transaction_code (transaction_code(8))
		) $charsetCollate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta($sql);
	}

	/**
	 * Return transaction by order id
	 *
	 * @param $orderId
	 * @return array|object|stdClass[]|null
	 */
	public static function getTransactionsByOrderId( $orderId ) {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				// we need to hardcode table name otherwise here will be sniffer problem
				'SELECT * FROM wp_yow_transactions WHERE order_id = %d',
				$orderId
			),
			ARRAY_A
		);
	}

	/**
	 * Add a transaction to database
	 *
	 * @param array $data Transaction's fields
	 * @return bool
	 */
	public static function insertTransaction( array $data ) {
		global $wpdb;
		if ($wpdb->insert(self::getTableName(), $data) !== false) {
			return true;
		}
		addErrorLog('Transaction with data: ' . json_encode($data) . ' didn\'t save to database');
		return false;
	}

	/**
	 * Update a transaction in database
	 *
	 * @param array $data Transaction's fields
	 * @param array $where conditions for transaction update
	 * @return bool
	 */
	public static function updateTransaction( array $data, array $where ) {
		global $wpdb;
		if ($wpdb->update(self::getTableName(), $data, $where) !== false) {
			return true;
		}
		addErrorLog('Transaction didn\'t update by data: ' . json_encode($data));
		return false;
	}

	/**
	 * Return Table name
	 *
	 * @return string
	 */
	private static function getTableName() {
		// we use 'wp_' to solve sniffer problem
		return 'wp_' . self::TABLE_NAME;
	}
}
