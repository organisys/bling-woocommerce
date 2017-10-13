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
	public $api_key;

	public function __construct($apikey) {
		$this->api_key = $apikey;
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
	public function do_request( $endpoint, $method = 'POST', $xml = '', $headers = array(), $numero = '', $serie = 1 ) {
		$url = $this->api_url . $endpoint;

		$post_data = array(
			'apikey' => $this->api_key
		);
		$params = array(
			'method'    => $method,
			'sslverify' => false,
			'timeout'   => 60
		);

		if ( 'POST' == $method ) {

			if( !empty($numero) ) {
				array_push($post_data, array(
					'number' => $numero,
					'serie' => $serie,
					'sendEmail' => true
				) );
			}

			if( !empty($xml) ) {
				if(array_key_exists('number', $post_data)) {
					unset($post_data['number']);
					unset($post_data['serie']);
				}
				array_push($post_data, array(
					'xml' => rawurlencode( $xml )
				));
			}
		}

		if ( ! empty( $headers ) ) {
			$params['headers'] = $headers;
		} else {
			$params['headers'] = array(
				'Content-Type' => 'application/xml;charset=UTF-8'
			);
		}

		$url = add_query_arg($post_data, $url);
		$response = wp_remote_post( $url, $params );

		if ( is_wp_error( $response ) ) {
			if ( 'yes' == BLING_WOOCOMMERCE_DEBUG ) {
				WC_Bling_Helper::write_log( $this->integration->id.' WP_Error: ' . $response->get_error_message() );
			}

			return false;
		}

		return $response;
	}

	/**
	 * Get response.
	 *
	 * @param  array $data
	 *
	 * @return array
	 */
	public function get_response( $response ) {
		$data = array();
		try {
			$data = json_decode( $response['body'], true );
		} catch ( Exception $e ) {
			if ( 'yes' == BLING_WOOCOMMERCE_DEBUG ) {
				WC_Bling_Helper::write_log( 'Error while parsing the response: ' . print_r( $e->getMessage(), true ) );
			}
		}
		return $data;
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

}
