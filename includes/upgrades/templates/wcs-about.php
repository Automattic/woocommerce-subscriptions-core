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

	<h1><?php _e( 'Welcome to Subscriptions 1.5', 'woocommerce-subscriptions' ); ?></h1>

	<div class="about-text woocommerce-about-text">
		<?php _e( 'Thank you for updating to the latest version! Subscriptions 1.5 is more powerful, scalable, and reliable than ever before. We hope you enjoy it.', 'woocommerce-subscriptions' ); ?>
	</div>

	<div class="wcs-badge"><?php printf( __( 'Version 1.5', 'woocommerce-subscriptions' ), self::$active_version ); ?></div>

	<p class="woocommerce-actions">
		<a href="<?php echo $settings_page; ?>" class="button button-primary"><?php _e( 'Settings', 'woocommerce-subscriptions' ); ?></a>
		<a class="docs button button-primary" href="<?php echo esc_url( apply_filters( 'woocommerce_docs_url', 'http://docs.woothemes.com/documentation/subscriptions/', 'woocommerce-subscriptions' ) ); ?>"><?php _e( 'Docs', 'woocommerce-subscriptions' ); ?></a>
		<a href="https://twitter.com/share" class="twitter-share-button" data-url="http://www.woothemes.com/products/woocommerce-subscriptions/" data-text="I just upgraded to Subscriptions 1.5" data-via="WooThemes" data-size="large" data-hashtags="WooCommerce">Tweet</a>
