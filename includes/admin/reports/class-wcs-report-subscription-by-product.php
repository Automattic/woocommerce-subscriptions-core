<?php
/**
 * Subscriptions Admin Report - Subscriptions by product
 *
 * Creates the subscription admin reports area.
 *
 * @package		WooCommerce Subscriptions
 * @subpackage	WC_Subscriptions_Admin_Reports
 * @category	Class
 * @author		Prospress
 * @since		2.1
 */
class WC_Report_Subscription_By_Product extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {

		parent::__construct( array(
			'singular'  => __( 'Product', 'woocommerce-subscriptions' ),
			'plural'    => __( 'Products', 'woocommerce-subscriptions' ),
			'ajax'      => false,
		) );
	}

	/**
	 * No subscription products found text.
	 */
	public function no_items() {
		esc_html_e( 'No products found.', 'woocommerce-subscriptions' );
	}

	/**
	 * Output the report.
	 */
	public function output_report() {

		$this->prepare_items();
		echo '<div id="poststuff" class="woocommerce-reports-wide" style="width:50%; float: left; min-width: 0px;">';
		$this->display();
		echo '</div>';
		$this->product_breakdown_chart();

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

			case 'product_name' :
				return edit_post_link( $user->product_name, null, null, $user->product_id );

			case 'sub_count' :
				return $user->sub_count;

			case 'sub_average_price' :
				$average_subscription_amount = ( 0 !== $user->sub_count ? wc_price( $user->product_total / $user->sub_count ) : '-' );
				return $average_subscription_amount;

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
			'product_name'      => __( 'Subscription Product', 'woocommerce-subscriptions' ),
			'sub_count'         => __( 'Current Subscriptions', 'woocommerce-subscriptions' ),
			'sub_average_price' => __( 'Average Recurring Amount', 'woocommerce-subscriptions' ),
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
		$per_page              = apply_filters( 'woocommerce_admin_stock_report_products_per_page', 20 );

		$product_query = apply_filters( 'wcs_reports_current_product_query',
			"SELECT product.id as product_id,	product.post_title as product_name,	mo.product_type, COUNT(orders.order_id) as sub_count, SUM(orders.product_total) as product_total
				FROM   {$wpdb->posts} AS product
				LEFT JOIN (
					SELECT tr.object_id AS id, t.slug AS product_type
					FROM {$wpdb->prefix}term_relationships AS tr
					INNER JOIN {$wpdb->prefix}term_taxonomy AS x
						ON ( x.taxonomy = 'product_type'
							AND x.term_taxonomy_id = tr.term_taxonomy_id )
					INNER JOIN {$wpdb->prefix}terms AS t
						ON t.term_id = x.term_id
				) AS mo
					ON product.id = mo.id
				LEFT JOIN (
					SELECT wcoitems.order_id, wcoimeta.meta_value as product_id, wcoimeta.order_item_id, wcoimeta2.meta_value as product_total
					FROM {$wpdb->prefix}woocommerce_order_items AS wcoitems
					INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS wcoimeta
						ON wcoimeta.order_item_id = wcoitems.order_item_id
					INNER JOIN {$wpdb->prefix}woocommerce_order_itemmeta AS wcoimeta2
						ON wcoimeta2.order_item_id = wcoitems.order_item_id
					WHERE wcoimeta.meta_key = '_product_id'
						AND wcoimeta2.meta_key = '_line_total'
				) as orders
					ON product.id = orders.product_id
				LEFT JOIN  {$wpdb->posts} as subs
					ON subs.ID = orders.order_id
				WHERE  product.post_status = 'publish'
					 AND product.post_type = 'product'
					 AND subs.post_type = 'shop_subscription'
					 AND subs.post_status in ( 'wc-" . implode( "','wc-", apply_filters( 'wcs_reports_active_statuses', array( 'active', 'pending-cancel' ) ) ) . "' )
		 GROUP BY product.id
		 ORDER BY COUNT(orders.order_id) DESC" );

		 $this->items = $wpdb->get_results( $product_query );

	}

	/**
	 * Output product breakdown chart.
	 */
	public function product_breakdown_chart() {

		$chart_colors = array( '#33a02c', '#1f78b4', '#6a3d9a', '#e31a1c', '#ff7f00', '#b15928', '#a6cee3', '#b2df8a', '#fb9a99', '#ffff99', '#fdbf6f', '#cab2d6' );

		//We only will display the first 12 plans in the chart
		$products = array_slice( $this->items, 0, 12 );

		?>
		<div class="chart-container" style="float: left; padding-top: 50px; min-width: 0px;">
			<div class="data-container" style="display: inline-block; margin-left: 30px; border: 1px solid #e5e5e5; background-color: #FFF; padding: 20px;">
				<div class="chart-placeholder product_breakdown_chart pie-chart" style="height:200px; width: 200px; float: left;"></div>
				<div class="legend-container" style="margin-left: 10px; float: left;"></div>
				<div style="clear:both;"></div>
			</div>
		</div>
		<script type="text/javascript">
			jQuery(function(){
	 			jQuery.plot(
					jQuery('.chart-placeholder.product_breakdown_chart'),
					[
					<?php
					$i = 0;
					foreach ( $products as $product ) {
						?>
						{
							label: '<?php echo esc_js( $product->product_name ); ?>',
							data:  '<?php echo esc_js( $product->sub_count ); ?>',
							color: '<?php echo esc_js( $chart_colors[ $i ] ); ?>'
						},
						<?php
						$i++;
					}
					?>
					],
					{
						grid: {
							hoverable: true
						},
						series: {
							pie: {
								show: true,
								radius: 1,
								innerRadius: 0.6,
								label: {
									show: false
								}
							},
							enable_tooltip: true,
							append_tooltip: "<?php echo ' ' . esc_js( __( 'subscriptions', 'woocommerce-subscriptions' ) ); ?>",
						},
						legend: {
							show: true,
							container: jQuery('.legend-container'),
						}
					}
				);

				jQuery('.chart-placeholder.product_breakdown_chart').resize();
			});
		</script>
		<?php
	}
}
