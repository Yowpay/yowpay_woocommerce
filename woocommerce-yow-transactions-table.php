<?php

/**
 * Class for transactions' page
 */
class Woocommerce_Yow_Transactions_Table
{
    /**
     * @var int
     */
    const STATUS_PENDING = 1;
    /**
     * @var int
     */
    const STATUS_APPROVED = 2;
    /**
     * @var int
     */
    const STATUS_PARTIALLY_PAID = 3;
    /**
     * @var int
     */
    const STATUS_OVERPAID = 4;
}