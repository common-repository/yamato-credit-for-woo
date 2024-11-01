<?php
/**
 * WC_Gateway_KuronekoPayment_PayPay class.
 *
 * @extends WC_Payment_Gateway
 * @package Woo - Kuroneko Payment Services
 * @since 1.0.7
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Gateway_KuronekoPayment_PayPay extends WC_Payment_Gateway {

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		$this->id                 = 'kuronekopayment_paypay';
		$this->has_fields         = true;
		$this->method_title       = __( 'KuronekoWebCollect (PayPay)', 'kupaywc' );
		$this->method_description = __( 'PayPay gateway of Kuroneko Web Collect. Only available for billing to Japan.', 'kupaywc' );
		$this->supports           = array(
			'products',
		);

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define user set variables.
		$this->enabled        = $this->get_option( 'enabled' );
		$this->title          = $this->get_option( 'title' );
		$this->description    = $this->get_option( 'description' );
		$this->testmode       = 'yes' === $this->get_option( 'testmode' );
		$this->trader_code    = $this->get_option( 'trader_code' );
		$this->access_key     = $this->get_option( 'access_key' );
		$this->token_code     = $this->get_option( 'token_code' );
		$this->operate_id     = $this->get_option( 'operate_id', '1Gathering' );
		$this->logging        = 'yes' === $this->get_option( 'logging', 'yes' );

		// Hooks.
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Check if this gateway is enabled and available in the user's country.
	 *
	 * @return bool
	 */
	public function is_valid_for_use() {

		return in_array( get_woocommerce_currency(), apply_filters( 'kupaywc_supported_currencies', array( 'JPY' ) ) );
	}


	/**
	 * Admin save options.
	 */
	public function admin_options() {

		if ( $this->is_valid_for_use() ) {
			parent::admin_options();
		} else {
			?>
			<div class="inline error">
				<p>
					<strong><?php echo esc_html__( 'Gateway disabled', 'kupaywc' ); ?></strong>: <?php echo esc_html__( 'KuronekoWebCollect does not support your store currency.', 'kupaywc' ); ?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Checks if required items are set.
	 *
	 * @return bool
	 */
	public function is_valid_setting() {

		if ( empty( $this->trader_code ) || empty( $this->access_key ) || 'no' === $this->enabled ) {
			return false;
		}

		return true;
	}


	/**
	 * Check if the gateway is available for use.
	 *
	 * @return bool
	 */
	public function is_available() {

		if ( ! $this->is_valid_setting() ) {
			return false;
		}

		return parent::is_available();
	}

	/**
	 * Initialise gateway settings form fields.
	 */
	public function init_form_fields() {

		$this->form_fields = apply_filters( 'kupaywc_gateway_paypay_settings',
			array(
				'enabled'        => array(
					'title'   => __( 'Enable/Disable', 'kupaywc' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable KuronekoWebCollect(PayPay)', 'kupaywc' ),
					'default' => 'no',
				),
				'title'          => array(
					'title'       => __( 'Title', 'kupaywc' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'kupaywc' ),
					'default'     => __( 'PayPay payment', 'kupaywc' ),
					'desc_tip'    => true,
				),
				'description'    => array(
					'title'       => __( 'Description', 'kupaywc' ),
					'type'        => 'text',
					'desc_tip'    => true,
					'description' => __( 'This controls the description which the user sees during checkout.', 'kupaywc' ),
					'default'     => __( 'Pay with PayPay.', 'kupaywc' ),
				),
				'testmode'       => array(
					'title'       => __( 'Test mode', 'kupaywc' ),
					'type'        => 'checkbox',
					'label'       => __( 'Enable Test mode', 'kupaywc' ),
					'default'     => 'yes',
					'description' => __( 'Connect to test environment and run in test mode.', 'kupaywc' ),
				),
				'trader_code'    => array(
					'title'       => __( 'Trader Code', 'kupaywc' ),
					'type'        => 'text',
					'description' => __( '9 digits on contract sheet with Kuroneko Web Collect.', 'kupaywc' ),
					'desc_tip'    => true,
				),
				'access_key'     => array(
					'title'       => __( 'Access Key', 'kupaywc' ),
					'type'        => 'text',
					'description' => __( 'Last numerics of access URL on contract sheet with Kuroneko Web Collect.', 'kupaywc' ),
					'default'     => '',
					'desc_tip'    => true,
					'placeholder' => '',
				),
				'operate_id'     => array(
					'title'       => __( 'Operation mode', 'kupaywc' ),
					'type'        => 'select',
					'options'     => array(
						'1Auth'      => __( 'Credit', 'kupaywc' ),
						'1Gathering' => __( 'Credit sales recorded', 'kupaywc' ),
					),
					'default'     => '1Auth',
					'description' => __( 'In case of \'Credit\' setting, it need to change to \'Sales recorded\' manually in later. In case of \'Credit sales recorded\' setting, sales will be recorded at the time of purchase.', 'kupaywc' ),
				),
				'logging'        => array(
					'title'       => __( 'Save the log', 'kupaywc' ),
					'label'       => __( 'Save the log of payment results', 'kupaywc' ),
					'type'        => 'checkbox',
					'description' => __( 'Save the log of payment results to WooCommerce System Status log.', 'kupaywc' ),
					'default'     => 'yes',
					'desc_tip'    => true,
				),
			)
		);
	}

	/**
	 * Payment form on checkout page.
	 */
	public function payment_fields() {

		$description = $this->get_description() ? $this->get_description() : '';

		ob_start();
		echo '<div id="kuronekopayment-paypay-payment-data" name="charge_form">';
		if ( $description ) {
			if ( $this->testmode ) {
				$description .= ' ' . __( 'TESTMODE RUNNING.', 'kupaywc' );
				$description = trim( $description );
			}
			echo apply_filters( 'kupaywc_payment_paypay_description', wpautop( wp_kses_post( $description ) ), $this->id );
		}
		echo '</div>';
		ob_end_flush();
	}

	/**
	 * Outputs scripts.
	 */
	public function payment_scripts() {

		if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) && ! is_add_payment_method_page() && ! isset( $_GET['change_payment_method'] ) ) {
			return;
		}
		if ( 'no' === $this->enabled || empty( $this->trader_code ) || empty( $this->access_key ) ) {
			return;
		}
		wp_register_style( 'kuronekopayment_styles', plugins_url( 'assets/css/kupaywc.css', KUPAYWC_PLUGIN_FILE ), array(), KUPAYWC_VERSION );
		wp_enqueue_style( 'kuronekopayment_styles' );

		wp_register_script( 'kuronekopayment_script', plugins_url( 'assets/js/kupaywc-payment.js', KUPAYWC_PLUGIN_FILE ), array(), KUPAYWC_VERSION, true );
		wp_enqueue_script( 'kuronekopayment_script' );
	}

	/**
	 * Process the payment.
	 *
	 * @param int  $order_id Order ID.
	 * @param bool $retry Retry.
	 *
	 * @return array
	 */
	public function process_payment( $order_id, $retry = true ) {

		$order = wc_get_order( $order_id );

		try {

			$return_url_res     = str_replace( 'http://', 'https://', home_url( '/?wc-api=wc_kuronekopayment_paypay_res_hook' ) );
			$return_url_success = str_replace( 'http://', 'https://', home_url( '/?wc-api=wc_kuronekopayment_paypay&res=success' ) );
			$return_url_failure = str_replace( 'http://', 'https://', home_url( '/?wc-api=wc_kuronekopayment_paypay&res=failure' ) );

			$checksum_text = 'L01';
			$checksum_text .= $this->trader_code;
			$checksum_text .= $order_id;
			$checksum_text .= $order->get_total();
			$checksum_text .= $order->get_billing_last_name() . $order->get_billing_first_name();
			$checksum_text .= $order->get_billing_phone();
			$checksum_text .= $order->get_billing_email();
			$checksum_text .= $return_url_success;
			$checksum_text .= $return_url_res;
			$checksum_text .= $this->access_key;
			$checksum       = hash( 'sha256', $checksum_text );

			$SLN                              = new KUPAYWC_SLN_Connection();
			$params                           = array();
			$param_list                       = array();
			$param_list['function_div']       = 'L01';
			$param_list['trader_code']        = $this->trader_code;
			$param_list['order_no']           = $order_id;
			$param_list['settle_price']       = $order->get_total();
			$param_list['buyer_name_kanji']   = $order->get_billing_last_name() . $order->get_billing_first_name();
			$param_list['buyer_tel']          = $order->get_billing_phone();
			$param_list['buyer_email']        = $order->get_billing_email();
			$param_list['return_url_res']     = $return_url_res;
			$param_list['return_url_success'] = $return_url_success;
			$param_list['return_url_failure'] = $return_url_failure;
			$param_list['check_sum']          = $checksum;
			$param_list['ec_cart_code']       = 'woocommerce';

			$params['param_list'] = $param_list;
			$params['send_url']   = $SLN->send_paypay_settle();
			$response             = $SLN->connection( $params );

			if ( isset( $response['redirectHtml'] ) && isset( $response['redirectHtml'] ) ) {

				$is_block = ( isset( $_POST['is_block'] ) && (bool) $_POST['is_block'] );
				if ( $is_block ) { // ブロックの決済処理.
					$key = uniqid( mt_rand() );
					$order->update_meta_data( 'wc_kuronekopayment_block_process_payment_paypay_transfer_' . $key, $response );
					$order->save();

					return array(
						'result'   => 'success',
						'redirect' => str_replace( 'http://', 'https://', add_query_arg( array(
							'wc-api'   => 'wc_kuronekopayment_paypay_transfer',
							'order_id' => $order_id,
							'key'      => $key,
						), home_url( '/' ) ) ),
					);
				}

				echo $response['redirectHtml'];
				exit;
			}
			if ( ! isset( $response['returnCode'] ) || '1' === $response['returnCode'] ) {
				// エラー時
				$localized_message = '';
				if ( isset( $response['errorCode'] ) ) {
					$error_message = KUPAYWCCodes::errorLabel( $response['errorCode'] );
					if ( ! empty( $error_message ) ) {
						$localized_message .= $error_message . '<br />';
					}
					$response['errorCode'] = KUPAYWCCodes::errorLabel( $response['errorCode'] );
				}

				if ( empty( $localized_message ) ) {
					$localized_message .= __( 'Payment processing failed. Please retry.', 'kupaywc' );
				}
				throw new KUPAYWC_Exception( print_r( $response, true ), $localized_message );
			}
		} catch ( KUPAYWC_Exception $e ) {

			wc_add_notice( $e->getLocalizedMessage(), 'error' );
			KUPAYWC_Logger::add_log( 'Error: ' . $e->getMessage() );

			do_action( 'kupaywc_process_payment_error', $e, $order );

			$order->update_status( 'failed' );

			return array(
				'result'   => 'fail',
				'redirect' => '',
			);
		}
	}
}
