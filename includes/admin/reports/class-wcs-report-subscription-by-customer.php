<?php
/**
 * Subscriptions Admin Report - Subscriptions by customer
 *
 * Creates the subscription admin reports area.
 *
 * @package		WooCommerce Subscriptions
 * @subpackage	WC_Subscriptions_Admin_Reports
 * @category	Class
 * @author		Prospress
 * @since		2.1
 */
class WC_Report_Subscription_By_Customer extends WP_List_Table {

	private $totals;

	/**
	 * Constructor.
	 */
	public function __construct() {

		parent::__construct( array(
			'singular'  => __( 'Customer', 'woocommerce-subscriptions' ),
			'plural'    => __( 'Customers', 'woocommerce-subscriptions' ),
			'ajax'      => false,
		) );
	}

	/**
	 * No subscription products found text.
	 */
	public function no_items() {
		esc_html_e( 'No customers found.', 'woocommerce-subscriptions' );
	}

	/**
	 * Output the report.
	 */
	public function output_report() {

		$this->prepare_items();
		echo '<div id="poststuff" class="woocommerce-reports-wide">';
		echo '	<div id="postbox-container-1" class="postbox-container" style="width: 280px;"><div class="postbox" style="padding: 10px;">';
		echo '	<h3>' . esc_html__( 'Customer Totals', 'woocommerce-subscriptions' ) . '</h3>';
		echo '	<p><strong>' . esc_html__( 'Total Customers', 'woocommerce-subscriptions' ) . '</strong> : ' . esc_html( $this->totals->total_customers ) . '<br />';
		echo '	<strong>' . esc_html__( 'Active Subscriptions', 'woocommerce-subscriptions' ) . '</strong> : ' . esc_html( $this->totals->active_subs ) . '<br />';
		echo '	<strong>' . esc_html__( 'Total Subscriptions', 'woocommerce-subscriptions' ) . '</strong> : ' . esc_html( $this->totals->total_subs ) . '<br />';
		echo '	<strong>' . esc_html__( 'Average CLV', 'woocommerce-subscriptions' ) . '</strong> : ' . wp_kses_post( wc_price( ( $this->totals->orig_total + $this->totals->renew_total ) / $this->totals->total_customers ) ) . '</p>';
		echo '</div></div>';
		$this->display();
		echo '</div>';

	}

	/**
	 * Get column value.
	 *
	 * @param WP_User $user
	 * @param string $column_name
	 * @return string
	 */
	public function column_default( $user, $column_name ) {
		global $wpdb;

		switch ( $column_name ) {

			case 'customer_name' :
				$user_info = get_userdata( $user->customer_id );
				return '<a href="' . get_edit_user_link( $user->customer_id ) . '">' . $user_info->user_email  . '</a>';

			case 'active_subs' :
				return $user->active_subs;

			case 'all_subs' :
				return $user->total_subs;

			case 'customer_lv' :
				return wc_price( $user->orig_total + $user->renew_total );

		}

		return '';
	}

	/**
	 * Get columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		$columns = array(
			'customer_name'  => __( 'Customer', 'woocommerce-subscriptions' ),
			'active_subs'    => __( 'Active Subscriptions', 'woocommerce-subscriptions' ),
			'all_subs'       => __( 'Total Subscriptions', 'woocommerce-subscriptions' ),
			'customer_lv'    => __( 'Lifetime Subscription Value', 'woocommerce-subscriptions' ),
		);

		return $columns;
	}

	/**
	 * Prepare subscription list items.
	 */
	public function prepare_items() {
		global $wpdb;

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );
		$current_page          = absint( $this->get_pagenum() );
		$per_page              = absint( apply_filters( 'wcs_reports_customers_per_page', 20 ) );
		$offset                = absint( ( $current_page - 1 ) * $per_page );

		$this->totals = $this->wcs_get_report_by_customer_totals();

