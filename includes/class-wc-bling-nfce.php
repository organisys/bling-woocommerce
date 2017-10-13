<?php

/**
 * Bling NFC-e Class
*/
class WC_Bling_NFCE extends WC_Bling_Base_Order implements  WC_Bling_Integracao_Interface {

	public function submit() {
		$xml = $this->get_xml();
		$data = array();

		if ( 'yes' == BLING_WOOCOMMERCE_DEBUG ) {
			WC_Bling_Helper::write_log( 'Enviando NFC-e ' . $this->get_order_number() . ' com seguinte dados: ' . $xml );
		}

		// Get the response.
		$response = $this->api->do_request( 'nfce/json/', 'POST', $xml );

		if ( $response ) {
			$data = $this->api->get_response($response);
		}

		if( isset($data['retorno']['notasfiscais'][0]['notaFiscal']) ) {
			
			$nfce_data = $data['retorno']['notasfiscais'][0]['notaFiscal'];

			// Sets the success notice.
			$this->order->update_meta_data( '_bling_notices', array( 'status' => 'updated', 'message' => __( 'NFC-e criada com sucesso, número: ' . $nfce_data['numero'] ) ) );

			$this->order->update_meta_data( '_bling_nfce_number', $nfce_data['numero'] );
			$this->order->update_meta_data( '_bling_nfce_id', $nfce_data['idNotaFiscal'] );
			$this->order->update_meta_data( '_bling_order_tracking', $nfce_data['codigos_rastreamento'] );
			$this->order->update_meta_data( '_bling_nfce_status', '' );
			$this->order->save();

			if ( 'yes' == BLING_WOOCOMMERCE_DEBUG ) {
				WC_Bling_Helper::write_log( 'NFC-e criada com sucesso, ID: ' . $nfce_data['idNotaFiscal'] );
			}
		}

		// Save the order error.
		if ( isset( $data['retorno']['erros'] ) ) {
			$errors = $this->api->get_errors( $data['retorno']['erros'] );

			// Sets the error notice.
			$this->order->update_meta_data( '_bling_notices', array( 'status' => 'error', 'message' => implode( ', ', $errors ) ) );
			$this->order->save();

			if ( 'yes' == BLING_WOOCOMMERCE_DEBUG ) {
				WC_Bling_Helper::write_log( 'Falha ao gerar nota: ' . print_r( $data['retorno']['erros'], true ) );
			}
		}
		
	}

	public function get_xml() {
		$this->order_number = ltrim( $this->get_order_number(), '#' );

		// Creates the payment xml
		$xml = new WC_Bling_SimpleXML( '<?xml version="1.0" encoding="utf-8"?><pedido></pedido>' );

		// Data Operacao
		$xml->addChild( 'data_operacao', $this->order->get_date_created()->date( 'd/m/Y H:i:s' ) );

		// Cliente
		$client = $xml->addChild( 'cliente' );
		$client->addChild( 'nome' )->addCData( $this->order->get_billing_first_name() . ' ' . $this->order->get_billing_last_name() );
		$client->addChild( 'tipo_pessoa' )->addCData( 'F' );
		$client->addChild( 'cpf_cnpj', WC_Bling_Helper::only_numbers( $this->order->billing_cpf ) );
		$client->addChild( 'endereco' )->addCData( $this->order->get_billing_address_1() );
		$client->addChild( 'numero', $this->order->get_meta( '_billing_number' ) );
		$client->addChild( 'bairro', $this->order->get_meta( '_billing_neighborhood' ) );
		$client->addChild( 'cep', $this->order->get_meta( '_billing_postcode' ) );
		$client->addChild( 'cidade' )->addCData( $this->order->get_billing_city() );
		$client->addChild( 'uf', $this->order->get_billing_state() );
		$client->addChild( 'fone', $this->order->get_billing_phone() );
		$client->addChild( 'email', $this->order->get_billing_email() );

		// Items
		$items = $xml->addChild( 'itens' );

		// Cart Contents
		if ( sizeof( $this->order->get_items() ) > 0 ) {
			foreach ( $this->order->get_items() as $this->order_item ) {
				if ( 0 < $this->order_item->get_quantity() ) {
					// Get product data
					$product = $this->order_item->get_product();
					if ( ! $product ) {
						continue;
					}

					// Item data
					$item = $items->addChild( 'item' );
					if ( '' !== $product->get_sku() ) {
						$item->addChild( 'codigo', $product ->get_sku() );
					}
					$item->addChild( 'descricao' )->addCData( str_replace( '&ndash;', '-', $this->order_item->get_name() ) );
					$item->addChild( 'un', 'un' );
					$item->addChild( 'qtde', $this->order_item->get_quantity() );
					$item->addChild( 'vlr_unit', number_format( $this->order->get_item_total( $this->order_item, false ), 2, '.', '' ) );
					$item->addChild( 'tipo', 'P' );
					$item->addChild( 'origem', '0' );
				}
			}
		}

		// Notes
		$note = __( 'Order number:', 'bling-woocommerce' ) . ' ' . $this->order_number;
		if ( '' !== $this->order->get_customer_note() ) {
			$note .= ' - ' . __( 'Client note:', 'bling-woocommerce' ) . ' ' . sanitize_text_field( $this->order->get_customer_note() );
		}

		$xml->addChild( 'obs',$note );

		// Filter the XML
		$xml = apply_filters( 'woocommerce_bling_nfce_xml', $xml, $this->order );

		WC_Bling_Helper::write_log($xml);

		return $xml->asXML();
	}

}