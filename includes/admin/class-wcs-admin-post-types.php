<?php
/**
 * Post Types Admin
 *
 * @author   Prospress
 * @category Admin
 * @package  WooCommerce Subscriptions/Admin
 * @version  2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( class_exists( 'WCS_Admin_Post_Types' ) ) {
	new WCS_Admin_Post_Types();

	return;
}

/**
 * WC_Admin_Post_Types Class
 *
 * Handles the edit posts views and some functionality on the edit post screen for WC post types.
 */
class WCS_Admin_Post_Types {

	/**
	 * Constructor
	 */
	public function __construct() {

		// Subscription list table columns and their content
		add_filter( 'manage_edit-shop_subscription_columns', array( $this, 'shop_subscription_columns' ) );
		add_filter( 'manage_edit-shop_subscription_sortable_columns', array( $this, 'shop_subscription_sortable_columns' ) );
		add_action( 'manage_shop_subscription_posts_custom_column', array( $this, 'render_shop_subscription_columns' ), 2 );

		// Bulk actions
		add_action( 'admin_print_footer_scripts', array( $this, 'bulk_actions' ) );
		add_action( 'load-edit.php', array( $this, 'parse_bulk_actions' ) );

		// Subscription order/filter
		add_filter( 'request', array( $this, 'request_query' ) );

		// Subscription Search
		add_filter( 'get_search_query', array( $this, 'shop_subscription_search_label' ) );
		add_filter( 'query_vars', array( $this, 'add_custom_query_var' ) );
		add_action( 'parse_query', array( $this, 'shop_subscription_search_custom_fields' ) );

		add_filter( 'post_updated_messages', array( $this, 'post_updated_messages' ) );
	}

	/**
	 * Add extra options to the bulk actions dropdown
	 *
	 * It's only on the All Shop Subscriptions screen.
	 * Introducing new filter: woocommerce_subscription_bulk_actions. This has to be done through jQuery as the
	 * 'bulk_actions' filter that WordPress has can only be used to remove bulk actions, not to add them.
	 *
	 * This is a filterable array where the key is the action (will become query arg), and the value is a translatable
	 * string. The same array is used to
	 *
	 */
	public function print_bulk_actions_script() {
		// We only want this on the shop_subscription all page
		if ( 'shop_subscription' !== get_post_type() ) {
			return;
		}

		// Make it filterable in case extensions want to change this
		$bulk_actions = apply_filters( 'woocommerce_subscription_bulk_actions', array(
			'active' => __( 'Reactivate', 'woocommerce-subscriptions' ),
			'on-hold' => __( 'Put on-hold', 'woocommerce-subscriptions' ),
			'cancelled' => __( 'Cancel', 'woocommerce-subscriptions' ),
		) );

		?>
		<script type="text/javascript">
			jQuery(document).ready(function($) {
				<?php
				foreach ( $bulk_actions as $action => $title ) {
					?>
					$('<option>')
						.val('<?php echo esc_attr( $action ); ?>')
						.text('<?php echo esc_html( $title ); ?>')
						.appendTo("select[name='action'], select[name='action2']" );
					<?php
				}
				?>
			});
		</script>
		<?php
	}

	/**
	 * Deals with bulk actions. The style is similar to what WooCommerce is doing. Extensions will have to define their
	 * own logic by copying the concept behind this method.
	 */
	public function parse_bulk_actions() {
		// We only want to deal with shop_subscriptions. In case any other CPTs have an 'active' action
		if ( 'shop_subscription' !== $_REQUEST['post_type'] ) {
			return;
		}

		$wp_list_table = _get_list_table( 'WP_Posts_List_Table' );
		$action = $wp_list_table->current_action();

		switch ( $action ) {
			case 'active':
			case 'on-hold':
			case 'cancelled' :
				$new_status = $action;
				break;
			default:
				return;
		}

		$changed = 0;

		$subscription_ids = array_map( 'absint', (array) $_REQUEST['post'] );

		foreach ( $subscription_ids as $subscription_id ) {
			$subscription = wcs_get_subscription( $subscription_id );
			$subscription->update_status( $new_status, __( 'Order status changed by bulk edit:', 'woocommerce' ) );
			$changed++;
		}

		$sendback = add_query_arg( array( 'post_type' => 'shop_subscription', 'changed' => $changed, 'ids' => join( ',', $subscription_ids ) ), '' );

		wp_redirect( $sendback );
		exit();
	}

