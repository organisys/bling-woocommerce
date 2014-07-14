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
			$params['body'] = array(
				'apikey' => $this->integration->access_key,
				'xml'    => rawurlencode( $xml )
			);
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
}
