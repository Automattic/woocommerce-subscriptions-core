<?php
/**
 * Class that handles our retries custom tables creation.
 *
 * @package        WooCommerce Subscriptions
 * @subpackage     WCS_Retry_Table_Maker
 * @category       Class
 * @author         Prospress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * Class WCS_Retry_Table_Maker
 */
class WCS_Retry_Table_Maker extends WCS_Table_Maker {
	const PAYMENT_RETRY_TABLE = 'wcs_payment_retries';

	/**
	 * @inheritDoc
	 */
	protected $schema_version = 1;

	/**
	 * WCS_Retry_Table_Maker constructor.
	 */
	public function __construct() {
		$this->tables = array(
			self::PAYMENT_RETRY_TABLE,
		);
	}

	/**
	 * @inheritDoc
	 */
	protected function get_table_definition( $table ) {
		global $wpdb;
		$table_name      = $wpdb->$table;
		$charset_collate = $wpdb->get_charset_collate();

		switch ( $table ) {
			case self::PAYMENT_RETRY_TABLE:
				return "
				CREATE TABLE {$table_name} (
					retry_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
					order_id BIGINT UNSIGNED NOT NULL,
					status varchar(255) NOT NULL,
					date_gmt datetime NOT NULL default '0000-00-00 00:00:00',
					rule_raw text,
					PRIMARY KEY  (retry_id),
					KEY order_id (order_id)
				) $charset_collate;
						";
			default:
				return '';
		}
	}
}
