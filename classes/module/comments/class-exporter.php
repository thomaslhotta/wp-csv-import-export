<?php
/**
 * Exports comments
 *
 * Date: 18.12.14
 * Time: 12:24
 */
class CIE_Module_Comments_Exporter extends CIE_Exporter
{
	public function get_supported_fields()
	{
		return array(
			'commentmeta',
			'buddypress',
			'user',
			'usermeta',
		);
	}

	public function get_available_fields( array $search = array() )
	{
		$fields['comment'] = array(
			'comment_ID'            => 'comment_ID',
			'comment_post_ID'       => 'comment_post_ID',
			'comment_author'        => 'comment_author',
			'comment_author_email'  => 'comment_author_email',
			'comment_author_url'    => 'comment_author_url',
			'comment_author_IP'     => 'comment_author_IP',
			'comment_date'          => 'comment_date',
			'comment_date_gmt'      => 'comment_date_gmt',
			'comment_content'       => 'comment_content',
			'comment_karma'         => 'comment_karma',
			'comment_approved'      => 'comment_approved',
			'comment_agent'         => 'comment_agent',
			'comment_type'          => 'comment_type',
			'comment_parent'        => 'comment_parent',
			'user_id'               => 'user_id',
		);

		$fields = array_merge( $fields, parent::get_available_fields( $search ) );

		return $fields;
	}

	public function get_available_searches()
	{
		return array(
			'post_id',
		);
	}

	public function get_main_elements( array $search, $offset, $limit )
	{
		$query_args = array(
			'offset' => $offset,
			'number' => $limit,
		);

		if ( ! empty( $search['post_id'] ) ) {
			$query_args['post_id'] = $search['post_id'];
		}

		$query = new WP_Comment_Query();

		$comments = $query->query( $query_args );

		unset( $query_args['offset'] );
		unset( $query_args['number'] );
		$query_args['count'] = true;
		$total = $query->query( $query_args );

		$return = array(
			'total' => $total,
		);

		foreach ( $comments as $comment ) {
			$element = new CIE_Element();
			$element->set_element( $comment, $comment->comment_ID, $comment->user_id );
			$return['elements'][] = $element;
		}

		return $return;
	}
}