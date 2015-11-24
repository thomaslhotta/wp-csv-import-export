<?php
abstract class CIE_Field_Object extends CIE_Field_Abstract {
	public function get_value( $element_object, $field_id ) {
		$value = 0;
		if ( is_object( $element_object ) ) {
			if ( method_exists( $element_object, 'get' ) ) {
				$value = $element_object->get( $field_id );
			} elseif ( isset( $element_object->$field_id ) || method_exists( $element_object, '__get' ) ) {
				$value = $element_object->$field_id;
			}
		} elseif ( is_array( $element_object ) && isset( $element_object[ $field_id ] ) ) {
			$value = $element_object[ $field_id ];
		}

		if ( is_array( $value ) ) {
			$value = join( ', ', $value );
		}

		return $value;
	}
}
