<?php
/**
 * WC_Gateway_KuronekoPayment class.
 *
 * @extends WC_Payment_Gateway
 * @package Woo - Kuroneko Payment Services
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Gateway_KuronekoPayment_Subscriptions extends WC_Gateway_KuronekoPayment {

	/**
	 * Constructor for the gateway.
	 * 定期購読時に実装
	 */
	public function __construct() {

		parent::__construct();

		if ( class_exists( 'WC_Subscriptions_Order' ) ) {

			$this->supports = array_merge(
				$this->supports,
				array(
					'subscriptions',
					'subscription_suspension',
					'subscription_cancellation',
					'subscription_reactivation',
					'subscription_amount_changes',
					'subscription_date_changes',
				)
			);

			add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array(
				$this,
				'scheduled_subscription_payment'
			), 10, 2 );
			add_filter( 'wcs_view_subscription_actions', array( $this, 'view_subscription_actions' ), 10, 2 );
			add_filter( 'kupaywc_display_save_payment_method_checkbox', array(
				$this,
				'hide_save_payment_method_checkbox'
			) );
			add_filter( 'kupaywc_display_howtopay_select', array( $this, 'hide_howtopay_select' ) );
			add_filter( 'kupaywc_deletable_cardmember', array( $this, 'deletable_cardmember' ), 10, 2 );
			add_filter( 'kupaywc_save_cardmember', array( $this, 'save_cardmember' ), 10, 3 );
		}
	}

	/**
	 * Check if order contains subscriptions.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return bool Returns true of order contains subscription.
	 */
	protected function order_contains_subscription( $order_id ) {
		return ( function_exists( 'wcs_order_contains_subscription' ) && ( wcs_order_contains_subscription( $order_id ) || wcs_is_subscription( $order_id ) || wcs_order_contains_renewal( $order_id ) ) );
	}

	/**
	 * Process a scheduled subscription payment.
	 *
	 * @param float $amount_to_charge The amount to charge.
	 * @param WC_Order $renewal_order A WC_Order object created to record the renewal payment.
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {

		try {
			$order_id    = $renewal_order->get_id();
			$customer_id = $renewal_order->get_customer_id();

			$settings         = get_option( 'woocommerce_kuronekopayment_settings', array() );
			$transaction_date = kupaywc_get_transaction_date();
			$trans_code       = kupaywc_get_transaction_code();
			$operate_id       = apply_filters( 'kupaywc_scheduled_subscription_card_operate_id', '1Gathering', $renewal_order );

			$SLN                           = new KUPAYWC_SLN_Connection();
			$params                        = array();
			$param_list                    = array();
			$param_list['MerchantId']      = $settings['merchant_id'];
			$param_list['MerchantPass']    = $settings['merchant_pass'];
			$param_list['TenantId']        = $settings['trader_code'];
			$param_list['TransactionDate'] = $transaction_date;
			$param_list['MerchantFree1']   = $trans_code;
			$param_list['MerchantFree2']   = $order_id;
			$member                        = new KUPAYWC_Card_Member( $customer_id );
			if ( 0 < $customer_id && $member->is_card_member() ) {
				$response_member = $member->search_card_member( $param_list );
				if ( 'OK' === $response_member['returnCode'] ) {
					$params['send_url']   = $SLN->send_url();
					$params['param_list'] = array_merge( $param_list,
						array(
							'MerchantFree3' => $customer_id,
							'KaiinId'       => $member->get_member_id(),
							'KaiinPass'     => $member->get_auth_key(),
							'OperateId'     => $operate_id,
							'PayType'       => '01',
							'Amount'        => $amount_to_charge
						)
					);
					$response_data        = $SLN->connection( $params );
					if ( 'OK' !== $response_data['returnCode'] ) {
						$localized_message = __( 'Subscription payment processing failed.', 'kupaywc' );
						$responsecd        = explode( '|', $response_data['returnCode'] );
						foreach ( (array) $responsecd as $cd ) {
							$response_data[ $cd ] = SPFWC_Payment_Message::response_message( $cd );
						}
						$localized_message = __( 'Subscription payment processing failed.', 'kupaywc' );
						throw new SPFWC_Exception( print_r( $response_data, true ), $localized_message );
					}
					do_action( 'kupaywc_scheduled_subscription_payment', $response_data, $renewal_order );
					parent::process_response( $response_data, $renewal_order );

				} else {
					$responsecd = explode( '|', $response_member['returnCode'] );
					foreach ( (array) $responsecd as $cd ) {
						$response_member[ $cd ] = SPFWC_Payment_Message::response_message( $cd );
					}
					$localized_message = __( 'Subscription payment processing failed.', 'kupaywc' );
					$localized_message .= __( 'Card member does not found.', 'kupaywc' );
					throw new SPFWC_Exception( print_r( $response_member, true ), $localized_message );
				}
			} else {
				$localized_message = __( 'Subscription payment processing failed.', 'kupaywc' );
				$localized_message .= __( 'Card member does not found.', 'kupaywc' );
				$renewal_order->update_status( 'failed' );
			}

		} catch ( SPFWC_Exception $e ) {
			wc_add_notice( $e->getLocalizedMessage(), 'error' );
			KUPAYWC_Logger::add_log( 'Error: ' . $e->getMessage() );

			do_action( 'kupaywc_scheduled_subscription_payment_error', $e, $renewal_order );

			$renewal_order->update_status( 'failed' );
		}
	}

	/**
	 * Render the payment method used for a subscription in the "My Subscriptions" table
	 *
	 * @param string $payment_method_to_display the default payment method text to display
	 * @param WC_Subscription $subscription the subscription details
	 *
	 * @return string the subscription payment method
	 */
	public function my_subscriptions_payment_method( $payment_method_to_display, $subscription ) {

		return $payment_method_to_display;
	}

	/**
	 * Initialise gateway settings form fields for sunscriptions.
	 *
	 */
	public function init_form_fields() {

		parent::init_form_fields();

		$form_fields = array();
		foreach ( $this->form_fields as $key => $field ) {
			if ( 'cardmember' === $key ) {
				$field['description'] .= '<br />' . __( 'Please be sure to enable when using the subscription.', 'kupaywc' );
			}
			$form_fields[ $key ] = $field;
			if ( 'operate_id' === $key ) {
				$form_fields['subscription_operate_id'] = array(
					'title'       => __( 'Subscriptions operation mode', 'kupaywc' ),
					'type'        => 'select',
					'options'     => array(
						'1Auth'      => __( 'Credit', 'kupaywc' ),
						'1Gathering' => __( 'Credit sales recorded', 'kupaywc' )
					),
					'default'     => '1Gathering',
					'description' => __( 'Setting up the operation mode of the subscription.', 'kupaywc' ) . '<br />' . __( 'In case of \'Credit\' setting, it need to change to \'Sales recorded\' manually in later. In case of \'Credit sales recorded\' setting, sales will be recorded at the time of subscription purchase.', 'kupaywc' ),
				);
			}
		}
		$this->form_fields = $form_fields;
	}

	/**
	 * Retrieve available actions that a user can perform on the subscription.
	 *
	 */
	public function view_subscription_actions( $actions, $subscription ) {

		if ( array_key_exists( 'resubscribe', $actions ) ) {
			unset( $actions['resubscribe'] );
		}

		return $actions;
	}

	/**
	 * Can not choose the option which won't register the credit card.
	 *
	 */
	public function hide_save_payment_method_checkbox( $display_save_payment_method ) {

		if ( WC_Subscriptions_Cart::cart_contains_subscription() ) {
			return false;
		}

		return $display_save_payment_method;
	}

	/**
	 * Set only for lump-sum payment.
	 *
	 */
	public function hide_howtopay_select( $display_howtopay ) {

		if ( WC_Subscriptions_Cart::cart_contains_subscription() ) {
			$display_howtopay = '1';
		}

		return $display_howtopay;
	}

	/**
	 * Members with subscription contracts can not deletable.
	 *
	 */
	public function deletable_cardmember( $deletable, $customer_id ) {

		$member = new KUPAYWC_Card_Member( $customer_id );
		if ( 0 < $customer_id && $member->is_card_member() ) {
			if ( wcs_user_has_subscription( $customer_id, '', 'active' ) ) {
				$deletable = false;
			}
		}

		return $deletable;
	}

	/**
	 * Always registering as a card member at the time of subscription purchase.
	 *
	 */
	public function save_cardmember( $card_member, $order, $customer_id ) {

		if ( empty( $card_member ) && 0 < $customer_id ) {
			$order_id = $order->get_id();
			if ( $this->order_contains_subscription( $order_id ) ) {
				$member      = new KUPAYWC_Card_Member( $customer_id );
				$card_member = ( $member->is_card_member() ) ? 'change' : 'add';
			}
		}

		return $card_member;
	}
}

