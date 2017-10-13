<?php

/**
 * Bling  Product Class
*/
class WC_Bling_Product implements WC_Bling_Integracao_Interface {
	private $product;
	protected $api;

	public function __construct( $product_data, $post_id, $api ) {
		$this->product = $product_data;
		$this->post_id = $post_id;
		$this->ID = $this->product->get_id();
		$this->api = $api;
	}

	public function get_product() {
		return $this->product;
	}

	public function get_id() {
		return $this->ID;
	}

	private function has_bling_id() {
		if(! ( isset(get_post_meta($this->post_id, '_bling_product_sync')[0]) && isset(get_post_meta($this->post_id, '_bling_product_id')[0]) ) ) {
			return false;
		}
		return ( (get_post_meta($this->post_id, '_bling_product_sync')[0] == 'true' ) && ( isset(get_post_meta($this->post_id, '_bling_product_id')[0]) ) );
	}

	public function sycronize() {
		if( $this->product->get_sku() == '' ) {
			update_post_meta($this->post_id, '_bling_notices', array( 'status' => 'error', 'message' => 'ATENÇÃO: Preencha o campo REF(em Inventário) para efetuar integração com Bling' ) );
			return;
		}

		if( $this->has_bling_id( $this->post_id ) ) {
			$this->update();
		} else {
			$this->submit();
		}
	}

	/**
	 * Submit product to Bling.
	 *
	 * @param  WC_Product $product Order data.
	 *
	 * @return array           Response data.
	 */
	public function submit() {
		$xml = $this->get_xml();
		$data = array();

		if ( 'yes' == BLING_WOOCOMMERCE_DEBUG ) {
			WC_Bling_Helper::write_log( 'Enviando Produto: ' . $this->get_id() . ' com seguinte dados: ' . $xml );
		}

		$response = $this->api->do_request( 'produto/json/', 'POST', $xml );

		if ( $response ) {
			$data = $this->api->get_response($response);
		}

		// Save the order error.
		if ( isset( $data['retorno']['erros'] ) ) {
			$errors = $this->api->get_errors( $data['retorno']['erros'] );

			// Sets the error notice
			update_post_meta($this->post_id, '_bling_notices', array( 'status' => 'error', 'message' => implode( ', ', $errors ) ) );

			if ( 'yes' == BLING_WOOCOMMERCE_DEBUG ) {
				WC_Bling_Helper::write_log( 'Falha ao criar o produto: ' . print_r( $data['retorno']['erros'], true ) );
			}
		}

		if( isset($data['retorno']['produtos'][0][0]['produto']) ) {
			
			$product_data = $data['retorno']['produtos'][0][0]['produto'];
			// Sets the success notice.
			update_post_meta( $this->post_id, '_bling_notices', array( 'status' => 'updated', 'message' => __( 'Produto criado com sucesso no bling, id: ' . $product_data['id'] . ' Nome: '. $product_data['descricao']) ) );
			update_post_meta($this->post_id, '_bling_product_sync', 'true' );
			update_post_meta($this->post_id, '_bling_product_id', $product_data['id'] );

			if ( 'yes' == BLING_WOOCOMMERCE_DEBUG ) {
				WC_Bling_Helper::write_log('Produto criado com sucesso no bling, ID: ' . $product_data['id']. ' Nome: '. $product_data['descricao'] );
			}
		}
	}

	/**
	 * Update product in Bling api.
	 *
	 * @param  WC_Product $product Order data.
	 *
	 * @return array           Response data.
	 */
	public function update() {
		$data = array();
		$xml = $this->get_xml();
		$bling_codigo = get_post_meta($this->get_id(), '_bling_product_id')[0];

		if ( 'yes' == BLING_WOOCOMMERCE_DEBUG ) {
			WC_Bling_Helper::write_log( 'Atualizando Produto: ' . $this->get_id() . ' com seguinte dados: ' . $xml );
		}

		$response = $this->api->do_request( "produto/{$bling_codigo}/json/", 'POST', $xml );

		if ( $response ) {
			$data = $this->api->get_response($response);
		}

		// Save the order error.
		if ( isset( $data['retorno']['erros'] ) ) {
			$errors = $this->api->get_errors( $data['retorno']['erros'] );

			// Sets the error notice
			update_post_meta($post_id, '_bling_notices', array( 'status' => 'error', 'message' => implode( ', ', $errors ) ) );

			if ( 'yes' == BLING_WOOCOMMERCE_DEBUG ) {
				WC_Bling_Helper::write_log( 'Falha ao sincronizar o produto: ' . print_r( $data['retorno']['erros'], true ) );
			}
		}
		if( isset($data['retorno']['produtos'][0][0]['produto']) ) {
			
			$product_data = $data['retorno']['produtos'][0][0]['produto'];
			// Sets the success notice.
			update_metadata('post', $this->post_id, '_bling_notices', array( 'status' => 'updated', 'message' => __( 'Produto atualizado com sucesso no bling: '. $product_data['descricao']) ) );
			update_metadata('post', $this->post_id, '_bling_product_sync', 'true');

			if ( 'yes' == BLING_WOOCOMMERCE_DEBUG ) {
				WC_Bling_Helper::write_log('Produto atualizado com sucesso no bling, Nome: '. $product_data['descricao'] );
			}
		}
	}

	/**
	 * Generate the Bling Product xml.
	 *
	 * @param object  WC_Product $product
	 *
	 * @return string  product xml.
	 */
	public function get_xml() {
		$product_data = $this->product->get_data();
		$xml = new WC_Bling_SimpleXML( '<?xml version="1.0" encoding="utf-8"?><produto></produto>' );

		$xml->addChild( 'codigo', $product_data['sku'] );
		$xml->addChild( 'descricao', $product_data['name'] );
		$xml->addChild( 'tipo', 'P' );
		$xml->addChild( 'descricaoCurta', $product_data['short_description'] );
		$xml->addChild( 'descricaoComplementar', $product_data['description'] );
		$xml->addChild( 'un', 'un' );
		$xml->addChild( 'vlr_unit', $product_data['price'] );
		
		if('yes' == BLING_WOOCOMMERCE_DEBUG) {
			WC_Bling_Helper::write_log('PRODUCT DATA');
			WC_Bling_Helper::write_log($this->product->get_data()); //LOG
			WC_Bling_Helper::write_log('XML do produto como array: '); //LOG
			WC_Bling_Helper::write_log($xml); //LOG
			WC_Bling_Helper::write_log('XML do produto: '); //LOG
			WC_Bling_Helper::write_log($xml->asXML()); //LOG
		}

		return $xml->asXML();
	}

}