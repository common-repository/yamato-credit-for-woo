<?php
/**
 * KUPAYWC_Payment_Request class.
 *
 * @package Woo - Kuroneko Payment Services
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KUPAYWC_Payment_Request {

	/**
	 * Initialize class actions.
	 *
	 */
	public function __construct() {

		// Don't load for change payment method page.
		if ( isset( $_GET['change_payment_method'] ) ) {
			return;
		}

		$this->init();
	}

	/**
	 * Initialize hooks.
	 *
	 */
	public function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		add_action( 'wc_ajax_kupaywc_get_card_member', array( $this, 'ajax_get_card_member' ) );
	}

	/**
	 * Load public scripts and styles.
	 *
	 */
	public function payment_scripts() {

		if ( ! is_ssl() ) {
			KUPAYWC_Logger::add_log( 'KuronekoWebCollect requires SSL.' );
		}

		if ( ! is_product() && ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
			return;
		}

		wp_register_script( 'kuronekopayment_request_script', plugins_url( 'assets/js/kupaywc-request.js', KUPAYWC_PLUGIN_FILE ), array(), KUPAYWC_VERSION, true );
		$kuronekopayment_request_params                = array();
		$kuronekopayment_request_params['ajax_url']    = WC_AJAX::get_endpoint( '%%endpoint%%' );
		$kuronekopayment_request_params['customer_id'] = get_current_user_id();
		$kuronekopayment_request_params['nonce']       = array(
			'payment'     => wp_create_nonce( 'kupaywc-payment_request' ),
			'card_member' => wp_create_nonce( 'kupaywc-get_card_member' ),
			'checkout'    => wp_create_nonce( 'woocommerce-process_checkout' ),
		);
		wp_localize_script( 'kuronekopayment_request_script', 'kuronekopayment_request_params', apply_filters( 'kuronekopayment_request_params', $kuronekopayment_request_params ) );
		wp_enqueue_script( 'kuronekopayment_request_script' );
	}

	/**
	 * Get card member info.
	 *
	 * @return array $data
	 */
	public function ajax_get_card_member() {
		check_ajax_referer( 'kupaywc-get_card_member', 'security' );

		$data        = array();
		$customer_id = absint( wp_unslash( $_POST['customer_id'] ) );
		$member      = new KUPAYWC_card_member( $customer_id );
		if ( $member->is_card_member() ) {
			$response_member = $member->search_card_member();
			if ( '0' === $response_member['returnCode'] ) {
				$data['cardlast4']  = substr( $response_member['cardData']['maskingCardNo'], - 4 );
				$data['cardfirst4'] = substr( $response_member['cardData']['maskingCardNo'], 0, 4 );
				$data['status']     = 'success';
			} else {
				$data['status'] = 'fail';
			}
		}
		wp_send_json( $data );
	}

	/**
	 * Checks if this is a product page or content contains a product_page shortcode.
	 *
	 * @since 5.2.0
	 * @return boolean
	 */
	public function is_product() {
		return is_product() || wc_post_content_has_shortcode( 'product_page' );
	}
}

new KUPAYWC_Payment_Request();
