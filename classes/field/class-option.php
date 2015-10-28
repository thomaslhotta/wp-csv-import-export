<?php
/**
 * Add 'user' options
 */
class CIE_Field_Option extends CIE_Field_Abstract {
	protected $type = 'user_option_';

	public function get_available_fields( array $search = array() ) {
		global $wpdb;

		$sql = $wpdb->prepare(
			'SELECT option_name ' . $wpdb->options . ' WHERE option_name LIKE \'%s%%\'',
			$this->type
		);

		$results = $wpdb->get_results( $sql, ARRAY_N );

		$fields = array();
		foreach ( $results as $result ) {
			$fields[ $result[0] ] = $result[0];
		}

		return $fields;
	}

	public function get_field_values( array $fields, CIE_Element $element ) {
		$user_id = $element->get_user_id();

		$data = array();
		foreach ( $fields as $field_id ) {
			if ( 0 !== strpos( $field_id, $this->type ) ) {
				continue;
			}

			$data[] = get_option( $this->type . '_' . $user_id, '' );
		}

		return $data;
	}

	public function set_field_values( array $fields, CIE_Element $element ) {
		if ( ! $element->get_user_id() ) {
			return array(
				__( 'User id required to set user options', 'cie' ),
			);
		}

		$errors = array();

		foreach ( $fields as $name => $value ) {
			if ( 0 !== strpos( $name, $this->type ) ) {
				continue;
			}

			$name = strtolower( $name . '_' . $element->get_user_id() );

			$added = add_option( $name, $value, '', 'no' );

			if ( ! $added ) {
				$added = update_option( $name, $value );
			}

			if ( ! $added ) {
				$errors[] = sprintf(
					__( 'User option "%s" could not be set', 'cie' ),
					strip_tags( $name )
				);
			}
		}

		return $errors;
	}
}