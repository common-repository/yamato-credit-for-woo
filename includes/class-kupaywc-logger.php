<?php
/**
 * KUPAYWC_Logger class.
 *
 * @package Woo - Kuroneko Payment Services
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KUPAYWC_Logger {

	/**
	 * @var $logger WC_Logger Logger instance.
	 */
	public static $logger;

	/**
	 * Logging method.
	 *
	 * @param string $message Log message.
	 * @param string $level Optional. Default 'info'.
	 *     emergency|alert|critical|error|warning|notice|info|debug
	 */
	public static function add_log( $message, $level = 'info' ) {

		$settings = get_option( 'woocommerce_kuronekopayment_settings' );
		if ( empty( $settings ) || ( isset( $settings['logging'] ) && 'yes' !== $settings['logging'] ) ) {
			return;
		}

		if ( version_compare( WC_VERSION, '3.0', '<' ) ) {
			if ( empty( self::$logger ) ) {
				self::$logger = new WC_Logger();
			}
			self::$logger->add( 'kuronekopayment', $message );

		} else {
			if ( empty( self::$logger ) ) {
				self::$logger = wc_get_logger();
			}
			self::$logger->log( $level, $message, array( 'source' => 'kuronekopayment' ) );
		}
	}
}
