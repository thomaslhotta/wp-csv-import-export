<?php
/**
 * Imports and exports comment meta data
 *
 * Date: 08.01.15
 * Time: 15:42
 */
class CIE_Field_Commentmeta extends CIE_Field_Abstract
{
	public function get_available_fields( array $search = array() )
	{
		global $wpdb;

		if ( empty( $search['post_id'] ) ) {
			$sql = 'SELECT DISTINCT( meta_key ) FROM ' . $wpdb->commentmeta;
		} else {
			$sql = 'SELECT DISTINCT( meta_key ) FROM ' . $wpdb->commentmeta . ' WHERE comment_id IN ( ';
			$sql .= 'SELECT comment_ID FROM ' . $wpdb->comments . ' WHERE comment_post_ID = %d )';

			$sql = $wpdb->prepare( $sql, $search['post_id'] );
		}

		$meta_keys = $wpdb->get_results( $sql, ARRAY_N );

		$return = array();
		foreach ( $meta_keys as $meta_key ) {
			$return[ $meta_key[0] ] = $meta_key[0];
		}

		return $return;
	}

	public function get_field_values( array $fields, CIE_Element $element )
	{
		$data = array();
		foreach ( $fields as $field_id ) {
			$data[] = get_comment_meta( $element->get_element_id(), $field_id, true );
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

			if ( ! update_comment_meta( $element->get_element_id(), $field_id, $value ) ) {
				$errors[] = sprintf(
					__( 'User comment value %s could not be set', 'cie' ),
					strip_tags( $value )
				);
			}
		}

		return $errors;
	}
}