<?php
/**
 * KUPAYWC_SLN_Connection class.
 *
 * Connection with SLN.
 *
 * @package Woo - Kuroneko Payment Services
 * @since 1.0.0
 */

class KUPAYWC_SLN_Connection {

	const API_TOKEN_URL             = 'https://api.kuronekoyamato.co.jp/api/token/js/embeddedTokenLib.js';
	const SEND_URL_TOKEN            = 'https://api.kuronekoyamato.co.jp/api/creditToken';
	const SEND_URL_3DSECURE         = 'https://api.kuronekoyamato.co.jp/api/creditToken3D';
	const SEND_URL_MEMBER           = 'https://api.kuronekoyamato.co.jp/api/creditInfoGet';
	const SEND_DELETE_MEMBER        = 'https://api.kuronekoyamato.co.jp/api/creditInfoDelete';
	const SEND_CREDIT_CANCEL        = 'https://api.kuronekoyamato.co.jp/api/creditCancel';
	const SEND_CHANGE_PRICE         = 'https://api.kuronekoyamato.co.jp/api/creditChangePrice';
	const SEND_URL_SHIPMENT_ENTRY   = 'https://api.kuronekoyamato.co.jp/api/shipmentEntry';
	const SEND_TRADE_INFO           = 'https://api.kuronekoyamato.co.jp/api/tradeInfo';
	const SEND_PAYPAY_SETTLE        = 'https://api.kuronekoyamato.co.jp/api/paypaySettle';
	const SEND_PAYPAY_SETTLE_CANCEL = 'https://api.kuronekoyamato.co.jp/api/paypaySettleCancel';
	const SEND_PAYPAY_CHANGE_PRICE  = 'https://api.kuronekoyamato.co.jp/api/paypayChangePrice';

	const TEST_API_TOKEN_URL             = 'https://ptwebcollect.jp/test_gateway/token/js/embeddedTokenLib.js';
	const TEST_SEND_URL_TOKEN            = 'https://ptwebcollect.jp/test_gateway/creditToken.api';
	const TEST_SEND_URL_3DSECURE         = 'https://ptwebcollect.jp/test_gateway/creditToken3D.api';
	const TEST_SEND_URL_MEMBER           = 'https://ptwebcollect.jp/test_gateway/creditInfoGet.api';
	const TEST_SEND_DELETE_MEMBER        = 'https://ptwebcollect.jp/test_gateway/creditInfoDelete.api';
	const TEST_SEND_CREDIT_CANCEL        = 'https://ptwebcollect.jp/test_gateway/creditCancel.api';
	const TEST_SEND_CHANGE_PRICE         = 'https://ptwebcollect.jp/test_gateway/creditChangePrice.api';
	const TEST_URL_SHIPMENT_ENTRY        = 'https://ptwebcollect.jp/test_gateway/shipmentEntry.api';
	const TEST_SEND_TRADE_INFO           = 'https://ptwebcollect.jp/test_gateway/tradeInfo.api';
	const TEST_SEND_PAYPAY_SETTLE        = 'https://ptwebcollect.jp/test_gateway/paypaySettle.api';
	const TEST_SEND_PAYPAY_SETTLE_CANCEL = 'https://ptwebcollect.jp/test_gateway/paypaySettleCancel.api';
	const TEST_SEND_PAYPAY_CHANGE_PRICE  = 'https://ptwebcollect.jp/test_gateway/paypayChangePrice.api';

	/**
	 * @var bool $testmode Test mode.
	 */
	private $testmode;

	/**
	 * @var string $connection_url Connection URL.
	 */
	private $connection_url;

