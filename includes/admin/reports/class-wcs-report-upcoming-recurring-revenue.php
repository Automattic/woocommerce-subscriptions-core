<?php
/**
 * Subscriptions Admin Report - Upcoming Recurring Revenue
 *
 * Creates the subscription admin reports area.
 *
 * @package		WooCommerce Subscriptions
 * @subpackage	WC_Subscriptions_Admin_Reports
 * @category	Class
 * @author		Prospress
 * @since		2.1
 */
class WC_Report_Upcoming_Recurring_Revenue extends WC_Admin_Report {

	public $chart_colours = array();
	public $order_ids_recurring_totals = null;

	/**
	 * Get the legend for the main chart sidebar
	 * @return array
	 */
	public function get_chart_legend() {
		global $wp_locale, $wpdb;

		$current_range = ! empty( $_GET['range'] ) ? $_GET['range'] : '7day';

		if ( ! in_array( $current_range, array( 'custom', 'year', 'month', '7day' ) ) ) {
			$current_range = '7day';
		}

		$this->calculate_future_range( $current_range );

		$base_query = $wpdb->prepare(
			"SELECT
						SUM(m.meta_value) as recurring_total,
						COUNT(m.meta_value) as total_renewals,
						o.scheduled_date
						FROM {$wpdb->prefix}postmeta m
						RIGHT JOIN (
							SELECT
							IF(
								LOCATE('subscription_id\":', post_content) > 0,
								SUBSTRING_INDEX (
									SUBSTRING(
										post_content,
										LOCATE('subscription_id\":', post_content) + 17),
									'}',
									1),
								post_content
							) as order_id,
							post_date as scheduled_date
							FROM {$wpdb->prefix}posts
							WHERE post_title = 'woocommerce_scheduled_subscription_payment' AND post_status = 'pending'
							 AND post_date BETWEEN '%s' AND '%s'
						) o ON m.post_id = o.order_id
						WHERE m.meta_key = '_order_total'
						GROUP BY {$this->group_by_query}",
			date( 'Y-m-d H:i:s', $this->start_date ),
			date( 'Y-m-d H:i:s', $this->end_date )
		);

		$this->order_ids_recurring_totals = $wpdb->get_results( $base_query );

		$total_renewal_revenue = 0;
		$total_renewal_count = 0;

		foreach ( $this->order_ids_recurring_totals as $r ) {
			$total_renewal_revenue += $r->recurring_total;
			$total_renewal_count   += $r->total_renewals;
		}

		$legend   = array();

		$this->average_sales = $total_renewal_revenue / $total_renewal_count;

		$legend[] = array(
			'title' => sprintf( __( '%s renewal income in this period', 'woocommerce-subscriptions' ), '<strong>' . wc_price( $total_renewal_revenue ) . '</strong>' ),
			'color' => $this->chart_colours['renewals_amount'],
			'highlight_series' => 1,
		);
		$legend[] = array(
			'title' => sprintf( __( '%s renewal orders', 'woocommerce-subscriptions' ), '<strong>' . $total_renewal_count . '</strong>' ),
			'color' => $this->chart_colours['renewals_count'],
			'highlight_series' => 0,
		);
		$legend[] = array(
			'title' => sprintf( __( '%s average renewal amount', 'woocommerce-subscriptions' ), '<strong>' . wc_price( $this->average_sales ) . '</strong>' ),
			'color' => $this->chart_colours['renewals_average'],
			'highlight_series' => 2,
		);

		return $legend;
	}

