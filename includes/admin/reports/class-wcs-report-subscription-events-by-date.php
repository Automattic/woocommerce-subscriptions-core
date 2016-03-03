<?php
/**
 * Subscriptions Admin Report - Subscription Events by Date
 *
 * Creates the subscription admin reports area.
 *
 * @package		WooCommerce Subscriptions
 * @subpackage	WC_Subscriptions_Admin_Reports
 * @category	Class
 * @author		Prospress
 * @since		2.1
 */
class WC_Report_Subscription_Events_By_Date extends WC_Admin_Report {
	public $chart_colours = array();
	private $report_data;

	/**
	 * Get report data
	 * @return array
	 */
	public function get_report_data() {
		if ( empty( $this->report_data ) ) {
			$this->query_report_data();
		}
		return $this->report_data;
	}

	/**
	 * Get all data needed for this report and store in the class
	 */
	private function query_report_data() {

		global $wpdb;

		$this->report_data = new stdClass;

		$this->report_data->renewal_counts = (array) $this->get_order_report_data(
			array(
				'data' => array(
					'ID' => array(
						'type'     => 'post_data',
						'function' => 'COUNT',
						'name'     => 'count',
						'distinct' => true,
					),
					'post_date' => array(
						'type'     => 'post_data',
						'function' => '',
						'name'     => 'post_date',
					),
					'_subscription_renewal' => array(
						'type'      => 'meta',
						'function'  => '',
						'name'      => 'renewal_orders',
					),
					'_order_total' => array(
						'type'      => 'meta',
						'function'  => 'SUM',
						'name'      => 'renewal_totals',
						'join_type' => 'LEFT',   // To avoid issues if there is no renewal_total meta
						),
				),
				'group_by'            => $this->group_by_query,
				'order_by'            => 'post_date ASC',
				'query_type'          => 'get_results',
				'filter_range'        => true,
				'order_types'         => wc_get_order_types( 'order-count' ),
			)
		);

		$this->report_data->switch_counts = (array) $this->get_order_report_data(
			array(
				'data' => array(
					'ID' => array(
						'type'     => 'post_data',
						'function' => 'COUNT',
						'name'     => 'count',
						'distinct' => true,
					),
					'post_date' => array(
						'type'     => 'post_data',
						'function' => '',
						'name'     => 'post_date',
					),
					'_subscription_switch' => array(
						'type'     => 'meta',
						'function' => '',
						'name'     => 'switch_orders',
					),
				),
				'group_by'            => $this->group_by_query,
				'order_by'            => 'post_date ASC',
				'query_type'          => 'get_results',
				'filter_range'        => true,
				'order_types'         => wc_get_order_types( 'order-count' ),
			)
		);

		/*
		* New subscription orders
		*/
		$query = $wpdb->prepare(
			"SELECT COUNT(DISTINCT wcsubs.ID) AS count, wcsubs.post_date as post_date, wcometa.meta_value as signup_totals
				FROM {$wpdb->posts} AS wcsubs
				INNER JOIN {$wpdb->posts} AS wcorder
					ON wcsubs.post_parent = wcorder.ID
				LEFT JOIN {$wpdb->postmeta} AS wcometa
					ON wcorder.ID = wcometa.post_id
				WHERE  wcorder.post_type IN ( 'shop_order' )
					AND wcsubs.post_type IN ( 'shop_subscription' )
					AND wcorder.post_status IN ( 'wc-completed', 'wc-processing', 'wc-on-hold', 'wc-refunded' )
					AND wcorder.post_date >= '%s'
					AND wcorder.post_date < '%s'
					AND wcometa.meta_key = '_order_total'
				GROUP BY YEAR(wcsubs.post_date), MONTH(wcsubs.post_date), DAY(wcsubs.post_date)
				ORDER BY post_date ASC",
			date( 'Y-m-d', $this->start_date ),
			date( 'Y-m-d', strtotime( '+1 DAY', $this->end_date ) )
		);

		$query_results = (array) $wpdb->get_results( $query );
		$this->report_data->signup_counts = $query_results;

		/*
		 * Subscribers by date
		 */
		$query = $wpdb->prepare(
			"SELECT searchdate.Date as date, COUNT( DISTINCT wcsubs.ID) as count
				FROM (
					SELECT DATE_FORMAT(a.Date,'%%Y-%%m-%%d') as Date, 0 as cnt
					FROM (
						SELECT curdate() - INTERVAL (a.a + (10 * b.a) + (100 * c.a)) DAY as Date
						FROM (
							SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2
							UNION ALL SELECT 3 UNION ALL SELECT 4
							UNION ALL SELECT 5 UNION ALL SELECT 6
							UNION ALL SELECT 7 UNION ALL SELECT 8
							UNION ALL SELECT 9
						) as a
						CROSS JOIN (
							SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2
							UNION ALL SELECT 3 UNION ALL SELECT 4
							UNION ALL SELECT 5 UNION ALL SELECT 6
							UNION ALL SELECT 7 UNION ALL SELECT 8
							UNION ALL SELECT 9
						) as b
						CROSS JOIN (
							SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2
							UNION ALL SELECT 3 UNION ALL SELECT 4
							UNION ALL SELECT 5 UNION ALL SELECT 6
							UNION ALL SELECT 7 UNION ALL SELECT 8
							UNION ALL SELECT 9
						) AS c
					) a
					WHERE a.Date >= %s AND a.Date <= %s
				) searchdate
				LEFT JOIN (
					{$wpdb->posts} AS wcsubs
					JOIN {$wpdb->posts} AS wcorder
						ON wcsubs.post_parent = wcorder.ID
							AND wcorder.post_type IN ( '" . implode( "','", wc_get_order_types( 'order-count' ) ) . "' )
							AND wcorder.post_status IN ( 'wc-" . implode( "','wc-", apply_filters( 'woocommerce_reports_order_statuses', array( 'completed', 'processing', 'on-hold', 'refunded' ) ) ) . "' )
					LEFT JOIN {$wpdb->postmeta} AS wcsmeta
						ON wcsubs.ID = wcsmeta.post_id
							AND wcsmeta.meta_key = %s
				) ON DATE( wcsubs.post_date ) <= searchdate.Date
					AND wcsubs.post_type IN ( 'shop_subscription' )
					AND ( DATE( wcsmeta.meta_value ) >= searchdate.Date
						OR wcsmeta.meta_value = 0 )
				GROUP BY searchdate.Date
				ORDER BY searchdate.Date ASC",
			date( 'Y-m-d', $this->start_date ),
			date( 'Y-m-d', strtotime( '+1 DAY', $this->end_date ) ),
			wcs_get_date_meta_key( 'end' )
		);

		$query_results = (array) $wpdb->get_results( $query );
		$this->report_data->subscriber_counts = $query_results;

		/*
		 * Subscription cancellations
		 */
		$query = $wpdb->prepare(
			"SELECT COUNT(DISTINCT wcsubs.ID) as count, wcsmeta_cancel.meta_value as cancel_date
				FROM {$wpdb->posts} as wcsubs
				JOIN {$wpdb->posts} AS wcorder
					ON wcsubs.post_parent = wcorder.ID
						AND wcorder.post_type IN ( 'shop_order' )
						AND wcorder.post_status IN ( 'wc-completed', 'wc-processing', 'wc-on-hold', 'wc-refunded' )
				JOIN {$wpdb->postmeta} AS wcsmeta_cancel
					ON wcsubs.ID = wcsmeta_cancel.post_id
						AND wcsmeta_cancel.meta_key = %s
				WHERE
						wcsmeta_cancel.meta_value BETWEEN %s AND %s
				GROUP BY YEAR(wcsmeta_cancel.meta_value), MONTH(wcsmeta_cancel.meta_value), DAY(wcsmeta_cancel.meta_value)
				ORDER BY wcsmeta_cancel.meta_value ASC",
			wcs_get_date_meta_key( 'cancelled' ),
			date( 'Y-m-d', $this->start_date ),
			date( 'Y-m-d', strtotime( '+1 DAY', $this->end_date ) )
		);

		$query_results = (array) $wpdb->get_results( $query );
		$this->report_data->cancel_counts = $query_results;

		/*
		 * Subscriptions ended
		 */
		$query = $wpdb->prepare(
			"SELECT COUNT(DISTINCT wcsubs.ID) as count, wcsmeta_end.meta_value as end_date
				FROM {$wpdb->posts} as wcsubs
				JOIN {$wpdb->posts} AS wcorder
					ON wcsubs.post_parent = wcorder.ID
						AND wcorder.post_type IN ( 'shop_order' )
						AND wcorder.post_status IN ( 'wc-completed', 'wc-processing', 'wc-on-hold', 'wc-refunded' )
				JOIN {$wpdb->postmeta} AS wcsmeta_end
					ON wcsubs.ID = wcsmeta_end.post_id
						AND wcsmeta_end.meta_key = %s
				WHERE
						wcsmeta_end.meta_value BETWEEN %s AND %s
				GROUP BY YEAR(wcsmeta_end.meta_value), MONTH(wcsmeta_end.meta_value), DAY(wcsmeta_end.meta_value)
				ORDER BY wcsmeta_end.meta_value ASC",
			wcs_get_date_meta_key( 'end' ),
			date( 'Y-m-d', $this->start_date ),
			date( 'Y-m-d', strtotime( '+1 DAY', $this->end_date ) )
		);

		$query_results = (array) $wpdb->get_results( $query );
		$this->report_data->ended_counts = $query_results;

		// Total up the query data
		$this->report_data->signup_totals  = absint( array_sum( wp_list_pluck( $this->report_data->signup_counts, 'signup_totals' ) ) );
		$this->report_data->renewal_totals = absint( array_sum( wp_list_pluck( $this->report_data->renewal_counts, 'renewal_totals' ) ) );
		$this->report_data->total_signups  = absint( array_sum( wp_list_pluck( $this->report_data->signup_counts, 'count' ) ) );
		$this->report_data->total_renewals = absint( array_sum( wp_list_pluck( $this->report_data->renewal_counts, 'count' ) ) );
		$this->report_data->total_switches = absint( array_sum( wp_list_pluck( $this->report_data->switch_counts, 'count' ) ) );
		$this->report_data->total_subs     = absint( end( $this->report_data->subscriber_counts )->count );
		$this->report_data->total_cancels  = absint( array_sum( wp_list_pluck( $this->report_data->cancel_counts, 'count' ) ) );
		$this->report_data->total_ended    = absint( array_sum( wp_list_pluck( $this->report_data->ended_counts, 'count' ) ) );

	}

