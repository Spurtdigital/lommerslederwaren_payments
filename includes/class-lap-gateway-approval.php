<?php
if ( ! defined('ABSPATH') ) exit;

class LAP_Gateway_Approval extends WC_Payment_Gateway {

    public function __construct() {
        $this->id                 = 'lap_approval';
        $this->method_title       = __('Betaalverzoek (na goedkeuring)', 'lommers-approval');
        $this->method_description = __('Plaats bestelling zonder directe betaling. Je ontvangt later een betaalverzoek.', 'lommers-approval');
        $this->has_fields         = false;
        $this->supports           = array( 'products' );

        $this->title       = __('Betaalverzoek (na goedkeuring)', 'lommers-approval');
        $this->description = __('Wij keuren je bestelling eerst handmatig. Je ontvangt daarna een betaalverzoek.', 'lommers-approval');

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
        if ( function_exists('is_checkout_pay_page') && is_checkout_pay_page() ) {
            return false; // niet op order-pay
        }
        return function_exists('is_checkout') && is_checkout();
    }

    public function process_payment( $order_id ) {
        $order = wc_get_order( $order_id );
        if ( ! $order ) {
            wc_add_notice(__('Order niet gevonden.', 'lommers-approval'), 'error');
            return array('result' => 'fail');
        }
        if ( function_exists('WC') && WC()->cart ) { WC()->cart->empty_cart(); }
        return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url( $order ),
        );
    }
}
