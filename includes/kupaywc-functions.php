<?php
/**
 * Functions
 *
 * @package Woo - Kuroneko Payment Services
 * @since 1.0.0
 */

/**
 * Transaction code.
 *
 * @param int $digit
 *
 * @return string
 */
function kupaywc_get_transaction_code( $digit = 12 ) {
	$num              = str_repeat( '9', $digit );
	$transaction_code = apply_filters( 'kupaywc_transaction_code', sprintf( '%0' . $digit . 'd', wp_rand( 1, (int) $num ) ), $num );

	return $transaction_code;
}

/**
 * Inittial transaction code.
 *
 * @param int $digit
 *
 * @return string
 */
function kupaywc_init_transaction_code( $digit = 12 ) {
	$transaction_code = apply_filters( 'kupaywc_init_transaction_code', str_repeat( '9', $digit ) );

	return $transaction_code;
}

/**
 * Transaction date.
 *
 * @return 'yyyymmdd'
 */
function kupaywc_get_transaction_date() {
	$transaction_date = date_i18n( 'Ymd', current_time( 'timestamp' ) );

	return $transaction_date;
}

/**
 * Date format.
 *
 * @param string $date Date.
 * @param bool   $localize Localize.
 *
 * @return mixed|string
 */
function kupaywc_get_formatted_date( $date, $localize = true ) {
	if ( strlen( $date ) === 14 ) {
		$formatted_date = substr( $date, 0, 4 ) . '-' . substr( $date, 4, 2 ) . '-' . substr( $date, 6, 2 ) . ' ' . substr( $date, 8, 2 ) . ':' . substr( $date, 10, 2 ) . ':' . substr( $date, 12, 2 );
		if ( $localize ) {
			$formatted_date = date_i18n( __( 'M j, Y @ G:i:s', 'kupaywc' ), strtotime( $formatted_date ) );
		}
	} elseif ( strlen( $date ) === 12 ) {
		$formatted_date = substr( $date, 0, 4 ) . '-' . substr( $date, 4, 2 ) . '-' . substr( $date, 6, 2 ) . ' ' . substr( $date, 8, 2 ) . ':' . substr( $date, 10, 2 );
		if ( $localize ) {
			$formatted_date = date_i18n( __( 'M j, Y @ G:i', 'kupaywc' ), strtotime( $formatted_date ) );
		}
	} elseif ( strlen( $date ) === 8 ) {
		$formatted_date = substr( $date, 0, 4 ) . '-' . substr( $date, 4, 2 ) . '-' . substr( $date, 6, 2 );
		if ( $localize ) {
			$formatted_date = date_i18n( __( 'M j, Y', 'kupaywc' ), strtotime( $formatted_date ) );
		}
	} else {
		$formatted_date = $date;
	}

	return $formatted_date;
}

/**
 * Get order property with compatibility for WC lt 3.0.
 *
 * @param WC_Order $order Order object.
 * @param string   $key Order property.
 *
 * @return mixed Value of order property.
 */
function kupaywc_get_order_prop( $order, $key ) {
	$getter = array( $order, 'get_' . $key );

	return is_callable( $getter ) ? call_user_func( $getter ) : $order->{$key};
}

if ( ! function_exists( 'is_edit_cardmember_page' ) ) {

	/**
	 * Checks if is an edit card member page.
	 *
	 * @return bool
	 */
	function is_edit_cardmember_page() {
		global $wp;

		return ( is_page( wc_get_page_id( 'myaccount' ) ) && isset( $wp->query_vars['edit-kuronekocardmember'] ) );
	}
}

/**
 * The number of installments.
 *
 * @param string $PayType 支払方法のタイプ.
 *
 * @return string
 */
function kupaywc_get_paytype( $PayType ) {
	switch ( $PayType ) {
		case '01':
			$paytype_name = __( 'Lump-sum payment', 'kupaywc' );
			break;
		case '02':
		case '03':
		case '05':
		case '06':
		case '10':
		case '12':
		case '15':
		case '18':
		case '20':
		case '24':
			$times        = (int) $PayType;
			$paytype_name = $times . __( '-installments', 'kupaywc' );
			break;
		case '80':
			$paytype_name = __( 'Pay for it out of a bonus', 'kupaywc' );
			break;
		case '88':
			$paytype_name = __( 'Revolving payment', 'kupaywc' );
			break;
	}

	return $paytype_name;
}

