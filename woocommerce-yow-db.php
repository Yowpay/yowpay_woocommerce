<?php

/**
 * Database transactions class
 */
class Woocommerce_Yow_Db {

    /**
     * @var string
     */
    const TABLE_NAME = 'yow_transactions';

    /**
     * Create table for transactions
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
     * Returns transactions for transactions' page
     *
     * @param int $status
     * @param string $search
     * @return array|object|stdClass[]|null
     */
    public static function getTransactionData(int $status, string $search)
    {
        global $wpdb;
        $tableName = self::getTableName();

        if ($search) {
            if (is_numeric($search)) {
                $sql = "SELECT * FROM $tableName 
                            WHERE (order_id = %d
                               OR transaction_id = %d
                               OR price = %f
                               OR paid = %f
                               OR transaction_code LIKE %s
                               OR sender_iban LIKE %s
                               OR sender_swift LIKE %s
                               OR sender_account_holder LIKE %s)";
                $args = array_merge(
                    array_fill(0, 2, $search),
                    array_fill(0, 2, (float)$search),
                    array_fill(0, 4, '%' . $search . '%')
                );
            } else {
                $sql = "SELECT * FROM $tableName 
                            WHERE (transaction_code LIKE %s
                               OR sender_iban LIKE %s
                               OR sender_swift LIKE %s
                               OR sender_account_holder LIKE %s)";
                $args = array_fill(0, 4, '%' . $search . '%');
            }

            if ($status > 0) {
                $sql .= " AND status = %d";
                $args = array_merge($args, [$status]);
            }

            $query = $wpdb->prepare($sql, $args);
        } else {
            $query = "SELECT * FROM $tableName";
            if ($status > 0) {
                $sql = $query . " WHERE status = %d";
                $query = $wpdb->prepare($sql, [$status]);
            }
        }

        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * @param $orderId
     * @return array|object|stdClass[]|null
     */
    public static function getTransactionsByOrderId($orderId)
    {
        global $wpdb;
        $tableName = self::getTableName();

        $sql = "SELECT * FROM $tableName WHERE order_id = %d";
        $query = $wpdb->prepare($sql, [$orderId]);
        return $wpdb->get_results($query, ARRAY_A);
    }

    /**
     * Add a transaction to database
     * @param array $data Transaction's fields
     * @return bool
     */
    public static function insertTransaction(array $data): bool
    {
        global $wpdb;
        if ($wpdb->insert(self::getTableName(), $data) !== false) {
            return true;
        }
        addErrorLog("Transaction with data: " . json_encode($data) . " didn't save to database");
        return false;
    }

    /**
     * Update a transaction in database
     * @param array $data Transaction's fields
     * @param array $where conditions for transaction update
     * @return bool
     */
    public static function updateTransaction(array $data, array $where): bool
    {
        global $wpdb;
        if ($wpdb->update(self::getTableName(), $data, $where) !== false) {
            return true;
        }
        addErrorLog("Transaction didn't update by data: " . json_encode($data));
        return false;
    }

    /**
     * Return array with count of transactions group by statuses.
     *
     * @return array
     */
    public static function getCountTransactions(): array
    {
        global $wpdb;
        $tableName = self::getTableName();

        $query = "SELECT status, COUNT(*) AS cnt FROM $tableName GROUP BY status";
        $count = [];
        foreach ($wpdb->get_results($query, ARRAY_A) as $row) {
            $count[(int)$row['status']] = $row['cnt'];
        }
        ksort($count);
        return $count;
    }

    /**
     * Return Table name
     *
     * @return string
     */
    private static function getTableName(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }
}