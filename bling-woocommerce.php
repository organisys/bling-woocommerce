<?php
/**
 * Plugin Name: Bling WooCommerce
 * Plugin URI: https://github.com/organisys/bling-woocommerce
 * Description: O Bling é um sistema online que permite a você controlar as finanças, estoques e emitir notas fiscais de maneira rápida e descomplicada.
 * Author: Bling
 * Author URI: http://bling.com.br/
 * Version: 1.0.0
 * License: GPLv2 or later
 * Text Domain: bling-woocommerce
 * Domain Path: /languages/
 */

define( 'WOO_BLING_PATH', plugin_dir_path( __FILE__ ) );
define( 'WOO_BLING_URL', plugin_dir_url( __FILE__ ) );

/**
 * WooCommerce fallback notice.
 */
function wcbling_woocommerce_fallback_notice() {
    echo '<div class="error"><p>' . sprintf( __( 'Bling WooCommerce depends on the last version of %s to work!', 'bling-woocommerce' ), '<a href="http://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a>' ) . '</p></div>';
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
     * @return array               Payment methods with PagSeguro.
     */
    function wcbling_add_integration( $integrations ) {
        $integrations[] = 'WC_Bling_Integration';

        return $integrations;
    }

    add_filter( 'woocommerce_integrations', 'wcbling_add_integration' );

    // Include the Bling classes.
    require_once WOO_BLING_PATH . 'includes/class-wc-bling-simplexml.php';
    require_once WOO_BLING_PATH . 'includes/class-wc-bling-integration.php';
}

add_action( 'plugins_loaded', 'wcbling_gateway_load', 0 );

/**
 * Adds custom settings url in plugins page.
 *
 * @param  array $links Default links.
 *
 * @return array        Default links and settings link.
 */
function wcbling_action_links( $links ) {

    $settings = array(
        'settings' => sprintf(
            '<a href="%s">%s</a>',
            admin_url( 'admin.php?page=woocommerce_settings&tab=integration&section=bling' ),
            __( 'Settings', 'bling-woocommerce' )
        )
    );

    return array_merge( $settings, $links );
}

add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wcbling_action_links' );
