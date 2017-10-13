<?php

/**
 * Bling Base Order Class
 */
abstract class WC_Bling_Base_Order {
	protected $order;
	protected $order_number;
	protected $api;

	public function __construct( $order_data, $api ) {
		$this->order = $order_data;
		$this->order_number =  $this->order->get_order_number();
		$this->api = $api;
	}

	public function get_order() {
		return $this->order;
	}

	public function get_order_number() {
		return $this->order_number;
	}
}