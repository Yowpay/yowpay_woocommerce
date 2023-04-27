<?php

/**
 * Class for transactions' page
 */
class Woocommerce_Yow_Transactions_Table {
	/**
	 * Pending
	 *
	 * @var int
	 */
	const STATUS_PENDING = 1;

	/**
	 * Approved
	 *
	 * @var int
	 */
	const STATUS_APPROVED = 2;

	/**
	 * Partially paid
	 *
	 * @var int
	 */
	const STATUS_PARTIALLY_PAID = 3;

	/**
	 * Overpaid
	 *
	 * @var int
	 */
	const STATUS_OVERPAID = 4;
}