		$customer_query = apply_filters( 'wcs_reports_current_customer_query',
			"SELECT wp_cust.meta_value as customer_id, COUNT(subs.ID) as total_subs, SUM(parent_total.meta_value) as orig_total, COALESCE( SUM(renewal_data.renewal_sum), 0) as renew_total,
					SUM(CASE
							WHEN subs.post_status
								IN  ( 'wc-" . implode( "','wc-", apply_filters( 'wcs_reports_active_statuses', array( 'active', 'pending-cancel' ) ) ) . "' ) THEN 1
							ELSE 0
							END) AS active_subs
				FROM {$wpdb->posts} subs
				INNER JOIN {$wpdb->postmeta} wp_cust
					ON wp_cust.post_id = subs.ID
					AND wp_cust.meta_key = '_customer_user'
				INNER JOIN {$wpdb->posts} parent_order
				  ON parent_order.ID = subs.post_parent
					AND parent_order.post_status IN ( 'wc-" . implode( "','wc-", apply_filters( 'woocommerce_reports_paid_order_statuses', array( 'completed', 'processing' ) ) ) . "' )
				LEFT JOIN {$wpdb->postmeta} parent_total
					ON parent_total.post_id = parent_order.ID
					AND parent_total.meta_key = '_order_total'
				LEFT JOIN (
						SELECT renewal_order.meta_value as subscription_id, SUM(renewal_order.meta_value) as renewal_sum
						FROM {$wpdb->posts} renewal_subs
						INNER JOIN {$wpdb->postmeta} renewal_order
						 	ON renewal_order.post_id = renewal_subs.ID
							AND renewal_order.meta_key = '_subscription_renewal'
						LEFT JOIN {$wpdb->postmeta} renewal_total
							ON renewal_total.post_id = renewal_order.post_id
							AND renewal_total.meta_key = '_order_total'
						WHERE renewal_subs.post_status IN ( 'wc-" . implode( "','wc-", apply_filters( 'woocommerce_reports_paid_order_statuses', array( 'completed', 'processing' ) ) ) . "' )
						GROUP BY renewal_order.meta_value
					) AS renewal_data
  				ON subs.ID = renewal_data.subscription_id
				WHERE subs.post_type = 'shop_subscription'
				GROUP BY wp_cust.meta_value
				LIMIT {$offset}, {$per_page}" );

		 $this->items = $wpdb->get_results( $customer_query );

		 /**
			* Pagination.
			*/
		 $this->set_pagination_args( array(
			 'total_items' => $this->totals->total_customers,
			 'per_page'    => $per_page,
			 'total_pages' => ceil( $this->totals->total_customers / $per_page ),
		 ) );

	}

	/**
	* Gather totals for customers
	*/
	public function wcs_get_report_by_customer_totals() {
		global $wpdb;

		$total_query = apply_filters( 'wcs_reports_current_customer_total_query',
			"SELECT COUNT( DISTINCT wp_cust.meta_value) as total_customers, COUNT(subs.ID) as total_subs, SUM(parent_total.meta_value) as orig_total, COALESCE( SUM(renewal_data.renewal_sum), 0) as renew_total,
					SUM(CASE
							WHEN subs.post_status
								IN  ( 'wc-" . implode( "','wc-", apply_filters( 'wcs_reports_active_statuses', array( 'active', 'pending-cancel' ) ) ) . "' ) THEN 1
							ELSE 0
							END) AS active_subs
				FROM {$wpdb->posts} subs
				INNER JOIN {$wpdb->postmeta} wp_cust
					ON wp_cust.post_id = subs.ID
					AND wp_cust.meta_key = '_customer_user'
				INNER JOIN {$wpdb->posts} parent_order
					ON parent_order.ID = subs.post_parent
					AND parent_order.post_status IN ( 'wc-" . implode( "','wc-", apply_filters( 'woocommerce_reports_paid_order_statuses', array( 'completed', 'processing' ) ) ) . "' )
				LEFT JOIN {$wpdb->postmeta} parent_total
					ON parent_total.post_id = parent_order.ID
					AND parent_total.meta_key = '_order_total'
				LEFT JOIN (
						SELECT renewal_order.meta_value as subscription_id, SUM(renewal_order.meta_value) as renewal_sum
						FROM {$wpdb->posts} renewal_subs
						INNER JOIN {$wpdb->postmeta} renewal_order
							ON renewal_order.post_id = renewal_subs.ID
							AND renewal_order.meta_key = '_subscription_renewal'
						LEFT JOIN {$wpdb->postmeta} renewal_total
							ON renewal_total.post_id = renewal_order.post_id
							AND renewal_total.meta_key = '_order_total'
						WHERE renewal_subs.post_status IN ( 'wc-" . implode( "','wc-", apply_filters( 'woocommerce_reports_paid_order_statuses', array( 'completed', 'processing' ) ) ) . "' )
						GROUP BY renewal_order.meta_value
					) AS renewal_data
					ON subs.ID = renewal_data.subscription_id
				WHERE subs.post_type = 'shop_subscription'");

		 $query_results = $wpdb->get_row( $total_query );

		 return $query_results;
	}
}
