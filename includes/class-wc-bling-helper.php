<?php


if( !class_exists( 'WC_Bling_Helper' ) ) {
	class WC_Bling_Helper {
		public static $instance;

		public static function get_instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new WC_Bling_Helper();
			}
			return self::$instance;

		}

		/**
		 * Write In log/bling.log response from some requests
		 *
		 * @return void
		 */
		public static function write_log ( $log ) {
			if( 'yes' == BLING_WOOCOMMERCE_DEBUG ) {
				error_log( print_r( $log, true ), 3, BLING_WOOCOMMERCE_DIR.'log/bling.log' );
			}
		}

		/**
		 * Format Zip Code.
		 *
		 * @param  string $zipcode Zip Code.
		 *
		 * @return string          Formated zip code.
		 */
		public static function format_zipcode( $zipcode ) {
			$zipcode = str_replace( array( '.', '-', ',' ), '', trim( $zipcode ) );
			$len     = strlen( $zipcode );

			if ( 8 == $len ) {
				$str_1 = substr( $zipcode, 0, 2 );
				$str_2 = substr( $zipcode, 2, 3 );
				$str_3 = substr( $zipcode, 5, 3 );

				return $str_1 . '.' . $str_2 . '-' . $str_3;
			}

			return false;
		}

		/**
		 * Only numbers.
		 *
		 * @param  mixed $value Value to extract.
		 *
		 * @return int          Number.
		 */
		public static function only_numbers( $value ) {
			$fixed = preg_replace( '([^0-9])', '', $value );

			return $fixed;
		}

	}

	WC_Bling_Helper::get_instance();
}

