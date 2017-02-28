<?php

/**
 * Simple class to generate the needed HTML/JS for Select2 regardless.
 * It works Select2 V3 and V4.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WCS_Select2 {
	protected $default_attrs = array(
		'type' => 'hidden',
		'placeholder' => '',
		'class' => '',
	);

	public static $added_script = false;

	protected $attrs = array();

	/**
	 * Render a select2 HTML out of an array with the properties. It doesn't return
	 * the object but it rather prints it out.
	 *
	 * @param Array $attrs		Select2 attributes
	 *
	 * @return null
	 */
	public static function render( Array $attrs ) {
		$obj = new self( $attrs );
		$obj->do_print();
	}

	/**
	 * Prints any javascript related to Select2
	 */
	public static function print_select2_javascript() {
		?>
		<script type="text/javascript">
			jQuery(function() {
				jQuery(".select2-container").attr("style", "");
			});
		</script><?php
	}

	public function __construct( Array $attributes ) {
		$this->attrs = array_merge( $this->default_attrs, $attributes );
		if ( ! self::$added_script ) {
			add_action( 'wp_footer', __CLASS__ . '::print_select2_javascript' );
			add_action( 'admin_footer', __CLASS__ . '::print_select2_javascript' );
			self::$added_script = true;
		}
	}

	/**
	 * Get the property name, it return class, name, id or data-$something;
	 *
	 * @param string $name	Property name
	 *
	 * @return string
	 */
	protected function get_property_name( $name ) {
		return in_array( $name, array( 'class', 'name', 'id' ) )
			? $name : 'data-' . $name;
	}

	/**
	 * Returns a list of properties/values (HTML) from an array. All the values
	 * are escaped and safe.
	 *
	 * @param $attrs	List of HTML attributes
	 *
	 * @return String
	 */
	protected function attrs_to_html( Array $attrs ) {
		$html = array();
		foreach ( $attrs as $name => $value ) {
			if ( ! is_scalar( $value ) ) {
				$value = wcs_json_encode( $value );
			}
			$html[] = $this->get_property_name( $name ) . '=' . var_export( esc_attr( $value, 'woocommerce-subscriptions' ), true );
		}
		return implode( ' ', $html );
	}

	/**
	 * Prints the HTML to show the Select2 input. We wrap it in a function to
	 * avoid a silly warning, it's not a XSS.
	 */
	public function do_print() {
		echo $this->get_html(); // WPCS: XSS OK.
	}

	/**
	 * Returns the HTML needed to show the Select2 input
	 *
	 * @return String
	 */
	public function get_html() {
		$str = "\n<!--select2 -->\n";
		if ( WC_Subscriptions::is_woocommerce_pre( '2.7' ) ) {
			$str .= '<input ';
			$str .= $this->attrs_to_html( $this->attrs );
			$str .= '/>';
		} else {
			$str .= '<select ';
			$str .= $this->attrs_to_html( $this->attrs );
			$str .= '></select>';
		}
		$str .= "\n<!--/select2 -->\n";

		return $str;
	}
}
