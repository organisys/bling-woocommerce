<?php
/**
 * WC Bling Integration Class.
 *
 * Create integration with the Bling.
 *
 * @since 1.0.0
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
		$this->api_url = 'https://bling.com.br/Api/v2/pedido/json/';

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

		// Define user set variables.
		$this->access_key = $this->get_option( 'access_key' );
		$this->debug      = $this->get_option( 'debug' );

		// Actions.
		add_action( 'woocommerce_update_options_integration_bling', array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'process_order' ) );
		add_filter( 'woocommerce_order_actions', array( $this, 'order_action' ) );
		add_action( 'woocommerce_order_action_bling_sync', array( $this, 'submit_order' ) );

		// Display notices in shop order admin page.
		add_action( 'admin_notices', array( $this, 'shop_order_notices' ) );

		// Active logs.
		if ( 'yes' == $this->debug ) {
			if ( class_exists( 'WC_Logger' ) ) {
				$this->log = new WC_Logger();
			} else {
				$this->log = $woocommerce->logger();
			}
		}
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
	 * Format Zip Code.
	 *
	 * @param  string $zipcode Zip Code.
	 *
	 * @return string          Formated zip code.
	 */
	protected function format_zipcode( $zipcode ) {
		$zipcode = str_replace( array( '.', '-', ',' ), '', trim( $zipcode ) );
		$len     = strlen( $zipcode );

		if ( 8 == $len ) {
			$str_1 = substr( $zipcode, 0, 2 );
			$str_2 = substr( $zipcode, 2, 3 );
			$str_3 = substr( $zipcode, 5, 3 );

			return $str_1 . '.' . $str_2 . '-' . $str_2;
		}

		return false;
	}

	/**
	 * Generate the Bling order xml.
	 *
	 * @param object  $order Order data.
	 *
	 * @return string        Order xml.
	 */
	protected function generate_order_xml( $order ) {
		// Added custom SimpleXML class.
		require_once plugin_dir_path( __FILE__ ) . 'class-wc-bling-simplexml.php';

		// Creates the payment xml.
		$xml = new WC_Bling_SimpleXML( '<?xml version="1.0" encoding="utf-8"?><pedido></pedido>' );

		// Order data.
		$xml->addChild( 'data', date( 'd/m/Y', strtotime( $order->order_date ) ) );
		$xml->addChild( 'numero', ltrim( $order->get_order_number(), '#' ) );

		// Client.
		$client = $xml->addChild( 'cliente' );
		$client->addChild( 'nome' )->addCData( $order->billing_first_name . ' ' . $order->billing_last_name );
		// $client->addChild( 'tipo_pessoa', '' );
		// $client->addChild( 'cpf_cnpj', '' );
		// $client->addChild( 'ie', '' );
		// $client->addChild( 'rg', '' );
		$client->addChild( 'endereco' )->addCData( $order->billing_address_1 );
		// $client->addChild( 'numero', '' );
		if ( ! empty( $order->billing_address_2 ) ) {
			$client->addChild( 'complemento' )->addCData( $order->billing_address_2 );
		}
		// $client->addChild( 'bairro', '' );
		$cep = $this->format_zipcode( $order->billing_postcode );
		if ( $cep ) {
			$client->addChild( 'cep', $cep );
		}
		$client->addChild( 'cidade' )->addCData( $order->billing_city );
		$client->addChild( 'uf', $order->billing_state );
		$client->addChild( 'fone', $order->billing_phone );
		$client->addChild( 'email', $order->billing_email );

		// Shipping.
		if ( $order->get_shipping() ) {
			$shipping = $xml->addChild( 'transporte' );
			$shipping->addChild( 'transportadora' )->addCData( $order->shipping_method_title );
			$shipping->addChild( 'tipo_frete', 'R' );
			// $shipping->addChild( 'servico_correios', '' );

			if ( ( $order->get_shipping() + $order->get_shipping_tax() ) > 0 ) {
				$xml->addChild( 'vlr_frete', number_format( $order->get_shipping() + $order->get_shipping_tax(), 2, '.', '' ) );
			}
		}

		// Discount.
		if ( $order->get_order_discount() > 0 ) {
			$xml->addChild( 'vlr_desconto', $order->get_order_discount() );
		}

		// Items.
		$items = $xml->addChild( 'itens' );

		// Cart Contents.
		if ( sizeof( $order->get_items() ) > 0 ) {
			foreach ( $order->get_items() as $order_item ) {
				if ( $order_item['qty'] ) {
					$item_name = $order_item['name'];

					// Get product data.
					$product = $order->get_product_from_item( $order_item );

					// Product with attrs.
					$item_meta = new WC_Order_Item_Meta( $order_item['item_meta'] );
					if ( $meta = $item_meta->display( true, true ) ) {
						$item_name .= ' - ' . $meta;
					}

					// Item data.
					$item = $items->addChild( 'item' );
					if ( $product->get_sku() ) {
						$item->addChild( 'codigo', $product->get_sku() );
					}
					$item->addChild( 'descricao' )->addCData( sanitize_text_field( $item_name ) );
					$item->addChild( 'un', 'un' );
					$item->addChild( 'qtde', $order_item['qty'] );
					$item->addChild( 'vlr_unit', $order->get_item_total( $order_item, false ) );
				}
			}
		}

		// Extras Amount.
		if ( $order->get_total_tax() > 0 ) {
			$item = $items->addChild( 'item' );
			$item->addChild( 'descricao' )->addCData( __( 'Tax', 'bling-woocommerce' ) );
			$item->addChild( 'un', 'un' );
			$item->addChild( 'qtde', 1 );
			$item->addChild( 'vlr_unit', $order->get_total_tax() );
		}

		// Customer notes.
		if ( $order->customer_note && ! empty( $order->customer_note ) ) {
			$xml->addChild( 'obs' )->addCData( sanitize_text_field( $order->customer_note ) );
		}

		// Filter the XML.
		$xml = apply_filters( 'woocommerce_bling_order_xml', $xml, $order );

		return $xml->asXML();
	}

	/**
	 * Submit the order to Bling.
	 *
	 * @param  object $order WC_Order object.
	 *
	 * @return void
	 */
	public function submit_order( $order ) {
		// Sets the xml.
		$xml = $this->generate_order_xml( $order );

		// Sets the url.
		$url = esc_url_raw( sprintf(
			"%s?apikey=%s&xml=%s",
			$this->api_url,
			$this->access_key,
			urlencode( $xml )
		) );

		if ( 'yes' == $this->debug ) {
			$this->log->add( 'bling', 'Submitting order ' . $order->get_order_number() . ' with the following data: ' . $xml );
		}

		// Get the response.
		$response = wp_remote_post( $url, array( 'timeout' => 60 ) );

		// Process the response.
		if ( is_wp_error( $response ) ) {
			if ( 'yes' == $this->debug ) {
				$this->log->add( 'bling', 'An error occurred with WP_Error: ' . $response->get_error_message() );
			}
		} else {
			try {
				$response_data = json_decode( $response['body'] );
			} catch ( Exception $e ) {
				$response_data = '';

				if ( 'yes' == $this->debug ) {
					$this->log->add( 'bling', 'Error while parsing the response: ' . print_r( $e->getMessage(), true ) );
				}
			}

			error_log( print_r( $response_data, true ) );

			// Save the order number.
			if ( isset( $response_data->retorno->pedidos[0]->pedido->numero ) ) {
				$number = (string) $response_data->retorno->pedidos[0]->pedido->numero;

				// Save the bling order number as order meta.
				update_post_meta( $order->id, __( 'Bling order number', 'bling-woocommerce' ), $number );

				// Sets the success notice.
				update_post_meta( $order->id, '_bling_notices', array( 'status' => 'updated', 'message' => __( 'Order sent successfully', 'bling-woocommerce' ) ) );

				if ( 'yes' == $this->debug ) {
					$this->log->add( 'bling', 'Order created with success! The order ID is: ' . $number );
				}
			}

			// Save the order error.
			if ( isset( $response_data->retorno->erros ) ) {
				$errors = array();
				foreach ( $response_data->retorno->erros as $error ) {
					if ( isset( $error->msg ) ) {
						$errors[] = (string) $error->msg;
					} else {
						$msg = (array) $error;
						$errors[] = (string) current( $msg );
					}
				}

				// Sets the error notice.
				update_post_meta( $order->id, '_bling_notices', array( 'status' => 'error', 'message' => implode( ', ', $errors ) ) );

				if ( 'yes' == $this->debug ) {
					$this->log->add( 'bling', 'Failed to generate the order: ' . print_r( $response_data->retorno->erros, true ) );
				}
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
		if ( 'shop_order' === get_current_screen()->id && isset( $_GET['post'] ) ) {
			$order_id = intval( $_GET['post'] );
			$message = get_post_meta( $order_id, '_bling_notices', true );

			if ( is_array( $message ) ) {
				echo '<div class="' . esc_attr( $message['status'] ) . '"><p><strong>' . __( 'Bling', 'bling-woocommerce' ) . ':</strong> ' . esc_attr( $message['message'] ) . '.</p></div>';
				delete_post_meta( $order_id, '_bling_notices' );
			}
		}
	}
}