	/**
	 * @var int $connection_timeout Connection timeout.
	 */
	private $connection_timeout;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$settings                 = get_option( 'woocommerce_kuronekopayment_settings', array() );
		$this->testmode           = ( ! empty( $settings['testmode'] ) && 'yes' === $settings['testmode'] ) ? true : false;
		$this->connection_url     = '';
		$this->connection_timeout = 60;
	}

	/**
	 * お預かりカード情報照会(A03).
	 *
	 * @return string
	 */
	public function send_url_member() {
		$url = ( $this->testmode ) ? self::TEST_SEND_URL_MEMBER : self::SEND_URL_MEMBER;

		return $url;
	}

	/**
	 * お預かりカード情報削除(A05).
	 *
	 * @return string
	 */
	public function send_delete_member() {
		$url = ( $this->testmode ) ? self::TEST_SEND_DELETE_MEMBER : self::SEND_DELETE_MEMBER;

		return $url;
	}

	/**
	 * トークン発行(js).
	 *
	 * @return string
	 */
	public static function api_token_url() {
		$settings = get_option( 'woocommerce_kuronekopayment_settings', array() );
		$url      = ( ! empty( $settings['testmode'] ) && 'yes' === $settings['testmode'] ) ? self::TEST_API_TOKEN_URL : self::API_TOKEN_URL;

		return $url;
	}

	/**
	 * 決済処理(A08).
	 *
	 * @return string
	 */
	public function send_url_token() {
		$url = ( $this->testmode ) ? self::TEST_SEND_URL_TOKEN : self::SEND_URL_TOKEN;

		return $url;
	}

	/**
	 * 3Dセキュア処理(A09).
	 *
	 * @return string
	 */
	public static function send_url_3dsecure() {
		$settings = get_option( 'woocommerce_kuronekopayment_settings', array() );
		$url      = ( ! empty( $settings['testmode'] ) && 'yes' === $settings['testmode'] ) ? self::TEST_SEND_URL_3DSECURE : self::SEND_URL_3DSECURE;

		return $url;
	}

	/**
	 * 取引照会(E04).
	 *
	 * @return string
	 */
	public function send_trade_info() {
		$url = ( $this->testmode ) ? self::TEST_SEND_TRADE_INFO : self::SEND_TRADE_INFO;

		return $url;
	}

	/**
	 * 金額変更(A07).
	 *
	 * @return string
	 */
	public function send_change_price() {
		$url = ( $this->testmode ) ? self::TEST_SEND_CHANGE_PRICE : self::SEND_CHANGE_PRICE;

		return $url;
	}

	/**
	 * 取引取消(A06).
	 *
	 * @return string
	 */
	public function send_credit_cancel() {
		$url = ( $this->testmode ) ? self::TEST_SEND_CREDIT_CANCEL : self::SEND_CREDIT_CANCEL;

		return $url;
	}

	/**
	 * 出荷情報登録(E01).
	 *
	 * @return string
	 */
	public function send_shipment_entry() {
		$url = ( $this->testmode ) ? self::TEST_URL_SHIPMENT_ENTRY : self::SEND_URL_SHIPMENT_ENTRY;

		return $url;
	}

	/**
	 * PayPay 決済登録(L01).
	 *
	 * @return string
	 */
	public function send_paypay_settle() {
		$url = ( $this->testmode ) ? self::TEST_SEND_PAYPAY_SETTLE : self::SEND_PAYPAY_SETTLE;

		return $url;
	}

	/**
	 * PayPay 決済取消(L02).
	 *
	 * @return string
	 */
	public function send_paypay_settle_cancel() {
		$url = ( $this->testmode ) ? self::TEST_SEND_PAYPAY_SETTLE_CANCEL : self::SEND_PAYPAY_SETTLE_CANCEL;

		return $url;
	}

	/**
	 * PayPay 金額変更(L03).
	 *
	 * @return string
	 */
	public function send_paypay_change_price() {
		$url = ( $this->testmode ) ? self::TEST_SEND_PAYPAY_CHANGE_PRICE : self::SEND_PAYPAY_CHANGE_PRICE;

		return $url;
	}

	/**
	 * Set connection URL.
	 *
	 * @param string $connection_url Connection URL.
	 *
	 * @return void
	 */
	public function set_connection_url( $connection_url ) {
		$this->connection_url = $connection_url;
	}

	/**
	 * Get connection URL.
	 *
	 * @return string
	 */
	public function get_connection_url() {
		return $this->connection_url;
	}

	/**
	 * Set connection timeout.
	 *
	 * @param int $connection_timeout Connection timeout.
	 *
	 * @return void
	 */
	public function set_connection_timeout( $connection_timeout = 0 ) {
		$this->connection_timeout = $connection_timeout;
	}

	/**
	 * Get connection timeout.
	 *
	 * @return int
	 */
	public function get_connection_timeout() {
		return $this->connection_timeout;
	}

	/**
	 * Connection.
	 *
	 * @param mixed $params Params.
	 *
	 * @return array
	 */
	public function connection( $params ) {

		$this->set_connection_url( $params['send_url'] );
		// $this->set_connection_timeout( 60 );
		$rValue = $this->send_request( $params['param_list'] );

		return $rValue;
	}

	/**
	 * Request connection.
	 *
	 * @param array $param_list Request parameters.
	 *
	 * @return array Response parameters.
	 */
	function send_request( &$param_list = array() ) {

		$rValue = array();

		// Parameter check
		if ( empty( $param_list ) === false ) {

			$url      = $this->connection_url;
			$response = $this->wp_post( $url, $param_list );

			$rValue = array_merge( array( 'send_url' => $url ), $this->parseXml2Array( $response ) );
		}

		return $rValue;
	}

	/**
	 * Xmlを連想配列に変換
	 *
	 * @param string $xml XML Data.
	 *
	 * @return array
	 * @link https://qiita.com/ka_to/items/54fe4d5bb655841f85d8
	 */
	private function parseXml2Array( $xml ) {
		$xml   = simplexml_load_string( $xml );
		$json  = json_encode( $xml );
		$array = json_decode( $json, true );

		return $array;
	}

	/**
	 * 通信用のベース関数.
	 *
	 * @param string $url URL.
	 * @param mixed  $body post data.
	 *
	 * @return string
	 */
	private function wp_post( $url, $body ) {
		$args     = array(
			'body'    => $body,
			'timeout' => '30',
			// 'redirection' => '5',
			// 'httpversion' => '1.0',
			// 'blocking' => true,
			// 'headers' => array(),
			// 'cookies' => array()
		);
		$response = wp_remote_post( $url, $args );

		return wp_remote_retrieve_body( $response );
	}
}
