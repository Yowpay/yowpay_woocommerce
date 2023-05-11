<?php

// if uninstall.php is not called by WordPress, die
if (!defined('WP_UNINSTALL_PLUGIN')) {
	die;
}

$optionName = 'woocommerce_woocommerce_yow_settings';
$settings = get_option($optionName, null);
if ($settings && isset($settings['delete_data_after_uninstall']) && 'yes' == $settings['delete_data_after_uninstall']) {
	include_once('woocommerce-yow.php');
	include_once('woocommerce-yow-db.php');

	// delete plugin options
	delete_option($optionName);

	// delete success page
	Woocommerce_Yow::deleteSuccessPage();

	// delete tables
	$db = new Woocommerce_Yow_Db();
	$db->deleteTables();
}
