<?php

/**
 * Base class for exporters
 */
abstract class CIE_Exporter extends CIE_Processor {

	/**
	 * Returns the available fields for this processor
	 *
	 * Format
	 *  array(
	 *      'Fieldtype' => array(
	 *          'Field ID' => 'Field Name'
	 *      )
	 *  )
	 *
	 * @param array $search
	 *
	 * @return array
	 * @throws Exception
	 */
	public function get_available_fields( array $search = array() ) {
		$fields = array();

		// Get generic field types
		foreach ( $this->get_supported_fields() as $field_type ) {
			$field                 = $this->get_field_type_object( $field_type );
			$fields[ $field_type ] = $field->get_available_fields( $search );
		}

		$fields = apply_filters( 'cie_available_fields', $fields, $search );

		return $fields;
	}

	/**
	 * Returns the available search values
	 * @param array $searches
	 *
	 * @return array
	 */
	public function get_available_searches( array $searches = array() ) {
		return array();
	}

	public function process_ajax() {
		$fields = $_REQUEST['fields'];

		// Sanitize given field names to export
		$sanitized_fields = array();
		foreach ( $this->get_available_fields() as $group_name => $field_group ) {
			foreach ( $field_group as $field_id => $field_name ) {
				if ( ! empty( $fields[ $group_name ] ) && ! empty( $fields[ $group_name ][ $field_id ] ) ) {
					$sanitized_fields[ $group_name ][ $field_id ] = $field_name;
				}
			}
		}

		$search = array();
		if ( ! empty( $_REQUEST['search'] ) ) {
			$search = $_REQUEST['search'];
		}

		$limit = 1000;
		if ( isset( $_REQUEST['per_page'] ) && is_numeric( $_REQUEST['per_page'] ) && $_REQUEST['per_page'] <= $limit ) {
			$limit = intval( $_REQUEST['per_page'] );
		}

		$page = 1;
		if ( ! empty( $_REQUEST['page'] ) && is_numeric( $_REQUEST['page'] ) ) {
			$page = $_REQUEST['page'];
		}

		// Export
		$this->print_export( $sanitized_fields, $search, $page, $limit );
	}

	public function print_export( $sanitized_fields, $search = array(), $page = 1, $per_page = 100 ) {
		$offset = $per_page * ( intval( $page ) - 1 );
		if ( 0 > $offset ) {
			$offset = 0;
		}

		$elements = $this->get_main_elements( $search, $offset, $per_page );
		$total    = $elements['total'];
		$elements = $elements['elements'];

		$first_row = $this->create_first_row( $sanitized_fields );

		header( 'Content-Type: application/json' );

		// We build our own JSON to be able to use flush()
		printf(
			'[{"total_entries":%d,"page":%d,"per_page":%d,"file_name":"%s"}, [',
			$total,
			$page,
			$per_page,
			$this->get_export_name( $search )
		);

		$not_first = false;
		foreach ( $elements as $element ) {
			if ( $not_first ) {
				echo ',';
			}
			$not_first = true;

			$row = $this->create_row( $element, $sanitized_fields );

			$row_data = array();
			foreach ( $first_row as $key => $name ) {
				$row_data[ $name ] = $row[ $key ];
			}

			$row_data = apply_filters( 'cie_export_row', $row_data, $search, $sanitized_fields, $first_row );
			echo json_encode( $row_data, JSON_UNESCAPED_UNICODE );

			// Flush every row
			flush();
		}

		echo ']]';
	}

	/**
	 * Returns the main elements
	 *
	 * @param array $search
	 * @param       $offset
	 * @param       $limit
	 *
	 * @return array
	 */
	abstract function get_main_elements( array $search, $offset, $limit );

	/**
	 * Creates a row
	 *
	 * @param CIE_Element $element
	 * @param array $fields
	 *
	 * @return array
	 * @throws Exception
	 */
	public function create_row( CIE_Element $element, array $fields ) {
		$row = array();

		do_action( 'cie_pre_create_row', $element );

		foreach ( $fields as $group_name => $field_group ) {
			// Get value from generic field object
			if ( in_array( $group_name, $this->get_supported_fields(), true ) ) {
				$object = $this->get_field_type_object( $group_name );
				$row    = array_merge( $row, $object->get_field_values( array_keys( $field_group ), $element ) );
				continue;
			}

			$element_object = $element->get_element();

			// Get value from the main element
			foreach ( array_keys( $field_group ) as $field_id ) {
				$value = apply_filters( 'cie_get_field_value', '', $element, $field_id, $group_name );
				if ( empty( $value ) ) {
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
				}

				$row[] = $value;
			}
		}

		// Sanitize output
		foreach ( $row as $key => $value ) {
			if ( is_array( $value ) ) {
				$value = implode( ';', $value );
			}
			$row[ $key ] = htmlspecialchars_decode( $value );
		}

		return $row;
	}

	/**
	 * Returns the first row
	 *
	 * @todo This is no longer needed as we switched to JSON
	 *
	 * @param array $fields
	 *
	 * @return array
	 */
	public function create_first_row( array $fields = array() ) {
		$first_row = array();
		foreach ( $fields as $group_name => $field_group ) {
			foreach ( $field_group as $field_id => $field_name ) {
				if ( isset( $fields[ $group_name ] ) && isset( $fields[ $group_name ][ $field_id ] ) ) {
					if ( ! empty( $_REQUEST['expert_mode'] ) ) {
						$first_row[] = $field_id;
					} else {
						$first_row[] = $field_name;
					}
				}
			}
		}

		return $first_row;
	}

	abstract public function get_export_name( array $search );
}
