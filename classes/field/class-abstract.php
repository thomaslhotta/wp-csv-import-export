<?php

/**
 * Abstract class for field handlers
 */
abstract class CIE_Field_Abstract {

	/**
	 * Returns an array of available field names
	 *
	 * @param array $search
	 *
	 * @return array
	 */
	abstract public function get_available_fields( array $search = array() );

	/**
	 * Returns field values
	 *
	 * @param array $fields
	 * @param CIE_Element $element
	 *
	 * @return array
	 */
	abstract public function get_field_values( array $fields, CIE_Element $element );

	/**
	 * Set field values for this field type. Returns an array of errors
	 *
	 * @param array $fields
	 * @param CIE_Element $element
	 *
	 * @return array
	 */
	abstract public function set_field_values( array $fields, CIE_Element $element );

	public function extract_meta( array $fields ) {
		$meta_fields = array();
		foreach ( $fields as $key => $value ) {
			if ( 0 !== strpos( $key, 'meta_' ) ) {
				continue;
			}
			$meta_fields[ str_replace( 'meta_', '', $key ) ] = $value;
		}

		$meta_to_set = array();
		foreach ( $meta_fields as $key => $value ) {
			// Ignore empty values that are not 0
			if ( ! is_numeric( $value ) && empty( $value ) ) {
				continue;
			}

			$matches = array();
			if ( 1 === preg_match( '/(.*)\[(\d*)\]$/', $key, $matches ) ) {
				$meta_to_set[ $matches[1] ][ intval( $matches[2] ) ] = $value;
				continue;
			}

			$meta_to_set[ $key ] = $value;
		}

		return $meta_to_set;
	}

	public function get_searchable_fields() {
		return array();
	}
}
