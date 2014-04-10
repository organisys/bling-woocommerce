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

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WC_Bling' ) ) :

/**
 * WooCommerce Bling main class.
 */
class WC_Bling {

	/**
	 * Plugin version.
	 *
	 * @since 1.0.0
	 *
	 * @var   string
	 */
	const VERSION = '1.0.0';

	/**
	 * Integration id.
	 *
	 * @since 1.0.0
	 *
	 * @var   string
	 */
	protected static $gateway_id = 'bling';

	/**
	 * Instance of this class.
	 *
	 * @since 1.0.0
	 *
	 * @var   object
	 */
	protected static $instance = null;

	/**
	 * Initialize the plugin public actions.
	 *
	 * @since  1.0.0
	 */
	private function __construct() {
		// Load plugin text domain
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

		// Checks with WooCommerce and WooCommerce Extra Checkout Fields for Brazil is installed.
		if ( class_exists( 'WC_Integration' ) && class_exists( 'Extra_Checkout_Fields_For_Brazil' ) ) {
			// Include the WC_Bling_Integration class.
			include_once 'includes/class-wc-bling-integration.php';

			add_filter( 'woocommerce_integrations', array( $this, 'add_integration' ) );
		} else {
			add_action( 'admin_notices', array( $this, 'dependencies_notice' ) );
		}
	}

	/**
	 * Return an instance of this class.
	 *
	 * @since  1.0.0
	 *
	 * @return object A single instance of this class.
	 */
	public static function get_instance() {
		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Return the gateway id/slug.
	 *
	 * @since  1.0.0
	 *
	 * @return string Gateway id/slug variable.
	 */
	public static function get_gateway_id() {
		return self::$gateway_id;
	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since  1.0.0
	 *
	 * @return void
	 */
	public function load_plugin_textdomain() {
		$domain = 'bling-woocommerce';
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );

		load_textdomain( $domain, trailingslashit( WP_LANG_DIR ) . $domain . '/' . $domain . '-' . $locale . '.mo' );
		load_plugin_textdomain( $domain, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Add the Bling integration to WooCommerce.
	 *
	 * @since  1.0.0
	 *
	 * @param  array $methods WooCommerce integrations.
	 *
	 * @return array          Bling integration.
	 */
	public function add_integration( $methods ) {
		$methods[] = 'WC_Bling_Integration';

		return $methods;
	}

	/**
	 * Dependencies notice.
	 *
	 * @since  1.0.0
	 *
	 * @return string
	 */
	public function dependencies_notice() {
		echo '<div class="error"><p>' . sprintf(
			__( 'Bling WooCommerce depends on the last version of the %s and the %s to work!', 'bling-woocommerce' ),
			'<a href="http://wordpress.org/extend/plugins/woocommerce/">' . __( 'WooCommerce', 'bling-woocommerce' ) . '</a>',
			'<a href="http://wordpress.org/plugins/woocommerce-extra-checkout-fields-for-brazil/">' . __( 'WooCommerce Extra Checkout Fields for Brazil', 'bling-woocommerce' ) . '</a>'
		) . '</p></div>';
	}
}

add_action( 'plugins_loaded', array( 'WC_Bling', 'get_instance' ), 0 );

endif;
