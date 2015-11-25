<?php

/**
 * Provides basic import processing functionality
 */
abstract class CIE_Importer extends CIE_Processor {
	const MODE_IMPORT = 1;
	const MODE_UPDATE = 2;
	const MODE_BOTH = 3;

	/**
	 * By default importers support no field types
	 *
	 * @return array
	 */
	public function get_supported_fields() {
		return array();
	}

	/**
	 * Returns the field names that are required for importing
	 *
	 * @param $mode
	 *
	 * @return array
	 */
	abstract public function get_required_fields( $mode );

	/**
	 * Returns MODE_IMPORT, MODE_EXPORT or MODE_BOTH, depending on what the importer is capable of.
	 *
	 * @return mixed
	 */
	abstract public function get_supported_mode();

	/**
	 * Creates an element from a CSV row
	 *
	 * @param array $row
	 * @param int $mode
	 *
	 * @return CIE_Element
	 */
	abstract public function create_element( array $row, $mode = self::MODE_IMPORT );

	/**
	 * Handles uploaded JSON data
	 */
	public function import_json() {
		$data = false;
		if ( ! empty( $_POST['data'] ) ) {
			$data = json_decode( stripslashes( $_POST['data'] ), true );
		}

		if ( ! is_array( $data ) ) {
			wp_send_json_error();

			return;
		}

		// Default mode is import
		$mode = self::MODE_IMPORT;
		if ( ! empty( $_POST['mode'] ) ) {
			$mode = intval( $_POST['mode'] );
		}

		$result = $this->import( $data, $mode );

		wp_send_json_success( $result );
	}

	/**
	 * @param array $data
	 * @param       $mode
	 *
	 * @return array
	 */
	public function import( array $data, $mode ) {
		$return = array(
			'errors'   => array(),
			'imported' => 0,
		);

		$required = $this->get_required_fields( $mode );

		foreach ( $data as $number => $row ) {
			$errors = $this->check_required_fields( $row, $required );
			// Make 1 based
			$number += 1;

			if ( ! empty( $errors ) ) {
				$return['errors'][ $number ] = $errors;
				continue;
			}

			$element = $this->create_element( $row, $mode );

			// Check if element could be created
			if ( $element->has_error() ) {
				$return['errors'][ $number ][] = $element->get_error();
				continue;
			}

			$return['imported'] ++;

			$errors = $this->precess_element( $element, $row );

			if ( ! empty( $errors ) ) {
				$return['errors'][ $number ] = $errors;
			}
		}

		return $return;
	}

	public function precess_element( CIE_Element $element, array $data ) {
		$errors = array();

		foreach ( $this->get_supported_fields() as $field_type ) {
			$object = $this->get_field_type_object( $field_type );
			array_merge( $errors, $object->set_field_values( $data, $element ) );
		}

		return $errors;
	}

	/**
	 * Checks if  all required columns of the current row exist
	 *
	 * @param array $row
	 * @param array $required
	 *
	 * @return array
	 */
	public function check_required_fields( array $row, array $required ) {
		$errors = array();

		foreach ( $required as $missing ) {
			$found = false;
			foreach ( $missing['columns'] as $column ) {
				if ( ! empty( $row[ $column ] ) ) {
					$found = true;
					break;
				}
			}

			// Required field was found
			if ( $found ) {
				continue;
			}

			if ( 1 === count( $missing['columns'] ) ) {
				$errors[] = sprintf(
					__(
						'"%s" is missing or empty',
						'cie'
					),
					$missing['columns'][0]
				);
			} else {
				$errors[] = sprintf(
					__(
						'One of the following columns must be present: %s ',
						'cie'
					),
					join( ', ', $missing['columns'] )
				);
			}
		}

		return $errors;
	}
}
