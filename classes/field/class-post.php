<?php
class CIE_Field_Post extends CIE_Field_Object {
	public function get_available_fields( array $search = array() ) {
		$post_type_name = __( 'Post' );
		if ( ! empty( $search['post'] ) && ! empty( $search['post']['post_type'] ) ) {
			$post_type_object = get_post_type_object( $search['post']['post_type'] );
			$post_type_name = $post_type_object->labels->name;
		}

		$fields = array(
			'ID'                 => $post_type_name . ' ID',
			'post_author'        => __( 'Author ID', 'cie' ),
			'post_date'          => __( 'Publish date', 'cie' ),
			'post_modified'      => __( 'Last Modified' ),
			'post_content'       => __( 'Content' ),
			'post_title'         => __( 'Title' ),
			'post_excerpt'       => __( 'Excerpt' ),
			'post_status'        => __( 'Status' ),
			'comment_status'     => __( 'Comment status' ),
			'ping_status'        => __( 'Ping status' ),
			'post_password'      => __( 'Password' ),
			'post_name'          => __( 'Post name' ),
			'to_ping'            => __( 'URLs to ping', 'cie' ),
			'pinged'             => __( 'Pinged URLs', 'cie' ),
			'post_parent'        => __( 'Post parent', 'cie' ),
			'guid'               => 'GUID',
			'permalink'          => __( 'Permalink' ),
			'menu_order'         => __( 'Menu order' ),
			'post_type'          => __( 'Post type', 'cie' ),
			'post_mime_type'     => 'MIME Type',
			'comment_count'      => __( 'Comment count', 'cie' ),
			'post_date_gmt'      => __( 'Publish date', 'cie' ) . ' (GMT)',
			'post_modified_gmt'  => __( 'Last Modified' ) . ' (GMT)',
		);

		return $fields;
	}

	public function get_field_values( array $fields, CIE_Element $element ) {
		$element_object = $element->get_element();

		$data = array();
		foreach ( $fields as $field ) {
			if ( 'permalink' === $field ) {
				$data[] = get_permalink( $element_object->ID );
				continue;
			}

			$data[] = $this->get_value( $element_object, $field );
		}

		return $data;
	}

	public function set_field_values( array $fields, CIE_Element $element ) {
		// TODO: Implement set_field_values() method.
	}
}
