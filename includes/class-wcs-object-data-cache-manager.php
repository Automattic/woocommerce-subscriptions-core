<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for managing caches of object data.
 *
 * @version  5.2.0
 * @category Class
 */
class WCS_Object_Data_Cache_Manager extends WCS_Post_Meta_Cache_Manager {

	/**
	 * The WC_Data object type this cache manager will track changes to. eg 'order', 'subscription'.
	 *
	 * @var string
	 */
	protected $object_type;

	/**
	 * The data keys this cache manager will track changes to on the object type. Can be an object property key ('customer_id') or meta key ('_subscription_renewal').
	 *
	 * @var array
	 */
	protected $data_keys;

	/**
	 * An internal record of changes to the object that this manager is tracking.
	 *
	 * This internal record is generated before the object is saved, so we can determine
	 * if the value has changed, what the previous value was, and what the new value is.
	 *
	 * In the event that the object is being created (doesn't have an ID prior to save), this
	 * record will be generated after the object is saved, and all the data this manager
	 * is tracking will be set.
	 *
	 * @var array Each element is keyed by the object's ID, and contains an array of tracked changes {
	 *     Data about the change that was made to the object.
	 *
	 *     @type mixed  $new      The new value.
	 *     @type mixed  $previous The previous value before it was changed.
	 *     @type string $type     The type of change. Can be 'update', 'add' or 'delete'.
	 * }
	 */
	protected $object_changes = [];

	/**
	 * Constructor.
	 *
	 * @param string The post type this cache manage acts on.
	 * @param array The post meta keys this cache manager should act on.
	 */
	public function __construct( $object_type, $data_keys ) {
		$this->object_type = $object_type;

		// We store the meta keys as the array keys to take advantage of the better query performance of isset() vs. in_array()
		$this->data_keys = array_flip( $data_keys );
	}

	/**
	 * Attaches callbacks to keep the caches up-to-date.
	 */
	public function init() {
		add_action( "woocommerce_before_{$this->object_type}_object_save", [ $this, 'prepare_object_changes' ] );
		add_action( "woocommerce_after_{$this->object_type}_object_save", [ $this, 'action_object_cache_changes' ] );
	}

	/**
	 * Generates a set of changes for tracked meta keys and properties.
	 *
	 * This method is hooked onto an action which is fired before the object is saved.
	 * Changes to the object's data is stored in the $this->object_changes property to
	 * be processed after the object is saved.
	 *
	 * @param WC_Data $object        The object which is being saved.
	 * @param string  $generate_type Optional. The data to generate changes from. Defaults to 'changes_only' which will generate the data from strictly changes to the object. 'all_fields' will fetch all tracked data keys.
	 */
	public function prepare_object_changes( $object, $generate_type = 'changes_only' ) {
		// If the object hasn't been created yet, we can't do anything here.
		if ( ! $object->get_id() ) {
			return;
		}

		$force_all_fields = 'all_fields' === $generate_type;
		$changes          = $object->get_changes();
		$base_data        = $object->get_base_data();
		$meta_data        = $object->get_meta_data();

		// Record the object ID so we know that it has been handled, in action_object_cache_changes().
		$this->object_changes[ $object->get_id() ] = [];

		foreach ( $this->data_keys as $data_key => $index ) {

			// Check if the data key is a base property and if it has changed.
			if ( isset( $changes[ $data_key ] ) ) {
				$this->object_changes[ $object->get_id() ][ $data_key ] = [
					'new'      => $changes[ $data_key ],
					'previous' => isset( $base_data[ $data_key ] ) ? $base_data[ $data_key ] : null,
					'type'     => 'update',
				];

				continue;
			} elseif ( isset( $base_data[ $data_key ] ) && $force_all_fields ) {
				// If we're forcing all fields, record the base data as the new value.
				$this->object_changes[ $object->get_id() ][ $data_key ] = [
					'new'      => $base_data[ $data_key ],
					'type'     => 'add',
				];

				continue;
			}

			// Check if the data key is stored as meta.
			foreach ( $meta_data as $meta ) {
				if ( $meta->key !== $data_key ) {
					continue;
				}

				$previous_meta = $meta->get_data();

				// If the value is being deleted.
				if ( is_null( $meta->value ) ) {
					if ( ! empty( $meta->id ) ) {
						$this->object_changes[ $object->get_id() ][ $data_key ] = [
							'new'      => $meta->value,
							'previous' => isset( $previous_meta['value'] ) ? $previous_meta['value'] : null,
							'type'     => 'delete',
						];
					}
				} elseif ( empty( $meta->id ) ) {
					// If the value is being added.
					$this->object_changes[ $object->get_id() ][ $data_key ] = [
						'new'      => $meta->value,
						'type'     => 'add',
					];
				} elseif ( $meta->get_changes() ) {
					// If the value is being updated.
					$this->object_changes[ $object->get_id() ][ $data_key ] = [
						'new'      => $meta->value,
						'previous' => isset( $previous_meta['value'] ) ? $previous_meta['value'] : null,
						'type'     => 'update',
					];
				} elseif ( $force_all_fields ) {
					// If we're forcing all fields to be recorded.
					$this->object_changes[ $object->get_id() ][ $data_key ] = [
						'new'      => $meta->value,
						'type'     => 'add',
					];
				}

				break;
			}
		}
	}

	/**
	 * Actions all the changes that were made to object by triggering the update cache hook.
	 *
	 * This method is hooked onto an action which is fired after the object is saved.
	 *
	 * @param WC_Data $object The object which was saved.
	 */
	public function action_object_cache_changes( $object ) {
		if ( ! $object->get_id() ) {
			return;
		}

		/**
		 * If the object ID hasn't been recorded, this object must have just been created (not updated).
		 * Without an ID prepare_object_changes() (pre-save) would have skipped it.
		 *
		 * Now that we have an ID, generate the changes now.
		 */
		if ( ! isset( $this->object_changes[ $object->get_id() ] ) ) {
			$this->prepare_object_changes( $object, 'all_fields' );
		}

		if ( empty( $this->object_changes[ $object->get_id() ] ) ) {
			return;
		}

		$object_changes = $this->object_changes[ $object->get_id() ];
		unset( $this->object_changes[ $object->get_id() ] );

		foreach ( $object_changes as $key => $change ) {
			$this->trigger_update_cache_hook_from_change( $object, $key, $change );
		}
	}

	/**
	 * Triggers the update cache hook for an object change.
	 *
	 * @param WC_Data $object The object that was changed.
	 * @param string  $key    The object's key that was changed. Can be a base property ('customer_id') or a meta key ('_subscription_renewal').
	 * @param array   $change {
	 *     Data about the change that was made to the object.
	 *
	 *     @type mixed  $new      The new value.
	 *     @type mixed  $previous The previous value before it was changed.
	 *     @type string $type     The type of change. Can be 'update', 'add' or 'delete'.
	 * }
	 */
	protected function trigger_update_cache_hook_from_change( $object, $key, $change ) {
		$this->trigger_update_cache_hook( $change['type'], $object->get_id(), $key, $change['new'] );
	}
}
