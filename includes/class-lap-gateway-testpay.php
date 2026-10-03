<?php
if ( ! defined('ABSPATH') ) exit;

class LAP_Gateway_TestPay extends WC_Payment_Gateway {

    public function __construct() {
        $this->id                 = 'lap_testpay';
        $this->method_title       = __('Simuleer betaling (test)', 'lommers-approval');
        $this->method_description = __('Testbetaalmethode die de order direct als betaald markeert. Alleen actief in testmodus en zichtbaar op /order-pay. Optioneel: alleen voor admins.', 'lommers-approval');
        $this->has_fields         = false;
        $this->supports           = array( 'products' );

        $this->title       = __('Simuleer betaling (test)', 'lommers-approval');
        $this->description = __('Markeert je bestelling direct als betaald (alleen test).', 'lommers-approval');

        $this->enabled = 'yes';

        $this->init_form_fields();
        $this->init_settings();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
    }

    public function init_form_fields() {
        $this->form_fields = array(
            'title' => array(
                'title'       => __('Titel', 'lommers-approval'),
                'type'        => 'text',
                'default'     => $this->title,
            ),
            'description' => array(
                'title'       => __('Omschrijving', 'lommers-approval'),
                'type'        => 'textarea',
                'default'     => $this->description,
            ),
        );
    }

    public function is_available() {
        if ( ! get_option('lap_test_mode') ) return false;
        if ( get_option('lap_test_admin_only') && ! current_user_can('manage_woocommerce') ) return false;
        return function_exists('is_checkout_pay_page') && is_checkout_pay_page();
    }

    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wc_add_notice(__('Order niet gevonden.', 'lommers-approval'), 'error');
            return array('result' => 'fail');
        }
        $order->add_order_note(__('TEST: betaling gesimuleerd via testgateway.', 'lommers-approval'));
        $order->payment_complete( 'lap_testpay_' . time() );
        $final = $order->needs_processing() ? 'processing' : 'completed';
        if ( $order->get_status() !== $final ) { $order->set_status($final); $order->save(); }
        if ( function_exists('WC') && WC()->cart ) { WC()->cart->empty_cart(); }
        lap_log("Test gateway → order #$order_id → {$final}");
        return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url( $order ),
        );
    }
}
