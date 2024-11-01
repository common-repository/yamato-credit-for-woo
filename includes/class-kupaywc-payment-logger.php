<?php
/**
 * SPFWC_Payment_Logger class.
 *
 * @package Woo - Kuroneko Payment Services
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KUPAYWC_Payment_Logger {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_delete_order_item', array( $this, 'clear_log' ) );
		add_action( 'woocommerce_deleted_order_items', array( $this, 'clear_log' ) );
	}

	/**
	 * Add log data.
	 *
	 * @param  array  $response  Log message.
	 * @param  int    $order_id  Order ID.
	 * @param  string $trans_code  Transaction code.
	 * @param  string $timestamp Timestamp.
	 */
	public static function add_log( $response, $order_id, $trans_code, $timestamp = '' ) {
		global $wpdb;

		if ( empty( $timestamp ) ) {
			$timestamp = current_time( 'mysql' );
		}

		$query = $wpdb->prepare( "INSERT INTO {$wpdb->prefix}kupaywc_log ( `timestamp`, `trans_code`,  `response`, `order_id` ) VALUES ( %s, %s, %s, %d )",
			$timestamp,
			$trans_code,
			wp_json_encode( $response ),
			$order_id
		);
		$res   = $wpdb->query( $query );
	}

	/**
	 * Get log data.
	 *
	 * @param int    $order_id  Order ID.
	 * @param string $trans_code  Transaction code.
	 */
	public static function get_log( $order_id, $trans_code ) {
		global $wpdb;

		$query    = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}kupaywc_log WHERE `order_id` = %d AND `trans_code` = %s ORDER BY `timestamp` DESC",
			$order_id,
			$trans_code
		);
		$log_data = $wpdb->get_results( $query, ARRAY_A );

		return $log_data;
	}

	/**
	 * Get the latest log data.
	 *
	 * @param  int    $order_id  Order ID.
	 * @param  string $trans_code  Transaction code.
	 * @param  bool   $all Get all logs.
	 *
	 * @return array|mixed|stdClass
	 */
	public static function get_latest_log( $order_id, $trans_code, $all = false ) {
		global $wpdb;

		$latest_log = array();
		$log_data   = self::get_log( $order_id, $trans_code );

		if ( $log_data ) {
			if ( $all ) {
				$latest_log = $log_data[0];
			} else {
				$reauth = false;
				foreach ( (array) $log_data as $data ) {
					$response = json_decode( $data['response'], true );
					if ( isset( $response['returnCode'] ) ) {
						if ( '0' === $response['returnCode'] ) {
							$latest_log = $data;
							break;
						}
					}
				}
			}
		}

		return $latest_log;
	}

	/**
	 * 決済のステータスラベルを返す
	 * 金額変更の時はその前のステータスを返す
	 *
	 * @param int    $order_id  Order ID.
	 * @param string $trans_code  Transaction code.
	 *
	 * @return string
	 */
	public static function get_label_status( $order_id, $trans_code ) {
		global $wpdb;

		$logs         = self::get_log( $order_id, $trans_code );
		$status_label = '';
		if ( $logs ) {
			foreach ( (array) $logs as $log ) {
				$response = json_decode( $log['response'], true );
				if ( isset( $response['resultData']['statusInfo'] ) ) {
					$statusInfo = $response['resultData']['statusInfo'];
					if ( '41' !== $statusInfo ) {
						$status_label = isset( KUPAYWCCodes::$StatusInfos[ $statusInfo ] ) ? KUPAYWCCodes::$StatusInfos[ $statusInfo ] : '';
						if ( $status_label ) {
							break;
						}
					}
				}
			}
		}

		return $status_label;
	}

	/**
	 * 決済のステータスの数字を返す
	 * 金額変更の時はその前のステータスを返す
	 *
	 * @param int    $order_id  Order ID.
	 * @param string $trans_code  Transaction code.
	 *
	 * @return string
	 */
	public static function get_result_status_number( $order_id, $trans_code ) {
		global $wpdb;

		$logs         = self::get_log( $order_id, $trans_code );
		$status_label = '';
		if ( $logs ) {
			foreach ( (array) $logs as $log ) {
				$response = json_decode( $log['response'], true );
				if ( isset( $response['resultData']['statusInfo'] ) ) {
					if ( '41' !== $response['resultData']['statusInfo'] ) {
						$status_label = $response['resultData']['statusInfo'];
						if ( $status_label ) {
							break;
						}
					}
				}
			}
		}

		return $status_label;
	}

	/**
	 * The first response data.
	 *
	 * @param int    $order_id  Order ID.
	 * @param string $trans_code  Transaction code.
	 */
	public function get_first_log( $order_id, $trans_code ) {
		global $wpdb;

		$query     = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}kupaywc_log WHERE `order_id` = %d AND `trans_code` = %s ORDER BY `timestamp` ASC LIMIT 1",
			$order_id,
			$trans_code
		);
		$first_log = $wpdb->get_row( $query, ARRAY_A );

		return $first_log;
	}

	/**
	 * The first operation.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $trans_code Transaction code.
	 */
	public static function get_first_operation( $order_id, $trans_code ) {
		global $wpdb;

		$query = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}kupaywc_log WHERE `order_id` = %d AND `trans_code` = %s ORDER BY `timestamp` ASC LIMIT 1",
			$order_id,
			$trans_code
		);
		$log_data = $wpdb->get_row( $query, ARRAY_A );
		if ( $log_data ) {
			$response  = json_decode( $log_data['response'], true );
			$operateid = ( isset( $response['OperateId'] ) ) ? $response['OperateId'] : '';
		} else {
			$operateid = '';
		}

		return $operateid;
	}

	/**
	 * Clear log data.
	 *
	 * @param int $order_id
	 */
	public function clear_log( $order_id ) {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->prefix}kupaywc_log WHERE `order_id` = %d", $order_id ) );
	}
}

new KUPAYWC_Payment_Logger();
