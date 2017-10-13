<?php

/**
 * Bling Order Class
*/
class WC_Bling_Order extends WC_Bling_Base_Order implements WC_Bling_Integracao_Interface {

	/**
	 * Submit the order to Bling
	 *
	 * @return void
	 */
	public function submit() {
		$xml = $this->get_xml();
		$data = array();

		if ( 'yes' == BLING_WOOCOMMERCE_DEBUG ) {
			WC_Bling_Helper::write_log('Enviando Pedido ' . $this->order->get_order_number() . ' com seguinte dados: ' . $xml );
		}

		// Get the response
		$response = $this->api->do_request( 'pedido/json/', 'POST', $xml );

		if ( $response ) {
			$data = $this->api->get_response($response);
		}

		// Save the order number
		if ( isset( $data['retorno']['pedidos'][0]['pedido'] ) ) {
			$order_data = $data['retorno']['pedidos'][0]['pedido'];
			$number     = intval( $order_data['numero'] );

			// Save the bling order number as order meta
			$this->order->update_meta_data( __( 'Bling numero do pedido ', 'bling-woocommerce' ), $number );

			// Sets the success notice
			$this->order->update_meta_data( '_bling_notices', array( 'status' => 'updated', 'message' => __( 'Pedido enviado com sucesso', 'bling-woocommerce' ) ) );

			// Save Order data
			$this->order->update_meta_data( '_bling_order_number', $number );
			$this->order->update_meta_data( '_bling_order_id', intval( $order_data['idPedido'] ) );
			$this->order->update_meta_data( '_bling_order_tracking', $order_data['codigos_rastreamento'] );
			$this->order->save();

			if ( 'yes' == BLING_WOOCOMMERCE_DEBUG ) {
				WC_Bling_Helper::write_log( 'Pedido criado com sucesso! ID do pedido : ' . $number );
			}
		}

		// Save the order error
		if ( isset( $data['retorno']['erros'] ) ) {
			$errors = $this->api->get_errors( $data['retorno']['erros'] );

			// Sets the error notice
			$this->order->update_meta_data( '_bling_notices', array( 'status' => 'error', 'message' => implode( ', ', $errors ) ) );
			$this->order->save();

			if ( 'yes' == BLING_WOOCOMMERCE_DEBUG ) {
				WC_Bling_Helper::write_log( 'Falha ao gerar pedido: ' . print_r( $data['retorno']['erros'], true ) );
			}
		}
	}

	/**
	 * Generate the Bling order xml
	 *
	 * @return string        Order xml
	*/
	public function get_xml() {
		$order_number = ltrim( $this->order->get_order_number(), '#' );
		
		// Creates the payment xml.
		$xml = new WC_Bling_SimpleXML( '<?xml version="1.0" encoding="utf-8"?><pedido></pedido>' );

		// Order data.
		$xml->addChild( 'data', $this->order->get_date_created()->date( 'd/m/Y' ) );
		$xml->addChild( 'numero_loja', $order_number );

		// Client.
		$client = $xml->addChild( 'cliente' );
		$client->addChild( 'nome' )->addCData( $this->order->get_billing_first_name() . ' ' . $this->order->get_billing_last_name() );

		$wcbcf_settings = get_option( 'wcbcf_settings' );

		if ( 2 === intval( $wcbcf_settings['person_type'] ) ) {
			$persontype = 'F';
		} elseif ( 3 === intval( $wcbcf_settings['person_type'] ) ) {
			$persontype = 'J';
		} else {
			$persontype = 1 === intval( $this->order->get_meta( '_billing_persontype' ) ) ? 'F' : 'J';
		}

		$client->addChild( 'tipoPessoa', $persontype );
		if ( 'F' === $persontype ) {
			$client->addChild( 'cpf_cnpj', WC_Bling_Helper::only_numbers( $this->order->get_meta( '_billing_cpf' ) ) );
			$client->addChild( 'rg', WC_Bling_Helper::only_numbers( $this->order->get_meta( '_billing_rg' ) ) );
		} else {
			$client->addChild( 'cpf_cnpj', WC_Bling_Helper::only_numbers( $this->order->get_meta( '_billing_cnpj' ) ) );
			$client->addChild( 'ie', WC_Bling_Helper::only_numbers( $this->order->get_meta( '_billing_ie' ) ) );
		}
		$client->addChild( 'endereco' )->addCData( $this->order->get_billing_address_1() );
		$client->addChild( 'numero', $this->order->get_meta( '_billing_number' ) );
		if ( '' !== $this->order->get_billing_address_2() ) {
			$client->addChild( 'complemento' )->addCData( $this->order->get_billing_address_2() );
		}
		if ( '' !== $this->order->get_meta( '_billing_neighborhood' ) ) {
			$client->addChild( 'bairro' )->addCData( $this->order->get_meta( '_billing_neighborhood' ) );
		}
		$cep = WC_Bling_Helper::format_zipcode( $this->order->get_billing_postcode() );
		if ( $cep ) {
			$client->addChild( 'cep', $cep );
		}
		$client->addChild( 'cidade' )->addCData( $this->order->get_billing_city() );
		$client->addChild( 'uf', $this->order->get_billing_state() );
		$client->addChild( 'fone', $this->order->get_billing_phone() );
		$client->addChild( 'email', $this->order->get_billing_email() );

		// Shipping.
		$shipping_total = $this->order->get_shipping_total() + $this->order->get_shipping_tax();
		if ( 0 < $shipping_total ) {
			$shipping_methods = array();

			foreach ( $this->order->get_items( 'shipping' ) as $shipping_data ) {
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
		if ( sizeof( $this->order->get_items() ) > 0 ) {
			foreach ( $this->order->get_items() as $order_item ) {
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
					$item->addChild( 'vlr_unit', number_format( $this->order->get_item_total( $order_item, false ), 2, '.', '' ) );
				}
			}
		}

		// Extras Amount.
		if ( 0 < $this->order->get_total_tax() ) {
			$item = $items->addChild( 'item' );
			$item->addChild( 'descricao' )->addCData( __( 'Tax', 'bling-woocommerce' ) );
			$item->addChild( 'un', 'un' );
			$item->addChild( 'qtde', 1 );
			$item->addChild( 'vlr_unit', $this->order->get_total_tax() );
		}

		// Notes.
		$note = __( 'Order number:', 'bling-woocommerce' ) . ' ' . $order_number;
		if ( '' !== $this->order->get_customer_note() ) {
			$note .= ' - ' . __( 'Client note:', 'bling-woocommerce' ) . ' ' . sanitize_text_field( $this->order->get_customer_note() );
		}

		$xml->addChild( 'obs',$note );

		// Filter the XML.
		$xml = apply_filters( 'woocommerce_bling_order_xml', $xml, $this->order );

		return $xml->asXML();
	}

}