<?php
/**
 * Deprecate actions and filters that use a dynamic hook by appending a variable, like a payment gateway's name.
 *
 * @package		WooCommerce Subscriptions
 * @subpackage	WCS_Hook_Deprecator
 * @category	Class
 * @author		Prospress
 * @since		2.0
 */

abstract class WCS_Dynamic_Hook_Deprecator extends WCS_Hook_Deprecator {

	/* The prefixes of hooks that have been deprecated, 'new_hook' => 'old_hook_prefix' */
	protected $deprecated_hook_prefixes = array();

	/**
	 * Bootstraps the class and hooks required actions & filters.
	 *
	 * We need to use the special 'all' hook here because we don't actually know the full hook names
	 * in advance, just their prefix. We can't simply hook in to 'plugins_loaded' and check the
	 * $wp_filter global for our hooks either, because sometime, hooks are dynamically hooked based
	 * on other hooks. Sigh.
	 *
	 * @since 2.0
	 */
	public function __construct() {
		add_filter( 'all', array( &$this, 'check_for_deprecated_hook' ) );
	}

	/**
	 * Check if the current hook contains the prefix of a dynamic hook that has been deprecated.
	 *
	 * @since 2.0
	 */
	public function check_for_deprecated_hook() {

		$current_filter = current_filter();

		foreach ( $this->deprecated_hook_prefixes as $new_hook_prefix => $old_hook_prefix ) {

			if ( false !== strpos( $current_filter, $new_hook_prefix ) ) {

				// Get the dynamic suffix on the hook, usually a payment gateway name, like 'stripe' or 'authorize_net_cim'
				$hook_suffix = str_replace( $new_hook_prefix, '', $current_filter );
				$old_hook    = $old_hook_prefix . $hook_suffix;

				// register the entire new and old hook
				$this->deprecated_hooks[ $current_filter ] = $old_hook;

				// and attach our handler now that we know the hooks
				add_filter( $current_filter, array( &$this, 'maybe_handle_deprecated_hook' ), -1000 );
			}
		}
	}
}
