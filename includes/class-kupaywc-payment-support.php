<?php
/**
 * WC_KuronekoPayment_Support class.
 *
 * @package Woo - Kuroneko Payment Services
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_KuronekoPayment_Support {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->add_user_profile();
		$this->add_kana_fields();

		$settings = get_option( 'woocommerce_kuronekopayment_settings', array() );
		if ( ! empty( $settings['testmode'] ) && 'yes' === $settings['testmode'] ) {
			add_action( 'kupaywc_delete_card_member', array( $this, 'delete_card_member' ), 10, 2 );
		}
	}

	/**
	 * Card member deletion processing in test mode.
	 *
	 * @param mixed $param_list Parameter list.
	 * @param mixed $response Response.
	 */
	public function delete_card_member( $param_list, $response ) {
		if ( '0' === $response['returnCode'] ) {
			delete_user_meta( $param_list['member_id'], '_kupaywc_auth_key' );
		}
	}

	/**
	 * Add card member information to edit user pages.
	 * The administrator can delete the card member information of the user.
	 */
	public function add_user_profile() {
		$settings = get_option( 'woocommerce_kuronekopayment_settings', array() );
		if ( ( isset( $settings['enabled'] ) && 'no' !== $settings['enabled'] ) && ( isset( $settings['cardmember'] ) && 'yes' === $settings['cardmember'] ) ) {
			add_action( 'show_user_profile', array( $this, 'add_card_member_fields' ), 11 );
			add_action( 'edit_user_profile', array( $this, 'add_card_member_fields' ), 11 );
			add_action( 'personal_options_update', array( $this, 'save_card_member_fields' ), 11 );
			add_action( 'edit_user_profile_update', array( $this, 'save_card_member_fields' ), 11 );
		}
	}

	/**
	 * Add kana fields.
	 * Only when "Woo Commerce For Japan" is not effective.
	 */
	public function add_kana_fields() {
		$settings = get_option( 'woocommerce_kuronekopayment_cvs_settings', array() );
		if ( isset( $settings['enabled'] ) && 'no' !== $settings['enabled'] ) {
			if ( ! get_option( 'wc4jp-yomigana' ) ) {
				add_filter( 'woocommerce_default_address_fields', array( $this, 'default_address_fields' ), 11 );
				add_action( 'woocommerce_formatted_address_replacements', array( $this, 'address_replacements' ), 21, 2 );
				add_filter( 'woocommerce_localisation_address_formats', array( $this, 'address_formats' ), 21 );
				add_filter( 'woocommerce_my_account_my_address_formatted_address', array( $this, 'formatted_address' ), 11, 3 );
				add_filter( 'woocommerce_order_formatted_billing_address', array( $this, 'billing_address' ), 11, 2 );
				add_filter( 'woocommerce_order_formatted_shipping_address', array( $this, 'shipping_address' ), 21, 2 );
				add_filter( 'woocommerce_get_order_address', array( $this, 'get_order_address' ), 21, 3 );
				add_filter( 'woocommerce_customer_meta_fields', array( $this, 'customer_meta_fields' ), 11 );
				add_filter( 'woocommerce_admin_billing_fields', array( $this, 'admin_billing_fields' ), 9 );
				add_filter( 'woocommerce_admin_shipping_fields', array( $this, 'admin_shipping_fields' ), 9 );
			}
		}
	}

	/**
	 * Show card member on edit user pages.
	 *
	 * @param WP_User $user
	 */
	public function add_card_member_fields( $user ) {

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$customer_id = $user->ID;
		$member      = new KUPAYWC_Card_Member( $customer_id );
		$cardlast4   = '';
		if ( $member->is_card_member() ) {
			$response_member = $member->search_card_member( array(), $customer_id );
			if ( '0' === $response_member['returnCode'] ) {
				$cardlast4 = substr( $response_member['cardData']['maskingCardNo'], - 4 );
			}
			?>
            <h2><?php echo esc_html__( 'KuronekoWebCollect Card Member', 'kupaywc' ); ?></h2>
            <table class="form-table" id="fieldset-kuronekopayment-card-member'">
				<?php if ( ! empty( $cardlast4 ) ) : ?>
                    <tr>
                        <th scope="row"><?php echo esc_html__( 'Last 4 digits of the saved card number', 'kupaywc' ); ?></th>
                        <td>
                            <span id="kuronekopayment-card-member-cardlast4"><?php echo esc_html( $cardlast4 ); ?></span>
                        </td>
                    </tr>
				<?php endif; ?>
                <tr>
                    <th scope="row"><?php echo esc_html__( 'Delete the saved card member', 'kupaywc' ); ?></th>
                    <td>
                        <label for="kuronekopayment-card-member-delete">
                            <input name="kuronekopayment_card_member_delete"
                                   type="checkbox"
                                   id="kuronekopayment-card-member-delete"
                                   value="false"><?php echo esc_html__( 'Delete a card member information registered in KuronekoWebCollect.', 'kupaywc' ); ?>
                        </label>
                    </td>
                </tr>
            </table>
			<?php
		}
	}

	/**
	 * Delete card member on edit user pages.
	 *
	 * @param  int  $user_id  User ID of the user being saved
	 *
	 * @throws KUPAYWC_Exception
	 */
	public function save_card_member_fields( $user_id ) {

		if ( isset( $_POST['kuronekopayment_card_member_delete'] ) ) {
			$member = new KUPAYWC_Card_Member( $user_id );
			if ( $member->is_card_member() ) {
				// Delete of card member.
				$response_member = $member->search_card_member( array(), $user_id );
				if ( '0' === $response_member['returnCode'] ) {
					// Delete of card member.
					$param_list['card_key']         = $response_member['cardData']['cardKey'];
					$param_list['last_credit_date'] = $response_member['cardData']['lastCreditDate'];
					$response_member                = $member->delete_card_member( $param_list, $user_id );
				}
				if ( '0' !== $response_member['returnCode'] ) {
					// エラー時
					$localized_message = '';
					if ( isset( $response_token['errorCode'] ) ) {
						$error_message = KUPAYWCCodes::errorLabel( $response_token['errorCode'] );
						if ( ! empty( $error_message ) ) {
							$localized_message .= $error_message . '<br />';
						}
						$response_token['errorCode'] = KUPAYWCCodes::errorLabel( $response_token['errorCode'] );
					}
					if ( empty( $localized_message ) ) {
						$localized_message = __( 'Failed deleting card member.', 'kupaywc' );
					}
					KUPAYWC_Logger::add_log( '[4MemDel] Error: ' . print_r( $response_member, true ) );
					throw new KUPAYWC_Exception( print_r( $response_member, true ), $localized_message );
				}
			}
		}
	}

	/**
	 * Add kana fields.
	 *
	 * @param array $fields
	 */
	public function default_address_fields( $fields ) {

		$address_fields = array();
		foreach ( $fields as $key => $field ) {
			if ( strpos( $key, 'company' ) !== false ) {
				$address_fields['first_name_kana'] = array(
					'label'    => __( 'First name (kana)', 'kupaywc' ),
					'required' => false,
					'class'    => array( 'form-row-first' ),
				);
				$address_fields['last_name_kana']  = array(
					'label'    => __( 'Last name (kana)', 'kupaywc' ),
					'class'    => array( 'form-row-last' ),
					'required' => false,
					'clear'    => true,
				);
			}
			$address_fields[ $key ] = $field;
		}

		return apply_filters( 'kupaywc_default_address_fields', $address_fields, $fields );
	}

	/**
	 * Add kana fields.
	 *
	 * @param array $fields
	 * @param array $args Arguments.
	 */
	public function address_replacements( $fields, $args ) {
		$fields['{first_name_kana}'] = ( isset( $args['first_name_kana'] ) ) ? $args['first_name_kana'] : '';
		$fields['{last_name_kana}']  = ( isset( $args['last_name_kana'] ) ) ? $args['last_name_kana'] : '';

		return apply_filters( 'kupaywc_formatted_address_replacements', $fields );
	}

	/**
	 * Add kana fields.
	 *
	 * @param array $fields Fields.
	 */
	public function address_formats( $fields ) {
		$fields['JP'] = "{postcode}\n{state} {city} {address_1}\n{address_2}\n{company}\n{last_name} {first_name}\n{last_name_kana} {first_name_kana}\n{country}";

		return apply_filters( 'kupaywc_localisation_address_formats', $fields );
	}

	/**
	 * Add kana fields to myaccount address.
	 *
	 * @param array  $address Address.
	 * @param string $customer_id Customer ID.
	 * @param string $address_type Address type.
	 */
	public function formatted_address( $address, $customer_id, $address_type ) {
		$address['first_name_kana'] = get_user_meta( $customer_id, $address_type . '_first_name_kana', true );
		$address['last_name_kana']  = get_user_meta( $customer_id, $address_type . '_last_name_kana', true );

		return apply_filters( 'kupaywc_my_account_my_address_formatted_address', $address, $customer_id, $address_type );
	}

	/**
	 * Add kana fields to billing.
	 *
	 * @param array    $address  Address.
	 * @param WC_Order $order  Order.
	 *
	 * @return mixed|null
	 */
	public function billing_address( $address, $order ) {
		$address['first_name_kana'] = $order->get_meta( '_billing_first_name_kana', true );
		$address['last_name_kana']  = $order->get_meta( '_billing_last_name_kana', true );

		return apply_filters( 'kupaywc_order_formatted_billing_address', $address, $order );
	}

	/**
	 * Add kana fields to shipping.
	 *
	 * @param array    $address Address.
	 * @param WC_Order $order Order.
	 */
	public function shipping_address( $address, $order ) {
		$address['first_name_kana'] = $order->get_meta( '_shipping_first_name_kana', true );
		$address['last_name_kana']  = $order->get_meta( '_shipping_last_name_kana', true );

		return apply_filters( 'kupaywc_order_formatted_shipping_address', $address, $order );
	}

	/**
	 * Add kana fields to order data.
	 *
	 * @param array    $address Address.
	 * @param string   $address_type Address type.
	 * @param WC_Order $order Order.
	 */
	public function get_order_address( $address, $address_type, $order ) {
		if ( 'billing' === $address_type ) {
			$address['first_name_kana'] = $order->get_meta( '_billing_first_name_kana', true );
			$address['last_name_kana']  = $order->get_meta( '_billing_last_name_kana', true );
		} else {
			$address['first_name_kana'] = $order->get_meta( '_shipping_first_name_kana', true );
			$address['last_name_kana']  = $order->get_meta( '_shipping_last_name_kana', true );
		}

		return apply_filters( 'kupaywc_get_order_address', $address, $address_type, $order );
	}

	/**
	 * Add kana fields to customer data.
	 *
	 * @param array $fields
	 */
	public function customer_meta_fields( $fields ) {
		$customer_meta_fields                     = array();
		$customer_meta_fields['billing']['title'] = $fields['billing']['title'];
		foreach ( $fields['billing']['fields'] as $key => $field ) {
			if ( strpos( $key, 'company' ) !== false ) {
				$customer_meta_fields['billing']['fields']['billing_first_name_kana'] = array(
					'label'       => __( 'First name kana', 'kupaywc' ),
					'description' => '',
				);
				$customer_meta_fields['billing']['fields']['billing_last_name_kana']  = array(
					'label'       => __( 'Last name kana', 'kupaywc' ),
					'description' => '',
				);
			}
			$customer_meta_fields['billing']['fields'][ $key ] = $field;
		}
		$customer_meta_fields['shipping']['title'] = $fields['shipping']['title'];
		foreach ( $fields['shipping']['fields'] as $key => $field ) {
			if ( strpos( $key, 'company' ) !== false ) {
				$customer_meta_fields['shipping']['fields']['shipping_first_name_kana'] = array(
					'label'       => __( 'First name kana', 'kupaywc' ),
					'description' => '',
				);
				$customer_meta_fields['shipping']['fields']['shipping_last_name_kana']  = array(
					'label'       => __( 'Last name kana', 'kupaywc' ),
					'description' => '',
				);
			}
			$customer_meta_fields['shipping']['fields'][ $key ] = $field;
		}

		return apply_filters( 'kupaywc_customer_meta_fields', $customer_meta_fields, $fields );
	}

	/**
	 * Add billing kana fields.
	 *
	 * @param array $fields
	 */
	public function admin_billing_fields( $fields ) {
		$billing_fields = array();
		foreach ( $fields as $key => $field ) {
			if ( strpos( $key, 'company' ) !== false ) {
				$billing_fields['first_name_kana'] = array(
					'label' => __( 'First name kana', 'kupaywc' ),
					'show'  => false,
				);
				$billing_fields['last_name_kana']  = array(
					'label' => __( 'Last name kana', 'kupaywc' ),
					'show'  => false,
				);
			}
			$billing_fields[ $key ] = $field;
		}

		return apply_filters( 'kupaywc_admin_billing_fields', $billing_fields, $fields );
	}

	/**
	 * Add shipping kana fields.
	 *
	 * @param array $fields
	 */
	public function admin_shipping_fields( $fields ) {
		$shipping_fields = array();
		foreach ( $fields as $key => $field ) {
			if ( strpos( $key, 'company' ) !== false ) {
				$shipping_fields['first_name_kana'] = array(
					'label' => __( 'First name kana', 'kupaywc' ),
					'show'  => false,
				);
				$shipping_fields['last_name_kana']  = array(
					'label' => __( 'Last name kana', 'kupaywc' ),
					'show'  => false,
				);
			}
			$shipping_fields[ $key ] = $field;
		}

		return apply_filters( 'kupaywc_admin_shipping_fields', $shipping_fields, $fields );
	}
}

new WC_KuronekoPayment_Support();
