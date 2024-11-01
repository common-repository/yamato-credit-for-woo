<?php
/**
 * KUPAYWC_Admin_Order class.
 *
 * @package Woo - Kuroneko Payment Services
 * @since 1.0.0
 */

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KUPAYWC_Admin_Order {

	/**
	 * Constructor.
	 *
	 */
	public function __construct() {
		add_filter( 'manage_shop_order_posts_columns', array( $this, 'define_columns' ), 20 );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'define_columns' ), 20 ); // HPOS
		add_filter( 'manage_shop_order_posts_custom_column', array( $this, 'render_columns' ), 20, 2 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'render_columns' ), 20, 2 ); // HPOS

		add_action( 'add_meta_boxes', array( $this, 'meta_box' ) );
		add_action( 'wp_ajax_kupaywc_settlement_actions', array( $this, 'ajax_handler' ) );
		wp_enqueue_script( 'jquery-ui-dialog' );
		add_action( 'admin_print_footer_scripts', array( $this, 'settlement_scripts' ) );
		add_filter( 'woocommerce_bulk_action_ids', array( $this, 'kupaywc_woocommerce_bulk_action_ids' ), 20, 2 );
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'kupaywc_woocommerce_order_actions_end' ), 20, 2 );
		if ( true === is_plugin_active( 'woocommerce-subscriptions/woocommerce-subscriptions.php' ) ) {
			function showNotUseNotices() {
				$html = '<div class="error notice">';
				$html .= '<p>' . sprintf( __( 'When using %s, Kuroneko Web Collect for Woo not use.', 'kupaywc' ), __( 'WooCommerce Subscriptions', 'kupaywc' ) ) . '</p>';
				$html .= '</div>';
				echo $html;
			}

			add_action( 'admin_notices', 'showNotUseNotices' );
		};
	}

	/**
	 * Render columm: kupaywc_status.
	 *
	 */
	public function define_columns( $columns ) {
		$columns['kupaywc_status'] = __( 'Payment Status', 'kupaywc' ) . '<br />' . __( '(KuronekoWebCollect)', 'kupaywc' );

		return $columns;
	}

	/**
	 * Render columm: kupaywc_status.
	 *
	 * @param  string  $column  Column ID to render.
	 * @param $post_or_order_object
	 */
	public function render_columns( $column, $post_or_order_object ) {

		if ( 'kupaywc_status' !== $column ) {
			return;
		}
		if ( $post_or_order_object instanceof WC_order ) {
			$order = $post_or_order_object;
		} else {
			$order = wc_get_order( $post_or_order_object );
		}
		if ( ! is_object( $order ) ) {
			return;
		}

		$order_id = $order->get_id();

		$trans_code = $order->get_meta( '_kupaywc_trans_code', true );
		if ( ! $trans_code ) {
			return;
		}
		$latest_log = KUPAYWC_Payment_Logger::get_latest_log( $order_id, $trans_code );
		if ( ! $latest_log ) {
			return;
		}

		$latest_status_number = KUPAYWC_Payment_Logger::get_result_status_number( $order_id, $trans_code );
		if ( '4' === $latest_status_number ) {
			$class = '';
		} elseif ( '2' === $latest_status_number || '3' === $latest_status_number || '30' === $latest_status_number || '31' === $latest_status_number ) {
			$class = 'card-capture';
		} elseif ( '0' === $latest_status_number || '40' === $latest_status_number ) {
			$class = 'card-delete';
		} elseif ( '41' === $latest_status_number ) {
			$class = 'cvs-chg';
		} else {
			$class = 'card-error';
		}

		printf( '<mark class="order-kupaywc-status %s"><span>%s</span></mark>', esc_attr( sanitize_html_class( $class ) ), esc_html( KUPAYWC_Payment_Logger::get_label_status( $order_id, $trans_code ) ) );
	}

	/**
	 * Settlement actions metabox.
	 *
	 */
	public function meta_box() {

		$order_no = wc_get_order( absint( isset( $_GET['id'] ) ? $_GET['id'] : 0 ) );
		$order    = wc_get_order( $order_no );
		if ( ! $order ) {
			return;
		}

		$payment_method = $order->get_payment_method();
		if ( 'kuronekopayment' === $payment_method || 'kuronekopayment_paypay' === $payment_method ) {
			$screen = class_exists( '\Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController' ) && wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
				? wc_get_page_screen_id( 'shop-order' )
				: 'shop_order';

			if ( 'kuronekopayment' === $payment_method ) {
				$label = __( 'KuronekoWebCollect', 'kupaywc' );
			} else {
				$label = __( 'KuronekoWebCollect (PayPay)', 'kupaywc' );
            }
			add_meta_box( 'kupaywc-settlement-actions', $label, array(
				$this,
				'settlement_actions_box'
			), $screen, 'side' );
			add_meta_box( 'kupaywc-invoice-number', __( 'Kuroneko Invoice Number', 'kupaywc' ), array(
				$this,
				'invoice_number_box'
			), $screen, 'side' );
		}
	}

	/**
	 * Settlement actions metabox content.
	 *
	 * @param $post_or_order_object
	 */
	public function settlement_actions_box( $post_or_order_object ) {
		$order = ( $post_or_order_object instanceof WP_Post ) ? wc_get_order( $post_or_order_object->ID ) : $post_or_order_object;
		if ( empty( $order ) ) {
			return;
		}
		$order_id = $order->get_id();
		$payment_method = $order->get_payment_method();

		$trans_code = $order->get_meta( '_kupaywc_trans_code', true );
		if ( empty( $trans_code ) ) {
			$trans_code = kupaywc_init_transaction_code();
		}
		$latest_info = $this->settlement_latest_info( $order_id, $trans_code, $payment_method );
		?>
        <div id="kupaywc-settlement-latest">
			<?php echo $latest_info; ?>
        </div>
        <p id="kupaywc-settlement-latest-button">
            <input type="button" class="button kupaywc-settlement-info"
                   id="kupaywc-<?php echo esc_attr( $order_id ); ?>-<?php echo esc_attr( $trans_code ); ?>-1"
                   value="<?php echo esc_attr__( 'Info', 'kupaywc' ); ?>"/>
        </p>
		<?php
	}

	/**
	 * Settlement actions metabox content.
	 *
	 * @param $post_or_order_object
	 *
	 * @return void
	 */
	public function invoice_number_box( $post_or_order_object ) {
		$order = ( $post_or_order_object instanceof WP_Post ) ? wc_get_order( $post_or_order_object->ID ) : $post_or_order_object;
		if ( empty( $order ) ) {
			return;
		}
		$order_no = $order->get_id();
		$payment_method = $order->get_payment_method();

		$trans_code = $order->get_meta( '_kupaywc_trans_code', true );
		if ( empty( $trans_code ) ) {
			$trans_code = kupaywc_init_transaction_code();
		}
		$shipment_entry_end = $order->get_meta( '_kupaywc_shipment_entry_end', true );
		$invoice_number     = $order->get_meta( 'kuroneko_order_invoice_number', true );
		?>
        <div class="add_invoice_number">
            <p>
                <label for="add_order_invoice_number"><?php esc_html_e( '送り状番号を追加', 'kupaywc' ); ?><?php echo wc_help_tip( __( '発送完了に変更する前に指定してください。', 'kupaywc' ) ); ?></label>
                <input type="text" name="order_invoice_number" id="add_order_invoice_number" class="input-text"
                       value="<?php echo esc_attr( $invoice_number ); ?>"<?php if ( $shipment_entry_end === '1' ) : ?> readonly <?php endif; ?>>
            </p>
        </div>
		<?php

	}

	/**
	 * 管理画面一括操作の処理
	 *
	 * @param $ids
	 * @param $action
	 */

	function kupaywc_woocommerce_bulk_action_ids( $ids, $action ) {
		$return_ids = array();
		foreach ( $ids as $id ) {
			$order          = wc_get_order( $id );
			$payment_method = $order->get_payment_method();
			if ( ( 'kuronekopayment' === $payment_method || 'kuronekopayment_paypay' === $payment_method ) && 'mark_completed' === $action ) {
				if ( $this->kupaywc_order_shipment_process( $id ) ) {
					$return_ids[] = $id;
				}
			} else {
				$return_ids[] = $id;
			}
		}

		return $return_ids;
	}

	/**
	 * 管理画面の受注更新
	 *
	 * @param $post_id
	 * @param $post
	 */
	public function kupaywc_woocommerce_order_actions_end( $order_no, $post ) {
		$order          = wc_get_order( $order_no );
		$payment_method = $order->get_payment_method();
		if ( 'kuronekopayment' === $payment_method || 'kuronekopayment_paypay' === $payment_method ) {
			$order->update_meta_data( 'kuroneko_order_invoice_number', wc_clean( wp_unslash( $_POST['order_invoice_number'] ) ) );
			$order->save();
			//グローバル変数に入れる。
			global $kupaywc;
			$kupaywc->kuroneko_order_invoice_number = wc_clean( wp_unslash( $_POST['order_invoice_number'] ) );
			if ( 'wc-completed' === wc_clean( $_POST['order_status'] ) ) {
				$this->kupaywc_order_shipment_process( $order_no );
			}
		}
	}

	/**
	 * 出荷情報登録
	 *
	 * @param $order_no
	 */
	function kupaywc_order_shipment_process( $order_no ) {
		$order              = wc_get_order( $order_no );
		$payment_method = $order->get_payment_method();
		if ( 'kuronekopayment_paypay' === $payment_method ) {
			$settings = get_option( 'woocommerce_kuronekopayment_paypay_settings', array() );
		} else {
			$settings = get_option( 'woocommerce_kuronekopayment_settings', array() );
		}
		$shipment_entry_end = $order->get_meta( '_kupaywc_shipment_entry_end', true );
		if ( '1' !== $shipment_entry_end ) {
			$is_delivery_kuroneko = false;
			foreach ( $order->get_shipping_methods() as $shipping ) {
				/** @var WC_Order_Item_Shipping $shipping */
				$woocommerce_settings = get_option( 'woocommerce_' . $shipping->get_method_id() . '_' . $shipping->get_instance_id() . '_settings' );
				if ( isset( $woocommerce_settings['kuroneko_delivery_check'] ) && 'yes' === $woocommerce_settings['kuroneko_delivery_check'] ) {
					$is_delivery_kuroneko = true;
				}
			}

			$SLN        = new KUPAYWC_SLN_Connection();
			$params     = array();
			$param_list = array();
			// post_metaに送り状番号が無い場合はグローバル変数から取得する
			global $kupaywc;
			if ( $is_delivery_kuroneko ) {
				$ship_no = $order->get_meta( 'kuroneko_order_invoice_number', true ) ? $order->get_meta( 'kuroneko_order_invoice_number', true ) : $kupaywc->kuroneko_order_invoice_number;
			} else {
				if ( 'kuronekopayment_paypay' === $payment_method ) {
					$ship_no = '';
				} else {
					$ship_no = $order_no;
				}
			}

			$param_list['function_div']          = 'E01';
			$param_list['trader_code']           = $settings['trader_code'];
			$param_list['order_no']              = $order_no;
			$param_list['slip_no']               = $ship_no;
			$param_list['delivery_service_code'] = $is_delivery_kuroneko ? '00' : '99';
			$params['param_list']                = $param_list;
			$params['send_url']                  = $SLN->send_shipment_entry();
			$response                            = $SLN->connection( $params );

			// エラー時
			if ( ! isset( $response['returnCode'] ) || '1' === $response['returnCode'] ) {
				// 注文完了時エラー文が出た時にメールが飛ばないようにする。
				remove_action( 'woocommerce_process_shop_order_meta', 'WC_Meta_Box_Order_Data::save', 40 );
				remove_action( 'woocommerce_process_shop_order_meta', 'WC_Meta_Box_Order_Actions::save', 50 );
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
				WC_Admin_Meta_Boxes::$meta_box_errors[] = '#' . $order_no . 'クロネコの更新失敗 ' . $localized_message;
				$order->add_order_note( __( 'Error during status transition.', 'woocommerce' ) . ' クロネコの更新失敗 ' . $localized_message );

				return false;
			} else {
				// 注文照会の処理
				$order->update_meta_data( '_kupaywc_shipment_entry_end', '1' );
				$order->save();
				$trans_code = $order->get_meta( '_kupaywc_trans_code', true );

				$SLN                                  = new KUPAYWC_SLN_Connection();
				$params                               = array();
				$param_list                           = array();
				$param_list['function_div']           = 'E04';
				$param_list['trader_code']            = $settings['trader_code'];
				$param_list['order_no']               = $order_no;
				$params['send_url']                   = $SLN->send_trade_info();
				$params['param_list']                 = $param_list;
				$response_data                        = $SLN->connection( $params );
				$response['resultData']['statusInfo'] = $response_data['resultData']['statusInfo'];

				if ( 'kuronekopayment_paypay' === $payment_method ) {
					$response['resultData']['crdCResCd']  = $trans_code;
				} else {
					$response['resultData']['crdCResCd']  = $response_data['resultData']['crdCResCd'];
				}

				KUPAYWC_Payment_Logger::add_log( $response, $order_no, $trans_code );

				return true;
			}
		}

		return true;
	}

	/**
	 * Settlement actions latest.
	 *
	 */
	private function settlement_latest_info( $order_id, $trans_code, $payment_method ) {

		$latest     = '';
		$latest_log = KUPAYWC_Payment_Logger::get_latest_log( $order_id, $trans_code );
		if ( $latest_log ) {
			$response         = json_decode( $latest_log['response'], true );
			$latest_operation = '';
			$latest           .= '<table>
				<tr><td colspan="2">' . $latest_operation . '</td></tr>
				<tr><th>' . esc_html__( 'Transaction date', 'kupaywc' ) . ':</th><td>' . esc_html( $latest_log['timestamp'] ) . '</td></tr>
				<tr><th>' . esc_html__( 'Transaction code', 'kupaywc' ) . ':</th><td>' . esc_html( $latest_log['trans_code'] ) . '</td></tr>';
			if ( 'kuronekopayment' === $payment_method && isset( $response['pay_way'] ) && '01' !== $response['pay_way'] ) {
				$latest .= '<tr><th>' . esc_html__( 'The number of installments', 'kupaywc' ) . ':</th><td>' . esc_html( kupaywc_get_paytype( $response['pay_way'] ) ) . '</td></tr>';
			}
			$latest .= '<tr><th>' . esc_html__( 'Status', 'kupaywc' ) . ':</th><td>' . esc_html( KUPAYWC_Payment_Logger::get_label_status( $order_id, $trans_code ) ) . '</td></tr>';
			$latest .= '</table>';
		}

		return $latest;
	}

	/**
	 * Settlement actions history.
	 *
	 */
	private function settlement_history( $order_id, $trans_code ) {
		$history  = '';
		$log_data = KUPAYWC_Payment_Logger::get_log( $order_id, $trans_code );
		if ( $log_data ) {
			$num     = count( $log_data );
			$history = '<table class="kupaywc-settlement-history">
				<thead class="kupaywc-settlement-history-head">
					<tr><th></th><th>' . esc_html__( 'Processing date', 'kupaywc' ) . '</th><th>' . esc_html__( 'Sequence number', 'kupaywc' ) . '</th><th>' . esc_html__( 'Result', 'kupaywc' ) . '</th></tr>
				</thead>
				<tbody class="kupaywc-settlement-history-body">';
			foreach ( (array) $log_data as $data ) {
				$response = json_decode( $data['response'], true );

				if ( isset( $response['resultData']['settleMethod'] ) && '91' === $response['resultData']['settleMethod'] ) {
					$sequence_number = $response['resultData']['idcodeResPayinfoKey'];
				} elseif ( isset( $response['resultData']['crdCResCd'] ) ) {
					$sequence_number = $response['resultData']['crdCResCd'];
				} else {
					$sequence_number = $data['trans_code'];
				}
				$responsecd = isset( KUPAYWCCodes::$StatusInfos[ $response['resultData']['statusInfo'] ] ) ? KUPAYWCCodes::$StatusInfos[ $response['resultData']['statusInfo'] ] : '';
				$class      = ( $response['returnCode'] !== '0' ) ? 'error' : '';
				$history    .= '<tr>
					<td class="num">' . esc_html( $num ) . '</td>
					<td class="datetime">' . esc_html( $data['timestamp'] ) . '</td>
					<td class="transactionid">' . esc_html( $sequence_number ) . '</td>
					<td class="responsecd' . esc_attr( $class ) . '">' . esc_html( $responsecd ) . '</td>
				</tr>';
				$num --;
			}
			$history .= '</tbody>
				</table>';
		}

		return $history;
	}

	/**
	 * AJAX handler that performs settlement actions.
	 *
	 */
	public function ajax_handler() {
		check_ajax_referer( 'kupaywc-settlement_actions', 'security' );

		if ( ! ( current_user_can( 'editor' ) || current_user_can( 'administrator' ) || current_user_can( 'shop_manager' ) || current_user_can( 'edit_shop_orders' ) ) ) {
			wp_die( 'You do not have sufficient permissions to perform this action.', 403 );
		}

		$mode = wc_clean( $_POST['mode'] );
		$data = array();

		switch ( $mode ) {
			// Get latest information
			case 'get_latest_info':
				$order_id       = ( isset( $_POST['order_id'] ) ) ? wc_clean( wp_unslash( $_POST['order_id'] ) ) : 0;
				$order_num      = ( isset( $_POST['order_num'] ) ) ? wc_clean( wp_unslash( $_POST['order_num'] ) ) : 0;
				$trans_code     = ( isset( $_POST['trans_code'] ) ) ? wc_clean( wp_unslash( $_POST['trans_code'] ) ) : '';
				$payment_method = ( isset( $_POST['payment_method'] ) ) ? wc_clean( wp_unslash( $_POST['payment_method'] ) ) : '';
				if ( empty( $order_id ) || empty( $order_num ) || empty( $trans_code ) ) {
					$data['status'] = 'NG';
					break;
				}
				$order = wc_get_order( $order_id );
				if ( empty( $order ) ) {
					$data['status'] = 'NG';
					break;
				}
				$init_code = str_repeat( '9', strlen( $trans_code ) );
				if ( $trans_code === $init_code ) {
					$trans_code         = $order->get_meta( '_kupaywc_trans_code', true );
					$data['trans_code'] = $trans_code;
				}
				$latest_info = $this->settlement_latest_info( $order_id, $trans_code, $payment_method );
				if ( $latest_info ) {
					$data['status'] = 'OK';
					$data['latest'] = $latest_info;
				}
				break;

			// Card - Transaction reference　取引照会
			case 'get_card':
			case 'get_paypay':
				$order_id   = ( isset( $_POST['order_id'] ) ) ? wc_clean( wp_unslash( $_POST['order_id'] ) ) : 0;
				$order_num  = ( isset( $_POST['order_num'] ) ) ? wc_clean( wp_unslash( $_POST['order_num'] ) ) : 0;
				$trans_code = ( isset( $_POST['trans_code'] ) ) ? wp_unslash( $_POST['trans_code'] ) : '';
				if ( empty( $order_id ) || empty( $order_num ) || empty( $trans_code ) ) {
					$data['status'] = 'NG';
					break;
				}
				$res       = '';
				$init_code = str_repeat( '9', strlen( $trans_code ) );
				if ( $trans_code === $init_code ) {
					$data['status'] = 'OK';
					$data['result'] = $res;
				} else {
					$latest_log                 = KUPAYWC_Payment_Logger::get_latest_log( $order_id, $trans_code );
					$latest_response            = json_decode( $latest_log['response'], true );

					$operateid                  = ( isset( $latest_response['OperateId'] ) ) ? $latest_response['OperateId'] : KUPAYWC_Payment_Logger::get_first_operation( $order_id, $trans_code );
					if ( 'get_paypay' === $mode ) {
						$settings = get_option( 'woocommerce_kuronekopayment_paypay_settings', array() );
					} else {
						$settings = get_option( 'woocommerce_kuronekopayment_settings', array() );
					}
					$transaction_date           = kupaywc_get_transaction_date();
					$SLN                        = new KUPAYWC_SLN_Connection();
					$params                     = array();
					$param_list                 = array();
					$param_list['function_div'] = 'E04';
					$param_list['trader_code']  = $settings['trader_code'];
					$param_list['order_no']     = $order_id;
					$params['send_url']         = $SLN->send_trade_info();
					$params['param_list']       = $param_list;
					$response_data              = $SLN->connection( $params );

					if ( 'get_paypay' === $mode ) {
						if ( isset( $latest_response['paypay_increasing'] ) && 'on' === $latest_response['paypay_increasing'] ) {
							global $wpdb;
							$wpdb->delete(
								$wpdb->prefix.'kupaywc_log',
								array(
									'log_id' => (int)$latest_log['log_id']
								),
								array(
									'%d'
								));
							KUPAYWC_Payment_Logger::add_log( $response_data, $order_id, $latest_response['res_payinfo_key'], $latest_response['timestamp'] );
						}
					}

					if ( '0' === $response_data['returnCode'] ) {
						if ( '40' === $response_data['resultData']['statusInfo'] ) {

							$res .= '<span class="kupaywc-settlement-admin card-delete">取消</span>';
							$res .= '</div>';
						} else {
//                            $res .= '<span class="kupaywc-settlement-admin'.esc_attr( $class ).'">'.esc_html( $operation_name ).'</span>';
							$res .= '<table class="kupaywc-settlement-admin-table">';

							if ( isset( $response_data['resultData']['settlePrice'] ) ) {
								$res .= '<tr><th>' . sprintf( __( 'Spending amount (%s)', 'kupaywc' ), get_woocommerce_currency_symbol() ) . '</th>
							<td><input type="text" id="kupaywc-amount_change" value="' . esc_attr( $response_data['resultData']['settlePrice'] ) . '" style="text-align:right;ime-mode:disabled" size="10" /><input type="hidden" id="kupaywc-amount" value="' . esc_attr( $response_data['resultData']['settlePrice'] ) . '" /></td>
							</tr>';
							}
							$res .= '</table>';
							$res .= '<div class="kupaywc-settlement-admin-button">';
							$res .= '<input type="button" id="kupaywc-delete-button" class="button" value="' . esc_attr__( 'Cancel', 'kupaywc' ) . '" />';
							$res .= '<input type="button" id="kupaywc-change-button" class="button" value="' . esc_attr__( 'Amount change', 'kupaywc' ) . '" />';
							$res .= '</div>';
						}
					} else {
						$res           .= '<span class="kupaywc-settlement-admin card-error">' . esc_html__( 'Error', 'kupaywc' ) . '</span>';
						$res           .= '<div class="kupaywc-settlement-admin-error">';
						$error_message = KUPAYWCCodes::errorLabel( $response_data['errorCode'] );
						$res           .= '<div><span class="code">' . esc_html( $response_data['errorCode'] ) . '</span> : <span class="message">' . esc_html( $error_message ) . '</span></div>';
						$res           .= '</div>';
						KUPAYWC_Logger::add_log( '[1Search] Error: ' . print_r( $response_data, true ) );
					}
					$res            .= $this->settlement_history( $order_id, $trans_code );
					$data['status'] = $response_data['resultData']['statusInfo'];
					$data['result'] = $res;
				}
				break;

			// Card - Cancel / Return　取引取消
			case 'delete_card':
				$order_id   = ( isset( $_POST['order_id'] ) ) ? wc_clean( wp_unslash( $_POST['order_id'] ) ) : 0;
				$order_num  = ( isset( $_POST['order_num'] ) ) ? wc_clean( wp_unslash( $_POST['order_num'] ) ) : 0;
				$trans_code = ( isset( $_POST['trans_code'] ) ) ? wc_clean( wp_unslash( $_POST['trans_code'] ) ) : '';
				$amount     = ( isset( $_POST['amount'] ) ) ? wc_clean( wp_unslash( $_POST['amount'] ) ) : '';
				if ( empty( $order_id ) || empty( $order_num ) || empty( $trans_code ) ) {
					$data['status'] = 'NG';
					break;
				}
				$res                        = '';
				$latest_log                 = KUPAYWC_Payment_Logger::get_latest_log( $order_id, $trans_code );
				$latest_response            = json_decode( $latest_log['response'], true );
				$settings                   = get_option( 'woocommerce_kuronekopayment_settings', array() );
				$transaction_date           = kupaywc_get_transaction_date();
				$SLN                        = new KUPAYWC_SLN_Connection();
				$params                     = array();
				$param_list                 = array();
				$param_list['function_div'] = 'A06';
				$param_list['trader_code']  = $settings['trader_code'];
				$param_list['order_no']     = $order_id;
				$params['send_url']         = $SLN->send_credit_cancel();
				$params['param_list']       = $param_list;


				$response_data = $SLN->connection( $params );

				if ( '0' === $response_data['returnCode'] ) {
					$order = wc_get_order( $order_id );
					if ( is_object( $order ) ) {
						$message = sprintf( __( '%s is completed.', 'kupaywc' ), __( 'Cancel', 'kupaywc' ) );
						$order->add_order_note( $message );
					}

					$res .= '<span class="kupaywc-settlement-admin card-delete">取消</span>';
					$res .= '</div>';

				} else {
					$res           .= '<span class="kupaywc-settlement-admin card-error">' . esc_html__( 'Error', 'kupaywc' ) . '</span>';
					$res           .= '<div class="kupaywc-settlement-admin-error">';
					$error_message = KUPAYWCCodes::errorLabel( $response_data['errorCode'] );
					$res           .= '<div><span class="code">' . esc_html( $response_data['errorCode'] ) . '</span> : <span class="message">' . esc_html( $error_message ) . '</span></div>';
					$res           .= '</div>';
					KUPAYWC_Logger::add_log( '[1Delete] Error: ' . print_r( $response_data, true ) );
				}
				$params                     = array();
				$param_list                 = array();
				$param_list['function_div'] = 'E04';
				$param_list['trader_code']  = $settings['trader_code'];
				$param_list['order_no']     = $order_id;
				$params['send_url']         = $SLN->send_trade_info();
				$params['param_list']       = $param_list;
				$response_info              = $SLN->connection( $params );
				do_action( 'kupaywc_action_admin_' . $mode, $response_data, $order_id, $trans_code );
				KUPAYWC_Payment_Logger::add_log( $response_info, $order_id, $trans_code );
				$res            .= $this->settlement_history( $order_id, $trans_code );
				$data['status'] = $response_data['resultData']['statusInfo'];
				$data['result'] = $res;
				break;

			// Card - Amount change　金額変更
			case 'change_card':
				$order_id   = ( isset( $_POST['order_id'] ) ) ? wc_clean( wp_unslash( $_POST['order_id'] ) ) : 0;
				$order_num  = ( isset( $_POST['order_num'] ) ) ? wc_clean( wp_unslash( $_POST['order_num'] ) ) : 0;
				$trans_code = ( isset( $_POST['trans_code'] ) ) ? wc_clean( wp_unslash( $_POST['trans_code'] ) ) : '';
				$amount     = ( isset( $_POST['amount'] ) ) ? wc_clean( wp_unslash( $_POST['amount'] ) ) : '';
				if ( empty( $order_id ) || empty( $order_num ) || empty( $trans_code ) || $amount === '' ) {
					$data['status'] = 'NG';
					break;
				}
				$res                        = '';
				$latest_log                 = KUPAYWC_Payment_Logger::get_latest_log( $order_id, $trans_code );
				$latest_response            = json_decode( $latest_log['response'], true );
				$settings                   = get_option( 'woocommerce_kuronekopayment_settings', array() );
				$transaction_date           = kupaywc_get_transaction_date();
				$SLN                        = new KUPAYWC_SLN_Connection();
				$params                     = array();
				$param_list                 = array();
				$param_list['function_div'] = 'A07';
				$param_list['trader_code']  = $settings['trader_code'];
				$param_list['order_no']     = $order_id;
				$param_list['new_price']    = $amount;
				$params['send_url']         = $SLN->send_change_price();
				$params['param_list']       = $param_list;
				$response_data              = $SLN->connection( $params );

				if ( '0' === $response_data['returnCode'] ) {
					$order = wc_get_order( $order_id );
					if ( is_object( $order ) ) {
						$message = sprintf( __( '%s is completed.', 'kupaywc' ), __( 'Amount change', 'kupaywc' ) );
						$order->add_order_note( $message );
					}

					$res         .= '<table class="kupaywc-settlement-admin-table">';
					$res         .= '<tr><th>' . sprintf( __( 'Spending amount (%s)', 'kupaywc' ), get_woocommerce_currency_symbol() ) . '</th>
					<td><input type="text" id="kupaywc-amount_change" value="' . esc_attr( $amount ) . '" style="text-align:right;ime-mode:disabled" size="10" /><input type="hidden" id="kupaywc-amount" value="' . esc_attr( $amount ) . '" /></td>
					</tr>';
					$res         .= '</table>';
					$res         .= '<div class="kupaywc-settlement-admin-button">';
					$res         .= '<input type="button" id="kupaywc-delete-button" class="button" value="' . esc_attr__( 'Cancel', 'kupaywc' ) . '" />';
					$res         .= '<input type="button" id="kupaywc-change-button" class="button" value="' . esc_attr__( 'Amount change', 'kupaywc' ) . '" />';
					$res         .= '</div>';
					$change_info = '41';
				} else {
					$res           .= '<span class="kupaywc-settlement-admin card-error">' . esc_html__( 'Error', 'kupaywc' ) . '</span>';
					$res           .= '<div class="kupaywc-settlement-admin-error">';
					$error_message = KUPAYWCCodes::errorLabel( $response_data['errorCode'] );
					$res           .= '<div><span class="code">' . esc_html( $response_data['errorCode'] ) . '</span> : <span class="message">' . esc_html( $error_message ) . '</span></div>';
					$res           .= '</div>';
					KUPAYWC_Logger::add_log( '[1Change] Error: ' . print_r( $response_data, true ) );
					$change_info = '17';
				}
				$params                     = array();
				$param_list                 = array();
				$param_list['function_div'] = 'E04';
				$param_list['trader_code']  = $settings['trader_code'];
				$param_list['order_no']     = $order_id;
				$params['send_url']         = $SLN->send_trade_info();
				$params['param_list']       = $param_list;
				$response_info              = $SLN->connection( $params );
				if ( '0' === $response_info['returnCode'] ) {
					$response_info['resultData']['statusInfo'] = $change_info;
				}
				do_action( 'kupaywc_action_admin_' . $mode, $response_data, $order_id, $trans_code );
				KUPAYWC_Payment_Logger::add_log( $response_info, $order_id, $trans_code );
				$res            .= $this->settlement_history( $order_id, $trans_code );
				$data['status'] = $response_info['resultData']["statusInfo"];
				$data['result'] = $res;

				break;

			// Card - Error
			case 'error_card':
				$order_id   = ( isset( $_POST['order_id'] ) ) ? wc_clean( wp_unslash( $_POST['order_id'] ) ) : 0;
				$order_num  = ( isset( $_POST['order_num'] ) ) ? wc_clean( wp_unslash( $_POST['order_num'] ) ) : 0;
				$trans_code = ( isset( $_POST['trans_code'] ) ) ? wc_clean( wp_unslash( $_POST['trans_code'] ) ) : '';
				if ( empty( $order_id ) || empty( $order_num ) || empty( $trans_code ) ) {
					$data['status'] = 'NG';
					break;
				}
				$res         = '';
				$customer_id = ( isset( $_POST['customer_id'] ) ) ? wc_clean( wp_unslash( $_POST['customer_id'] ) ) : 0;
				$member      = new KUPAYWC_Card_Member( $customer_id );
				if ( 0 < $customer_id && $member->is_card_member() ) {
					$response_member = $member->search_card_member();
					if ( '0' === $response_member['returnCode'] ) {
						$order  = wc_get_order( $order_id );
						$amount = $order->get_total();
						$res    .= '<span class="kupaywc-settlement-admin card-error">' . esc_html__( 'Repayment', 'kupaywc' ) . '</span>';
						$res    .= '<table class="kupaywc-settlement-admin-table">';
						$res    .= '<tr><th>' . sprintf( __( 'Spending amount (%s)', 'kupaywc' ), get_woocommerce_currency_symbol() ) . '</th>
						<td><input type="text" id="kupaywc-amount_change" value="' . esc_attr( $amount ) . '" style="text-align:right;ime-mode:disabled" size="10" /><input type="hidden" id="kupaywc-amount" value="' . esc_attr( $amount ) . '" /></td>
						</tr>';
						$res    .= '</table>';
						$res    .= '<div class="kupaywc-settlement-admin-button">';
						$res    .= '<input type="button" id="kupaywc-auth-button" class="button" value="' . esc_attr__( 'Credit', 'kupaywc' ) . '" />';
						$res    .= '<input type="button" id="kupaywc-gathering-button" class="button" value="' . esc_attr__( 'Credit sales recorded', 'kupaywc' ) . '" />';
						$res    .= '</div>';
						$res    .= $this->settlement_history( $order_id, $trans_code );
					} else {
						$res .= '<span class="kupaywc-settlement-admin card-error">' . esc_html__( 'Payment error', 'kupaywc' ) . '</span>';
						$res .= '<div class="kupaywc-settlement-admin-error">';
						$res .= '<div><span class="message">' . esc_html__( 'Not registered of card member information', 'kupaywc' ) . '</span></div>';
						$res .= '</div>';
					}
					$data['status'] = $response_member['returnCode'];
					$data['result'] = $res;
				} else {
					$res            .= '<span class="kupaywc-settlement-admin card-error">' . esc_html__( 'Payment error', 'kupaywc' ) . '</span>';
					$res            .= '<div class="kupaywc-settlement-admin-error">';
					$res            .= '<div><span class="message">' . esc_html__( 'Not registered of card member information', 'kupaywc' ) . '</span></div>';
					$res            .= '</div>';
					$data['status'] = 'NG';
					$data['result'] = $res;
				}
				break;

			// paypay - Cancel / Return　取引取消
			case 'delete_paypay':
				$order_id   = ( isset( $_POST['order_id'] ) ) ? wc_clean( wp_unslash( $_POST['order_id'] ) ) : 0;
				$order_num  = ( isset( $_POST['order_num'] ) ) ? wc_clean( wp_unslash( $_POST['order_num'] ) ) : 0;
				$trans_code = ( isset( $_POST['trans_code'] ) ) ? wc_clean( wp_unslash( $_POST['trans_code'] ) ) : '';
				$amount     = ( isset( $_POST['amount'] ) ) ? wc_clean( wp_unslash( $_POST['amount'] ) ) : '';
				if ( empty( $order_id ) || empty( $order_num ) || empty( $trans_code ) ) {
					$data['status'] = 'NG';
					break;
				}
				$res                        = '';
				$latest_log                 = KUPAYWC_Payment_Logger::get_latest_log( $order_id, $trans_code );
				$latest_response            = json_decode( $latest_log['response'], true );
				$settings                   = get_option( 'woocommerce_kuronekopayment_paypay_settings', array() );
				$transaction_date           = kupaywc_get_transaction_date();
				$SLN                        = new KUPAYWC_SLN_Connection();
				$params                     = array();
				$param_list                 = array();
				$param_list['function_div'] = 'L02';
				$param_list['trader_code']  = $settings['trader_code'];
				$param_list['order_no']     = $order_id;
				$params['send_url']         = $SLN->send_paypay_settle_cancel();
				$params['param_list']       = $param_list;


				$response_data = $SLN->connection( $params );

				if ( '0' === $response_data['returnCode'] ) {
					$order = wc_get_order( $order_id );
					if ( is_object( $order ) ) {
						$message = sprintf( __( '%s is completed.', 'kupaywc' ), __( 'Cancel', 'kupaywc' ) );
						$order->add_order_note( $message );
					}

					$res .= '<span class="kupaywc-settlement-admin card-delete">取消</span>';
					$res .= '</div>';

				} else {
					$res           .= '<span class="kupaywc-settlement-admin card-error">' . esc_html__( 'Error', 'kupaywc' ) . '</span>';
					$res           .= '<div class="kupaywc-settlement-admin-error">';
					$error_message = KUPAYWCCodes::errorLabel( $response_data['errorCode'] );
					$res           .= '<div><span class="code">' . esc_html( $response_data['errorCode'] ) . '</span> : <span class="message">' . esc_html( $error_message ) . '</span></div>';
					$res           .= '</div>';
					KUPAYWC_Logger::add_log( '[1Delete] Error: ' . print_r( $response_data, true ) );
				}
				$params                     = array();
				$param_list                 = array();
				$param_list['function_div'] = 'E04';
				$param_list['trader_code']  = $settings['trader_code'];
				$param_list['order_no']     = $order_id;
				$params['send_url']         = $SLN->send_trade_info();
				$params['param_list']       = $param_list;
				$response_info              = $SLN->connection( $params );
				do_action( 'kupaywc_action_admin_' . $mode, $response_data, $order_id, $trans_code );
				KUPAYWC_Payment_Logger::add_log( $response_info, $order_id, $trans_code );
				$res            .= $this->settlement_history( $order_id, $trans_code );
				$data['status'] = $response_data['resultData']['statusInfo'];
				$data['result'] = $res;
				break;

			// paypay - Amount change　金額変更
			case 'change_paypay':
				$order_id   = ( isset( $_POST['order_id'] ) ) ? wc_clean( wp_unslash( $_POST['order_id'] ) ) : 0;
				$order_num  = ( isset( $_POST['order_num'] ) ) ? wc_clean( wp_unslash( $_POST['order_num'] ) ) : 0;
				$trans_code = ( isset( $_POST['trans_code'] ) ) ? wc_clean( wp_unslash( $_POST['trans_code'] ) ) : '';
				$amount     = ( isset( $_POST['amount'] ) ) ? wc_clean( wp_unslash( $_POST['amount'] ) ) : '';
				if ( empty( $order_id ) || empty( $order_num ) || empty( $trans_code ) || $amount === '' ) {
					$data['status'] = 'NG';
					break;
				}
				$res                        = '';
				$latest_log                 = KUPAYWC_Payment_Logger::get_latest_log( $order_id, $trans_code );
				$latest_response            = json_decode( $latest_log['response'], true );
				$settings                   = get_option( 'woocommerce_kuronekopayment_paypay_settings', array() );
				$transaction_date           = kupaywc_get_transaction_date();
				$SLN                        = new KUPAYWC_SLN_Connection();
				$params                     = array();
				$param_list                 = array();
				$param_list['function_div'] = 'L03';
				$param_list['trader_code']  = $settings['trader_code'];
				$param_list['order_no']     = $order_id;
				$param_list['new_price']    = $amount;
				$params['send_url']         = $SLN->send_paypay_change_price();
				$params['param_list']       = $param_list;
				$response_data              = $SLN->connection( $params );

				if ( '0' === $response_data['returnCode'] ) {
					$order = wc_get_order( $order_id );
					if ( is_object( $order ) ) {
						$message = sprintf( __( '%s is completed.', 'kupaywc' ), __( 'Amount change', 'kupaywc' ) );
						$order->add_order_note( $message );
					}
					$is_sold = false;
					$logs    = KUPAYWC_Payment_Logger::get_log( $order_id, $trans_code );
					foreach ( $logs as $log_row ) {
						$log_row_response = json_decode( $log_row['response'], true );

						if ( ! $is_sold && isset( $log_row_response['resultData']['statusInfo'] ) && '31' === $log_row_response['resultData']['statusInfo'] ) {
							$is_sold = true;
							break;
						}
					}

					if ( $is_sold ) {
						$change_info = '41';
					} else {
						$response_data['resultData']['statusInfo'] = '41';
						KUPAYWC_Payment_Logger::add_log( $response_data, $order_id, $trans_code );
						$change_info = '31';
					}

					$res         .= '<table class="kupaywc-settlement-admin-table">';
					$res         .= '<tr><th>' . sprintf( __( 'Spending amount (%s)', 'kupaywc' ), get_woocommerce_currency_symbol() ) . '</th>
					<td><input type="text" id="kupaywc-amount_change" value="' . esc_attr( $amount ) . '" style="text-align:right;ime-mode:disabled" size="10" /><input type="hidden" id="kupaywc-amount" value="' . esc_attr( $amount ) . '" /></td>
					</tr>';
					$res         .= '</table>';
					$res         .= '<div class="kupaywc-settlement-admin-button">';
					$res         .= '<input type="button" id="kupaywc-delete-button" class="button" value="' . esc_attr__( 'Cancel', 'kupaywc' ) . '" />';
					$res         .= '<input type="button" id="kupaywc-change-button" class="button" value="' . esc_attr__( 'Amount change', 'kupaywc' ) . '" />';
					$res         .= '</div>';
				} elseif ( '2' === $response_data['returnCode'] ) {
					$order = wc_get_order( $order_id );
					if ( is_object( $order ) ) {
						$message = sprintf( __( '%s is completed.', 'kupaywc' ), __( 'Amount change', 'kupaywc' ) ) . __( 'Waiting for increase approval.', 'kupaywc' );
						$order->add_order_note( $message );
					}

					$res         .= '<table class="kupaywc-settlement-admin-table">';
					$res         .= '<tr><th>' . sprintf( __( 'Spending amount (%s)', 'kupaywc' ), get_woocommerce_currency_symbol() ) . '</th>
					<td><input type="text" id="kupaywc-amount_change" value="' . esc_attr( $amount ) . '" style="text-align:right;ime-mode:disabled" size="10" /><input type="hidden" id="kupaywc-amount" value="' . esc_attr( $amount ) . '" /></td>
					</tr>';
					$res         .= '</table>';
					$res         .= '<div class="kupaywc-settlement-admin-button">';
					$res         .= '<input type="button" id="kupaywc-delete-button" class="button" value="' . esc_attr__( 'Cancel', 'kupaywc' ) . '" />';
					$res         .= '<input type="button" id="kupaywc-change-button" class="button" value="' . esc_attr__( 'Amount change', 'kupaywc' ) . '" />';
					$res         .= '</div>';
					$change_info = '23';
				} else {
					$res           .= '<span class="kupaywc-settlement-admin card-error">' . esc_html__( 'Error', 'kupaywc' ) . '</span>';
					$res           .= '<div class="kupaywc-settlement-admin-error">';
					$error_message = KUPAYWCCodes::errorLabel( $response_data['errorCode'] );
					$res           .= '<div><span class="code">' . esc_html( $response_data['errorCode'] ) . '</span> : <span class="message">' . esc_html( $error_message ) . '</span></div>';
					$res           .= '</div>';
					KUPAYWC_Logger::add_log( '[1Change] Error: ' . print_r( $response_data, true ) );
					$order = wc_get_order( $order_id );
					if ( is_object( $order ) ) {
						$order->add_order_note( $error_message );
					}
					$change_info = '17';
				}
				$params                     = array();
				$param_list                 = array();
				$param_list['function_div'] = 'E04';
				$param_list['trader_code']  = $settings['trader_code'];
				$param_list['order_no']     = $order_id;
				$params['send_url']         = $SLN->send_trade_info();
				$params['param_list']       = $param_list;
				$response_info              = $SLN->connection( $params );
				if ( '0' === $response_info['returnCode'] ) {
					$response_info['resultData']['statusInfo'] = $change_info;
				}
				do_action( 'kupaywc_action_admin_' . $mode, $response_data, $order_id, $trans_code );
				KUPAYWC_Payment_Logger::add_log( $response_info, $order_id, $trans_code );
				$res            .= $this->settlement_history( $order_id, $trans_code );
				$data['status'] = $response_info['resultData']["statusInfo"];
				$data['result'] = $res;

				break;
		}
		wp_send_json( $data );
	}

	/**
	 * Outputs scripts.
	 *
	 */
	public function settlement_scripts() {
		$screen = get_current_screen();
		if ( $screen->id === 'woocommerce_page_wc-orders' ) {
			$order_id = wc_get_order( absint( isset( $_GET['id'] ) ? $_GET['id'] : 0 ) );
			$order    = wc_get_order( $order_id );
			if ( ! $order ) {
				return;
			}
			$order_id = $order->get_id();
		} else {
			global $post, $post_type, $pagenow;
			if ( ! is_object( $post ) ) {
				return;
			}
			if ( ! current_user_can( 'edit_shop_orders' ) ) {
				return;
			}
			if ( 'post.php' !== $pagenow && 'shop_order' !== $post_type ) {
				return;
			}

			$order_id = absint( $post->ID );
			$order    = wc_get_order( $order_id );
		}

		if ( ! $order ) {
			return;
		}

		$nonce                = wp_create_nonce( 'kupaywc-settlement_actions' );
		$customer_id          = $order->get_customer_id();
		$payment_method       = $order->get_payment_method();
		$payment_method_title = $order->get_payment_method_title();
		?>
        <div id="kupaywc-settlement-dialog" title="">
            <div id="kupaywc-settlement-response-loading"></div>
            <fieldset>
                <div id="kupaywc-settlement-response"></div>
                <input type="hidden" id="kupaywc-order_id">
                <input type="hidden" id="kupaywc-order_num">
                <input type="hidden" id="kupaywc-trans_code">
                <input type="hidden" id="kupaywc-error"/>
            </fieldset>
        </div>
        <script type="text/javascript">
            jQuery(document).ready(function ($) {

                kupaywc_admin_order = {
                    payment_method: '<?php esc_attr_e( $payment_method ); ?>',

                    loadingOn: function () {
                        $('#kupaywc-settlement-response-loading').html('<img src="<?php echo admin_url(); ?>images/loading.gif" />');
                    },

                    loadingOff: function () {
                        $('#kupaywc-settlement-response-loading').html('');
                    },

                    getSettlementLatestInfo: function (payment_method) {
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            cache: false,
                            dataType: 'json',
                            data: {
                                action: 'kupaywc_settlement_actions',
                                mode: 'get_latest_info',
                                order_id: <?php echo $order_id; ?>,
                                order_num: $('#kupaywc-order_num').val(),
                                trans_code: $('#kupaywc-trans_code').val(),
                                customer_id: <?php echo $customer_id; ?>,
                                payment_method: payment_method,
                                security: '<?php echo $nonce; ?>'
                            }
                        }).done(function (retVal, dataType) {
                            $('#kupaywc-settlement-latest').html(retVal.latest);
                            if (retVal.trans_code != undefined) {
                                var init_id = '#kupaywc-<?php echo $order_id; ?>-' + $('#kupaywc-trans_code').val() + '-1';
                                var new_id = '#kupaywc-<?php echo $order_id; ?>-' + retVal.trans_code + '-1';
                                $(init_id).attr('id', new_id);
                                //$( '#kupaywc-trans_code' ).val( retVal.trans_code );
                            }
                        }).fail(function (retVal) {
                            window.console.log(retVal);
                        });
                        return false;
                    },

					<?php if ( 'kuronekopayment' === $payment_method ) : ?>
                    getSettlementInfoCard: function () {
                        kupaywc_admin_order.loadingOn();
                        var mode = ('' != $('#kupaywc-error').val()) ? 'error_card' : 'get_card';
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            cache: false,
                            dataType: 'json',
                            data: {
                                action: 'kupaywc_settlement_actions',
                                mode: mode,
                                order_id: <?php echo $order_id; ?>,
                                order_num: $('#kupaywc-order_num').val(),
                                trans_code: $('#kupaywc-trans_code').val(),
                                customer_id: <?php echo $customer_id; ?>,
                                security: '<?php echo $nonce; ?>',
                            }
                        }).done(function (retVal, dataType) {
                            $('#kupaywc-settlement-response').html(retVal.result);
                        }).fail(function (retVal) {
                            window.console.log(retVal);
                        }).always(function (retVal) {
                            kupaywc_admin_order.loadingOff();
                        });
                        return false;
                    },

                    captureSettlementCard: function (amount) {
                        kupaywc_admin_order.loadingOn();

                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            cache: false,
                            dataType: 'json',
                            data: {
                                action: 'kupaywc_settlement_actions',
                                mode: 'capture_card',
                                order_id: <?php echo $order_id; ?>,
                                order_num: $('#kupaywc-order_num').val(),
                                trans_code: $('#kupaywc-trans_code').val(),
                                customer_id: <?php echo $customer_id; ?>,
                                amount: amount,
                                security: '<?php echo $nonce; ?>'
                            }
                        }).done(function (retVal, dataType) {
                            $('#kupaywc-settlement-response').html(retVal.result);
                        }).fail(function (retVal) {
                            window.console.log(retVal);
                        }).always(function (retVal) {
                            $('#kupaywc-settlement-response-loading').html('');
                        });
                        return false;
                    },

                    changeSettlementCard: function (amount) {
                        kupaywc_admin_order.loadingOn();

                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            cache: false,
                            dataType: 'json',
                            data: {
                                action: 'kupaywc_settlement_actions',
                                mode: 'change_card',
                                order_id: <?php echo $order_id; ?>,
                                order_num: $('#kupaywc-order_num').val(),
                                trans_code: $('#kupaywc-trans_code').val(),
                                customer_id: <?php echo $customer_id; ?>,
                                amount: amount,
                                security: '<?php echo $nonce; ?>'
                            }
                        }).done(function (retVal, dataType) {
                            $('#kupaywc-settlement-response').html(retVal.result);
                        }).fail(function (retVal) {
                            window.console.log(retVal);
                        }).always(function (retVal) {
                            kupaywc_admin_order.loadingOff();
                        });
                        return false;
                    },

                    deleteSettlementCard: function (amount) {
                        kupaywc_admin_order.loadingOn();

                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            cache: false,
                            dataType: 'json',
                            data: {
                                action: 'kupaywc_settlement_actions',
                                mode: 'delete_card',
                                order_id: <?php echo $order_id; ?>,
                                order_num: $('#kupaywc-order_num').val(),
                                trans_code: $('#kupaywc-trans_code').val(),
                                customer_id: <?php echo $customer_id; ?>,
                                amount: amount,
                                security: '<?php echo $nonce; ?>'
                            }
                        }).done(function (retVal, dataType) {
                            $('#kupaywc-settlement-response').html(retVal.result);
                        }).fail(function (retVal) {
                            window.console.log(retVal);
                        }).always(function (retVal) {
                            kupaywc_admin_order.loadingOff();
                        });
                        return false;
                    },

                    authSettlementCard: function (mode, amount) {
                        kupaywc_admin_order.loadingOn();

                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            cache: false,
                            dataType: 'json',
                            data: {
                                action: 'kupaywc_settlement_actions',
                                mode: mode + '_card',
                                order_id: <?php echo $order_id; ?>,
                                order_num: $('#kupaywc-order_num').val(),
                                trans_code: $('#kupaywc-trans_code').val(),
                                customer_id: <?php echo $customer_id; ?>,
                                amount: amount,
                                security: '<?php echo $nonce; ?>'
                            }
                        }).done(function (retVal, dataType) {
                            $('#kupaywc-settlement-response').html(retVal.result);
                        }).fail(function (retVal) {
                            window.console.log(retVal);
                        }).always(function (retVal) {
                            kupaywc_admin_order.loadingOff();
                        });
                        return false;
                    }

					<?php endif; ?>
	                <?php if ( 'kuronekopayment_paypay' === $payment_method ) : ?>
                    getSettlementInfoPaypay: function () {
                        kupaywc_admin_order.loadingOn();
                        var mode = ('' != $('#kupaywc-error').val()) ? 'error_paypay' : 'get_paypay';
                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            cache: false,
                            dataType: 'json',
                            data: {
                                action: 'kupaywc_settlement_actions',
                                mode: mode,
                                order_id: <?php echo $order_id; ?>,
                                order_num: $('#kupaywc-order_num').val(),
                                trans_code: $('#kupaywc-trans_code').val(),
                                customer_id: <?php echo $customer_id; ?>,
                                security: '<?php echo $nonce; ?>',
                            }
                        }).done(function (retVal, dataType) {
                            $('#kupaywc-settlement-response').html(retVal.result);
                        }).fail(function (retVal) {
                            window.console.log(retVal);
                        }).always(function (retVal) {
                            kupaywc_admin_order.loadingOff();
                        });
                        return false;
                    },

                    captureSettlementPaypay: function (amount) {
                        kupaywc_admin_order.loadingOn();

                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            cache: false,
                            dataType: 'json',
                            data: {
                                action: 'kupaywc_settlement_actions',
                                mode: 'capture_paypay',
                                order_id: <?php echo $order_id; ?>,
                                order_num: $('#kupaywc-order_num').val(),
                                trans_code: $('#kupaywc-trans_code').val(),
                                customer_id: <?php echo $customer_id; ?>,
                                amount: amount,
                                security: '<?php echo $nonce; ?>'
                            }
                        }).done(function (retVal, dataType) {
                            $('#kupaywc-settlement-response').html(retVal.result);
                        }).fail(function (retVal) {
                            window.console.log(retVal);
                        }).always(function (retVal) {
                            $('#kupaywc-settlement-response-loading').html('');
                        });
                        return false;
                    },

                    changeSettlementPaypay: function (amount) {
                        kupaywc_admin_order.loadingOn();

                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            cache: false,
                            dataType: 'json',
                            data: {
                                action: 'kupaywc_settlement_actions',
                                mode: 'change_paypay',
                                order_id: <?php echo $order_id; ?>,
                                order_num: $('#kupaywc-order_num').val(),
                                trans_code: $('#kupaywc-trans_code').val(),
                                customer_id: <?php echo $customer_id; ?>,
                                amount: amount,
                                security: '<?php echo $nonce; ?>'
                            }
                        }).done(function (retVal, dataType) {
                            $('#kupaywc-settlement-response').html(retVal.result);
                        }).fail(function (retVal) {
                            window.console.log(retVal);
                        }).always(function (retVal) {
                            kupaywc_admin_order.loadingOff();
                        });
                        return false;
                    },

                    deleteSettlementPaypay: function (amount) {
                        kupaywc_admin_order.loadingOn();

                        $.ajax({
                            url: ajaxurl,
                            type: 'POST',
                            cache: false,
                            dataType: 'json',
                            data: {
                                action: 'kupaywc_settlement_actions',
                                mode: 'delete_paypay',
                                order_id: <?php echo $order_id; ?>,
                                order_num: $('#kupaywc-order_num').val(),
                                trans_code: $('#kupaywc-trans_code').val(),
                                customer_id: <?php echo $customer_id; ?>,
                                amount: amount,
                                security: '<?php echo $nonce; ?>'
                            }
                        }).done(function (retVal, dataType) {
                            $('#kupaywc-settlement-response').html(retVal.result);
                        }).fail(function (retVal) {
                            window.console.log(retVal);
                        }).always(function (retVal) {
                            kupaywc_admin_order.loadingOff();
                        });
                        return false;
                    },

	                <?php endif; ?>
                };

                $('#kupaywc-settlement-dialog').dialog({
                    bgiframe: true,
                    autoOpen: false,
                    height: 'auto',
                    width: 'auto',
                    modal: true,
                    resizable: true,
                    buttons: {
                        "<?php _e( 'Close' ); ?>": function () {
                            $(this).dialog('close');
                        }
                    },
                    open: function () {
	                    <?php if( 'kuronekopayment' === $payment_method || 'kuronekopayment_paypay' === $payment_method ) : ?>
                        if ( kupaywc_admin_order.payment_method === 'kuronekopayment_paypay' ) {
                            kupaywc_admin_order.getSettlementInfoPaypay();
                        } else {
                            kupaywc_admin_order.getSettlementInfoCard();
                        }
                        <?php endif; ?>
                    },
                    close: function () {
                        kupaywc_admin_order.getSettlementLatestInfo('<?php echo esc_attr( $payment_method ); ?>');
                    }
                });

                $(document).on('click', '.kupaywc-settlement-info', function () {
                    var idname = $(this).attr('id');
                    var ids = idname.split('-');
                    $('#kupaywc-trans_code').val(ids[2]);
                    $('#kupaywc-order_num').val(ids[3]);
                    $('#kupaywc-error').val('');
                    $('#kupaywc-settlement-dialog').dialog('option', 'title', '<?php echo $payment_method_title; ?>');
                    $('#kupaywc-settlement-dialog').dialog('open');
                });

	            <?php if ( 'kuronekopayment' === $payment_method || 'kuronekopayment_paypay' === $payment_method ) : ?>
                $(document).on('click', '#kupaywc-capture-button', function () {
                    if (!confirm("<?php _e( 'Are you sure you want to execute a processing sales recording?', 'kupaywc' ); ?>")) {
                        return;
                    }
                    kupaywc_admin_order.captureSettlementCard($('#kupaywc-amount_change').val());
                });

                $(document).on('click', '#kupaywc-delete-button', function () {
                    if (!confirm("<?php _e( 'Are you sure you want to a processing of cancellation?', 'kupaywc' ); ?>")) {
                        return;
                    }
                    if ( kupaywc_admin_order.payment_method === 'kuronekopayment_paypay' ) {
                        kupaywc_admin_order.deleteSettlementPaypay($('#kupaywc-amount_change').val());
                    } else {
                        kupaywc_admin_order.deleteSettlementCard($('#kupaywc-amount_change').val());
                    }
                });

                $(document).on('click', '#kupaywc-change-button', function () {
                    if ($('#kupaywc-amount_change').val() == $('#kupaywc-amount').val()) {
                        return;
                    }
                    var amount = $('#kupaywc-amount_change').val();
                    if (amount == "" || parseInt(amount) === 0 || !$.isNumeric(amount)) {
                        alert("<?php _e( 'The spending amount format is incorrect. Please enter with numeric value.', 'kupaywc' ); ?>");
                        return;
                    }
                    if (!confirm("<?php _e( 'Are you sure you want to change the spending amount?', 'kupaywc' ); ?>")) {
                        return;
                    }
                    if ( kupaywc_admin_order.payment_method === 'kuronekopayment_paypay' ) {
                        kupaywc_admin_order.changeSettlementPaypay($('#kupaywc-amount_change').val());
                    } else {
                        kupaywc_admin_order.changeSettlementCard($('#kupaywc-amount_change').val());
                    }
                });

                $(document).on('click', '#kupaywc-auth-button', function () {
                    var amount = $('#kupaywc-amount_change').val();
                    if (amount == "" || parseInt(amount) === 0 || !$.isNumeric(amount)) {
                        alert("<?php _e( 'The spending amount format is incorrect. Please enter with numeric value.', 'kupaywc' ); ?>");
                        return;
                    }
                    if (!confirm("<?php _e( 'Are you sure you want to execute a processing of credit?', 'kupaywc' ); ?>")) {
                        return;
                    }
                    kupaywc_admin_order.authSettlementCard('auth', $('#kupaywc-amount_change').val());
                });

                $(document).on('click', '#kupaywc-gathering-button', function () {
                    var amount = $('#kupaywc-amount_change').val();
                    if (amount == "" || parseInt(amount) === 0 || !$.isNumeric(amount)) {
                        alert("<?php _e( 'The spending amount format is incorrect. Please enter with numeric value.', 'kupaywc' ); ?>");
                        return;
                    }
                    if (!confirm("<?php _e( 'Are you sure you want to execute a processing of credit sales recording?', 'kupaywc' ); ?>")) {
                        return;
                    }
                    kupaywc_admin_order.authSettlementCard('gathering', $('#kupaywc-amount_change').val());
                });

                $(document).on('click', '#kupaywc-reauth-button', function () {
                    var amount = $('#kupaywc-amount_change').val();
                    if (amount == "" || parseInt(amount) === 0 || !$.isNumeric(amount)) {
                        alert("<?php _e( 'The spending amount format is incorrect. Please enter with numeric value.', 'kupaywc' ); ?>");
                        return;
                    }
                    if (!confirm("<?php _e( 'Are you sure you want to a processing of re-authorization?', 'kupaywc' ); ?>")) {
                        return;
                    }
                    kupaywc_admin_order.authSettlementCard('reauth', $('#kupaywc-amount_change').val());
                });
				<?php endif; ?>
            });
        </script>
		<?php
	}
}

new KUPAYWC_Admin_Order();

