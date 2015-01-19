<?php
/**
 * Date: 15.01.15
 * Time: 15:53
 */
class Module_Comments_Creator_Test extends WP_UnitTestCase {
	/**
	 * @var CIE_Module_Comments_Importer
	 */
	protected $importer;

	protected $users;

	protected $posts;

	public function setUp()
	{
		parent::setUp();
		$this->importer = new CIE_Module_Comments_Importer();

		$users = array(
			array(
				'user_login' => 'testuser1',
				'user_pass'  => 'password1',
				'user_email' => 'testUserEmail1@mail.com',
			),
			array(
				'user_login' => 'testuser2',
				'user_pass'  => 'password2',
				'user_email' => 'testUserEmail2@mail.com',
			),
			array(
				'user_login' => 'testuser3',
				'user_pass'  => 'password3',
				'user_email' => 'testUserEmail3@mail.com',
			)
		);

		foreach ( $users as $user ) {
			$user = $this->factory->user->create_and_get(
				$user
			);

			$this->users[ $user->ID ] = $user;
		}

		for ( $i = 0; $i < 3; $i ++ ) {
			$post = $this->factory->post->create_and_get();
			$this->posts[ $post->ID ] = $post;
		}
	}

	public function test_import_unregistered()
	{
		$post = reset( $this->posts );

		$data = array(
			'comment_post_ID'      => $post->ID,
			'comment_author'       => 'Author',
			'comment_author_email' => 'email' . $post->ID . '@mail.com',
			'comment_content'      => sprintf( 'Comment 1 on post %d', $post->ID ),
		);

		$element = $this->importer->create_element( $data, CIE_Module_Comments_Importer::MODE_IMPORT );

		$this->check_comment( $element, $data, $post->ID );
	}

	public function test_import_unregistered_missing_author()
	{
		$post = reset( $this->posts );

		$data = array(
			'comment_post_ID'      => $post->ID,
			'comment_author_email' => 'email' . $post->ID . '@mail.com',
			'comment_content'      => sprintf( 'Comment 1 on post %d', $post->ID ),
		);

		$element = $this->importer->create_element( $data, CIE_Module_Comments_Importer::MODE_IMPORT );

		$this->assertEmpty( $element->get_element() );
		$this->assertNotEmpty( $element->get_error() );
	}

	public function test_import_registered()
	{
		$post = reset( $this->posts );
		$user = reset( $this->users );

		$data = array(
			'comment_post_ID'      => $post->ID,
			'user_id' => $user->ID,
			'comment_content'      => sprintf( 'Comment 1 on post %d', $post->ID ),
		);

		$element = $this->importer->create_element( $data, CIE_Module_Comments_Importer::MODE_IMPORT );

		$this->assertEmpty( $element->get_error() );
		$this->check_comment( $element, $data, $post->ID );

		// Comment author and email should be retrieved from user
		$this->assertEquals( $user->display_name, $element->get_element()->comment_author );
		$this->assertEquals( $user->user_email, $element->get_element()->comment_author_email );
	}

	public function test_import_registered_not_found()
	{
		$post = reset( $this->posts );
		$user = reset( $this->users );

		$data = array(
			'comment_post_ID'      => $post->ID,
			'user_id'              => 999,
			'comment_content'      => sprintf( 'Comment 1 on post %d', $post->ID ),
		);

		$element = $this->importer->create_element( $data, CIE_Module_Comments_Importer::MODE_IMPORT );

		$this->assertEmpty( $element->get_element() );
		$this->assertNotEmpty( $element->get_error() );
	}

	public function test_import_registered_by_email()
	{
		$post = reset( $this->posts );
		$user = reset( $this->users );

		$data = array(
			'comment_post_ID'      => $post->ID,
			'comment_author_email' => $user->user_email,
			'comment_content'      => sprintf( 'Comment 1 on post %d', $post->ID ),
		);

		$element = $this->importer->create_element( $data, CIE_Module_Comments_Importer::MODE_IMPORT );

		$this->assertEmpty( $element->get_error() );
		$this->check_comment( $element, $data, $post->ID );
		$this->assertEquals( $user->display_name, $element->get_element()->comment_author );
		$this->assertEquals( $user->user_email, $element->get_element()->comment_author_email );
	}


	public function test_import_missing_post_id()
	{
		$post = reset( $this->posts );

		$data = array(
			'comment_author'       => 'Author',
			'comment_author_email' => 'email' . $post->ID . '@mail.com',
			'comment_content'      => sprintf( 'Comment 1 on post %d', $post->ID ),
		);

		$element = $this->importer->create_element( $data, CIE_Module_Comments_Importer::MODE_IMPORT );

		$this->assertEmpty( $element->get_element() );
		$this->assertNotEmpty( $element->get_error() );
	}

