<?php
/**
 * WC Bling Integration Class.
 *
 * Create integration with the Bling.
 *
 * @since 1.0.0
 */
class WC_Bling_Integration extends WC_Integration {

    /**
     * Init and hook in the integration.
     *
     * @return void
     */
    public function __construct() {
        global $woocommerce;

        $this->id                 = 'bling';
        $this->method_title       = __( 'Bling', 'wcbling' );
        $this->method_description = __( 'The Bling is an online system that allows you to control the finances, inventory and issue invoices quickly and uncomplicated.', 'wcbling' );

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables.
        $this->access_key = $this->get_option( 'access_key' );
        $this->debug      = $this->get_option( 'debug' );

        // Actions.
        add_action( 'woocommerce_update_options_integration_bling', array( $this, 'process_admin_options' ) );

        // Active logs.
        if ( 'yes' == $this->debug )
            $this->log = $woocommerce->logger();
    }

    /**
     * Initialise Gateway Settings Form Fields.
     *
     * @return void
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'access_key' => array(
                'title'       => __( 'Access Key', 'wcbling' ),
                'type'        => 'text',
                'description' => sprintf( __( 'Please enter your Bling Access Key. This is needed to integration works. Is possible generate a new Access Key %s.', 'wcbling' ), '<a href="http://bling.com.br/configuracoes.api.web.services.php">' . __( 'here', 'wcbling' ) . '</a>' ),
                'default'     => ''
            ),
            'testing' => array(
                'title'       => __( 'Testing', 'wcbling' ),
                'type'        => 'title',
                'description' => ''
            ),
            'debug' => array(
                'title'       => __( 'Debug Log', 'wcbling' ),
                'type'        => 'checkbox',
                'label'       => __( 'Enable logging', 'wcbling' ),
                'default'     => 'no',
                'description' => sprintf( __( 'Log Bling events, such as API requests, inside %s', 'wcbling' ), '<code>woocommerce/logs/bling-' . sanitize_file_name( wp_hash( 'bling' ) ) . '.txt</code>' )
            )
        );
    }
}
