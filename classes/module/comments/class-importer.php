<?php
/**
 * Imports comments
 */
class CIE_Module_Comments_Importer extends CIE_Importer
{
	protected $posts = array();

	protected $users = array();

	public function get_supported_mode()
	{
		return self::MODE_BOTH;
	}

	public function get_supported_fields()
	{
		return array(
			'commentmeta',
		);
	}

	public function get_required_fields( $mode )
	{
		if ( parent::MODE_UPDATE === $mode ) {
			return array(
				array(
					'columns'     => array( 'comment_ID' ),
					'description' => __( 'Comment ID.', 'cie' )
				),
			);
		}

		return array(
			array(
				'columns'     => array( 'comment_post_ID', 'comment_post_title', 'comment_post_name' ),
				'description' => __( 'User login name.', 'cie' )
			),
		);
	}

	public function create_element( array $data, $mode = parent::MODE_IMPORT )
	{
		$element = new CIE_Element();

		// Merge data with existing comment in update mode
		if ( $mode === parent::MODE_UPDATE ) {
			if ( empty( $data['comment_ID'] ) ) {
				$element->set_error( __( 'comment_ID is missing', 'cie' ) );
				return $element;
			}

			$comment = get_comment( $data['comment_ID'], ARRAY_A );
			if ( empty( $comment ) ) {
				$element->set_error(
					sprintf(
						__( 'Comment with ID %d could not be found', 'cie' ),
						$data['comment_ID']
					)
				);
				return $element;
			}

			$data = array_merge( $comment, $data );
		}

		$data['comment_post_ID'] = $this->find_post_id( $data );
		if ( empty( $data['comment_post_ID'] ) ) {
			$element->set_error( __( 'Could not find parent post', 'cie' ) );
			return $element;
		}

		// Find comment parent
		if ( ! empty( $data['comment_parent'] ) ) {
			$comment_parent = get_comment( $data['comment_parent'] );

			if ( empty( $comment_parent ) ) {
				$element->set_error( __( 'Comment parent could not be found', 'cie' ) );
				return $element;
			}

			$data['comment_parent'] = $comment_parent->comment_ID;
		}


		$comment_author = $this->get_user( $data );
		if ( false === $comment_author ) {
			$element->set_error( __( 'Comment author could not be found', 'cie' ) );
			return $element;
		}

		// Set comment author values if a user was found
		if ( $comment_author instanceof WP_User ) {
			$data['user_id'] = $comment_author->ID;
			if ( empty( $data['comment_author'] ) ) {
				$data['comment_author'] = $comment_author->display_name;
			}

			if ( empty( $data['comment_author_email'] ) ) {
				$data['comment_author_email'] = $comment_author->user_email;
			}
		}

		// Ensure that comment author name is set
		if ( empty( $data['comment_author'] ) ) {
			$element->set_error( __( 'Comment author name is missing', 'cie' ) );
			return $element;
		}

		$comment = null;

		if ( self::MODE_IMPORT === $mode ) {
			$comment_id = wp_insert_comment( $data );
			$comment = get_comment( $comment_id );
		} else {
			if ( wp_update_comment( $data ) ) {
				$comment = get_comment( $data['comment_ID'] );
			}
		}

		if ( is_object( $comment ) && ! empty( $comment->comment_ID ) ) {
			$element->set_element( $comment, $comment->comment_ID, $comment->user_id );
		} else {
			$element->set_error( __( 'Comment could not be inserted', 'cie' ) );
		}

		return $element;
	}

	public function find_post_id( array $data )
	{
		$post = null;
		if ( ! empty( $data['comment_post_ID'] ) && is_numeric( $data['comment_post_ID'] ) ) {
			$post = get_post( $data['comment_post_ID'] );
		} elseif ( ! empty( $data['comment_post_title'] ) ) {
			$post = $this->get_post( $data['comment_post_title'], 'post_title' );
		} elseif ( ! empty( $data['comment_post_name'] ) ) {
			$post = $this->get_post( $data['comment_post_name'], 'post_name' );
		}

		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		return intval( $post->ID );
	}

	/**
	 * Returns the post if for a post title or name.
	 *
	 * @param string $name
	 * @param string $type
	 * @return WP_Post|null
	 */
	public function get_post( $name, $type = 'post_name' )
	{
		if ( isset( $this->posts[ $name ] ) ) {
			return $this->posts[ $name ];
		}

		global $wpdb;

		$query = $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE $type = %s", $name );

		$wpdb->query( $query );

		$id = $wpdb->get_var( $query );

		if ( is_numeric( $id ) ) {
			$post = get_post( $id );
		} else {
			$post = null;
		}

		$this->posts[ $name ] = $post;
		return $post;
	}

	public function get_user( array $data )
	{
		$user = null;
		if ( ! empty( $data['user_id'] ) ) {
			$user = get_user_by( 'id', $data['user_id'] );
		} elseif ( empty( $data['comment_author'] ) && ! empty( $data['comment_author_email'] ) ) {
			// If a comment author but no user id is given an anonymous comment is assumed
			$user = get_user_by( 'email', $data['comment_author_email'] );
		}

		return $user;
	}
}