<?php
/**
 * KUPAYWC_Install class.
 *
 * @package Woo - Kuroneko Payment Services
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class KUPAYWC_Install {

	private static $db_updates = array();

	/**
	 * Create tables.
	 *
	 */
	public static function create_tables() {

		global $wpdb;

		$wpdb->hide_errors();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$collate = '';
		if ( $wpdb->has_cap( 'collation' ) ) {
			$collate = $wpdb->get_charset_collate();
		}

		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}kupaywc_log';" ) !== $wpdb->prefix . 'kupaywc_log' ) {
			$query = "CREATE TABLE {$wpdb->prefix}kupaywc_log (
  log_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  timestamp datetime NOT NULL,
  trans_code char(20) NOT NULL,
  response longtext NOT NULL,
  order_id BIGINT UNSIGNED NULL,
  payment_type char(20) NULL,
  log_type char(20) NULL,
  PRIMARY KEY (log_id),
  KEY trans_code (trans_code),
  KEY order_trans (order_id, trans_code),
  KEY order_id (order_id)
) $collate;";
			dbDelta( $query );
		}
		//update_option( 'kupaywc_db_updates', $this->db_updates );
	}
}