	/**
	 * Define custom columns for subscription
	 *
	 * Column names that have a corresponding `WC_Order` column use the `order_` prefix here
	 * to take advantage of core WooCommerce assets, like JS/CSS.
	 *
	 * @param  array $existing_columns
	 * @return array
	 */
	public function shop_subscription_columns( $existing_columns ) {

		$columns = array(
			'cb'                => '<input type="checkbox" />',
			'status'            => __( 'Status', 'woocommerce-subscriptions' ),
			'order_title'       => __( 'Subscription', 'woocommerce-subscriptions' ),
			'order_items'       => __( 'Items', 'woocommerce-subscriptions' ),
			'recurring_total'   => __( 'Total', 'woocommerce-subscriptions' ),
			'start_date'        => __( 'Start Date', 'woocommerce-subscriptions' ),
			'trial_end_date'    => __( 'Trial End', 'woocommerce-subscriptions' ),
			'next_payment_date' => __( 'Next Payment', 'woocommerce-subscriptions' ),
			'last_payment_date' => __( 'Last Payment', 'woocommerce-subscriptions' ),
			'end_date'          => __( 'End Date', 'woocommerce-subscriptions' ),
			'orders'            => __( 'Orders', 'woocommerce-subscriptions' ),
		);

		return $columns;
	}