/**
 * Card members have active orders.
 *
 * @param int $customer_id Customer ID.
 *
 * @return bool
 */
function kupaywc_get_customer_active_card_orders( $customer_id ) {

	$active                = false;
	$active_order_statuses = apply_filters( 'kupaywc_active_order_statuses',
		array(
			'wc-pending',
			'wc-processing',
			'wc-on-hold',
			'wc-refunded',
		)
	);

	$customer        = get_user_by( 'id', absint( $customer_id ) );
	$customer_orders = wc_get_orders(
		array(
			'limit'    => - 1,
			'customer' => array( array( 0, $customer->user_email ) ),
			'return'   => 'ids',
		)
	);

	if ( ! empty( $customer_orders ) ) {
		foreach ( $customer_orders as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}
			$payment_method = $order->get_payment_method();
			$order_status   = get_post_status( $order_id );
			if ( 'kuronekopayment' === $payment_method && in_array( $order_status, $active_order_statuses, true ) ) {
				$active = true;
				break;
			}
		}
	}

	return $active;
}

$ids = array(
	'flat_rate',
	'free_shipping',
);

foreach ( $ids as $id ) {
	add_filter( 'woocommerce_shipping_instance_form_fields_' . $id, 'kupaywc_woocommerce_shipping_instance_form_field', 100, 1 );
}

/**
 * WooCommerceの配送設定にクロネコヤマト配送の利用するかどうか設定.
 *
 * @param array $array 支払方法の設定.
 *
 * @return array
 */
function kupaywc_woocommerce_shipping_instance_form_field( $array ) {
	return array_merge( $array, array(
		'kuroneko_delivery_check' => array(
			'title'       => __( 'クロネコヤマト配送', 'kuatowc' ),
			'type'        => 'checkbox',
			'label'       => __( '利用する', 'kuatowc' ),
			'description' => 'クロネコヤマト配送を利用します。',
			'default'     => 'no',
			'desc_tip'    => true,
		),
	) );
}

/**
 * Load script for admin page.
 *
 * @return void
 */
function kupaywc_woocommerce_admin_script() {
	if ( ! is_admin() ) {
		return;
	}

	if ( filter_input( INPUT_GET, 'page' ) !== 'wc-settings' ) {
		return;
	}
	if ( filter_input( INPUT_GET, 'tab' ) !== 'checkout' ) {
		return;
	}
	if ( filter_input( INPUT_GET, 'section' ) !== 'kuronekopayment' ) {
		return;
	}
	wp_enqueue_script( 'kuroneko_admin_scripts', plugins_url( 'assets/js/kupaywc-check-payment-admin.js', KUPAYWC_PLUGIN_FILE ), array( 'jquery' ), KUPAYWC_VERSION, true );
}

add_action( 'admin_print_scripts', 'kupaywc_woocommerce_admin_script' );

add_action( 'woocommerce_before_checkout_form', 'kupaywc_woocommerce_before_checkout_form' );

/**
 * Show Error Message.
 *
 * @return void
 */
function kupaywc_woocommerce_before_checkout_form() {

	$error_code = filter_input( INPUT_GET, 'ku_pay_error' );

	if ( empty( $error_code ) ) {
		return;
	}

	$localized_message = '';
	if ( isset( $error_code ) ) {
		$error_message = KUPAYWCCodes::errorLabel( $error_code );
		if ( ! empty( $error_message ) ) {
			$localized_message .= $error_message . '<br />';
		}
	}
	if ( empty( $localized_message ) ) {
		$localized_message .= __( 'Payment processing failed. Please retry.', 'kupaywc' );
	}

	$localized_message = __( 'Your credit card information is incorrect.', 'kupaywc' );
	wc_add_notice( $localized_message, 'error' );
}
