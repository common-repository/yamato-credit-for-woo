<?php
/**
 * KUPAYWC_Payment_Response_Handler class.
 *
 * @package Woo - KURONEKO Payment Services
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KUPAYWC_Payment_Response_Handler {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_api_wc_kuronekopayment', array( $this, 'response_handler' ) );
		add_action( 'woocommerce_api_wc_kuronekopayment_transfer', array( $this, 'block_process_payment_transfer' ) );
	}

	/**
	 * Response handler.
	 */
	public function response_handler() {
		if ( ! isset( $_GET['wc-api'] ) || ( 'wc_kuronekopayment' !== $_GET['wc-api'] ) || ! isset( $_REQUEST['dkey'] ) ) {
			return;
		}

		try {
			$order_no    = null;
			$threeDToken = null;
			$wc_kuronekopayment_dkey = 'wc_kuronekopayment_dkey_' . wc_clean( $_REQUEST['dkey'] );

			$order = null;
			$args = array(
				'status'   => 'pending',
				'meta_key' => $wc_kuronekopayment_dkey,
				'limit'    => 1,
			);
			$orders = wc_get_orders( $args );

			if ( ! empty ( $orders ) ) {
				foreach ( $orders as $row_order ) {
					$three_d_token_data = $row_order->get_meta( $wc_kuronekopayment_dkey, true );
					$order_no           = $row_order->get_id();
					$threeDToken        = $three_d_token_data['threeDToken'];
					$order              = $row_order;
				}
			}

			/*
			$args  = array(
				'post_type'      => 'shop_order',
				'meta_key'       => $wc_kuronekopayment_dkey,
				'post_status'    => 'wc-pending',
				'posts_per_page' => 1,
			);
			$query = new WP_Query( $args );
			if ( $query->have_posts() ) {
				// ループ
				while ( $query->have_posts() ) {
					$query->the_post();
					$three_d_token_data = get_post_meta( get_the_ID(), $wc_kuronekopayment_dkey, true );
					$order_no           = get_the_ID();
					$threeDToken        = $three_d_token_data['threeDToken'];
				}
				wp_reset_postdata();
			}
			*/

			if ( empty( $order ) ) {
				wp_safe_redirect( home_url() );

				return;
			}

			$order->delete_meta_data( $wc_kuronekopayment_dkey );
			$order->save();

			$settings = get_option( 'woocommerce_kuronekopayment_settings', array() );

			// 3dセキュアで決済処理
			$SLN                           = new KUPAYWC_SLN_Connection();
			$params                        = array();
			$param_list                    = array();
			$param_list['function_div']    = 'A09';
			$param_list['trader_code']     = $settings['trader_code'];
			$param_list['order_no']        = $order_no;
			$param_list['comp_cd']         = wc_clean( $_REQUEST['COMP_CD'] );
			$param_list['token']           = wc_clean( $_REQUEST['TOKEN'] );
			$param_list['card_exp']        = wc_clean( $_REQUEST['CARD_EXP'] );
			$param_list['item_price']      = wc_clean( $_REQUEST['ITEM_PRICE'] );
			$param_list['item_tax']        = wc_clean( $_REQUEST['ITEM_TAX'] );
			$param_list['cust_cd']         = wc_clean( $_REQUEST['CUST_CD'] );
			$param_list['shop_id']         = wc_clean( $_REQUEST['SHOP_ID'] );
			$param_list['term_cd']         = wc_clean( $_REQUEST['TERM_CD'] );
			$param_list['crd_res_cd']      = wc_clean( $_REQUEST['CRD_RES_CD'] );
			$param_list['res_ve']          = wc_clean( $_REQUEST['RES_VE'] );
			$param_list['res_pa']          = wc_clean( $_REQUEST['RES_PA'] );
			$param_list['res_code']        = wc_clean( $_REQUEST['RES_CODE'] );
			$param_list['three_d_inf']     = $_REQUEST['3D_INF']; // Do not change this data.
			$param_list['three_d_tran_id'] = wc_clean( $_REQUEST['3D_TRAN_ID'] );
			$param_list['send_dt']         = wc_clean( $_REQUEST['SEND_DT'] );
			$param_list['hash_value']      = wc_clean( $_REQUEST['HASH_VALUE'] );
			$param_list['three_d_token']   = $threeDToken;
			$params['param_list']          = $param_list;
			$params['send_url']            = $SLN->send_url_3dsecure();
			$response_token                = $SLN->connection( $params );

			if ( ! isset( $response_token['returnCode'] ) || '1' === $response_token['returnCode'] ) {
				if ( isset( $response_token['errorCode'] ) && 'A092000001' === $response_token['errorCode'] ) {
					// 受付番号重複の時
					// Remove cart.
					WC()->cart->empty_cart();
					// Return thank you page redirect.
					wp_safe_redirect( $order->get_checkout_order_received_url() );

					return;
				}
				// エラー時
				$localized_message = '';
				$error_code = '';
				if ( isset( $response_token['errorCode'] ) ) {
					$error_message = KUPAYWCCodes::errorLabel( $response_token['errorCode'] );
					if ( ! empty( $error_message ) ) {
						$localized_message .= $error_message . '<br />';
					}
					$error_code = $response_token['errorCode'];
					$response_token['errorCode'] = KUPAYWCCodes::errorLabel( $response_token['errorCode'] );
				}
				if ( isset( $response_token['creditErrorCode'] ) ) {
					$error_message = KUPAYWCCodes::errorLabel( $response_token['creditErrorCode'] );
					if ( ! empty( $error_message ) ) {
						$localized_message .= $error_message . '<br />';
					}
					$error_code = $response_token['creditErrorCode'];
					$response_token['creditErrorCode'] = KUPAYWCCodes::errorLabel( $response_token['creditErrorCode'] );
				}

				if ( empty( $localized_message ) ) {
					$localized_message .= __( 'Payment processing failed. Please retry.', 'kupaywc' );
				}
				throw new KUPAYWC_Exception( print_r( $response_token, true ), $localized_message, $error_code );
			}

			$flg        = false;
			$settings   = get_option( 'woocommerce_kuronekopayment_settings', array() );
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
				$this->shipmentEntry( $order_no, $order_no, $flg, $settings );
			}

			$return_url = $order->get_checkout_order_received_url();

			$order_id   = $order->get_id();
			$trans_code = $response_token['crdCResCd'];
			$order->update_meta_data( '_kupaywc_trans_code', $trans_code );
			$order->save();

			$SLN                                        = new KUPAYWC_SLN_Connection();
			$params                                     = array();
			$param_list                                 = array();
			$param_list['function_div']                 = 'E04';
			$param_list['trader_code']                  = $settings['trader_code'];
			$param_list['order_no']                     = $order_id;
			$params['send_url']                         = $SLN->send_trade_info();
			$params['param_list']                       = $param_list;
			$response_data                              = $SLN->connection( $params );
			$response_token['resultData']['statusInfo'] = $response_data['resultData']['statusInfo'];

			KUPAYWC_Payment_Logger::add_log( $response_token, $order_id, $trans_code );

			$order->payment_complete( $trans_code );

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
			KUPAYWC_Logger::add_log( 'Error: ' . $e->getMessage() );

			do_action( 'kupaywc_process_payment_error', $e, $order );

			$order->update_status( 'failed' );
			$checkout_page_id = get_option( 'woocommerce_checkout_page_id' );
			wp_safe_redirect( get_page_link( $checkout_page_id ) . '?ku_pay_error=' . urlencode( $e->getErrorCode() ) );
			die();
		}
	}

	/**
	 * 売上確定処理.
	 *
	 * @param string $order_no Order number.
	 * @param string $slip_no 送り状番号.
	 * @param string $is_delivery_kuroneko クロネコ配送かどうか.
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

		$order = wc_get_order( $order_id );
		$kuroneko_res = $order->get_meta( 'wc_kuronekopayment_block_process_payment_transfer_' . $key, true );
		if ( empty( $kuroneko_res ) ) {
			wc_add_notice( 'パラメータが不正です。', 'error' ); // todo not working
			$checkout_page_id = get_option( 'woocommerce_checkout_page_id' );
			wp_safe_redirect( get_page_link( $checkout_page_id ) );
			die();
		}
		$order->delete_meta_data( 'wc_kuronekopayment_block_process_payment_transfer_' . $key );
		$order->save();
		echo $kuroneko_res['threeDAuthHtml'];
		exit;
	}
}

new KUPAYWC_Payment_Response_Handler();
