<?php
/**
 * Plugin Name: Bling WooCommerce
 * Plugin URI: https://github.com/organisys/bling-woocommerce
 * Description: The Bling is an online system that allows you to control the finances, inventory and issue invoices quickly and uncomplicated..
 * Author: Bling
 * Author URI: http://bling.com.br/
 * Version: 1.0.0
 * License: GPLv2 or later
 * Text Domain: bling-woocommerce
 * Domain Path: /languages/
 */

/**
 * WooCommerce fallback notice.
 */
function wcbling_woocommerce_fallback_notice() {
	echo '<div class="error"><p>' . sprintf( __( 'Bling WooCommerce depends on the last version of %s to work!', 'bling-woocommerce' ), '<a href="http://wordpress.org/extend/plugins/woocommerce/">' . __( 'WooCommerce', 'bling-woocommerce' ) . '</a>' ) . '</p></div>';
}

/**
 * Adds custom settings url in plugins page.
 *
 * @param  array $links Default links.
 *
 * @return array        Default links and settings link.
 */
function wcbling_action_links( $links ) {
	global $woocommerce;

	if ( version_compare( $woocommerce->version, '2.1', '>=' ) ) {
		$admin_url = admin_url( 'admin.php?page=wc-settings&tab=integration&section=bling' );
	} else {
		$admin_url = admin_url( 'admin.php?page=woocommerce_settings&tab=integration&section=bling' );
	}

	$settings = array(
		'settings' => sprintf(
			'<a href="%s">%s</a>',
			$admin_url,
			__( 'Settings', 'bling-woocommerce' )
		)
	);

	return array_merge( $settings, $links );
}

/**
 * Load functions.
 */
function wcbling_gateway_load() {

	// Checks with WooCommerce is installed.
	if ( ! class_exists( 'WC_Integration' ) ) {
		add_action( 'admin_notices', 'wcbling_woocommerce_fallback_notice' );

		return;
	}

	/**
	 * Load textdomain.
	 */
	load_plugin_textdomain( 'bling-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

	/**
	 * Add a new integration to WooCommerce.
	 *
	 * @param  array $integrations WooCommerce payment methods.
	 *
	 * @return array               Payment methods with Bling.
	 */
	function wcbling_add_integration( $integrations ) {
		$integrations[] = 'WC_Bling_Integration';

		return $integrations;
	}

	add_filter( 'woocommerce_integrations', 'wcbling_add_integration' );

	// Include the Bling classes.
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wc-bling-integration.php';

	if ( is_admin() ) {
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wcbling_action_links' );
	}
}

add_action( 'plugins_loaded', 'wcbling_gateway_load', 0 );