	/**
	 * Output the report
	 */
	public function output_report() {
		global $woocommerce, $wpdb, $wp_locale;

		$ranges = array(
			'year'  => __( 'Next Year', 'woocommerce-subscriptions' ),
			'month' => __( 'Next Month', 'woocommerce-subscriptions' ),
			'7day'  => __( 'Next 7 Days', 'woocommerce-subscriptions' ),
		);

		$this->chart_colours = array(
			'renewals_amount' => '#1abc9c',
			'renewals_count'  => '#e67e22',
			'renewals_average'   => '#d4d9dc',
		);

		$current_range = ! empty( $_GET['range'] ) ? sanitize_text_field( $_GET['range'] ) : '7day';

		if ( ! in_array( $current_range, array( 'custom', 'year', 'month', '7day' ) ) ) {
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
			data-xaxes="<?php esc_attr_e( 'Date', 'woocommerce' ); ?>"
			data-exclude_series="2"
			data-groupby="<?php echo esc_attr( $this->chart_groupby ); ?>"
		>
			<?php esc_html_e( 'Export CSV', 'woocommerce' ); ?>
		</a>
		<?php
	}

	/**
	 * Get the main chart
	 * @return string
	 */
	public function get_main_chart() {
		global $wp_locale, $wpdb;

		// Prepare data for report
		$renewal_amounts     = $this->prepare_chart_data( $this->order_ids_recurring_totals, 'scheduled_date', 'recurring_total', $this->chart_interval, $this->start_date, $this->chart_groupby );
		$renewal_counts      = $this->prepare_chart_data( $this->order_ids_recurring_totals, 'scheduled_date', 'total_renewals', $this->chart_interval, $this->start_date, $this->chart_groupby );

		$chart_data = array(
			'renewal_amounts'      => array_values( $renewal_amounts ),
			'renewal_counts'       => array_values( $renewal_counts ),
		);

		?>
		<div class="chart-container" id="woocommerce_subscriptions_coming_rev_chart">
			<div class="chart-placeholder main"></div>
		</div>
		<script type="text/javascript">

			var main_chart;

			jQuery(function(){
				var order_data = jQuery.parseJSON( '<?php echo json_encode( $chart_data ); ?>' );
				var drawGraph = function( highlight ) {
					var series = [

						{
							label: "<?php echo esc_js( __( 'Renewals count', 'woocommerce' ) ) ?>",
							data: order_data.renewal_counts,
							yaxis: 2,
							color: '<?php echo esc_js( $this->chart_colours['renewals_count'] ); ?>',
							points: { show: true, radius: 5, lineWidth: 3, fillColor: '#fff', fill: true },
							lines: { show: true, lineWidth: 4, fill: false },
							shadowSize: 0
						},
						{
							label: "<?php echo esc_js( __( 'Renewals amount', 'woocommerce' ) ) ?>",
							data: order_data.renewal_amounts,
							yaxis: 2,
							color: '<?php echo esc_js( $this->chart_colours['renewals_amount'] ); ?>',
							points: { show: true, radius: 5, lineWidth: 3, fillColor: '#fff', fill: true },
							lines: { show: true, lineWidth: 4, fill: false },
							shadowSize: 0,
							prepend_tooltip: "<?php echo esc_js( get_woocommerce_currency_symbol() ); ?>"
						}
					];

					if ( highlight !== 'undefined' && series[ highlight ] ) {
						highlight_series = series[ highlight ];

						highlight_series.color = '#9c5d90';

						if ( highlight_series.bars )
							highlight_series.bars.fillColor = '#9c5d90';

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
								timeformat: "<?php echo esc_js( ( $this->chart_groupby == 'day' ? '%d %b' : '%b' ) ); ?>",
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
	 * Get the current range and calculate the start and end dates
	 *
	 * @param  string $current_range
	 */
	public function calculate_future_range( $current_range ) {
		switch ( $current_range ) {
			case 'custom' :
				$this->start_date = strtotime( sanitize_text_field( $_GET['start_date'] ) );
				$this->end_date   = strtotime( 'midnight', strtotime( sanitize_text_field( $_GET['end_date'] ) ) );

				if ( ! $this->end_date ) {
					$this->end_date = current_time( 'timestamp' );
				}

				$interval = 0;
				$min_date = $this->start_date;
				while ( ( $min_date = wcs_add_months( $min_date, '1' ) ) <= $this->end_date ) {
				    $interval ++;
				}

				// 3 months max for day view
				if ( $interval > 3 ) {
					$this->chart_groupby  = 'month';
				} else {
					$this->chart_groupby  = 'day';
				}
			break;
			case 'year' :
				$this->start_date    = strtotime( 'now', current_time( 'timestamp' ) );
				$this->end_date      = strtotime( '+1 YEAR', current_time( 'timestamp' ) );
				$this->chart_groupby = 'month';
			break;
			case 'month' :
				$this->start_date    = strtotime( 'now', current_time( 'timestamp' ) );
				$this->end_date      = wcs_add_months( current_time( 'timestamp' ), '1' );
				$this->chart_groupby = 'day';
			break;
			case '7day' :
				$this->start_date    = strtotime( 'now', current_time( 'timestamp' ) );
				$this->end_date   = strtotime( '+7 days', current_time( 'timestamp' ) );
				$this->chart_groupby         = 'day';
			break;
		}

		// Group by
		switch ( $this->chart_groupby ) {
			case 'day' :
				$this->group_by_query       = 'YEAR(o.scheduled_date), MONTH(o.scheduled_date), DAY(o.scheduled_date)';
				$this->chart_interval       = ceil( max( 0, ( $this->end_date - $this->start_date ) / ( 60 * 60 * 24 ) ) );
				$this->barwidth             = 60 * 60 * 24 * 1000;
			break;
			case 'month' :
				$this->group_by_query       = 'YEAR(o.scheduled_date), MONTH(o.scheduled_date)';
				$this->chart_interval = 0;
				$min_date             = $this->start_date;
				while ( ( $min_date   = wcs_add_months( $min_date, '1' ) ) <= $this->end_date ) {
					$this->chart_interval ++;
				}
				$this->barwidth             = 60 * 60 * 24 * 7 * 4 * 1000;
			break;
		}
	}
}
