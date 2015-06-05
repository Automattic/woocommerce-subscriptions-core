<?php
/**
 * About page for Subscriptions 2.0.0
 *
 * @author		Prospress
 * @category	Admin
 * @package		WooCommerce Subscriptions/Admin/Upgrades
 * @version		2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings_page = admin_url( 'admin.php?page=wc-settings&tab=subscriptions' );
?>

<div class="wrap about-wrap">

	<h1><?php esc_html_e( 'Welcome to Subscriptions 2.0', 'woocommerce-subscriptions' ); ?></h1>

	<div class="about-text woocommerce-about-text">
		<?php esc_html_e( 'Thank you for updating to the latest version.', 'woocommerce-subscriptions' ); ?>
		<?php esc_html_e( 'Subscriptions version 2.0 has been in development for more than a year, taking into account 3 years of feedback from store managers to reinvent the extension.', 'woocommerce-subscriptions' ); ?>
		<?php esc_html_e( 'We hope you enjoy it!', 'woocommerce-subscriptions' ); ?>
	</div>

	<div class="wcs-badge"><?php printf( esc_html__( 'Version %s', 'woocommerce-subscriptions' ), esc_html__( $active_version ) ); ?></div>

	<p class="woocommerce-actions">
		<a href="<?php echo esc_url( $settings_page ); ?>" class="button button-primary"><?php esc_html_e( 'Settings', 'woocommerce-subscriptions' ); ?></a>
		<a class="docs button button-primary" href="<?php echo esc_url( apply_filters( 'woocommerce_docs_url', 'http://docs.woothemes.com/documentation/subscriptions/', 'woocommerce-subscriptions' ) ); ?>"><?php esc_html_e( 'Docs', 'woocommerce-subscriptions' ); ?></a>
		<a href="https://twitter.com/share" class="twitter-share-button" data-url="http://www.woothemes.com/products/woocommerce-subscriptions/" data-text="I just upgraded to Subscriptions v2.0" data-via="WooThemes" data-size="large" data-hashtags="WooCommerce">Tweet</a>
<script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src="//platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","twitter-wjs");</script>
	</p>

	<div class="changelog">
		<h2><?php esc_html_e( "Check Out What's New", 'woocommerce-subscriptions' ); ?></h2>
		<hr/>

		<div class="feature-section col two-col">
			<div class="feature-image">
				<img src="<?php echo esc_url( plugins_url( '/images/checkout-recurring-totals.png', WC_Subscriptions::$plugin_file ) ); ?>" />
			</div>

			<div class="last-feature">
				<h3><?php esc_html_e( 'Multiple Subscriptions', 'woocommerce-subscriptions' ); ?></h3>
				<p><?php esc_html_e( 'It\'s now a whole lot easier for your customers to buy subscriptions!', 'woocommerce-subscriptions' ); ?></p>
				<p><?php esc_html_e( 'Customers can now purchase multiple different subscription products in the one transaction. The products can bill on any schedule and have any combination of sign-up fees and/or free trial.', 'woocommerce-subscriptions' ); ?></p>
				<p><?php esc_html_e( 'Subscription products are also grouped by billing schedule to reduce payment gateway fees. Even if a customers buys 100 different products, if they all renew monthly, you only pay one fee to your payment gateway.', 'woocommerce-subscriptions' ); ?></p>
				<p><?php printf( esc_html__( '%sLearn more about multiple subscriptions support%s.', 'woocommerce-subscriptions' ), '<a href="' .  esc_url( 'http://docs.woothemes.com/document/subscriptions/multiple-subscriptions/' ) . '">', '</a>' ); ?></p>
			</div>
		</div>
		<hr/>
		<div class="feature-section col two-col">

			<div class="last-feature feature-right feature-image">
				<img src="<?php echo esc_url( plugins_url( '/images/add-edit-subscription-screen.png', WC_Subscriptions::$plugin_file ) ); ?>" />
			</div>

			<div>
				<h3><?php esc_html_e( 'Subscription Administration Screen', 'woocommerce-subscriptions' ); ?></h3>
				<p><?php esc_html_e( 'While it was already possible to add or edit a subscription, it certainly wasn\'t easy. That changes now.', 'woocommerce-subscriptions' ); ?></p>
				<p><?php esc_html_e( 'Subscriptions v2.0 introduces a new administration interface to add or edit a subscription. You can make all the familiar changes, like modiying recurring amount and status, as well as some new modifications, like changing the expiration date, adding a shipping cost or other line item.', 'woocommerce-subscriptions' ); ?></p>
				<p><?php printf( esc_html__( 'The best part? The new interface is built on the existing %sEdit Order%s screen. If you\'ve ever modified an order, you already know how to modify a subscription.', 'woocommerce-subscriptions' ), '<strong>', '</strong>' ); ?></p>
				<p><?php printf( esc_html__( '%sAdd a subscription%s now or %slearn more%s about the new interface.', 'woocommerce-subscriptions' ), '<a href="' . esc_url( admin_url( 'post-new.php?post_type=shop_subscription' ) ) . '">', '</a>', '<a href="' . esc_url( 'http://docs.woothemes.com/document/subscriptions/add-or-modify-a-subscription/' ) . '">', '</a>' ); ?></p>
			</div>
		</div>
		<hr/>
		<div class="feature-section col two-col">
			<div class="feature-image">
				<img src="<?php echo esc_url( plugins_url( '/images/view-subscription.png', WC_Subscriptions::$plugin_file ) ); ?>" />
			</div>

			<div class="last-feature">
				<h3><?php esc_html_e( 'Customer View Subscription Page', 'woocommerce-subscriptions' ); ?></h3>
				<p><?php printf( esc_html__( 'Customers could already login and view the most important details of their subscriptions on their %sMy Account%s page.', 'woocommerce-subscriptions' ), '<strong>', '</strong>' ); ?></p>
				<p><?php printf( esc_html__( 'Now they can view the full details of a subscription, including line items, billing and shipping address, as well as other relevant information, like related orders, from a special %sMy Account > View Subscription%s page.', 'woocommerce-subscriptions' ), '<strong>', '</strong>' ); ?></p>
			</div>
		</div>
	</div>
	<div class="changelog">

		<div class="feature-section col three-col">

			<div>
				<img src="<?php echo esc_url( plugins_url( '/images/new-subscriptions-list-table.jpg', WC_Subscriptions::$plugin_file ) ); ?>" />
				<h3><?php esc_html_e( 'Improved Subscriptions Table', 'woocommerce-subscriptions' ); ?></h3>
				<p><?php printf( esc_html__( 'The Subscriptions administration table has received a revamp. In addition to being much faster to search, sort and filter, it now makes more of a subscription\'s information easy to see at a glance, including the recurring total and payment method.', 'woocommerce-subscriptions' ), '<a href="' . esc_url( admin_url( 'edit.php?post_type=shop_subscription' ) ) . '">', '</a>' ); ?></p>
			</div>

			<div>
				<img src="<?php echo esc_url( plugins_url( '/images/admin-change-payment-method.jpg', WC_Subscriptions::$plugin_file ) ); ?>" />
				<h3><?php esc_html_e( 'Admin Change Payment Method', 'woocommerce-subscriptions' ); ?></h3>
				<p><?php printf( esc_html__( 'To change a customer from automatic to manual, or manual to automatic renewal payments once required modifying the database directly. Now Subscriptions provides a way for payment gateways to allow you to change that from the %sEdit Subscription%s interface.', 'woocommerce-subscriptions' ), '<strong>', '</strong>' ); ?></p>
			</div>

			<div class="last-feature">
				<img src="<?php echo esc_url( plugins_url( '/images/billing-schedules-meta-box.png', WC_Subscriptions::$plugin_file ) ); ?>" />
				<h3><?php esc_html_e( 'Change Trial and End Date', 'woocommerce-subscriptions' ); ?></h3>
				<p><?php printf( esc_html__( 'It used to be possible to change a subscriptions next payment date, but sometimes you want to provide a customer with an extended free trial or add an extra month or two before their subscription expires. Now you can from the %sEdit Subscription%s interface.', 'woocommerce-subscriptions' ), '<strong>', '</strong>' ); ?></p>
			</div>

		</div>
	</div>
	<div class="changelog under-the-hood">

		<h2><?php esc_html_e( 'Peak Under the Hood for Developers', 'woocommerce-subscriptions' ); ?></h2>
		<p><?php esc_html_e( 'Subscriptions v2.0 also introduces a completely new architecture built on WooCommerce 2.2\'s Custom Order Types API.', 'woocommerce-subscriptions' ); ?></p>

		<div class="feature-section col three-col">
			<div>
				<h3><?php printf( esc_html__( 'New %sshop_subscription%s Post Type', 'woocommerce-subscriptions' ), '<code>', '</code>' ); ?></h3>
				<p><?php esc_html_e( 'Built on the WooCommerce Custom Order Types API meant a subscription also become its own post type. This makes it much faster to query subscriptions and uses a database schema that is as scalable as WordPress itself.', 'woocommerce-subscriptions' ); ?></p>
				<p><?php printf( esc_html__( 'Developers can also use all their favourite WordPress functions, like %sget_posts()%s, to query or modify subscription data.', 'woocommerce-subscriptions' ), '<code>', '</code>' ); ?></p>
			</div>
			<div>
				<h3><?php printf( esc_html__( 'New %sWC_Subscription%s Object', 'woocommerce-subscriptions' ), '<code>', '</code>' ); ?></h3>
				<p><?php esc_html_e( 'Subscriptions v2.0 also introduces a new object for instantiating a subscription in memory. The days of having to use cumbersome functions to retreive or modify a subscriptions data are gone!', 'woocommerce-subscriptions' ); ?></p>
				<p><?php printf( esc_html__( 'Want to find a subscription next payment date? Just call %s$subscription->get_date( \'next_payment\' )%s. Want a list of the orders associated with a subscription? Call %s$subscription->get_related_orders()%s.', 'woocommerce-subscriptions' ), '<code>', '</code>', '<code>', '</code>' ); ?></p>
				<p><?php printf( esc_html__( 'Because the %sWC_Subscription%s extends the %sWC_Order%s class, you can also use all of its familiar methods, like %s$subscription->update_status()%s or %s$subscription->get_total()%s.', 'woocommerce-subscriptions' ), '<code>', '</code>', '<code>', '</code>', '<code>', '</code>', '<code>', '</code>' ); ?></p>
			</div>
			<div class="last-feature">
				<h3><?php esc_html_e( 'REST API Endpoints', 'woocommerce-subscriptions' ); ?></h3>
				<p><?php esc_html_e( 'We didn\'t just improve interfaces for humans, we also improved them for computers. Your applications can now create, read, update or delete subscriptions with RESTful API endpoints similar to those exposed for orders.', 'woocommerce-subscriptions' ); ?></p>
				<p><?php printf( esc_html__( 'Want to list all the subscriptions on a site? Hit %sexample.com/wc-api/v2/subscriptions/%s. Want the details of a specific subscription? Hit %s/wc-api/v2/subscriptions/<id>/%s.', 'woocommerce-subscriptions' ), '<code>', '</code>', '<code>', '</code>' ); ?></p>
			</div>
	</div>
	<hr/>
	<div class="return-to-dashboard">
		<a href="<?php echo esc_url( $settings_page ); ?>"><?php esc_html_e( 'Go to WooCommerce Subscriptions Settings', 'woocommerce-subscriptions' ); ?></a>
	</div>
</div>
