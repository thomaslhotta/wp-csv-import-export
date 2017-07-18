<?php

/**
 * Handles post meta
 */
class CIE_Field_Postmeta extends CIE_Field_Meta {
	public function get_available_fields( array $search = array() ) {
		global $wpdb;

		$sql = 'SELECT DISTINCT( meta_key ) FROM ' . $wpdb->postmeta;

		if ( ! empty( $search['post'] ) ) {
			if ( ! empty( $search['post']['post_type'] ) ) {
				$sql = 'SELECT DISTINCT( meta_key ) FROM ' . $wpdb->postmeta . ' WHERE post_id IN ( ';
				$sql .= 'SELECT ID FROM ' . $wpdb->posts . ' WHERE post_type = %s )';
				$sql = $wpdb->prepare( $sql, $search['post']['post_type'] );
			} elseif ( ! empty( $search['post']['post_id'] ) ) {
				$sql = 'SELECT DISTINCT( meta_key ) FROM ' . $wpdb->usermeta . ' WHERE post_id = %d ';
				$sql = $wpdb->prepare( $sql, $search['post']['post_id'] );
			}
		}

		$meta_keys = $wpdb->get_results( $sql, ARRAY_N );

		$return = array();
		foreach ( $meta_keys as $meta_key ) {
			$meta_key            = $meta_key[0];

			// Skip metas with underscore keys
			if ( 0 === strpos( $meta_key, '_' ) ) {
				continue;
			}

			$return[ $meta_key ] = $meta_key;
		}

		return $return;
	}

	public function get_searchable_fields( array $search = array() ) {
		$searches = array();
		foreach ( $this->get_available_fields( $search ) as $search ) {
			$searches[ $search ] = $search;
		}

		return $searches;
	}

	public function get_field_values( array $fields, CIE_Element $element ) {
		$data = array();

		$id      = null;
		$element = $element->get_element();
		if ( $element instanceof WP_Post ) {
			$id = $element->ID;
		} elseif ( is_object( $element ) && isset( $element->comment_post_ID ) ) {
			$id = $element->comment_post_ID;
		}

		if ( ! is_numeric( $id ) ) {
			foreach ( $fields as $field_id ) {
				$data[] = '';
			}
		}

		return $this->get_meta_values( $fields, 'post', $id );
	}

	public function set_field_values( array $fields, CIE_Element $element ) {
		$errors = array();
		foreach ( $fields as $field_id => $value ) {
			if ( 0 !== strpos( $field_id, 'meta_' ) ) {
				continue;
			}

			$field_id = str_replace( 'meta_', '', $field_id );

			if ( ! update_post_meta( $element->get_element_id(), $field_id, $value ) ) {
				$errors[] = sprintf(
					__( 'User post value %s could not be set', 'cie' ),
					strip_tags( $value )
				);
			}
		}

		return $errors;
	}
}
