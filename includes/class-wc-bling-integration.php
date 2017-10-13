<?php
/**
 * WC Bling Integration Class.
 *
 * Create integration with the Bling.
 */
class WC_Bling_Integration extends WC_Integration {

	/**
	 * Init and hook in the integration.
	 *
	 * @return void
	 */
	public function __construct() {
		global $woocommerce;
		$this->settings = new WC_Bling_Settings();
		$this->id                 = 'bling';
		$this->method_title       = __( 'Bling', 'bling-woocommerce' );
		$this->method_description = __( 'O Bling é um software de gestão empresarial, ERP para a micro e pequena empresa.', 'bling-woocommerce' );
		// Load the settings.
		$this->form_fields = $this->settings->get_fields();
		$this->init_settings();
		// Define user set variables.
		define( 'BLING_WOOCOMMERCE_DEBUG', $this->get_option( 'debug' ) );
		$this->access_key  = $this->get_option( 'access_key' );
		$this->sendnfe     = $this->get_option( 'sendnfe' );
		$this->transmitnfe = $this->get_option( 'transmitnfe' );
		$this->sendnfce    = $this->get_option( 'sendnfce' );
		$this->transmitnfce = $this->get_option( 'transmitnfce' );
		$this->api = new WC_Bling_API( $this->access_key );
		$this->add_wc_bling_filters_callback();
		$this->add_wc_bling_actions_callback();
	}

	/**
	 * Add methods as callback to woocommerce filters
	 *
	 * @return void
	 */
	private function add_wc_bling_filters_callback() {
		//Filters
		add_filter( 'woocommerce_order_actions', array( $this, 'order_action' ) );
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_shop_order_bling_columns' ) );
	}

	/**
	 * Add methods as callback to woocommerce actions
	 *
	 * @return void
	 */
	private function add_wc_bling_actions_callback() {
		// Actions.
		add_action( 'woocommerce_update_options_integration_bling', array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'process_order' ) );
		add_action( 'woocommerce_order_action_bling_nfce', array( $this, 'sync_nfce' ) );
		add_action( 'woocommerce_order_action_bling_nfe', array( $this, 'sync_nfe' ) );
		add_action( 'woocommerce_order_action_bling_sync', array( $this, 'sync_order' ) );
		add_action( 'woocommerce_order_action_bling_transmit_nf', array( $this, 'transmit_nf' ) );
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'show_bling_status_value' ) );

		add_action( 'save_post', array($this, 'sync_product_bling') );

		if ( 'yes' == $this->sendnfe ) {
			add_action( 'woocommerce_checkout_update_order_meta',  array( $this, 'sync_nfe' ) );
			if( 'yes' == $this->transmitnfe ) {
				add_action( 'after_sync_nfe', array($this, 'transmit_nf') );
			}
		}
		if ( 'yes' == $this->sendnfce ) {
			add_action( 'woocommerce_checkout_update_order_meta',  array( $this, 'sync_nfce' ) );
		}

		add_action( 'admin_notices', array( $this, 'shop_order_notices' ) );
	}

	/**
	 * Process order and submit to Bling.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return void
	 */
	public function process_order( $order_id ) {
		$order = new WC_Order( $order_id );
		$this->sync_order( $order );
	}

	/**
	 * Submit the order to Bling.
	 *
	 * @param object $order WC_Order object.
	 */
	public function sync_order( $order ) {
		$order = is_array($order) ? $order : wc_get_order($order);
		$bling_order = new WC_Bling_Order( $order, $this->api );
		$bling_order->submit();
	}

	/**
	 * Add "Send order to the Bling" order action.
	 *
	 * @param  array $actions Order actions.
	 *
	 * @return array          New Bling order action.
	 */
	public function order_action( $actions ) {
		$actions['bling_sync'] = __( 'Enviar pedido para o Bling', 'bling-woocommerce' );
		$actions['bling_nfce'] = __( 'Reenviar NFCE do pedido no Bling', 'bling-woocommerce' );
		$actions['bling_nfe'] = __( 'Reenviar NFE do pedido no Bling', 'bling-woocommerce' );
		$actions['bling_transmit_nf'] = __( 'Transmitir para SEFAZ via Bling', 'bling-woocommerce' );

		return $actions;
	}

	/**
	 * Syncronize NFCE from the order to Bling.
	 *
	 * @param object $order WC_Order object.
	 */
	public function sync_nfce( $order ) {
		$order = is_array($order) ? $order : wc_get_order($order);
		$bling_nfce = new WC_Bling_NFCE( $order, $this->api );
		$bling_nfce->submit();
	}

	/**
	 * Syncronize NFE from the order to Bling.
	 *
	 * @param object $order WC_Order object.
	 */
	public function sync_nfe( $order ) {
		$order = is_array($order) ? $order : wc_get_order($order);
		$bling_nfe = new WC_Bling_NFE( $order, $this->api );
		$bling_nfe->submit();
	}

	/**
	 * Transmit NFE to SEFAZ via API Bling.
	 *
	 * @param object $order WC_Order object.
	 */
	public function transmit_nf($order) {
		$order = is_array($order) ? $order : wc_get_order($order);
		$bling_nfe = new WC_Bling_NFE( $order, $this->api );
		$bling_nfe->transmit();
	}

	/**
	 * Hook after save product.
	 *
	 * @return array Bling product returned.
	 */
	public function sync_product_bling( $post_id ) {
		if ( ( get_post_type( $post_id ) !== 'product' ) || (get_post_status($post_id) == 'auto-draft') ) {
			return;
		}
		$product = wc_get_product( $post_id );
		$bling_product = new WC_Bling_Product($product, $post_id, $this->api);
		$bling_product->sycronize();
	}

	/**
	 * Display notices in shop order admin page.
	 *
	 * @return string Bling notice.
	 */
	public function shop_order_notices() {
		$screen = get_current_screen();

		if ( ( 'shop_order' === $screen->id || 'product' === $screen->id ) && isset( $_GET['post'] ) ) {
			$post_id =  intval($_GET['post']);
			$bling_message = get_post_meta($post_id, '_bling_notices') ? : NULL ;

			if ( isset( $bling_message[0] ) ) {
				echo '<div class="' . esc_attr( $bling_message[0]['status'] ) . '"><p><strong>' . __( 'Bling', 'bling-woocommerce' ) . ':</strong> ' . esc_attr( $bling_message[0]['message'] ) . '.</p></div>';
				delete_post_meta($post_id, '_bling_notices');
			}
		}
	}

	/**
	 * Display bling status in shop order admin page.
	 *
	 * @return array Bling orders columns.
	 */
	public function add_shop_order_bling_columns( $columns ) {
		return array_merge( $columns, array( 'status_bling' => 'Status Bling' ) );
	}

	/**
	 * Display value bling status in shop order admin page.
	 *
	 * @return string Bling status value.
	 */
	public function show_bling_status_value( $columns ) {
		global $the_order;
		$bling_nfe = new WC_Bling_NFE( $the_order, $this->api );
		switch ( $columns ) {
			case 'status_bling' :
				echo $bling_nfe->get_status();
			break;
		}
	}

}