	/**
	 * Output custom columns for coupons
	 * @param  string $column
	 */
	public function render_shop_subscription_columns( $column ) {
		global $post, $the_subscription;

		if ( empty( $the_subscription ) || $the_subscription->id != $post->ID ) {
			$the_subscription = wcs_get_subscription( $post->ID );
		}

		$column_content   = '';

		switch ( $column ) {
			case 'status' :

				echo esc_html( $the_subscription->get_status() );
				//printf( '<mark class="%s tips" data-tip="%s">%s</mark>', sanitize_title( $the_subscription->get_status() ), wc_get_order_status_name( $the_subscription->get_status() ), wc_get_order_status_name( $the_subscription->get_status() ) );
				break;

			case 'order_title' :

				$customer_tip = '';

				if ( $address = $the_subscription->get_formatted_billing_address() ) {
					$customer_tip .= __( 'Billing:', 'woocommerce-subscriptions' ) . ' ' . esc_html( $address );
				}

				if ( $the_subscription->billing_email ) {
					$customer_tip .= '<br/><br/>' . __( 'Email:', 'woocommerce-subscriptions' ) . ' ' . esc_attr( $the_subscription->billing_email );
				}

				if ( $the_subscription->billing_phone ) {
					$customer_tip .= '<br/><br/>' . __( 'Tel:', 'woocommerce-subscriptions' ) . ' ' . esc_html( $the_subscription->billing_phone );
				}

				if ( ! empty( $customer_tip ) ) {
					echo '<div class="tips" data-tip="' . esc_attr( $customer_tip ) . '">';
				}

				// This is to stop PHP from complaining
				$username = '';

				if ( $the_subscription->get_user_id() ) {

					$user_info = get_userdata( $the_subscription->get_user_id() );
					$username  = '<a href="user-edit.php?user_id=' . absint( $user_info->ID ) . '">';

					if ( $the_subscription->billing_first_name || $the_subscription->billing_last_name ) {
						$username .= esc_html( ucfirst( $the_subscription->billing_first_name ) . ' ' . ucfirst( $the_subscription->billing_last_name ) );
					} elseif ( $user_info->first_name || $user_info->last_name ) {
						$username .= esc_html( ucfirst( $user_info->first_name ) . ' ' . ucfirst( $user_info->last_name ) );
					} else {
						$username .= esc_html( ucfirst( $user_info->display_name ) );
					}

					$username .= '</a>';

				} elseif ( $the_subscription->billing_first_name || $the_subscription->billing_last_name ) {
					$username = trim( $the_subscription->billing_first_name . ' ' . $the_subscription->billing_last_name );
				}

				printf( _x( '%s for %s', 'Subscription number for X', 'woocommerce-subscriptions' ), '<a href="' . esc_url( admin_url( 'post.php?post=' . absint( $post->ID ) . '&action=edit' ) ) . '"><strong>' . esc_attr( $the_subscription->get_order_number() ) . '</strong></a>', $username );

				echo '</div>';

				break;
			case 'order_items' :
				// Display either the item name or item count with a collapsed list of items
				$subscription_items = $the_subscription->get_items();
				switch ( count( $subscription_items ) ) {
					case 0 :
						echo '&ndash;';
						break;
					case 1 :
						foreach ( $the_subscription->get_items() as $item ) {
							$_product       = apply_filters( 'woocommerce_order_item_product', $the_subscription->get_product_from_item( $item ), $item );
							$item_meta      = new WC_Order_Item_Meta( $item['item_meta'] );
							$item_meta_html = $item_meta->display( true, true );
							$item_quantity  = absint( $item['qty'] );

							$item_name = '';
							if ( wc_product_sku_enabled() && $_product && $_product->get_sku() ) {
								$item_name .= $_product->get_sku() . ' - ';
							}
							$item_name .= $item['name'];
							$item_name = apply_filters( 'woocommerce_order_item_name', $item_name, $item );
							$item_name = esc_html( $item_name );
							if ( $item_quantity > 1 ) {
								$item_name = sprintf( '%s &times; %s', absint( $item_quantity ), $item_name );
							}
							if ( $_product ) {
								$item_name = sprintf( '<a href="%s">%s</a>', get_edit_post_link( $_product->id ), $item_name );
							}
							?>
							<div class="order-item">
								<?php echo wp_kses( $item_name, array( 'a' => array( 'href' => array() ) ) ); ?>
								<?php if ( $item_meta_html ) : ?>
								<a class="tips" href="#" data-tip="<?php echo esc_attr( $item_meta_html ); ?>">[?]</a>
								<?php endif; ?>
							</div>
							<?php
						}
						break;
					default :
						echo '<a href="#" class="show_order_items">' . esc_html( apply_filters( 'woocommerce_admin_order_item_count', sprintf( _n( '%d item', '%d items', $the_subscription->get_item_count(), 'woocommerce-subscriptions' ), $the_subscription->get_item_count() ), $the_subscription ) ) . '</a>';
						echo '<table class="order_items" cellspacing="0">';

						foreach ( $the_subscription->get_items() as $item ) {
							$_product       = apply_filters( 'woocommerce_order_item_product', $the_subscription->get_product_from_item( $item ), $item );
							$item_meta      = new WC_Order_Item_Meta( $item['item_meta'] );
							$item_meta_html = $item_meta->display( true, true );
							?>
							<tr class="<?php echo esc_attr( apply_filters( 'woocommerce_admin_order_item_class', '', $item ) ); ?>">
								<td class="qty"><?php echo absint( $item['qty'] ); ?></td>
								<td class="name">
									<?php if ( wc_product_sku_enabled() && $_product && $_product->get_sku() ) {
										echo esc_html( $_product->get_sku() ) . ' - ';
									} ?>
									<?php echo esc_html( apply_filters( 'woocommerce_order_item_name', $item['name'], $item ) ); ?>
									<?php if ( $item_meta_html ) { ?>
										<a class="tips" href="#" data-tip="<?php echo esc_attr( $item_meta_html ); ?>">[?]</a>
									<?php } ?>
								</td>
							</tr>
							<?php
						}

						echo '</table>';
						break;
				}
				break;

			case 'recurring_total' :
				echo esc_html( strip_tags( $the_subscription->get_formatted_order_total() ) );

				if ( $the_subscription->payment_method_title ) {
					echo '<small class="meta">' . esc_html( sprintf( __( 'Via %s', 'woocommerce-subscriptions' ), $the_subscription->payment_method_title ) ) . '</small>';
				}
				break;

			case 'start_date':
			case 'trial_end_date':
			case 'next_payment_date':
			case 'last_payment_date':
			case 'end_date':
				if ( 0 == $the_subscription->get_time( $column, 'gmt' ) ) {
					$column_content = '-';
				} else {
					$column_content = sprintf( '<time class="%s" title="%s">%s</time>', esc_attr( $column ), esc_attr( $the_subscription->get_time( $column, 'site' ) ), esc_html( $the_subscription->get_date_to_display( $column, 'site' ) ) );
				}

				echo wp_kses( $column_content, array( 'time' => array( 'class' => array(), 'title' => array() ) ) );
				break;
			case 'orders' :
				echo esc_html( count( $the_subscription->get_related_orders() ) );
				break;
		}
	}

