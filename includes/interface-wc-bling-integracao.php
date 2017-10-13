<?php 

interface WC_Bling_Integracao_Interface {
	/**
	 * Generate XML
	 *
	 * @return void
	 */
	public function get_xml();

	/**
	 * Submit the XML via Bling Api
	 *
	 * @return void
	 */
	public function submit();
}