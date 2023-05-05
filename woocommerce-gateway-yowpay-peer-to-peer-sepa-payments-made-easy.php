<?php

/*
 * Plugin Name: YowPay Payment - WooCommerce Yow Payment Gateway
 * Plugin URI: https://yowpay.com/
 * Description: WooCommerce Yow custom payment gateway integration.
 * Version: 1.0.0
 * Author: YowPay
 * Author URI: https://yowpay.com/
 * Text Domain: woocommerce-yow-payment
 * Domain Path: /languages
 * WC requires at least: 7.1
 * WC tested up to: 7.3.0
*/

use Automattic\Jetpack\Constants;

define('WC_YOW_ASSETS', plugin_dir_url(__FILE__) . 'assets/');

// update db
register_activation_hook( __FILE__, 'woocommerceYowInitActivate' );
include_once('woocommerce-yow-db.php');
function woocommerceYowInitActivate() {
	$db = new Woocommerce_Yow_Db();
	$db->createTables();
}

add_action('plugins_loaded', 'woocommerceYowInit', 0);
function woocommerceYowInit() {
	//if condition use to do nothing while WooCommerce is not installed
	if (!class_exists('WC_Payment_Gateway')) {
		addErrorLog('Woocommerce Plugin is required');
		return;
	}

	// init translations
	load_plugin_textdomain(
		'woocommerce-yow-payment',
		false,
		dirname(plugin_basename( __FILE__ )) . '/languages'
	);

	// register payment
	include_once('woocommerce-yow.php');
	add_filter('woocommerce_payment_gateways', 'addWoocommerceYow');
	function addWoocommerceYow( $methods ) {
		$methods[] = Woocommerce_Yow::class;
		return $methods;
	}

	// add styles
	add_action('admin_enqueue_scripts', 'woocommerceYowAdminStyles');
	function woocommerceYowAdminStyles() {
		wp_enqueue_style(
			'woocommerce-yow-css',
			WC_YOW_ASSETS . '/css/admin_main.css',
			array(),
			Constants::get_constant('WC_VERSION')
		);
	}

	add_action('wp_enqueue_scripts', 'woocommerceYowFrontStyles');
	function woocommerceYowFrontStyles() {
		wp_enqueue_style(
			'woocommerce-yow-css',
			WC_YOW_ASSETS . '/css/front_main.css',
			array(),
			Constants::get_constant('WC_VERSION')
		);
	}

	// add endpoint for webhook
	include_once('woocommerce-yow-webhook.php');
	Woocommerce_Yow_Webhook::register_routs();

	// add transactions page
	include_once('woocommerce-yow-transactions-table.php');
}

// succeed page
register_activation_hook( __FILE__, 'woocommerceYowCreatePage');
function woocommerceYowCreatePage() {
	include_once('woocommerce-yow.php');
	Woocommerce_Yow::createSuccessPage();
}

// Add custom action links
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'woocommerceYowActionLinks');
function woocommerceYowActionLinks( array $links ) {
	$pluginLinks = [
		'<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">' . __('Settings', 'woocommerce-yow-payment') . '</a>',
	];
	return array_merge($pluginLinks, $links);
}

function addErrorLog( $msg ) {
	$msg = gmdate('[Y-m-d H:i:s]') . ' WARNING: ' . $msg . PHP_EOL;
	$path = ABSPATH . 'wp-content/yowpay_logs.log';
	error_log($msg, 3, $path );
}
