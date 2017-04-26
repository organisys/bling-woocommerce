<?php
/**
 * Bling API class.
 */
class WC_Bling_API {

	/**
	 * API URL.
	 *
	 * @var string
	 */
	protected $api_url = 'https://bling.com.br/Api/v2/';

	/**
	 * Integration.
	 *
	 * @var WC_Bling_Integration
	 */
	protected $integration;

	/**
	 * API constructor.
	 *
	 * @param WC_Bling_Integration $integration
	 */
	public function __construct( $integration ) {
		$this->integration = $integration;
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

			return $str_1 . '.' . $str_2 . '-' . $str_3;
		}

		return false;
	}

	/**
	 * Only numbers.
	 *
	 * @param  mixed $value Value to extract.
	 *
	 * @return int          Number.
	 */
	protected function only_numbers( $value ) {
		$fixed = preg_replace( '([^0-9])', '', $value );

		return $fixed;
	}

	/**
	 * Do requests.
	 *
	 * @param string $endpoint API endpoint
	 * @param string $method   Request method.
	 * @param array  $xml      Request XML data.
	 * @param array  $headers  Request headers.
	 *
	 * @return bool|SimpleXMLElement
	 */
	protected function do_request( $endpoint, $method = 'POST', $xml = '', $headers = array() ) {
		$url = $this->api_url . $endpoint;

		$params = array(
			'method'    => $method,
			'sslverify' => false,
			'timeout'   => 60
		);

		if ( 'POST' === $method && '' !== $xml ) {
			$url = add_query_arg( array(
				'apikey' => $this->integration->access_key,
				'xml'    => rawurlencode( $xml )
			), $url );
		}

		if ( ! empty( $headers ) ) {
			$params['headers'] = $headers;
		} elseif ( empty( $headers ) && 'POST' == $method ) {
			$params['headers'] = array(
				'Content-Type' => 'application/xml;charset=UTF-8'
			);
		}

		$response = wp_remote_post( $url, $params );

		if ( is_wp_error( $response ) ) {
			if ( 'yes' == $this->integration->debug ) {
				$this->integration->log->add( $this->integration->id, 'WP_Error: ' . $response->get_error_message() );
			}

			return false;
		}

		return $response;
	}

	/**
	 * Get errors.
	 *
	 * @param  array $data
	 *
	 * @return array
	 */
	public function get_errors( $data ) {
		$errors = array();

		foreach ( $data as $error ) {
			if ( isset( $error['erro']['msg'] ) ) {
				$errors[] = sanitize_text_field( $error['erro']['msg'] );
			} elseif ( isset( $error['msg'] ) ) {
				$errors[] = sanitize_text_field( $error['msg'] );
			} else {
				$errors[] = sanitize_text_field( $error );
			}
		}

		return $errors;
	}

	/**
	 * Generate the Bling legacy order xml.
	 *
	 * @param object  $order Order data.
	 *
	 * @return string        Order xml.
	 */
	protected function get_legacy_order_xml( $order ) {
		$order_number = ltrim( $order->get_order_number(), '#' );

		// Creates the payment xml.
		$xml = new WC_Bling_SimpleXML( '<?xml version="1.0" encoding="utf-8"?><pedido></pedido>' );

		// Order data.
		$xml->addChild( 'data', date( 'd/m/Y', strtotime( $order->order_date ) ) );
		$xml->addChild( 'numero_loja', $order_number );

		// Client.
		$client = $xml->addChild( 'cliente' );
		$client->addChild( 'nome' )->addCData( $order->billing_first_name . ' ' . $order->billing_last_name );

		$wcbcf_settings = get_option( 'wcbcf_settings' );

		if ( 2 == $wcbcf_settings['person_type'] ) {
			$persontype = 'F';
		} elseif ( 3 == $wcbcf_settings['person_type'] ) {
			$persontype = 'J';
		} else {
			$persontype = ( 1 == $order->billing_persontype ) ? 'F' : 'J';
		}

		$client->addChild( 'tipoPessoa', $persontype );
		if ( 'F' == $persontype ) {
			$client->addChild( 'cpf_cnpj', $this->only_numbers( $order->billing_cpf ) );
			$client->addChild( 'rg', $this->only_numbers( $order->billing_rg ) );
		} else {
			$client->addChild( 'cpf_cnpj', $this->only_numbers( $order->billing_cnpj ) );
			$client->addChild( 'ie', $this->only_numbers( $order->billing_ie ) );
		}
		$client->addChild( 'endereco' )->addCData( $order->billing_address_1 );
		$client->addChild( 'numero', $order->billing_number );
		if ( ! empty( $order->billing_address_2 ) ) {
			$client->addChild( 'complemento' )->addCData( $order->billing_address_2 );
		}
		if ( $order->billing_neighborhood ) {
			$client->addChild( 'bairro' )->addCData( $order->billing_neighborhood );
		}
		$cep = $this->format_zipcode( $order->billing_postcode );
		if ( $cep ) {
			$client->addChild( 'cep', $cep );
		}
		$client->addChild( 'cidade' )->addCData( $order->billing_city );
		$client->addChild( 'uf', $order->billing_state );
		$client->addChild( 'fone', $order->billing_phone );
		$client->addChild( 'email', $order->billing_email );

		// Shipping.
		if ( method_exists( $order, 'get_total_shipping' ) ) {
			$shipping_total = $order->get_total_shipping();
		} else {
			$shipping_total = $order->get_shipping();
		}

		if ( $shipping_total ) {
			$shipping = $xml->addChild( 'transporte' );
			$shipping->addChild( 'transportadora' )->addCData( $order->shipping_method_title );
			$shipping->addChild( 'tipo_frete', 'R' );
			// $shipping->addChild( 'servico_correios', '' );

			if ( ( $shipping_total + $order->get_shipping_tax() ) > 0 ) {
				$xml->addChild( 'vlr_frete', number_format( $shipping_total + $order->get_shipping_tax(), 2, '.', '' ) );
			}
		}

		// Discount.
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '2.3', '<' ) ) {
			if ( $order->get_order_discount() > 0 ) {
				$xml->addChild( 'vlr_desconto', $order->get_order_discount() );
			}
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
					if ( ! $product ) {
						continue;
					}

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

		// Notes.
		$note = __( 'Order number:', 'bling-woocommerce' ) . ' ' . $order_number;
		if ( isset( $order->customer_note ) && ! empty( $order->customer_note ) ) {
			$note .= ' - ' . __( 'Client note:', 'bling-woocommerce' ) . ' ' . sanitize_text_field( $order->customer_note );
		}

		$xml->addChild( 'obs', $note );

		// Filter the XML.
		$xml = apply_filters( 'woocommerce_bling_order_xml', $xml, $order );

		return $xml->asXML();
	}

	/**
	 * Generate the Bling order xml.
	 *
	 * @param object  $order Order data.
	 *
	 * @return string        Order xml.
	 */
	protected function get_order_xml( $order ) {
		$order_number = ltrim( $order->get_order_number(), '#' );

		// Creates the payment xml.
		$xml = new WC_Bling_SimpleXML( '<?xml version="1.0" encoding="utf-8"?><pedido></pedido>' );

		// Order data.
		$xml->addChild( 'data', $order->get_date_created()->date( 'd/m/Y' ) );
		$xml->addChild( 'numero_loja', $order_number );

		// Client.
		$client = $xml->addChild( 'cliente' );
		$client->addChild( 'nome' )->addCData( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );

		$wcbcf_settings = get_option( 'wcbcf_settings' );

		if ( 2 === intval( $wcbcf_settings['person_type'] ) ) {
			$persontype = 'F';
		} elseif ( 3 === intval( $wcbcf_settings['person_type'] ) ) {
			$persontype = 'J';
		} else {
			$persontype = 1 === intval( $order->get_meta( '_billing_persontype' ) ) ? 'F' : 'J';
		}

		$client->addChild( 'tipoPessoa', $persontype );
		if ( 'F' === $persontype ) {
			$client->addChild( 'cpf_cnpj', $this->only_numbers( $order->get_meta( '_billing_cpf' ) ) );
			$client->addChild( 'rg', $this->only_numbers( $order->get_meta( '_billing_rg' ) ) );
		} else {
			$client->addChild( 'cpf_cnpj', $this->only_numbers( $order->get_meta( '_billing_cnpj' ) ) );
			$client->addChild( 'ie', $this->only_numbers( $order->get_meta( '_billing_ie' ) ) );
		}
		$client->addChild( 'endereco' )->addCData( $order->get_billing_address_1() );
		$client->addChild( 'numero', $order->get_meta( '_billing_number' ) );
		if ( '' !== $order->get_billing_address_2() ) {
			$client->addChild( 'complemento' )->addCData( $order->get_billing_address_2() );
		}
		if ( '' !== $order->get_meta( '_billing_neighborhood' ) ) {
			$client->addChild( 'bairro' )->addCData( $order->get_meta( '_billing_neighborhood' ) );
		}
		$cep = $this->format_zipcode( $order->get_billing_postcode() );
		if ( $cep ) {
			$client->addChild( 'cep', $cep );
		}
		$client->addChild( 'cidade' )->addCData( $order->get_billing_city() );
		$client->addChild( 'uf', $order->get_billing_state() );
		$client->addChild( 'fone', $order->get_billing_phone() );
		$client->addChild( 'email', $order->get_billing_email() );

		// Shipping.
		$shipping_total = $order->get_shipping_total() + $order->get_shipping_tax();
		if ( 0 < $shipping_total ) {
			$shipping_methods = array();

			foreach ( $order->get_items( 'shipping' ) as $shipping_data ) {
				$shipping_methods[] = $shipping_data->get_name();
			}

			$shipping = $xml->addChild( 'transporte' );
			$shipping->addChild( 'transportadora' )->addCData( implode( ', ', $shipping_methods ) );
			$shipping->addChild( 'tipo_frete', 'R' );
			// $shipping->addChild( 'servico_correios', '' );
			$xml->addChild( 'vlr_frete', number_format( $shipping_total, 2, '.', '' ) );
		}

		// Items.
		$items = $xml->addChild( 'itens' );

		// Cart Contents.
		if ( sizeof( $order->get_items() ) > 0 ) {
			foreach ( $order->get_items() as $order_item ) {
				if ( 0 < $order_item->get_quantity() ) {
					// Get product data.
					$product = $order_item->get_product();
					if ( ! $product ) {
						continue;
					}

					// Item data.
					$item = $items->addChild( 'item' );
					if ( '' !== $product->get_sku() ) {
						$item->addChild( 'codigo', $product->get_sku() );
					}
					$item->addChild( 'descricao' )->addCData( str_replace( '&ndash;', '-', $order_item->get_name() ) );
					$item->addChild( 'un', 'un' );
					$item->addChild( 'qtde', $order_item->get_quantity() );
					$item->addChild( 'vlr_unit', number_format( $order->get_item_total( $order_item, false ), 2, '.', '' ) );
				}
			}
		}

		// Extras Amount.
		if ( 0 < $order->get_total_tax() ) {
			$item = $items->addChild( 'item' );
			$item->addChild( 'descricao' )->addCData( __( 'Tax', 'bling-woocommerce' ) );
			$item->addChild( 'un', 'un' );
			$item->addChild( 'qtde', 1 );
			$item->addChild( 'vlr_unit', $order->get_total_tax() );
		}

		// Notes.
		$note = __( 'Order number:', 'bling-woocommerce' ) . ' ' . $order_number;
		if ( '' !== $order->get_customer_note() ) {
			$note .= ' - ' . __( 'Client note:', 'bling-woocommerce' ) . ' ' . sanitize_text_field( $order->get_customer_note() );
		}

		$xml->addChild( 'obs',$note );

		// Filter the XML.
		$xml = apply_filters( 'woocommerce_bling_order_xml', $xml, $order );

		return $xml->asXML();
	}

	/**
	 * Submit the order to Bling.
	 *
	 * @param  WC_Order $order Order data.
	 *
	 * @return array           Response data.
	 */
	public function submit_order( $order ) {
		$data = array();

		if ( method_exists( $order, 'get_id' ) ) {
			$xml = $this->get_order_xml( $order );
		} else {
			$xml = $this->get_legacy_order_xml( $order );
		}

		error_log( print_r( $xml, true ) );

		if ( 'yes' == $this->integration->debug ) {
			$this->integration->log->add( 'bling', 'Submitting order ' . $order->get_order_number() . ' with the following data: ' . $xml );
		}

		// Get the response.
		$response = $this->do_request( 'pedido/json/', 'POST', $xml );

		if ( $response ) {
			try {
				$data = json_decode( $response['body'], true );
			} catch ( Exception $e ) {
				if ( 'yes' == $this->integration->debug ) {
					$this->integration->add( 'bling', 'Error while parsing the response: ' . print_r( $e->getMessage(), true ) );
				}
			}
		}

		return $data;
	}
}