	/**
	 * Get the legend for the main chart sidebar
	 * @return array
	 */
	public function get_chart_legend() {
		$legend = array();
		$data   = $this->get_report_data();

		$legend[] = array(
			'title'            => sprintf( __( '%s signup revenue in this period', 'woocommerce-subscriptions' ), '<strong>' . wc_price( $data->signup_totals ) . '</strong>' ),
			'placeholder'      => __( 'The sum of all orders containing signups including other items, fees, tax and shipping.', 'woocommerce-subscriptions' ),
			'color'            => $this->chart_colours['signup_total'],
			'highlight_series' => 4,
		);
		$legend[] = array(
			'title'            => sprintf( __( '%s renewal revenue in this period', 'woocommerce-subscriptions' ), '<strong>' . wc_price( $data->renewal_totals ) . '</strong>' ),
			'placeholder'      => __( 'The sum of all renewal orders including tax and shipping.', 'woocommerce-subscriptions' ),
			'color'            => $this->chart_colours['renewal_total'],
			'highlight_series' => 3,
		);
		$legend[] = array(
			'title' => sprintf( __( '%s subscription signups', 'woocommerce-subscriptions' ), '<strong>' . $this->report_data->total_signups . '</strong>' ),
			'color' => $this->chart_colours['signup_count'],
			'highlight_series' => 1,
		);

		$legend[] = array(
			'title' => sprintf( __( '%s switched subscriptions', 'woocommerce-subscriptions' ), '<strong>' . $data->total_switches . '</strong>' ),
			'color' => $this->chart_colours['switch_count'],
			'highlight_series' => 0,
		);

		$legend[] = array(
			'title' => sprintf( __( '%s subscription renewals', 'woocommerce-subscriptions' ), '<strong>' . $data->total_renewals . '</strong>' ),
			'color' => $this->chart_colours['renewal_count'],
			'highlight_series' => 2,
		);

		$legend[] = array(
			'title'            => sprintf( __( '%s current subscriptions', 'woocommerce-subscriptions' ), '<strong>' . $data->total_subs . '</strong>' ),
			'placeholder'      => __( 'The number of subscriptions at the end of the period which have not ended.', 'woocommerce-subscriptions' ),
			'color'            => $this->chart_colours['subscriber_count'],
			'highlight_series' => 5,
		);

		$legend[] = array(
			'title'            => sprintf( __( '%s subscription cancellations', 'woocommerce-subscriptions' ), '<strong>' . $data->total_cancels . '</strong>' ),
			'placeholder'      => __( 'All subscriptions a customer or store manager has cancelled within this timeframe.  The pre-paid term may not yet have ended so the customer may still have access.', 'woocommerce-subscriptions' ),
			'color'            => $this->chart_colours['cancel_count'],
			'highlight_series' => 5,
		);

		$legend[] = array(
			'title'            => sprintf( __( '%s subscriptions ended', 'woocommerce-subscriptions' ), '<strong>' . $data->total_ended . '</strong>' ),
			'placeholder'      => __( 'All subscriptions which have either expired or reached the end of the prepaid term if it was cancelled.', 'woocommerce-subscriptions' ),
			'color'            => $this->chart_colours['ended_count'],
			'highlight_series' => 5,
		);

		return $legend;
	}

