<?php
/**
 * Mock scheduler for subscription events that does nothing
 */
class WCS_Mock_Scheduler extends WCS_Scheduler {

	public function update_date( $subscription, $date_type, $datetime ) {
		return false;
	}

	public function delete_date( $subscription, $date_type ) {
		return false;
	}

	public function update_status( $subscription, $new_status, $old_status ) {
		return false;
	}
}
