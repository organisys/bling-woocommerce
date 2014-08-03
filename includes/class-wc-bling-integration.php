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

		$this->id                 = 'bling';
		$this->method_title       = __( 'Bling', 'bling-woocommerce' );
		$this->method_description = __( 'The Bling is an online system that allows you to control the finances, inventory and issue invoices quickly and uncomplicated.', 'bling-woocommerce' );

		// API.
		$this->api_url = 'https://bling.com.br/Api/v2/';

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->access_key = $this->get_option( 'access_key' );
		$this->debug      = $this->get_option( 'debug' );

		// Active logs.
		if ( 'yes' == $this->debug ) {
			if ( class_exists( 'WC_Logger' ) ) {
				$this->log = new WC_Logger();
			} else {
				$this->log = $woocommerce->logger();
			}
		}

		$this->api = new WC_Bling_API( $this );

		// Actions.
		add_action( 'woocommerce_update_options_integration_bling', array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'process_order' ) );
		add_filter( 'woocommerce_order_actions', array( $this, 'order_action' ) );
		add_action( 'woocommerce_order_action_bling_sync', array( $this, 'submit_order' ) );

		// Display notices in shop order admin page.
		add_action( 'admin_notices', array( $this, 'shop_order_notices' ) );
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'access_key' => array(
				'title'       => __( 'Access Key', 'bling-woocommerce' ),
				'type'        => 'text',
				'description' => sprintf( __( 'Please enter your Bling Access Key. This is needed to integration works. Is possible generate a new Access Key %s.', 'bling-woocommerce' ), '<a href="http://bling.com.br/configuracoes.api.web.services.php">' . __( 'here', 'bling-woocommerce' ) . '</a>' ),
				'default'     => ''
			),
			'testing' => array(
				'title'       => __( 'Testing', 'bling-woocommerce' ),
				'type'        => 'title',
				'description' => ''
			),
			'debug' => array(
				'title'       => __( 'Debug Log', 'bling-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable logging', 'bling-woocommerce' ),
				'default'     => 'no',
				'description' => sprintf( __( 'Log Bling events, such as API requests, inside %s', 'bling-woocommerce' ), '<code>woocommerce/logs/bling-' . sanitize_file_name( wp_hash( 'bling' ) ) . '.txt</code>' )
			)
		);
	}

	/**
	 * Submit the order to Bling.
	 *
	 * @param  object $order WC_Order object.
	 *
	 * @return void
	 */
	public function submit_order( $order ) {
		// Submit the order via API.
		$data = $this->api->submit_order( $order );

		// Save the order number.
		if ( isset( $data['retorno']['pedidos'][0]['pedido'] ) ) {
			$order_data = $data['retorno']['pedidos'][0]['pedido'];
			$number = intval( $order_data['numero'] );

			// Save the bling order number as order meta.
			update_post_meta( $order->id, __( 'Bling order number', 'bling-woocommerce' ), $number );

			// Sets the success notice.
			update_post_meta( $order->id, '_bling_notices', array( 'status' => 'updated', 'message' => __( 'Order sent successfully', 'bling-woocommerce' ) ) );

			// Save Order data.
			update_post_meta( $order->id, '_bling_order_number', $number );
			update_post_meta( $order->id, '_bling_order_id', intval( $order_data['idPedido'] ) );
			update_post_meta( $order->id, '_bling_order_tracking', $order_data['codigos_rastreamento'] );

			if ( 'yes' == $this->debug ) {
				$this->log->add( 'bling', 'Order created with success! The order ID is: ' . $number );
			}
		}

		// Save the order error.
		if ( isset( $data['retorno']['erros'] ) ) {
			$errors = $this->api->get_errors( $data['retorno']['erros'] );

			// Sets the error notice.
			update_post_meta( $order->id, '_bling_notices', array( 'status' => 'error', 'message' => implode( ', ', $errors ) ) );

			if ( 'yes' == $this->debug ) {
				$this->log->add( 'bling', 'Failed to generate the order: ' . print_r( $data['retorno']['erros'], true ) );
			}
		}
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

		$this->submit_order( $order );
	}

	/**
	 * Add "Send order to the Bling" order action.
	 *
	 * @param  array $actions Order actions.
	 *
	 * @return array          New Bling order action.
	 */
	public function order_action( $actions ) {
		$actions['bling_sync'] = __( 'Send order to the Bling', 'bling-woocommerce' );

		return $actions;
	}

	/**
	 * Display notices in shop order admin page.
	 *
	 * @return string Bling notice.
	 */
	public function shop_order_notices() {
		$screen = get_current_screen();

		if ( 'shop_order' === $screen->id && isset( $_GET['post'] ) ) {
			$order_id = intval( $_GET['post'] );
			$message = get_post_meta( $order_id, '_bling_notices', true );

			if ( is_array( $message ) ) {
				echo '<div class="' . esc_attr( $message['status'] ) . '"><p><strong>' . __( 'Bling', 'bling-woocommerce' ) . ':</strong> ' . esc_attr( $message['message'] ) . '.</p></div>';
				delete_post_meta( $order_id, '_bling_notices' );
			}
		}
	}
}