	public function test_import_by_post_title()
	{
		$post = reset( $this->posts );

		$data = array(
			'comment_author'       => 'Author',
			'comment_author_email' => 'email' . $post->ID . '@mail.com',
			'comment_content'      => sprintf( 'Comment 1 on post %d', $post->ID ),
			'comment_post_title'   => $post->post_title,
		);

		$element = $this->importer->create_element( $data, CIE_Module_Comments_Importer::MODE_IMPORT );

		$this->check_comment( $element, $data, $post->ID );
	}

	public function test_import_by_post_name()
	{
		$post = reset( $this->posts );

		$data = array(
			'comment_author'       => 'Author',
			'comment_author_email' => 'email' . $post->ID . '@mail.com',
			'comment_content'      => sprintf( 'Comment 1 on post %d', $post->ID ),
			'comment_post_name'   => $post->post_name,
		);

		$element = $this->importer->create_element( $data, CIE_Module_Comments_Importer::MODE_IMPORT );

		$this->check_comment( $element, $data, $post->ID );
	}

	public function test_import_with_parent()
	{
		$post = reset( $this->posts );

		$comment_id = $this->factory->comment->create();

		$data = array(
			'comment_post_ID'      => $post->ID,
			'comment_author'       => 'Author',
			'comment_author_email' => 'email' . $post->ID . '@mail.com',
			'comment_content'      => sprintf( 'Comment 1 on post %d', $post->ID ),
			'comment_parent'       => $comment_id
		);

		$element = $this->importer->create_element( $data, CIE_Module_Comments_Importer::MODE_IMPORT );

		$this->assertEmpty( $element->get_error() );
		$this->check_comment( $element, $data, $post->ID );
		$this->assertEquals( $comment_id, $element->get_element()->comment_parent );
	}

	public function test_import_missing_parent()
	{
		$post = reset( $this->posts );

		$data = array(
			'comment_post_ID'      => $post->ID,
			'comment_author'       => 'Author',
			'comment_author_email' => 'email' . $post->ID . '@mail.com',
			'comment_content'      => sprintf( 'Comment 1 on post %d', $post->ID ),
			'comment_parent'       => 999
		);

		$element = $this->importer->create_element( $data, CIE_Module_Comments_Importer::MODE_IMPORT );

		$this->assertEmpty( $element->get_element() );
		$this->assertContains( 'parent', $element->get_error() );
	}

	public function test_update()
	{
		$post = reset( $this->posts );

		// Create comment to be updated, comment_post_ids cannot be updated
		$comment_id = $this->factory->comment->create( array('comment_post_ID' => $post->ID ) );

		$data = array(
			'comment_author'       => 'Author',
			'comment_author_email' => 'email' . $post->ID . '@mail.com',
			'comment_content'      => sprintf( 'Comment 1 on post %d', $post->ID ),
			'comment_ID'           => $comment_id,
		);

		$element = $this->importer->create_element( $data, CIE_Module_Comments_Importer::MODE_UPDATE );

		$this->assertEquals( get_comment( $comment_id ), $element->get_element() );
		$this->check_comment( $element, $data, $post->ID );
	}

	public function test_update_missing_comment_id()
	{
		$data = array(
			'comment_author'       => 'Author',
			'comment_author_email' => 'email 1@mail.com',
		);

		$element = $this->importer->create_element( $data, CIE_Module_Comments_Importer::MODE_UPDATE );

		$this->assertContains( 'comment_ID', $element->get_error() );
		$this->assertEmpty( $element->get_element() );
	}

	public function test_update_missing_comment()
	{
		$data = array(
			'comment_author'       => 'Author',
			'comment_author_email' => 'email 1@mail.com',
			'comment_ID'           => 999,
		);

		$element = $this->importer->create_element( $data, CIE_Module_Comments_Importer::MODE_UPDATE );

		$this->assertContains( 'found', $element->get_error() );
		$this->assertEmpty( $element->get_element() );
	}

	public function check_comment( CIE_Element $element, array $data, $post_id = null )
	{
		$comment = $element->get_element();
		$this->assertEquals( $comment->comment_ID, $element->get_element_id() );
		$this->assertEquals( $comment->user_id, $element->get_user_id() );
		$this->assertEquals( $post_id, $comment->comment_post_ID );

		$comment = get_object_vars( $comment );

		foreach ( $data as $name => $value ) {
			if ( isset( $comment[ $name ] ) ) {
				$this->assertEquals( $value, $comment[ $name ] );
			}
		}
	}

}