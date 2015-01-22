<?php
/**
 * Date: 08.01.15
 * Time: 15:42
 */
class CIE_Field_Usermeta extends CIE_Field_Abstract
{
	public function get_available_fields( array $search = array() )
	{
		global $wpdb;

		if ( ! empty( $search['post_type'] ) ) {
			$sql = 'SELECT DISTINCT( meta_key ) FROM ' . $wpdb->usermeta . ' WHERE user_id IN ( ';
			$sql .= 'SELECT post_author FROM ' . $wpdb->posts . ' WHERE post_type = %s )';
			$sql = $wpdb->prepare( $sql, $search['post_type'] );
		} elseif( ! empty( $search['post_id'] ) ) {
			$sql = 'SELECT DISTINCT( meta_key ) FROM ' . $wpdb->usermeta . ' AS m INNER JOIN ' . $wpdb->comments . ' AS c '
				 . 'ON c.comment_post_ID = %d AND m.user_id = c.user_id';
			$sql = $wpdb->prepare( $sql, $search['post_id'] );
		} else {
			$sql = $sql = 'SELECT DISTINCT( meta_key ) FROM ' . $wpdb->usermeta;
		}

		$meta_keys = $wpdb->get_results( $sql, ARRAY_N );

		$return = array();
		foreach( $meta_keys as $meta_key ) {
			$meta_key = $meta_key[0];
			$blog_prefix = 'wp_' . get_current_blog_id() . '_';
			if ( ! is_network_admin() && 0 === strpos( $meta_key, 'wp_' ) && 0 !== strpos( $meta_key, $blog_prefix ) ) {
				continue;
			}

			$return[ $meta_key ] = $meta_key;
		}

		return $return;
	}

	public function get_field_values( array $fields, CIE_Element $element )
	{
		$data = array();
		foreach ( $fields as $field_id ) {
			$data[] = get_user_meta( $element->get_user_id(), $field_id, true ) ;
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
					__( 'User meta value %s could not be set', 'cie' ),
					strip_tags( $value )
				);
			}
		}

		return $errors;
	}
}
