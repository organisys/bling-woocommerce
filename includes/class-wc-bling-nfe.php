<?php

/**
 * Bling NFE Class
*/
class WC_Bling_NFE extends WC_Bling_Base_Order implements WC_Bling_Integracao_Interface {

	public function submit() {
		$xml = $this->get_xml();
		$data = array();

		if ( 'yes' == BLING_WOOCOMMERCE_DEBUG ) {
			WC_Bling_Helper::write_log( 'Enviando NFE ' . $this->get_order_number() . ' com seguinte dados: ' . $xml );
		}

		// Get the response.
		$response = $this->api->do_request( 'notafiscal/json/', 'POST', $xml );

		if ( $response ) {
			$data = $this->api->get_response($response);
		}

		if( isset($data['retorno']['notasfiscais'][0]['notaFiscal']) ) {
			
			$nfe_data = $data['retorno']['notasfiscais'][0]['notaFiscal'];
			// Sets the success notice.
			$this->order->update_meta_data( '_bling_notices', array( 'status' => 'updated', 'message' => __( 'NF-e criada com sucesso, número: ' . $nfe_data['numero']) ) );

			$this->order->update_meta_data( '_bling_nfe_number', $nfe_data['numero'] );
			$this->order->update_meta_data( '_bling_nfe_id', $nfe_data['idNotaFiscal'] );
			$this->order->update_meta_data( '_bling_order_tracking', $nfe_data['codigos_rastreamento'] );
			$this->order->update_meta_data( '_bling_nfe_status', '' );
			$this->order->save();

			if ( 'yes' == BLING_WOOCOMMERCE_DEBUG ) {
				WC_Bling_Helper::write_log('NF-e criada com sucesso, ID: ' . $nfe_data['idNotaFiscal'] );
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

		do_action( 'after_sync_nfe', $this->order );
	}

	public function transmit() {
		$data = array();
		
		if (! empty($this->order->get_meta( '_bling_nfe_number' ))) {
			$numero = $this->order->get_meta( '_bling_nfe_number' );

			$response = $this->api->do_request("notafiscal/json/", "POST", "", array(), $numero, 1 );
			if ( $response ) {
				$data = $this->api->get_response($response);
			}

		} elseif (! empty($this->order->get_meta( '_bling_nfce_number' ))) {
			$numero = $this->order->get_meta( '_bling_nfce_number' );

			$response = $this->api->do_request("nfce/json/", "POST", "", array(), $numero, 1 );
			if ( $response ) {
				$data = $this->api->get_response($response);
			}

		} else {
			
			$data['retorno']['erros']['erro'] = array(
				'msg' => 'É necessário enviar a nota para o Bling antes de transmitir'
			);
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

	/**
	 * Get status the nf from Bling.
	 *
	 * @return array           Response data.nf bling status
	 */
	public function get_status() {
		if (! empty($this->order->get_meta( '_bling_nfe_number' ))) {
			$numero = $this->order->get_meta( '_bling_nfe_number' );

			$response = $this->api->do_request("notafiscal/{$numero}/1/json/", "GET", "", array(), $numero, 1 );
			$response = json_decode( $response['body'], true );

			if( isset($response['retorno']['notasfiscais'][0]['notafiscal'] ) ) {
				$ret = $response['retorno']['notasfiscais'][0]['notafiscal'];
				$this->order->update_meta_data('_bling_nfe_status', $ret['situacao']);
				$this->order->save();
			} else {
				$this->order->update_meta_data('_bling_nfe_status','Excluída');
				$this->order->save();
			}

			$status = $this->order->get_meta('_bling_nfe_status');
			return "NF-e - {$numero} Status: {$status}";

		} elseif (! empty($this->order->get_meta( '_bling_nfce_number' ))) {
			$numero = $this->order->get_meta( '_bling_nfce_number' );

			$response = $this->api->do_request("nfce/{$numero}/1/json/", "GET", "", array(), $numero, 1 );
			$response = json_decode( $response['body'], true );

			if( isset($response['retorno']['notasfiscais'][0]['notafiscal'] ) ) {
				$ret = $response['retorno']['notasfiscais'][0]['notafiscal'];
				$this->order->update_meta_data('_bling_nfce_status', $ret['situacao']);
				$this->order->save();
			} else {
				$this->order->update_meta_data('_bling_nfe_status','Excluída');
				$this->order->save();
			}

			$status = $this->order->get_meta('_bling_nfce_status');
			
			return "NFC-e - {$numero} Status: {$status}";

		} else {
			return 'Pedido não sincronizado';
		}
	}

	/**
	 * Generate the Bling NFE xml
	 *
	 * @return string        NFE xml
	 */
	public function get_xml() {
		$order_number = ltrim( $this->get_order_number(), '#' );

		// Creates the NFE xml
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
			foreach ( $this->order->get_items() as $order_item ) {
				if ( 0 < $order_item->get_quantity() ) {
					// Get product data
					$product = $order_item->get_product();
					if ( ! $product ) {
						continue;
					}

					// Item data
					$item = $items->addChild( 'item' );
					if ( '' !== $product->get_sku() ) {
						$item->addChild( 'codigo', $product->get_sku() );
					}
					$item->addChild( 'descricao' )->addCData( str_replace( '&ndash;', '-', $order_item->get_name() ) );
					$item->addChild( 'un', 'un' );
					$item->addChild( 'qtde', $order_item->get_quantity() );
					$item->addChild( 'vlr_unit', number_format( $this->order->get_item_total( $order_item, false ), 2, '.', '' ) );
					$item->addChild( 'tipo', 'P' );
					$item->addChild( 'origem', '0' );
				}
			}
		}

		// Notes
		$note = __( 'Order number:', 'bling-woocommerce' ) . ' ' . $order_number;
		if ( '' !== $this->order->get_customer_note() ) {
			$note .= ' - ' . __( 'Client note:', 'bling-woocommerce' ) . ' ' . sanitize_text_field( $this->order->get_customer_note() );
		}

		$xml->addChild( 'obs',$note );

		// Filter the XML
		$xml = apply_filters( 'woocommerce_bling_nfe_xml', $xml, $this->order );

		return $xml->asXML();

	}


}