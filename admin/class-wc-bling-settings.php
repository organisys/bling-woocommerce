<?php

class WC_Bling_Settings {
	private $fields = array();

	public function __construct() {
		$this->fields = self::init_form_fields();
	}

	public function get_fields() {
		return $this->fields;
	}

	private static function init_form_fields() {
		return array(
			'access_key' => array(
				'title'       => __( 'Chave de Acesso', 'bling-woocommerce' ),
				'type'        => 'text',
				'description' => sprintf( __( 'Insira sua chave de acesso ao Bling para integraçã. Para gerar uma chave de acesso ao bling acesse  %s.', 'bling-woocommerce' ), '<a href="http://bling.com.br/configuracoes.api.web.services.php">' . __( 'Bling Api Key', 'bling-woocommerce' ) . '</a>' ),
				'default'     => ''
			),
			'sendauto' => array(
				'title'       => __( 'Integração Bling', 'bling-woocommerce' ),
				'type'        => 'title',
				'description' => ''
			),
			'sendnfe' => array(
				'title'       => __( 'Integração NF-e', 'bling-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enviar NF-e para Bling', 'bling-woocommerce' ),
				'default'     => 'no',
				'description' => sprintf( __( 'Cria Nota Fiscal eletrônica no Bling a cada venda realizada.', 'bling-woocommerce' ) )
			),
			'transmitnfe' => array(
				'type'        => 'checkbox',
				'label'       => __( 'Transmitir NF-e para Sefaz', 'bling-woocommerce' ),
				'default'     => 'no',
				'description' => sprintf( __( 'Transmite a NF-e criada para SEFAZ, só funcionará se a opção anterior também estiver habilitada.', 'bling-woocommerce' ) )
			),
			'sendnfce' => array(
				'title'       => __( 'Integração NFC-e', 'bling-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enviar NFC-e para Bling', 'bling-woocommerce' ),
				'default'     => 'no',
				'description' => sprintf( __( 'Cria Nota Fiscal do Consumidor eletrônica no Bling a cada venda realizada.', 'bling-woocommerce' ) )
			),
			'transmitnfce' => array(
				'type'        => 'checkbox',
				'label'       => __( 'Transmitir NFC-e para Sefaz', 'bling-woocommerce' ),
				'default'     => 'no',
				'description' => sprintf( __( 'Transmite a NFc-e criada para SEFAZ, só funcionará se a opção anterior também estiver habilitada.', 'bling-woocommerce' ) )
			),
			'testing' => array(
				'title'       => __( 'Testes', 'bling-woocommerce' ),
				'type'        => 'title',
				'description' => ''
			),
			'debug' => array(
				'title'       => __( 'Debug Log', 'bling-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Habilita log', 'bling-woocommerce' ),
				'default'     => 'no',
				'description' =>  __( 'Escreve log dos eventos da API do Bling no diretório do plugin: bling-woocommerce/log/bling.log' ) 
			)
		);
	}
}