	/**
	 * Output the report
	 */
	public function output_report() {
		$ranges = array(
			'year'         => __( 'Year', 'woocommerce-subscriptions' ),
			'last_month'   => __( 'Last Month', 'woocommerce-subscriptions' ),
			'month'        => __( 'This Month', 'woocommerce-subscriptions' ),
			'7day'         => __( 'Last 7 Days', 'woocommerce-subscriptions' ),
		);

		$this->chart_colours = array(
			'signup_count'     => '#5da5da',
			'switch_count'     => '#439ad9',
			'renewal_count'    => '#f29ec4',
			'subscriber_count' => '#cc3300',
			'renewal_total'    => '#CC9900',
			'signup_total'     => '#99CC00',
			'cancel_count'	   => '#800000',
			'ended_count'      => '#804000',
		);

		$current_range = ! empty( $_GET['range'] ) ? sanitize_text_field( $_GET['range'] ) : '7day';

		if ( ! in_array( $current_range, array( 'custom', 'year', 'last_month', 'month', '7day' ) ) ) {
			$current_range = '7day';
		}

		$this->calculate_current_range( $current_range );

		include( WC()->plugin_path() . '/includes/admin/views/html-report-by-date.php' );
	}

	/**
	 * Output an export link
	 */
	public function get_export_button() {
		$current_range = ! empty( $_GET['range'] ) ? sanitize_text_field( $_GET['range'] ) : '7day';
		?>
		<a
			href="#"
			download="report-<?php echo esc_attr( $current_range ); ?>-<?php echo esc_attr( date_i18n( 'Y-m-d', current_time( 'timestamp' ) ) ); ?>.csv"
			class="export_csv"
			data-export="chart"
			data-xaxes="<?php esc_attr_e( 'Date', 'woocommerce-subscriptions' ); ?>"
			data-exclude_series="2"
			data-groupby="<?php echo esc_attr( $this->chart_groupby ); ?>"
		>
			<?php esc_attr_e( 'Export CSV', 'woocommerce-subscriptions' ); ?>
		</a>
		<?php
	}