<script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src="//platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","twitter-wjs");</script>
	</p>

	<div class="changelog">
		<h3><?php _e( "Check Out What's New", 'woocommerce-subscriptions' ); ?></h3>

		<div class="feature-section col two-col">
			<div>
				<img src="<?php echo plugins_url( '/images/customer-view-syncd-subscription-monthly.png', WC_Subscriptions::$plugin_file ); ?>" />
			</div>

			<div class="last-feature">
				<h4><?php _e( 'Renewal Synchronisation', 'woocommerce-subscriptions' ); ?></h4>
				<p><?php _e( 'Subscription renewal dates can now be aligned to a specific day of the week, month or year.', 'woocommerce-subscriptions' ); ?></p>
				<p><?php _e( 'If you sell physical goods and want to ship only on certain days, or sell memberships and want to align membership periods to a calendar day, WooCommerce Subscriptions can now work on your schedule.', 'woocommerce-subscriptions' ); ?></p>
				<p><?php printf( __( '%sEnable renewal synchronisation%s now or %slearn more%s about this feature.', 'woocommerce-subscriptions' ), '<a href="' . esc_url( $settings_page ) . '">', '</a>', '<a href="' .  esc_url( 'http://docs.woothemes.com/document/subscriptions/renewal-synchronisation/' ) . '">', '</a>' ); ?></p>
			</div>
		</div>
		<hr/>
		<div class="feature-section col two-col">
			<div>
				<h4><?php _e( 'Mixed Checkout', 'woocommerce-subscriptions' ); ?></h4>
				<p><?php _e( 'Simple, variable and other non-subscription products can now be purchased in the same transaction as a subscription product.', 'woocommerce-subscriptions' ); ?></p>
				<p><?php printf( __( 'This makes it easier for your customers to buy more from your store and soon it will also be possible to offer %sProduct Bundles%s & %sComposite Products%s which include a subscription.', 'woocommerce-subscriptions' ), '<a href="' . esc_url( 'http://www.woothemes.com/products/product-bundles/' ) . '">', '</a>', '<a href="' . esc_url( 'http://www.woothemes.com/products/composite-products/' ) . '">', '</a>' ); ?></p>
				<p><?php printf( __( '%sEnable mixed checkout%s now under the %sMiscellaneous%s settings section.', 'woocommerce-subscriptions' ), '<a href="' . esc_url( $settings_page ) . '">', '</a>', '<strong>', '</strong>' ); ?></p>
			</div>

			<div class="last-feature">
				<img src="<?php echo plugins_url( '/images/mixed-checkout.png', WC_Subscriptions::$plugin_file ); ?>" />
			</div>
		</div>
		<hr/>
		<div class="feature-section col two-col">
			<div>
				<img src="<?php echo plugins_url( '/images/subscription-quantities.png', WC_Subscriptions::$plugin_file ); ?>" />
			</div>

			<div class="last-feature">
				<h4><?php _e( 'Subscription Quantities', 'woocommerce-subscriptions' ); ?></h4>
				<p><?php _e( 'Your customers no longer need to purchase a subscription multiple times to access multiple quantities of the same product.', 'woocommerce-subscriptions' ); ?></p>
				<p><?php printf( __( 'For any subscription product not marked as %sSold Individually%s on the %sInventory%s tab%s of the %sEdit Product%s screen, your customers can now choose to purchase multiple quantities in the one transaction.', 'woocommerce-subscriptions' ), '<strong>', '</strong>', '<strong><a href="' . esc_url( 'http://docs.woothemes.com/document/managing-products/#inventory-tab' ) . '">', '</strong>', '</a>', '<strong>', '</strong>' ); ?></p>
				<p><?php printf( __( 'Your existing subscription products have been automatically set as %sSold Individually%s, so nothing will change for existing products, unless you want it to. Edit %ssubscription products%s.', 'woocommerce-subscriptions' ), '<strong>', '</strong>', '<a href="' . admin_url( 'edit.php?post_type=product&product_type=subscription' ) . '">', '</a>' ); ?></p>
			</div>
		</div>
	</div>
	<hr/>
	<div class="changelog">

		<div class="feature-section col three-col">

			<div>
				<img src="<?php echo plugins_url( '/images/responsive-subscriptions.png', WC_Subscriptions::$plugin_file ); ?>" />
				<h4><?php _e( 'Responsive Subscriptions Table', 'woocommerce-subscriptions' ); ?></h4>
				<p><?php printf( __( 'The default template for the %sMy Subscriptions%s table is now responsive to make it easy for your customers to view and manage their subscriptions on any device.', 'woocommerce-subscriptions' ), '<strong><a href="' . esc_url( 'http://docs.woothemes.com/document/subscriptions/customers-view/#section-1' ) . '">', '</a></strong>' ); ?></p>
			</div>

			<div>
				<img src="<?php echo plugins_url( '/images/subscription-switch-customer-email.png', WC_Subscriptions::$plugin_file ); ?>" />
				<h4><?php _e( 'Subscription Switch Emails', 'woocommerce-subscriptions' ); ?></h4>
				<p><?php printf( __( 'Subscriptions now sends two new emails when a customer upgrades or downgrades her subscription. Enable, disable or customise these emails on the %sEmail Settings%s screen.', 'woocommerce-subscriptions' ), '<strong><a href="' . admin_url( 'admin.php?page=wc-settings&tab=email&section=wcs_email_completed_switch_order' ) . '">', '</a></strong>' ); ?></p>
			</div>

			<div class="last-feature">
				<img src="<?php echo plugins_url( '/images/woocommerce-points-and-rewards-points-log.png', WC_Subscriptions::$plugin_file ); ?>" />
				<h4><?php _e( 'Points & Rewards', 'woocommerce-subscriptions' ); ?></h4>
				<p><?php printf( __( 'Support for the %sPoints & Rewards extension%s: points will now be rewarded for each subscription renewal.', 'woocommerce-subscriptions' ), '<a href="' . esc_url( 'http://www.woothemes.com/products/woocommerce-points-and-rewards/' ) . '">', '</a>' ); ?></p>
			</div>

		</div>
	</div>
	<hr/>
	<div class="changelog under-the-hood">

		<h3><?php _e( 'Under the Hood - New Scheduling System', 'woocommerce-subscriptions' ); ?></h3>
		<p><?php _e( 'Subscriptions 1.5 also introduces a completely new scheduling system - Action Scheduler.', 'woocommerce-subscriptions' ); ?></p>

		<div class="feature-section col three-col">
			<div>
				<h4><?php _e( 'Built to Sync', 'woocommerce-subscriptions' ); ?></h4>
				<p><?php _e( 'Introducing the new subscription synchronisation feature also introduces a new technical challenge - thousands of renewals may be scheduled for the same time.', 'woocommerce-subscriptions' ); ?></p>
				<p><?php _e( 'WordPress\'s scheduling system was not made to handle queues like that, but the new Action Scheduler is designed to process queues with thousands of renewals so you can sync subscriptions with confidence.', 'woocommerce-subscriptions' ); ?></p>
			</div>
			<div>
				<h4><?php _e( 'Built to Debug', 'woocommerce-subscriptions' ); ?></h4>
				<p><?php _e( 'When things go wrong, the more information available, the easier it is to diagnose and find a fix. Traditionally, a subscription renewal problem was tricky to diagnose because renewal happened in the background.', 'woocommerce-subscriptions' ); ?></p>
				<p><?php _e( 'Action Scheduler now logs important events around renewals and makes this and other important information available through a specially designed administration interface.', 'woocommerce-subscriptions' ); ?></p>
			</div>
			<div class="last-feature">
				<h4><?php _e( 'Built to Scale', 'woocommerce-subscriptions' ); ?></h4>
				<p><?php _e( 'The new Action Scheduler uses battle tested WordPress core functionality to ensure your site can scale its storage of scheduled subscription events, like an expiration date or renewal date, to handle thousands or even hundreds of thousands of subscriptions.', 'woocommerce-subscriptions' ); ?></p>
				<p><?php _e( 'We want stores of all sizes to be able to rely on WooCommerce Subscriptions.', 'woocommerce-subscriptions' ); ?></p>
			</div>
	</div>
	<hr/>
	<div class="return-to-dashboard">
		<a href="<?php echo esc_url( $settings_page ); ?>"><?php _e( 'Go to WooCommerce Subscriptions Settings', 'woocommerce-subscriptions' ); ?></a>
	</div>
</div>
