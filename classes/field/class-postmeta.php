<?php
/**
 * Date: 16.01.15
 * Time: 12:21
 */
class CIE_Field_Postmeta extends CIE_Field_Abstract
{
	public function get_available_fields( array $search = array() )
	{
		global $wpdb;

		if ( ! empty( $search['post_type'] ) ) {
			$sql = 'SELECT DISTINCT( meta_key ) FROM ' . $wpdb->postmeta . ' WHERE post_id IN ( ';
			$sql .= 'SELECT ID FROM ' . $wpdb->posts . ' WHERE post_type = %s )';
			$sql = $wpdb->prepare( $sql, $search['post_type'] );
		} elseif( ! empty( $search['post_id'] ) ) {
			$sql = 'SELECT DISTINCT( meta_key ) FROM ' . $wpdb->usermeta . ' WHERE post_id = %d ';
			$sql = $wpdb->prepare( $sql, $search['post_id'] );
		} else {
			$sql = $sql = 'SELECT DISTINCT( meta_key ) FROM ' . $wpdb->postmeta;
		}

		$meta_keys = $wpdb->get_results( $sql, ARRAY_N );

		$return = array();
		foreach( $meta_keys as $meta_key ) {
			$meta_key = $meta_key[0];
			$return[ $meta_key ] = $meta_key;
		}

		return $return;
	}

	public function get_field_values( array $fields, CIE_Element $element )
	{
		$data = array();

		$id = null;
		$element = $element->get_element();
		if ( $element instanceof WP_Post ) {
			$id = $element->ID;
		} elseif ( is_object( $element ) && isset( $element->comment_post_ID ) ) {
			$id = $element->comment_post_ID;
		}


		foreach ( $fields as $field_id ) {
			$value = '';
			if ( is_numeric( $id ) ) {
				$value = get_post_meta( $id, $field_id, true ) ;
			}

			$data[] = $value;
		}
		return $data;
	}

	public function set_field_values( array $fields, CIE_Element $element )
	{
		$errors = array();

		foreach ( $fields as $field_id => $value ) {
			if ( 0 !== strpos( $field_id, 'meta_' ) ) {
				continue;
			}

			$field_id = str_replace( 'meta_', '', $field_id );

			if ( ! update_user_meta( $element->get_user_id(), $field_id, $value ) ) {
				$errors[] = sprintf(
					__( 'User post value %s could not be set', 'cie' ),
					strip_tags( $value )
				);
			}
		}

		return $errors;
	}
}