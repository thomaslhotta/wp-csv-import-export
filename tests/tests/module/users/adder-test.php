<?php
/**
 * Date: 14.01.15
 * Time: 14:21
 */
class Module_Users_Adder_Test extends WP_UnitTestCase
{
	/**
	 * @var CIE_Module_Users_Creator
	 */
	protected $importer;

	public function setUp()
	{
		$this->importer = new CIE_Module_Users_Adder();
		parent::setUp();
	}

	/**
	 * Test user import
	 */
	public function test_import_user()
	{
		$user_id_1 = wp_create_user( 'u1', 'pw', 'u1@email.com' );
		$user_id_2 = wp_create_user( 'u2', 'pw', 'u2@email.com' );
		$user_id_3 = wp_create_user( 'u3', 'pw', 'u3@email.com' );

		$user_1 = get_user_by( 'id', $user_id_1 );
		$user_2 = get_user_by( 'id', $user_id_2 );
		$user_3 = get_user_by( 'id', $user_id_3 );

		$this->assertContains( 'subscriber', $user_1->roles );
		$this->assertNotContains( 'editor', $user_2->roles );
		$this->assertNotContains( 'administrator', $user_3->roles );
		$this->assertNotContains( 'author', $user_3->roles );

		$data = array(
			array(
				'ID' => $user_id_1,  // Should default to subscriber role, should fail because user already has subscriber role
			),
			array(
				'user_login' => 'u2',
				'role'       => 'editor',
			),
			array(
				'user_email' => 'u3@email.com',
				'role'       => 'administrator',
			),
			array(
				'role'       => 'administrator', // Should fail because no id is provided
			),
		);

		$result = $this->importer->import( $data, $this->importer->get_supported_mode() );

		$this->assertCount( 2, $result['errors'] );
		$this->assertEquals( 2, $result['imported'] );

		$user_1 = get_user_by( 'id', $user_id_1 );
		$user_2 = get_user_by( 'id', $user_id_2 );
		$user_3 = get_user_by( 'id', $user_id_3 );

		$this->assertContains( 'subscriber', $user_1->roles );
		$this->assertContains( 'editor', $user_2->roles );
		$this->assertContains( 'administrator', $user_3->roles );
	}
}