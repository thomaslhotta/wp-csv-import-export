<?php
/**
 * Exports posts
 */
class CIE_Module_Posts_Exporter extends CIE_Exporter
{
	public function get_supported_fields()
	{
		return array(
			'postmeta',
			'user',
			'usermeta',
			'buddypress',
			'attachment',
		);
	}

	public function get_available_searches( array $searches = array() )
	{
		return array(
			'post' => array( 'post_type' => 'post_type' ),
			'postmeta' => $this->get_field_type_object( 'postmeta' )->get_searchable_fields( $searches )
		);
	}

	public function get_available_fields( array $search = array() )
	{
		$post = new WP_Post( new stdClass() );

		foreach ( array_keys( get_object_vars( $post ) ) as $field ) {
			$fields['post'][ $field ] = $field;
		}

		$fields = array_merge( $fields, parent::get_available_fields( $search ) );
		return $fields;
	}

	public function get_main_elements( array $search, $offset, $limit )
	{
		if ( empty( $search['post'] ) || empty( $search['post']['post_type'] ) ) {
			throw new Exception( 'No post type provided.' );
		}

		$args = array(
			'post_type'            => $search['post']['post_type'],
			'posts_per_page'       => $limit,
			'offset'               => $offset,
			'ignore_sticky_posts'  => true,
			'post_status'          => 'any',
			'orderby'              => 'ID',
			'order'                => 'ASC',
		);

		if ( ! empty( $search['postmeta'] ) ) {
			foreach ( $search['postmeta'] as $key => $value ) {
				if ( empty( $value ) ) {
					continue;
				}
				$args['meta_query'][] = array(
					'key'   => $key,
					'value' => $value,
				);
			}
		}

		$args = apply_filters( 'cie_export_posts_args', $args );
		$query = new WP_Query( $args );

		$return = array(
			'total'    => $query->found_posts,
			'elements' => array(),
		);

		if ( 0 === $limit ) {
			return $return;
		}

		$posts = apply_filters( 'cie_export_posts', $query->get_posts(), $args );

		foreach ( $posts as $post ) {
			$element = new CIE_Element();
			$return['elements'][] = $element->set_element( $post, $post->ID, $post->post_author );
		}

		$return = apply_filters( 'cie_export_posts_elements', $return, $args );

		return $return;
	}
}
