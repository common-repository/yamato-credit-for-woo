<?php
/**
 * KUPAYWC_Payment_Response_Handler class.
 *
 * @package Woo - KURONEKO Payment Services
 * @since 1.0.7
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KUPAYWC_Payment_Response_Handler_PayPay {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_api_wc_kuronekopayment_paypay_res_hook', array( $this, 'response_hook_handler' ) );
		add_action( 'woocommerce_api_wc_kuronekopayment_paypay', array( $this, 'response_handler' ) );
		add_action( 'woocommerce_api_wc_kuronekopayment_paypay_transfer', array( $this, 'block_process_payment_transfer' ) );
	}

	/**
	 * Response handler.
	 */
	public function response_handler() {
		if ( ! isset( $_GET['wc-api'] ) || ( 'wc_kuronekopayment_paypay' !== $_GET['wc-api'] ) || ! isset( $_REQUEST['res'] ) ) {
			return;
		}

		try {

			$return_code     = filter_input( INPUT_POST, 'return_code', FILTER_SANITIZE_SPECIAL_CHARS );
			$error_code      = filter_input( INPUT_POST, 'error_code', FILTER_SANITIZE_SPECIAL_CHARS );
			$return_date     = filter_input( INPUT_POST, 'return_date', FILTER_SANITIZE_SPECIAL_CHARS );
			$order_no        = filter_input( INPUT_POST, 'order_no', FILTER_SANITIZE_SPECIAL_CHARS );
			$res_payinfo_key = filter_input( INPUT_POST, 'res_payinfo_key', FILTER_SANITIZE_SPECIAL_CHARS );
			$check_sum       = filter_input( INPUT_POST, 'check_sum', FILTER_SANITIZE_SPECIAL_CHARS );
			$res             = filter_input( INPUT_GET, 'res', FILTER_SANITIZE_SPECIAL_CHARS );

			$order = wc_get_order( $order_no );
			if ( empty( $order_no ) || empty( $order ) ) {
				wp_safe_redirect( home_url() );

				return;
			}

			$response_data = array(
				'returnCode'      => $return_code,
				'errorCode'       => $error_code,
				'returnDate'      => $return_date,
				'order_no'        => $order_no,
				'res_payinfo_key' => $res_payinfo_key,
				// 'check_sum'
			);

			if ( 'failure' === $res ) {
				// エラー時
				$localized_message = '';
				if ( isset( $error_code ) ) {
					$error_message = KUPAYWCCodes::errorLabel( $error_code );
					if ( ! empty( $error_message ) ) {
						$localized_message .= $error_message . '<br />';
					}
					$response_data['errorCode'] = KUPAYWCCodes::errorLabel( $error_code );
				}
				if ( empty( $localized_message ) ) {
					$localized_message .= __( 'Payment processing failed. Please retry.', 'kupaywc' );
				}
				throw new KUPAYWC_Exception( print_r( $response_data, true ), $localized_message, $error_code );
			}

			$flg        = false;
			$settings   = get_option( 'woocommerce_kuronekopayment_paypay_settings', array() );
			$operate_id = $settings['operate_id'];

			foreach ( $order->get_shipping_methods() as $shipping ) {
				/** @var WC_Order_Item_Shipping $shipping */
				$woocommerce_settings = get_option( 'woocommerce_' . $shipping->get_method_id() . '_' . $shipping->get_instance_id() . '_settings' );
				if ( isset( $woocommerce_settings['kuroneko_delivery_check'] ) && 'yes' === $woocommerce_settings['kuroneko_delivery_check'] ) {
					$flg = true;
				}
			}

			// 配送がヤマト以外の初期決済が「売上確定（実売上）」の場合
			if ( false === $flg && '1Gathering' === $operate_id ) {
				$this->shipmentEntry( $order_no, '', $flg, $settings );
			}

			$return_url = $order->get_checkout_order_received_url();
			$order_id   = $order->get_id();
			$order->update_meta_data( '_kupaywc_trans_code', $res_payinfo_key );
			$order->save();

			$SLN                                       = new KUPAYWC_SLN_Connection();
			$params                                    = array();
			$param_list                                = array();
			$param_list['function_div']                = 'E04';
			$param_list['trader_code']                 = $settings['trader_code'];
			$param_list['order_no']                    = $order_id;
			$params['send_url']                        = $SLN->send_trade_info();
			$params['param_list']                      = $param_list;
			$response_e04                              = $SLN->connection( $params );
			$response_data['resultData']['statusInfo'] = $response_e04['resultData']['statusInfo'];

			KUPAYWC_Payment_Logger::add_log( $response_data, $order_id, $res_payinfo_key );

			$order->payment_complete( $res_payinfo_key );

			if ( false === $flg && '1Gathering' === $operate_id ) {
				$message = __( 'Payment is completed.', 'kupaywc' );
			} else {
				$message = __( 'Credit is completed.', 'kupaywc' );
			}
			$order->add_order_note( $message );

			if ( is_callable( array( $order, 'save' ) ) ) {
				$order->save();
			}

			// Remove cart.
			WC()->cart->empty_cart();

			// Return thank you page redirect.
			wp_safe_redirect( $return_url );

		} catch ( Exception $e ) {
			// エラーの時はメッセージを追加し、購入画面にリダイレクト
			// wc_add_notice( $e->getLocalizedMessage(), 'error' );

			$order->add_order_note( $e->getLocalizedMessage() );

			KUPAYWC_Logger::add_log( 'Error: ' . $e->getMessage() );

			do_action( 'kupaywc_process_payment_error', $e, $order );

			$order->update_status( 'failed' );
			$checkout_page_id = get_option( 'woocommerce_checkout_page_id' );
			wp_safe_redirect( get_page_link( $checkout_page_id ) . '?ku_pay_error=' . urlencode( $e->getErrorCode() ) );
			die();
		}
	}

	/**
	 * 受注確定処理.
	 *
	 * @param string $order_no Order number.
	 * @param string $slip_no 送り状番号.
	 * @param bool   $is_delivery_kuroneko クロネコ配送かどうか.
	 * @param mixed  $settings Settings.
	 *
	 * @return void
	 * @throws KUPAYWC_Exception
	 */
	private function shipmentEntry( $order_no, $slip_no, $is_delivery_kuroneko, $settings ) {

		$SLN        = new KUPAYWC_SLN_Connection();
		$params     = array();
		$param_list = array();

		$param_list['function_div']          = 'E01';
		$param_list['trader_code']           = $settings['trader_code'];
		$param_list['order_no']              = $order_no;
		$param_list['slip_no']               = $slip_no;
		$param_list['delivery_service_code'] = $is_delivery_kuroneko ? '00' : '99';
		$params['param_list']                = $param_list;
		$params['send_url']                  = $SLN->send_shipment_entry();
		$response                            = $SLN->connection( $params );

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
			if ( isset( $response['creditErrorCode'] ) ) {
				$error_message = KUPAYWCCodes::errorLabel( $response['creditErrorCode'] );
				if ( ! empty( $error_message ) ) {
					$localized_message .= $error_message . '<br />';
				}
				$response['creditErrorCode'] = KUPAYWCCodes::errorLabel( $response['creditErrorCode'] );
			}

			if ( empty( $localized_message ) ) {
				$localized_message .= __( 'Payment processing failed. Please retry.', 'kupaywc' );
			}
			throw new KUPAYWC_Exception( print_r( $response, true ), $localized_message );
		}
		$order = wc_get_order( $order_no );
		$order->update_meta_data( '_kupaywc_shipment_entry_end', '1' );
		$order->save();
	}

	/**
	 * Response hook handler.
	 *
	 * @return void
	 */
	public function response_hook_handler() {

		if ( ! isset( $_GET['wc-api'] ) || ( 'wc_kuronekopayment_paypay_res_hook' !== $_GET['wc-api'] ) ) {
			return;
		}

		try {
			$trader_code     = filter_input( INPUT_POST, 'trader_code', FILTER_SANITIZE_SPECIAL_CHARS );
			$order_no        = filter_input( INPUT_POST, 'order_no', FILTER_SANITIZE_SPECIAL_CHARS );
			$settle_price    = filter_input( INPUT_POST, 'settle_price', FILTER_SANITIZE_SPECIAL_CHARS );
			$settle_date     = filter_input( INPUT_POST, 'settle_date', FILTER_SANITIZE_SPECIAL_CHARS );
			$settle_result   = filter_input( INPUT_POST, 'settle_result', FILTER_SANITIZE_SPECIAL_CHARS );
			$settle_detail   = filter_input( INPUT_POST, 'settle_detail', FILTER_SANITIZE_SPECIAL_CHARS );
			$settle_method   = filter_input( INPUT_POST, 'settle_method', FILTER_SANITIZE_SPECIAL_CHARS );
			$res_payinfo_key = filter_input( INPUT_POST, 'res_payinfo_key', FILTER_SANITIZE_SPECIAL_CHARS );

			$order = wc_get_order( $order_no );
			if ( empty( $order_no ) || empty( $order ) ) {
				return;
			}

			if ( ! ( '1' === $settle_result && '91' === $settle_method ) ) {
				return;
			}

			if ( ! ( '11' === $settle_detail || '31' === $settle_detail ) ) {
				return;
			}

			$response_data = array(
				'returnCode'        => '0',
				'orderNo'           => $order_no,
				'res_payinfo_key'   => $res_payinfo_key,
				'paypay_increasing' => 'on',
				'resultData'        => array(
					'statusInfo' => $settle_detail,
				),
			);

			KUPAYWC_Payment_Logger::add_log( $response_data, $order_no, $res_payinfo_key );

			if ( '11' === $settle_detail ) {
				$message = KUPAYWCCodes::statusInfoLabel( $settle_detail ) . 'になりました。';
			} else {
				$message = $message = sprintf( __( '%s is completed.', 'kupaywc' ), KUPAYWCCodes::statusInfoLabel( $settle_detail ) );
			}
			$order->add_order_note( $message );

		} catch ( Exception $e ) {
			// エラーの時はメッセージを追加し、購入画面にリダイレクト
			wc_add_notice( $e->getLocalizedMessage(), 'error' );
			$order->add_order_note( $e->getLocalizedMessage() );

			KUPAYWC_Logger::add_log( 'Error: ' . $e->getMessage() );

			do_action( 'kupaywc_process_payment_error', $e, $order );

			$order->update_status( 'failed' );
			$checkout_page_id = get_option( 'woocommerce_checkout_page_id' );
			wp_safe_redirect( get_page_link( $checkout_page_id ) );
			die();
		}
	}

	/**
	 * ブロックでの支払い処理.画面表示用.
	 *
	 * @return void
	 */
	public function block_process_payment_transfer() {

		$key      = ( isset( $_GET['key'] ) ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';
		$order_id = ( isset( $_GET['order_id'] ) ) ? (int) wc_clean( wp_unslash( $_GET['order_id'] ) ) : 0;
		if ( empty( $key ) || empty( $order_id ) ) {
			wc_add_notice( 'パラメータが不正です。', 'error' ); // todo not working
			$checkout_page_id = get_option( 'woocommerce_checkout_page_id' );
			wp_safe_redirect( get_page_link( $checkout_page_id ) );
			die();
		}

		$order        = wc_get_order( $order_id );
		$kuroneko_res = $order->get_meta( 'wc_kuronekopayment_block_process_payment_paypay_transfer_' . $key, true );
		if ( empty( $kuroneko_res ) ) {
			wc_add_notice( 'パラメータが不正です。', 'error' ); // todo not working
			$checkout_page_id = get_option( 'woocommerce_checkout_page_id' );
			wp_safe_redirect( get_page_link( $checkout_page_id ) );
			die();
		}
		$order->delete_meta_data( 'wc_kuronekopayment_block_process_payment_paypay_transfer_' . $key );
		$order->save();
		echo $kuroneko_res['redirectHtml'];
		exit;
	}
}

new KUPAYWC_Payment_Response_Handler_PayPay();
