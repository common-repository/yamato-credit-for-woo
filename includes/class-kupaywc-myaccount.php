<?php
/**
 * KUPAYWC_MyAccount class.
 *
 * @package Woo - Kuroneko Payment Services
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KUPAYWC_MyAccount {

	/**
	 * Constructor.
	 *
	 */
	public function __construct() {
		add_filter( 'woocommerce_get_query_vars', array( $this, 'get_query_vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'account_menu_items' ) );
		add_action( 'woocommerce_account_edit-kuronekocardmember_endpoint', array( $this, 'account_edit_endpoint' ) );
		add_filter( 'woocommerce_endpoint_edit-kuronekocardmember_title', array( $this, 'get_endpoint_title' ) );
		add_action( 'template_redirect', array( $this, 'save_cardmember' ) );
		add_action( 'template_redirect', array( $this, 'delete_cardmember' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'account_scripts' ) );
	}

	/**
	 * Add "Edit card member page" to My Account.
	 *
	 */
	public function get_query_vars( $query_vars ) {
		$query_vars['edit-kuronekocardmember'] = 'edit-kuronekocardmember';

		return $query_vars;
	}

	/**
	 * Menu name of "Edit card member page".
	 *
	 */
	public function account_menu_items( $items ) {
		$settings = get_option( 'woocommerce_kuronekopayment_settings', array() );
		if ( isset( $settings['cardmember'] ) && 'yes' === $settings['cardmember'] ) {
			$member_id = get_current_user_id();
			$member    = new KUPAYWC_Card_Member( $member_id );
			if ( $member->is_card_member() ) {
				$items['edit-kuronekocardmember'] = __( 'Delete the saved card member', 'kupaywc' );
			}
		}

		return $items;

	}

	/**
	 * Display "Edit card member page".
	 *
	 */
	public function account_edit_endpoint() {
		$deletable = true;
		$settings  = get_option( 'woocommerce_kuronekopayment_settings', array() );
		$member_id = get_current_user_id();
		$member    = new KUPAYWC_Card_Member( $member_id );
		$cardlast4 = '';
		if ( $member->is_card_member() ) {
			$response_member = $member->search_card_member();
			if ( '0' === $response_member['returnCode'] && isset( $response_member['cardData'] ) ) {
				$cardlast4 = substr( $response_member['cardData']['maskingCardNo'], - 4 );
			}
		}
		$deletable = apply_filters( 'kupaywc_deletable_cardmember', $deletable, $member_id );

		include KUPAYWC_PLUGIN_DIR . '/includes/kupaywc-form-cardmember.php';
	}

	/**
	 * Title of "Edit card member page".
	 *
	 */
	public function get_endpoint_title( $title ) {
		$member_id = get_current_user_id();
		$member    = new KUPAYWC_Card_Member( $member_id );
		if ( $member->is_card_member() ) {
			$title = __( 'Update credit card', 'kupaywc' );
		} else {
			$title = __( 'Save credit card', 'kupaywc' );
		}

		return $title;
	}

	/**
	 * Update card information.
	 *
	 */
	public function save_cardmember() {
		global $wp;

		if ( 'POST' !== strtoupper( wp_unslash($_SERVER['REQUEST_METHOD']) ) ) {
			return;
		}

		if ( empty( $_POST['action'] ) || 'save_cardmember' !== wc_clean(wp_unslash( $_POST['action'] )) || empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['_wpnonce'] ), 'kupaywc_edit_cardmember' ) ) {
			return;
		}

		wc_nocache_headers();

		$member_id = get_current_user_id();

		if ( $member_id <= 0 ) {
			return;
		}

		$settings = get_option( 'woocommerce_kuronekopayment_settings', array() );

		if ( empty( $_POST['kuronekopayment_card_number'] ) ) {
			wc_add_notice( sprintf( __( '%s is a required field.', 'kupaywc' ), __( 'Card number', 'kupaywc' ) ), 'error' );
		}

		if ( empty( $_POST['kuronekopayment_card_expmm'] ) || empty( $_POST['kuronekopayment_card_expyy'] ) ) {
			wc_add_notice( sprintf( __( '%s is a required field.', 'kupaywc' ), __( 'Expiry (MM/YY)', 'kupaywc' ) ), 'error' );
		}

		if ( isset( $settings['seccd'] ) && 'yes' === $settings['seccd'] ) {
			if ( empty( $_POST['kuronekopayment_card_seccd'] ) ) {
				wc_add_notice( sprintf( __( '%s is a required field.', 'kupaywc' ), __( 'Card code', 'kupaywc' ) ), 'error' );
			}
		}

		if ( 0 === wc_notice_count( 'error' ) ) {
			try {
				if ( is_user_logged_in() && 'yes' === $settings['cardmember'] ) {
					$param_list                    = array();
					$param_list['MerchantId']      = $settings['merchant_id'];
					$param_list['MerchantPass']    = $settings['merchant_pass'];
					$param_list['TenantId']        = $settings['tenant_id'];
					$param_list['TransactionDate'] = kupaywc_get_transaction_date();
					$param_list['MerchantFree1']   = kupaywc_get_transaction_code();
					$param_list['MerchantFree3']   = $member_id;

					$token = ( isset( $_POST['kuronekopayment_token_code'] ) ) ? wc_clean( wp_unslash( $_POST['kuronekopayment_token_code'] ) ) : '';
					if ( ! empty( $token ) ) {
						// Refer to token status.
						$SLN                     = new KUPAYWC_SLN_Connection();
						$param_list['Token']     = $token;
						$param_list['OperateId'] = '1TokenSearch';
						$params['param_list']    = $param_list;
						$params['send_url']      = $SLN->send_url_token();
						$response_token          = $SLN->connection( $params );
						if ( ! isset( $response_token['returnCode'] ) || '1' === $response_token['returnCode'] ) {
							// エラー時
							$localized_message = '';
							if ( isset( $response_token['errorCode'] ) ) {
								$error_message = KUPAYWCCodes::errorLabel( $response_token['errorCode'] );
								if ( ! empty( $error_message ) ) {
									$localized_message .= $error_message . '<br />';
								}
								$response_token['errorCode'] = KUPAYWCCodes::errorLabel( $response_token['errorCode'] );
							}
							if ( isset( $response_token['creditErrorCode'] ) ) {
								$error_message = KUPAYWCCodes::errorLabel( $response_token['creditErrorCode'] );
								if ( ! empty( $error_message ) ) {
									$localized_message .= $error_message . '<br />';
								}
								$response_token['creditErrorCode'] = KUPAYWCCodes::errorLabel( $response_token['creditErrorCode'] );
							}

							if ( empty( $localized_message ) ) {
								$localized_message .= __( 'Update processing failed. Please retry.', 'kupaywc' );
							}
							throw new KUPAYWC_Exception( print_r( $response_token, true ), $localized_message );
						}
					}


					$member = new KUPAYWC_Card_Member( $member_id );
					if ( $member->is_card_member() ) {
						// Search of card member.
						$response_member = $member->search_card_member( $param_list );
						if ( '0' === $response_member['returnCode'] ) {
							// Update card member.
							$response_member = $member->update_card_member( $param_list );
							if ( '0' === $response_member['returnCode'] ) {
								wc_add_notice( __( 'Card member updated successfully.', 'kupaywc' ) );
							} else {
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
									$localized_message = __( 'Failed updating card number.', 'kupaywc' );
								}
								throw new KUPAYWC_Exception( print_r( $response_member, true ), $localized_message );
							}
						} else {
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
								$localized_message = __( 'Card member does not found.', 'kupaywc' );
							}
							throw new KUPAYWC_Exception( print_r( $response_member, true ), $localized_message );
						}
					} else {
						// Register card member.
						$response_member = $member->create_card_member( $param_list );
						if ( '0' === $response_member['returnCode'] ) {
							wc_add_notice( __( 'Card member saved successfully.', 'kupaywc' ) );
						} else {
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
								$localized_message = __( 'Failed saving card member.', 'kupaywc' );
							}
							throw new KUPAYWC_Exception( print_r( $response_member, true ), $localized_message );
						}
					}
				}

				wp_safe_redirect( wc_get_endpoint_url( 'edit-kuronekocardmember', '', wc_get_page_permalink( 'myaccount' ) ) );
				exit;

			} catch ( KUPAYWC_Exception $e ) {
				wc_add_notice( $e->getLocalizedMessage(), 'error' );
				KUPAYWC_Logger::add_log( 'Error: ' . $e->getMessage() );

				wp_safe_redirect( wc_get_endpoint_url( 'edit-kuronekocardmember', '', wc_get_page_permalink( 'myaccount' ) ) );
				exit;
			}
		} else {
			wp_safe_redirect( wc_get_endpoint_url( 'edit-kuronekocardmember', '', wc_get_page_permalink( 'myaccount' ) ) );
			exit;
		}
	}

	/**
	 * Update card member.
	 *
	 */
	public function delete_cardmember() {
		global $wp;

		if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
			return;
		}

		if ( empty( $_POST['action'] ) || 'delete_cardmember' !== wp_unslash( $_POST['action'] ) || empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['_wpnonce'] ), 'kupaywc_edit_cardmember' ) ) {
			return;
		}

		wc_nocache_headers();

		$member_id = get_current_user_id();

		if ( $member_id <= 0 ) {
			return;
		}

		$settings = get_option( 'woocommerce_kuronekopayment_settings', array() );

		try {
			if ( is_user_logged_in() && 'yes' === $settings['cardmember'] ) {
				$param_list = array();
				$member     = new KUPAYWC_Card_Member( $member_id );
				if ( $member->is_card_member() ) {
					// Search of card member.
					$response_member = $member->search_card_member( $param_list );
					if ( '0' === $response_member['returnCode'] ) {
						// Delete of card member.
						$param_list['card_key']         = $response_member['cardData']['cardKey'];
						$param_list['last_credit_date'] = $response_member['cardData']['lastCreditDate'];
						$response_member                = $member->delete_card_member( $param_list );
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
							throw new KUPAYWC_Exception( print_r( $response_member, true ), $localized_message );
						}
					} else {
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
							$localized_message = __( 'Card member does not found.', 'kupaywc' );
						}
						throw new KUPAYWC_Exception( print_r( $response_member, true ), $localized_message );
					}
				} else {
					$localized_message = __( 'Card member does not found.', 'kupaywc' );
					throw new KUPAYWC_Exception( $param_list, $localized_message );
				}
			}

			wc_add_notice( __( 'Card member deleted successfully.', 'kupaywc' ) );

			wp_safe_redirect( wc_get_page_permalink( 'myaccount' ) );
			exit;

		} catch ( KUPAYWC_Exception $e ) {
			wc_add_notice( $e->getLocalizedMessage(), 'error' );
			KUPAYWC_Logger::add_log( 'Error: ' . $e->getMessage() );

			wp_safe_redirect( wc_get_endpoint_url( 'edit-kuronekocardmember', '', wc_get_page_permalink( 'myaccount' ) ) );
			exit;
		}
	}

	/**
	 * Outputs scripts.
	 *
	 */
	public function account_scripts() {
		global $wp;

		if ( ! is_page( wc_get_page_id( 'myaccount' ) ) && ! isset( $wp->query_vars['edit-kuronekocardmember'] ) ) {
			return;
		}

		$settings = get_option( 'woocommerce_kuronekopayment_settings', array() );
		if ( ! isset( $settings['cardmember'] ) || 'yes' !== $settings['cardmember'] ) {
			return;
		}

		wp_register_script( 'kuronekopayment_script', plugins_url( 'assets/js/kupaywc-myaccount.js', KUPAYWC_PLUGIN_FILE ), array(), KUPAYWC_VERSION, true );
		$member_id              = get_current_user_id();
		$member                 = new KUPAYWC_Card_Member( $member_id );
		$kuronekopayment_params = array();
		$kuronekopayment_params['message'] = array(
			'error_card_number' => __( 'The card number is not a valid credit card number.', 'kupaywc' ),
			'error_card_expmm'  => __( 'The card\'s expiration month is invalid.', 'kupaywc' ),
			'error_card_expyy'  => __( 'The card\'s expiration year is invalid.', 'kupaywc' ),
			'error_card_seccd'  => __( 'The card\'s security code is invalid.', 'kupaywc' ),
			'confirm_delete'    => __( 'Are you sure you want to delete card member?', 'kupaywc' ),
		);
		wp_localize_script( 'kuronekopayment_script', 'kuronekopayment_params', apply_filters( 'kuronekopayment_params', $kuronekopayment_params ) );
		wp_enqueue_script( 'kuronekopayment_script' );
	}
}

new KUPAYWC_MyAccount();
