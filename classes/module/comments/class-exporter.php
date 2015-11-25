<?php

/**
 * Exports comments
 *
 * Date: 18.12.14
 * Time: 12:24
 */
class CIE_Module_Comments_Exporter extends CIE_Exporter {
	public function get_supported_fields() {
		return array(
			'commentmeta',
			'buddypress',
			'user',
			'usermeta',
		);
	}

	public function get_available_fields( array $search = array() ) {
		$fields['comment'] = array(
			'comment_ID'           => __( 'Comment', 'cie' ) .' ID',
			'comment_post_ID'      => __( 'Post', 'cie' ) .' ID',
			'comment_author'       => __( 'Author:' ) . ' ' .  __( 'Name' ),
			'comment_author_email' => __( 'Author:' ) . ' Email',
			'comment_author_url'   => __( 'Author:' ) . ' URL',
			'comment_author_IP'    => rtrim( __( 'IP address:' ), ':' ),
			'comment_date'         => __( 'Date' ),
			'comment_date_gmt'     => __( 'Date' ) . ' (GMT)',
			'comment_content'      => __( 'Content' ),
			'comment_karma'        => 'Karma',
			'comment_approved'     => __( 'Approved' ),
			'comment_agent'        => 'User Agent',
			'comment_type'         => 'comment_type',
			'comment_parent'       => 'comment_parent',
			'user_id'              => __( 'Author:' ) . ' ID',
		);

		$fields = array_merge( $fields, parent::get_available_fields( $search ) );

		return $fields;
	}

	public function get_available_searches( array $search = array() ) {
		return array(
			'post'        => array(
				'post_id' => 'post_id',
			),
			'commentmeta' => $this->get_field_type_object( 'commentmeta' )->get_searchable_fields( $search ),
		);
	}

	public function get_main_elements( array $search, $offset, $limit ) {
		$query_args = array(
			'offset' => $offset,
			'number' => $limit,
		);

		$post_id = $this->get_post_id( $search );
		if ( $post_id ) {
			$query_args['post_id'] = $post_id;
		}

		if ( ! empty( $search['commentmeta'] ) ) {
			foreach ( $search['commentmeta'] as $key => $value ) {
				$query_args['meta_query'][] = array(
					'key'   => $key,
					'value' => $value,
				);
			}
		}

		$query = new WP_Comment_Query();

		$comments = $query->query( $query_args );

		unset( $query_args['offset'] );
		unset( $query_args['number'] );
		$query_args['count'] = true;
		$total               = $query->query( $query_args );

		$return = array(
			'total'    => $total,
			'elements' => array(),
		);

		foreach ( $comments as $comment ) {
			$element = new CIE_Element();
			$element->set_element( $comment, $comment->comment_ID, $comment->user_id );
			$return['elements'][] = $element;
		}

		return $return;
	}

	public function get_post_id( array $search ) {
		if ( ! empty( $search['post'] ) ) {
			if ( ! empty( $search['post']['post_id'] ) ) {
				return intval( $search['post']['post_id'] );
			}
		}

		return null;
	}

	public function get_export_name( array $search ) {
		$post_id = $this->get_post_id( $search );
		if ( empty( $post_id ) ) {
			return 'comment';
		}

		return sanitize_title( get_the_title( $post_id ) );
	}
}
