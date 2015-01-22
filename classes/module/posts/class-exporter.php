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
			//'attachment', // @todo Implement attachment importing and exporting
		);
	}

	public function get_available_searches()
	{
		return array(
			'post_type',
			'meta_key',
			'meta_value',
		);
	}

	public function get_available_fields( array $search = array() )
	{
		$post = new WP_Post( new stdClass() );

		foreach  ( array_keys( get_object_vars( $post ) ) as $field ) {
			$fields['post'][ $field ] = $field;
		}

		$fields = array_merge( $fields, parent::get_available_fields( $search ) );
		return $fields;
	}

	public function get_main_elements( array $search, $offset, $limit )
	{
		$args = array(
			'post_type'            => $search['post_type'],
			'posts_per_page'       => $limit,
			'offset'               => $offset,
			'ignore_sticky_posts'  => true,
		);

		if ( ! empty( $search['meta_key'] ) ) {
			$args['meta_key'] = $search['meta_key'];

			if ( ! empty( $search['meta_value'] ) ) {
				$args['meta_value'] = $search['meta_value'];
			}
		}

		$args = apply_filters( 'cie_export_posts_args', $args );

		$query = new WP_Query( $args );

		$return = array(
			'total'    => $query->found_posts,
			'elements' => array(),
		);

		foreach ( $query->get_posts() as $post ) {
			$element = new CIE_Element();
			$return['elements'][] = $element->set_element( $post, $post->ID, $post->post_author );
		}

		return $return;
	}
}