	/**
	 * Get the main chart
	 *
	 * @return string
	 */
	public function get_main_chart() {
		global $wp_locale;

		// Prepare data for report
		$signup_totals     = $this->prepare_chart_data( $this->report_data->signup_counts, 'post_date', 'signup_totals', $this->chart_interval, $this->start_date, $this->chart_groupby );
		$renewal_totals    = $this->prepare_chart_data( $this->report_data->renewal_counts, 'post_date', 'renewal_totals', $this->chart_interval, $this->start_date, $this->chart_groupby );
		$signup_counts     = $this->prepare_chart_data( $this->report_data->signup_counts, 'post_date', 'count', $this->chart_interval, $this->start_date, $this->chart_groupby );
		$renewal_counts    = $this->prepare_chart_data( $this->report_data->renewal_counts, 'post_date', 'count', $this->chart_interval, $this->start_date, $this->chart_groupby );
		$switch_counts     = $this->prepare_chart_data( $this->report_data->switch_counts, 'post_date', 'count', $this->chart_interval, $this->start_date, $this->chart_groupby );
		$subscriber_counts = $this->prepare_chart_data( $this->report_data->subscriber_counts, 'date', 'count', $this->chart_interval, $this->start_date, $this->chart_groupby );

		// Encode in json format
		$chart_data = array(
			'signup_totals'      => array_map( array( $this, 'round_chart_totals' ), array_values( $signup_totals ) ),
			'renewal_totals'     => array_map( array( $this, 'round_chart_totals' ), array_values( $renewal_totals ) ),
			'signup_counts'      => array_values( $signup_counts ),
			'renewal_counts'     => array_values( $renewal_counts ),
			'switch_counts'      => array_values( $switch_counts ),
			'subscriber_counts'  => array_values( $subscriber_counts ),
		);

		$timeformat = ( $this->chart_groupby == 'day' ? '%d %b' : '%b' );

		?>
		<div class="chart-container">
			<div class="chart-placeholder main"></div>
		</div>
		<script type="text/javascript">

			var main_chart;

			jQuery(function(){
				var order_data = jQuery.parseJSON( '<?php echo json_encode( $chart_data ); ?>' );
				var drawGraph = function( highlight ) {
					var series = [
						{
							label: "<?php echo esc_js( __( 'Switched subscriptions', 'woocommerce-subscriptions' ) ) ?>",
							data: order_data.switch_counts,
							color: '<?php echo esc_js( $this->chart_colours['switch_count'] ); ?>',
							bars: { fillColor: '<?php echo esc_js( $this->chart_colours['switch_count'] ); ?>', order: 1, fill: true, show: true, lineWidth: 0, barWidth: <?php echo esc_js( $this->barwidth ); ?> * 0.33, align: 'center' },
							shadowSize: 0,
							hoverable: false,
						},
						{
							label: "<?php echo esc_js( __( 'Subscriptions signups', 'woocommerce-subscriptions' ) ) ?>",
							data: order_data.signup_counts,
							color: '<?php echo esc_js( $this->chart_colours['signup_count'] ); ?>',
							bars: { order: 1, fill: true, show: true, lineWidth: 0, barWidth: <?php echo esc_js( $this->barwidth ); ?> * 0.33, align: 'center' },
							shadowSize: 0,
							hoverable: false,
						},
						{
							label: "<?php echo esc_js( __( 'Number of renewals', 'woocommerce-subscriptions' ) ) ?>",
							data: order_data.renewal_counts,
							color: '<?php echo esc_js( $this->chart_colours['renewal_count'] ); ?>',
							bars: { fillColor: '<?php echo esc_js( $this->chart_colours['renewal_count'] ); ?>', order: 2, fill: true, show: true, lineWidth: 0, barWidth: <?php echo esc_js( $this->barwidth ); ?> * 0.33, align: 'center' },
							shadowSize: 0,
							hoverable: false,
						},
						{
							label: "<?php echo esc_js( __( 'Renewal Totals', 'woocommerce-subscriptions' ) ) ?>",
							data: order_data.renewal_totals,
							yaxis: 2,
							color: '<?php echo esc_js( $this->chart_colours['renewal_total'] ); ?>',
							points: { show: true, radius: 5, lineWidth: 2, fillColor: '#fff', fill: true },
							lines: { show: true, lineWidth: 2, fill: false },
							shadowSize: 0,
							<?php echo wp_kses_post( $this->get_currency_tooltip() ); ?>
						},
						{
							label: "<?php echo esc_js( __( 'Signup Totals', 'woocommerce-subscriptions' ) ) ?>",
							data: order_data.signup_totals,
							yaxis: 2,
							color: '<?php echo esc_js( $this->chart_colours['signup_total'] ); ?>',
							points: { show: true, radius: 5, lineWidth: 2, fillColor: '#fff', fill: true },
							lines: { show: true, lineWidth: 2, fill: false },
							shadowSize: 0,
							<?php echo wp_kses_post( $this->get_currency_tooltip() ); ?>
						},
						{
							label: "<?php echo esc_js( __( 'Subscriptions', 'woocommerce-subscriptions' ) ) ?>",
							data: order_data.subscriber_counts,
							color: '<?php echo esc_js( $this->chart_colours['subscriber_count'] ); ?>',
							bars: { fillColor: '<?php echo esc_js( $this->chart_colours['subscriber_count'] ); ?>', order: 3, fill: true, show: true, lineWidth: 0, barWidth: <?php echo esc_js( $this->barwidth ); ?> * 0.33, align: 'center' },
							shadowSize: 0,
							hoverable: false,
						},
					];

					if ( highlight !== 'undefined' && series[ highlight ] ) {
						highlight_series = series[ highlight ];

						highlight_series.color = '#9c5d90';

						if ( highlight_series.bars ) {
							highlight_series.bars.fillColor = '#9c5d90';
						}

						if ( highlight_series.lines ) {
							highlight_series.lines.lineWidth = 5;
						}
					}

					main_chart = jQuery.plot(
						jQuery('.chart-placeholder.main'),
						series,
						{
							legend: {
								show: false
							},
							grid: {
								color: '#aaa',
								borderColor: 'transparent',
								borderWidth: 0,
								hoverable: true
							},
							xaxes: [ {
								color: '#aaa',
								position: "bottom",
								tickColor: 'transparent',
								mode: "time",
								timeformat: "<?php echo esc_js( $timeformat ) ?>",
								monthNames: <?php echo json_encode( array_values( $wp_locale->month_abbrev ) ) ?>,
								tickLength: 1,
								minTickSize: [1, "<?php echo esc_js( $this->chart_groupby ); ?>"],
								font: {
									color: "#aaa"
								}
							} ],
							yaxes: [
								{
									min: 0,
									minTickSize: 1,
									tickDecimals: 0,
									color: '#d4d9dc',
									font: { color: "#aaa" }
								},
								{
									position: "right",
									min: 0,
									tickDecimals: 2,
									alignTicksWithAxis: 1,
									color: 'transparent',
									font: { color: "#aaa" }
								}
							],
							stack: true,
							bars:{ // show the bars with a width of .4
        						show: true,
        						barWidth: .4
   							},
						}
					);

					jQuery('.chart-placeholder').resize();
				}

				drawGraph();

				jQuery('.highlight_series').hover(
					function() {
						drawGraph( jQuery(this).data('series') );
					},
					function() {
						drawGraph();
					}
				);
			});
		</script>
		<?php
	}

	/**
	 * Round our totals correctly.
	 * @param  string $amount
	 * @return string
	 */
	private function round_chart_totals( $amount ) {
		if ( is_array( $amount ) ) {
			return array( $amount[0], wc_format_decimal( $amount[1], wc_get_price_decimals() ) );
		} else {
			return wc_format_decimal( $amount, wc_get_price_decimals() );
		}
	}
}
