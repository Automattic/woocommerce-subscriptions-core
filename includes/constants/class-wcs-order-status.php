<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Order_Status
 */
class Order_Status {
	const CANCELLED  = 'cancelled';
	const COMPLETED  = 'completed';
	const FAILED     = 'failed';
	const ON_HOLD    = 'on-hold';
	const PENDING    = 'pending';
	const PROCESSING = 'processing';
	const REFUNDED   = 'refunded';
	const TRASH      = 'trash';
}
