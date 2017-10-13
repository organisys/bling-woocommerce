<?php
/**
 * Plugin Name: Bling WooCommerce
 * Plugin URI: https://github.com/organisys/bling-woocommerce
 * Description: The Bling is an online system that allows you to control the finances, inventory and issue invoices quickly and uncomplicated..
 * Author: Bling, Agência resultate
 * Author URI: http://bling.com.br/
 * Version: 1.0.5
 * License: GPLv2 or later
 * Text Domain: bling-woocommerce
 * Domain Path: /languages/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'WC_Bling' ) ) :
/* Set constant path to the plugin directory. */
define( 'BLING_WOOCOMMERCE_DIR', trailingslashit( plugin_dir_path( __FILE__ ) ) );
/**
 * WooCommerce Bling main class.
 */
class WC_Bling {

	/**
	 * Plugin version.
	 *
	 * @var string
	 */
	const VERSION = '1.0.5';

	/**
	 * Instance of this class.
	 *
	 * @var object
	 */
	protected static $instance = null;

	/**
	 * Initialize the plugin public actions.
	 */
	private function __construct() {

		// Load plugin text domain
		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

		// Checks with WooCommerce and WooCommerce Extra Checkout Fields for Brazil is installed.
		if ( class_exists( 'WC_Integration' ) && class_exists( 'Extra_Checkout_Fields_For_Brazil' ) ) {
			$this->includes();

			add_filter( 'woocommerce_integrations', array( $this, 'add_integration' ) );
		} else {
			add_action( 'admin_notices', array( $this, 'dependencies_notice' ) );
		}
	}

	/**
	 * Return an instance of this class.
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
	 * Load the plugin text domain for translation.
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
	 * Includes.
	 *
	 * @return void
	 */
	private function includes() {
		include_once 'admin/class-wc-bling-settings.php';

		include_once 'includes/interface-wc-bling-integracao.php';
		include_once 'includes/class-wc-bling-simplexml.php';
		include_once 'includes/class-wc-bling-helper.php';
		include_once 'includes/class-abstract-wc-bling-base-order.php';
		include_once 'includes/class-wc-bling-order.php';
		include_once 'includes/class-wc-bling-nfe.php';
		include_once 'includes/class-wc-bling-nfce.php';
		include_once 'includes/class-wc-bling-product.php';
		include_once 'includes/class-wc-bling-api.php';
		include_once 'includes/class-wc-bling-integration.php';
	}

	/**
	 * Add the Bling integration to WooCommerce.
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

add_action( 'plugins_loaded', array( 'WC_Bling', 'get_instance' ) );

endif;
