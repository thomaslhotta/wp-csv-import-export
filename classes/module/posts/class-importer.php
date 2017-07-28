<?php

/**
 * Handles importing of posts
 */
class CIE_Module_Posts_Importer extends CIE_Importer {
	public function get_supported_fields() {
		return array(
			'postmeta',
		);
	}

	public function get_supported_mode() {
		return self::MODE_BOTH;
	}

	/**
	 * @param $mode
	 *
	 * @return array
	 */
	public function get_required_fields( $mode ) {
		if ( parent::MODE_UPDATE === $mode ) {
			return array(
				array(
					'columns'     => array( 'ID' ),
					'description' => 'Post ID',
				),
			);
		}

		return array(
			array(
				'columns'     => array( 'post_type' ),
				'description' => '',
			),
		);
	}

	public function import( array $data, $mode ) {
		remove_action( 'transition_post_status', '_update_blog_date_on_post_publish', 10 );
		remove_action( 'transition_post_status', '_update_posts_count_on_transition_post_status', 10 );


		$return = parent::import( $data, $mode );

		add_action( 'transition_post_status', '_update_blog_date_on_post_publish', 10, 3 );
		wpmu_update_blogs_date();

		add_action( 'transition_post_status', '_update_posts_count_on_transition_post_status', 10, 2 );
		update_posts_count();

		return $return;
	}

	/**
	 * @param array $data
	 * @param int $mode
	 *
	 * @return CIE_Element
	 * @throws Exception
	 */
	public function create_element( array $data, $mode = parent::MODE_IMPORT ) {
		if ( empty( $data['post_author'] ) ) {
			$data['post_author'] = get_current_user_id();
		}

		$id = wp_insert_post(
			$data
		);

		if ( empty( $id ) ) {
			throw new Exception( 'Could not create post' );
		}

		$post = get_post( $id );

		$element = new CIE_Element();
		$element->set_element( $post, $id, $data['post_author'] );

		return $element;
	}
}
