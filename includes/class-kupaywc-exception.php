<?php
/**
 * KUPAYWC_Exception class.
 *
 * @extends Exception
 * @package - Kuroneko Payment Services
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KUPAYWC_Exception extends Exception {

	/** @var string sanitized/localized error message */
	protected $localized_message;

	/** @var string sanitized/localized error code */
	protected $error_code;

	/**
	 * Setup exception.
	 *
	 * @param string $error_message Full response
	 * @param string $localized_message user-friendly translated error message
	 */
	public function __construct( $error_message = '', $localized_message = '', $error_code = '' ) {
		$this->localized_message = $localized_message;
		$this->error_code = $error_code;
		parent::__construct( $error_message );
	}

	/**
	 * Returns the localized message.
	 *
	 * @return string
	 */
	public function getLocalizedMessage() {
		return __( 'Your credit card information is incorrect.', 'kupaywc' ) . '<br />';
	}

	/**
	 * Returns the error code.
	 *
	 * @return mixed|string
	 */
	public function getErrorCode(){
		return $this->error_code;
	}
}
