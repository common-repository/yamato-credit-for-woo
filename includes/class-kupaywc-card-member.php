<?php
/**
 * KUPAYWC_Card_Member class.
 *
 * @package Woo - Kuroneko Payment Services
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KUPAYWC_Card_Member {

	/**
	 * KuronekoWebCollect Member ID.
	 *
	 * @var string $member_id Member ID
	 */
	private $member_id = '';

	/**
	 * KuronekoWebCollect Member password.
	 *
	 * @var string $auth_key Auth key
	 */
	private $auth_key = '';

	/**
	 * WP User ID.
	 *
	 * @var integer $customer_id Customer ID
	 */
	private $customer_id = 0;

	/**
	 * Constructor.
	 *
	 * @param int $customer_id The WP user ID
	 */
	public function __construct( $customer_id = 0 ) {
		if ( 0 < $customer_id ) {
			self::set_customer_id( $customer_id );
		}
	}

	/**
	 * 認証キー作成
	 *
	 * @return string
	 */
	public function make_auth_key() {
		return substr( uniqid(), 0, 8 );
	}


	/**
	 * Member ID in KuronekoWebCollect.
	 *
	 * @return string
	 */
	public function get_member_id() {
		$this->member_id = get_user_meta( $this->get_customer_id(), '_kupaywc_member_id', true );

		return $this->member_id;
	}

	/**
	 * Member password in KuronekoWebCollect.
	 *
	 * @return string
	 */
	public function get_auth_key() {
		$this->auth_key = get_user_meta( $this->get_customer_id(), '_kupaywc_auth_key', true );

		return $this->auth_key;
	}

	/**
	 * User ID in WordPress.
	 *
	 * @return int
	 */
	public function get_customer_id() {
		return absint( $this->customer_id );
	}

	/**
	 * Set Member ID used by KuronekoWebCollect.
	 *
	 * @param int $member_id Member ID.
	 */
	public function set_member_id( $member_id ) {
		$this->member_id = $member_id;
		update_user_meta( $this->get_customer_id(), '_kupaywc_member_id', $this->member_id );
	}

	/**
	 * Set Member password used by KuronekoWebCollect.
	 *
	 * @param string $auth_key Auth key.
	 */
	public function set_auth_key( $auth_key ) {
		$this->auth_key = $auth_key;
		update_user_meta( $this->get_customer_id(), '_kupaywc_auth_key', $this->auth_key );
	}

	/**
	 * Set Member password used by KuronekoWebCollect.
	 *
	 * @param string $auth_key Auth key.
	 */
	public function delete_auth_key( $auth_key ) {
		$this->auth_key = $auth_key;
		delete_user_meta( $this->get_customer_id(), '_kupaywc_auth_key', $this->auth_key );
	}

	/**
	 * Set User ID used by WordPress.
	 *
	 * @param int $customer_id
	 */
	public function set_customer_id( $customer_id ) {
		$this->customer_id = absint( $customer_id );
	}

	/**
	 * Is member of KuronekoWebCollect?
	 *
	 * @return bool
	 */
	public function is_card_member() {
		$member_id = $this->get_member_id();
		$auth_key  = $this->get_auth_key();

		return ( ! empty( $member_id ) && ! empty( $auth_key ) ) ? true : false;
	}

	/**
	 * Setting required parameters.
	 *
	 * @param int|null $member_id Customer ID.
	 *
	 * @return array $param_list
	 */
	protected function set_palam_list( $member_id = null ) {
		$settings = get_option( 'woocommerce_kuronekopayment_settings', array() );
		if ( $member_id === null ) {
			$member_id = get_current_user_id();
		}
		$member                           = new KUPAYWC_Card_Member( $member_id );
		$auth_key                         = $member->get_auth_key();
		$param_list['trader_code']        = $settings['trader_code'];
		$param_list['member_id']          = $member_id;
		$param_list['authentication_key'] = $auth_key;
		$param_list['check_sum']          = hash( 'sha256', $member_id . $auth_key . $settings['access_key'] );

		return $param_list;
	}

	/**
	 * Search of members for KuronekoWebCollect.
	 *
	 * @param array $param_list Parameters.
	 * @param int   $user_id Customer ID.
	 *
	 * @return array
	 */
	public function search_card_member( $param_list = array(), $user_id = null ) {
		if ( empty( $param_list ) ) {
			$param_list = $this->set_palam_list( $user_id );
		}
		$SLN                        = new KUPAYWC_SLN_Connection();
		$params                     = array();
		$param_list['function_div'] = 'A03';
		$params['send_url']         = $SLN->send_url_member();
		$params['param_list']       = $param_list;
		$response                   = $SLN->connection( $params );
		if ( '0' === $response['returnCode'] ) {
			$response['member_id'] = $params['param_list']['member_id'];
			$response['auth_key']  = $params['param_list']['authentication_key'];
		}
		do_action( 'kupaywc_search_card_member', $param_list, $response );

		return $response;
	}

	/**
	 * Delete of members for KuronekoWebCollect.
	 *
	 * @param array $param_list Parameters.
	 * @param int   $user_id Customer ID.
	 *
	 * @return array
	 */
	public function delete_card_member( $param_list = array(), $user_id = null ) {
		$param_list                 = array_merge( $this->set_palam_list( $user_id ), $param_list );
		$SLN                        = new KUPAYWC_SLN_Connection();
		$params                     = array();
		$param_list['function_div'] = 'A05';
		$params['send_url']         = $SLN->send_delete_member();
		$params['param_list']       = $param_list;
		// Member invalidation.
		$response = $SLN->connection( $params );
		if ( '0' === $response['returnCode'] ) {
			// Member delete.
			$this->delete_auth_key( $param_list['authentication_key'] );
		}
		do_action( 'kupaywc_delete_card_member', $param_list, $response );

		return $response;
	}
}
