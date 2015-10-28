<?php

/**
 * Imports and exports comment meta data
 */
class CIE_Field_Commentmeta extends CIE_Field_Meta {
	public function get_available_fields( array $search = array() ) {
		global $wpdb;

		if ( empty( $search['post']['post_id'] ) || empty( $search['post']['post_id'] ) ) {
			$sql = 'SELECT DISTINCT( meta_key ) FROM ' . $wpdb->commentmeta;
		} else {
			$sql = 'SELECT DISTINCT( meta_key ) FROM ' . $wpdb->commentmeta . ' WHERE comment_id IN ( ';
			$sql .= 'SELECT comment_ID FROM ' . $wpdb->comments . ' WHERE comment_post_ID = %d )';

			$sql = $wpdb->prepare( $sql, $search['post']['post_id'] );
		}

		$meta_keys = $wpdb->get_results( $sql, ARRAY_N );

		$return = array();
		foreach ( $meta_keys as $meta_key ) {
			$return[ $meta_key[0] ] = $meta_key[0];
		}

		return $return;
	}

	public function get_searchable_fields( array $search = array() ) {
		return $this->get_available_fields( $search );
	}

	public function get_field_values( array $fields, CIE_Element $element ) {
		return $this->get_meta_values( $fields, 'comment', $element->get_element_id() );
	}

	public function set_field_values( array $fields, CIE_Element $element ) {
		$errors = array();

		$fields = $this->extract_meta( $fields );

		foreach ( $fields as $field_id => $value ) {
			$field_id = apply_filters( 'cie_import_comment_meta_key', $field_id, $value, $element );

			$value = apply_filters( 'cie_import_comment_meta_value_' . $field_id, $value, $element );


			if ( is_array( $value ) ) {
				foreach ( array_reverse( $value ) as $v ) {
					if ( ! add_comment_meta( $element->get_element_id(), $field_id, $v ) ) {
						$errors[] = sprintf(
							__( 'User comment value %s could not be set', 'cie' ),
							strip_tags( $v )
						);
					}
				}
			} else {
				if ( ! update_comment_meta( $element->get_element_id(), $field_id, $value ) ) {
					$errors[] = sprintf(
						__( 'User comment value %s could not be set', 'cie' ),
						strip_tags( $value )
					);
				}
			}
		}

		return $errors;
	}
}