	/**
	 * Make columns sortable
	 *
	 * @param array $columns
	 * @return array
	 */
	public function shop_subscription_sortable_columns( $columns ) {

		$sortable_columns = array(
			'status'            => 'post_status',
			'order_title'       => 'ID',
			'recurring_total'   => 'order_total',
			'start_date'        => 'date',
			'trial_end_date'    => 'trial_end_date',
			'next_payment_date' => 'next_payment_date',
			'last_payment_date' => 'last_payment_date',
			'end_date'          => 'end_date',
		);

		return wp_parse_args( $sortable_columns, $columns );
	}

	/**
	 * Search custom fields as well as content.
	 *
	 * @access public
	 * @param WP_Query $wp
	 * @return void
	 */
	public function shop_subscription_search_custom_fields( $wp ) {
		global $pagenow, $wpdb;

		if ( 'edit.php' !== $pagenow || empty( $wp->query_vars['s'] ) || 'shop_subscription' !== $wp->query_vars['post_type'] ) {
			return;
		}

		$search_fields = array_map( 'wc_clean', apply_filters( 'woocommerce_shop_subscription_search_fields', array(
			'_order_key',
			'_billing_company',
			'_billing_address_1',
			'_billing_address_2',
			'_billing_city',
			'_billing_postcode',
			'_billing_country',
			'_billing_state',
			'_billing_email',
			'_billing_phone',
			'_shipping_address_1',
			'_shipping_address_2',
			'_shipping_city',
			'_shipping_postcode',
			'_shipping_country',
			'_shipping_state',
		) ) );

		$search_order_id = str_replace( 'Order #', '', $_GET['s'] );
		if ( ! is_numeric( $search_order_id ) ) {
			$search_order_id = 0;
		}

		// Search orders
		$post_ids = array_unique( array_merge(
			$wpdb->get_col(
				$wpdb->prepare( "
					SELECT p1.post_id
					FROM {$wpdb->postmeta} p1
					INNER JOIN {$wpdb->postmeta} p2 ON p1.post_id = p2.post_id
					WHERE
						( p1.meta_key = '_billing_first_name' AND p2.meta_key = '_billing_last_name' AND CONCAT(p1.meta_value, ' ', p2.meta_value) LIKE '%%%s%%' )
					OR
						( p1.meta_key = '_shipping_first_name' AND p2.meta_key = '_shipping_last_name' AND CONCAT(p1.meta_value, ' ', p2.meta_value) LIKE '%%%s%%' )
					OR
						( p1.meta_key IN ('" . implode( "','", $search_fields ) . "') AND p1.meta_value LIKE '%%%s%%' )
					",
					esc_attr( $_GET['s'] ), esc_attr( $_GET['s'] ), esc_attr( $_GET['s'] )
				)
			),
			$wpdb->get_col(
				$wpdb->prepare( "
					SELECT order_id
					FROM {$wpdb->prefix}woocommerce_order_items as order_items
					WHERE order_item_name LIKE '%%%s%%'
					",
					esc_attr( $_GET['s'] )
				)
			),
			array( $search_order_id )
		) );

		// Remove s - we don't want to search order name
		unset( $wp->query_vars['s'] );

		// so we know we're doing this
		$wp->query_vars['shop_subscription_search'] = true;

		// Search by found posts
		$wp->query_vars['post__in'] = $post_ids;
	}

