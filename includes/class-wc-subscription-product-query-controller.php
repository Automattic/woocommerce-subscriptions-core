<?php
/**
 * Subscription Product Query Controller
 *
 * This class is used by wcs_get_subscriptions() to determine if the query should be filtered by product ID or variation ID after the query has been run.
 *
 * Querying subscriptions by product or variation ID is an expensive database operation. This class provides methods to determine if a wcs_get_subscriptions()
 * set of args would be better served by filtering the query results by product ID or variation ID after the query has been run, rather than querying for
 * subscriptions by products.
 *
 * If the wcs_get_subscriptions() args are already limited by customer ID or order ID we know that the results will be sufficiently limited. In these cases, we can
 * filter the results by product ID or variation ID after the query has been run.
 *
 * @package WooCommerce Subscriptions
 * @subpackage Component
 * @since 6.9.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * WC_Subscription_Product_Query class.
 */
class WC_Subscription_Product_Query_Controller {

	/**
	 * The wcs_get_subscriptions() query variables.
	 *
	 * @var array
	 */
	private $query_vars = [];

	/**
	 * Constructor.
	 *
	 * @param array $query_vars The wcs_get_subscriptions() query variables.
	 */
	public function __construct( $query_vars ) {
		$this->query_vars = $query_vars;
	}

	/**
	 * Determines if the query is for a specific product or variation.
	 *
	 * @return bool True if the query is for a specific product or variation, otherwise false.
	 */
	public function has_product_query() {
		return ( 0 !== $this->query_vars['product_id'] && is_numeric( $this->query_vars['product_id'] ) ) || ( 0 !== $this->query_vars['variation_id'] && is_numeric( $this->query_vars['variation_id'] ) );
	}

	/**
	 * Determines if the wcs_get_subscription() query should filter the results by product ID or variation ID after the query has been run.
	 *
	 * If the wcs_get_subscriptions() query is substantially limited (eg to a customer or order) we know that the results will be small. In these cases, we can
	 * filter the results by product ID or variation ID after the query has been run for better performance.
	 *
	 * @return bool True if the subscriptions should be queried by product ID, otherwise false.
	 */
	public function should_filter_query_results() {
		$can_filter_results = false;

		// If we're querying by order ID or customer ID, we can filter the results by product ID after the query has been run.
		if ( isset( $this->query_vars['order_id'] ) || isset( $this->query_vars['customer_id'] ) ) {
			$can_filter_results = apply_filters( 'wcs_should_filter_subscriptions_results_by_product_id', true, $this->query_vars );
		}

		return $can_filter_results;
	}

	/**
	 * Filters the subscription query results by product ID or variation ID.
	 *
	 * @param WC_Subscriptions[] $subscriptions
	 * @return WC_Subscriptions[] The filtered subscriptions.
	 */
	public function filter_subscriptions( $subscriptions ) {
		$filtered_subscriptions = [];
		$product_id             = $this->query_vars['product_id'] ?? 0;
		$variation_id           = $this->query_vars['variation_id'] ?? 0;

		if ( empty( $product_id ) && empty( $variation_id ) ) {
			return $subscriptions;
		}

		// Filter the subscriptions by product ID or variation ID.
		foreach ( $subscriptions as $subscription_id => $subscription ) {
			if (
				( $variation_id && $subscription->has_product( $variation_id ) ) ||
				( $product_id && $subscription->has_product( $product_id ) )
			) {
				$filtered_subscriptions[ $subscription_id ] = $subscription;
			}
		}

		return $filtered_subscriptions;
	}

	/**
	 * Applies pagination to the subscriptions array.
	 *
	 * @param WC_Subscriptions[] $subscriptions
	 * @return WC_Subscriptions[] The subscriptions array with pagination applied.
	 */
	public function paginate_results( $subscriptions ) {
		$per_page = $this->query_vars['subscriptions_per_page'];
		$page     = $this->query_vars['paged'];
		$offset   = $this->query_vars['offset'];

		// If the limit is -1, return all subscriptions.
		if ( -1 === $per_page ) {
			return $subscriptions;
		}

		if ( $offset ) {
			$start_index = $offset;
		} else {
			// Calculate the starting index for the slice.
			$start_index = ( $page - 1 ) * $per_page;
		}

		// Slice the subscriptions array to get the required items.
		return array_slice( $subscriptions, $start_index, $per_page, true );
	}
}
