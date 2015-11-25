<?php
/**
 * Handles BuddyPress xProfile fields
 */
class CIE_Field_Buddypress extends CIE_Field_Abstract {
	public function get_available_fields( array $search = array() ) {
		$fields = array();
		if ( function_exists( 'buddypress' ) ) {
			$bp = buddypress();
			global $wpdb;

			$wpdb->query( "SELECT id, name FROM {$bp->profile->table_name_fields} WHERE NOT type = 'option'" );

			foreach ( $wpdb->last_result as $key ) {
				$fields[ intval( $key->id ) ] = $key->name;
			}
		}

		return $fields;
	}

	public function get_field_values( array $fields, CIE_Element $element ) {
		$data = array();

		$field_objects = array();

		foreach ( BP_XProfile_ProfileData::get_data_for_user( $element->get_user_id(), $fields ) as $field_object ) {
			$field_objects[ $field_object->field_id ] = $field_object->value;
		}

		foreach ( $fields as $field_id ) {
			$value = '';

			if ( isset( $field_objects[ $field_id ] ) ) {
				$value = maybe_unserialize( $field_objects[ $field_id ] );

				if ( is_array( $value ) ) {
					$value = implode( ';', $value );
				}
			}

			$data[] = (string) $value;
		}

		return $data;
	}

	/**
	 * @param array $fields
	 * @param CIE_Element $element
	 *
	 * @return array
	 */
	public function set_field_values( array $fields, CIE_Element $element ) {
		if ( ! $element->get_user_id() ) {
			return array(
				__( 'User id required to set BuddyPress profile values', 'cie' ),
			);
		}

		$errors = array();

		foreach ( $fields as $name => $value ) {
			if ( 0 !== strpos( $name, 'bp_' ) ) {
				continue;
			}

			$name = str_replace( 'bp_', '', $name );

			// Convert id to name if necessary
			if ( ! is_numeric( $name ) ) {
				$name = xprofile_get_field_id_from_name( $name );
			}

			$field = xprofile_get_field( $name );
			if ( 'checkbox' === $field->type ) {
				$value = explode( ';', $value );
			}

			if ( ! xprofile_set_field_data( $name, $element->get_user_id(), $value ) ) {
				$errors[] = sprintf(
					__( 'BuddyPress profile field "%s" could not be set', 'cie' ),
					strip_tags( $name )
				);
			}
		}

		return $errors;
	}
}
