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

	protected function do_request( $endpoint, $method = 'POST', $data = array(), $headers = array() ) {
		$params = array(
			'method'    => $method,
			'sslverify' => false,
			'timeout'   => 60
		);

		if ( 'POST' == $method && ! empty( $data ) ) {
			$params['body'] = $data;
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
