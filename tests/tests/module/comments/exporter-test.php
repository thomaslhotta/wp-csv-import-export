<?php
/**
 * Date: 14.01.15
 * Time: 14:21
 */
class Module_Comments_Exporter_Test extends WP_UnitTestCase
{
	/**
	 * @var CIE_Module_Comments_Exporter
	 */
	protected $exporter;

	protected $users = array();

	protected $comments = array();

	protected $posts = array();

	public function setUp()
	{
		parent::setUp();
		$this->exporter = new CIE_Module_Comments_Exporter();

		$post_1 = $this->factory->post->create();
		$post_2 = $this->factory->post->create();
		$post_3 = $this->factory->post->create();

		$this->posts = array( $post_1, $post_2, $post_3 );

		$users = array(
			array(
				'user_login' => 'user1',
				'user_email' => 'user1@email.com',
				'user_pass'  => 'pw1',
				'comments' => array( $post_1, $post_2, $post_3, $post_3 )
			),
			array(
				'user_login' => 'user2',
				'user_email' => 'user2@email.com',
				'user_pass'  => 'pw2',
				'comments'   => array(  $post_2, $post_2, $post_3 )
			),
			array(
				'user_login' => 'user3',
				'user_email' => 'user3@email.com',
				'user_pass'  => 'pw3',
				'comments'   => array( $post_3, $post_3 )
			),
		);

		foreach ( $users as $key => $user ) {
			$id = wp_create_user( $user['user_login'], $user['user_pass'], $user['user_email'] );
			$user['ID'] = $id;
			$this->users[ $id ] = $user;

			foreach ( $user['comments'] as $post_id ) {
				$comment = $this->factory->comment->create_and_get(
					array(
						'comment_post_ID' => $post_id,
						'user_id'         => $id,
					)
				);

				$this->comments[ $comment->comment_ID ] = $comment;

				update_comment_meta( $comment->comment_ID, 'comment_post_meta' . $post_id, 'value' );
			}
		}
	}

	public function test_get_all_comments()
	{
		$elements = $this->exporter->get_main_elements( array(), 0, 100 );

		$this->assertEquals( count( $this->comments ), $elements['total'] );
		$this->check_comments( $elements['elements'] );
	}

	public function test_get_for_post()
	{
		$elements = $this->exporter->get_main_elements(
			array(
				'post' => array(
					'post_id' => $this->posts[0],
				)
			),
			0,
			100
		);
		$this->assertEquals( 1 , $elements['total'] );
		$this->check_comments( $elements['elements'], $this->posts[0] );

		$elements = $this->exporter->get_main_elements(
			array(
				'post' => array(
					'post_id' => $this->posts[1],
				)
			),
			0,
			100
		);
		$this->assertEquals( 3 , $elements['total'] );
		$this->check_comments( $elements['elements'], $this->posts[1] );

		$elements = $this->exporter->get_main_elements(
			array(
				'post' => array(
					'post_id' => $this->posts[2],
				)
			),
			0,
			100
		);
		$this->assertEquals( 5 , $elements['total'] );
		$this->check_comments( $elements['elements'], $this->posts[2] );
	}

	public function test_get_available_fields()
	{
		$fields = $this->exporter->get_available_fields();
		$comment = get_object_vars( reset( $this->comments ) );

		// Check if all comment fields are available
		foreach ( array_keys( $comment ) as $name  ) {
			$this->assertContains( $name, $fields['comment'] );
		}

		// Check if all meta key that where set are available
		foreach ( $this->posts as $post_id ) {
			$this->assertContains( 'comment_post_meta' . $post_id , $fields['commentmeta'] );
		}

		// Check if only the meta keys for a specific post are available
		foreach ( $this->posts as $post_id ) {
			$fields = $this->exporter->get_available_fields(
				array(
					'post' => array( 'post_id' => $post_id ),
				)
			);
			$this->assertEquals(
				array( 'comment_post_meta' . $post_id => 'comment_post_meta' . $post_id ),
				$fields['commentmeta']
			);
		}
	}

	/**
	 * @throws Exception
	 * @todo finish
	 */
	public function test_export_row()
	{
		$fields = $this->exporter->get_available_fields();

		$comment = reset( $this->comments );

		$element = new CIE_Element();
		$element->set_element( $comment, $comment->comment_ID, $comment->user_id );

		$result = $this->exporter->create_row( $element, $fields );

	}

	public function check_comments( array $comments, $post_id = null )
	{
		foreach ( $comments as $comment ) {
			$this->check_comment( $comment, $post_id );
		}
	}

	public function check_comment( CIE_Element $element, $post_id = null )
	{
		$comment = $element->get_element();
		$this->assertEquals( $comment->comment_ID, $element->get_element_id() );
		$this->assertEquals( $comment->user_id, $element->get_user_id() );


		$comment = get_object_vars( $comment );
		$this->assertArrayHasKey( $comment['comment_ID'], $this->comments );
		$check_comment = $this->comments[ $comment['comment_ID'] ];

		foreach ( $comment as $name => $value ) {
			$this->assertEquals( $value, $check_comment->$name );
		}
	}
}