	/**
	 * Change the label when searching orders.
	 *
	 * @access public
	 * @param mixed $query
	 * @return string
	 */
	public function shop_subscription_search_label( $query ) {
		global $pagenow, $typenow;

		if ( 'edit.php' !== $pagenow ) {
			return $query;
		}

		if ( 'shop_subscription' !== $typenow ) {
			return $query;
		}

		if ( ! get_query_var( 'shop_subscription_search' ) ) {
			return $query;
		}

		return wp_unslash( $_GET['s'] );
	}

	/**
	 * Query vars for custom searches.
	 *
	 * @access public
	 * @param mixed $public_query_vars
	 * @return array
	 */
	public function add_custom_query_var( $public_query_vars ) {
		$public_query_vars[] = 'sku';
		$public_query_vars[] = 'shop_subscription_search';

		return $public_query_vars;
	}

	/**
	 * Filters and sorting handler
	 *
	 * @param  array $vars
	 * @return array
	 */
	public function request_query( $vars ) {
		global $typenow;

		if ( 'shop_subscription' === $typenow ) {

			// Filter the orders by the posted customer.
			if ( isset( $_GET['_customer_user'] ) && $_GET['_customer_user'] > 0 ) {
				$vars['meta_key'] = '_customer_user';
				$vars['meta_value'] = (int) $_GET['_customer_user'];
			}

			// Sorting
			if ( isset( $vars['orderby'] ) ) {
				switch ( $vars['orderby'] ) {
					case 'order_total' :
						$vars = array_merge( $vars, array(
							'meta_key' 	=> '_order_total',
							'orderby' 	=> 'meta_value_num',
						) );
					break;
					case 'trial_end_date' :
					case 'next_payment_date' :
					case 'last_payment_date' :
					case 'end_date' :
						$vars = array_merge( $vars, array(
							'meta_key'     => sprintf( '_schedule_%s', str_replace( '_date', '', $vars['orderby'] ) ),
							'meta_type'    => 'DATETIME',
							'orderby'      => 'meta_value',
						) );
					break;
				}
			}

			// Status
			if ( ! isset( $vars['post_status'] ) ) {
				$vars['post_status'] = array_keys( wcs_get_subscription_statuses() );
			}
		}

		return $vars;
	}

	/**
	 * Change messages when a post type is updated.
	 *
	 * @param  array $messages
	 * @return array
	 */
	public function post_updated_messages( $messages ) {
		global $post, $post_ID;

		$messages['shop_subscription'] = array(
			0 => '', // Unused. Messages start at index 1.
			1 => __( 'Subscription updated.', 'woocommerce-subscriptions' ),
			2 => __( 'Custom field updated.', 'woocommerce-subscriptions' ),
			3 => __( 'Custom field deleted.', 'woocommerce-subscriptions' ),
			4 => __( 'Subscription updated.', 'woocommerce-subscriptions' ),
			5 => isset( $_GET['revision'] ) ? sprintf( __( 'Subscription restored to revision from %s', 'woocommerce-subscriptions' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
			6 => __( 'Subscription updated.', 'woocommerce-subscriptions' ),
			7 => __( 'Subscription saved.', 'woocommerce-subscriptions' ),
			8 => __( 'Subscription submitted.', 'woocommerce-subscriptions' ),
			9 => sprintf( __( 'Subscription scheduled for: <strong>%1$s</strong>.', 'woocommerce-subscriptions' ), date_i18n( __( 'M j, Y @ G:i', 'woocommerce-subscriptions' ), strtotime( $post->post_date ) ) ),
			10 => __( 'Subscription draft updated.', 'woocommerce-subscriptions' )
		);

		return $messages;
	}

}

new WCS_Admin_Post_Types();