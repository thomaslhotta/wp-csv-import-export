<?php
class Module_Users_Adder_Test extends WP_UnitTestCase {
	/**
	 * @var CIE_Module_Users_Creator
	 */
	protected $importer;

	public function setUp() {
		$this->importer = new CIE_Module_Users_Adder();
		parent::setUp();
	}

	/**
	 * Test user import
	 */
	public function test_import_user() {
		$user_1 = $this->factory->user->create_and_get(
			array(
				'role'       => 'subscriber',
				'user_login' => 'u1',
				'user_email' => 'u1@email.com',
			)
		);

		$user_2 = $this->factory->user->create_and_get(
			array(
				'role'       => array(),
				'user_login' => 'u2',
				'user_email' => 'u2@email.com',
			)
		);

		$user_3 = $this->factory->user->create_and_get(
			array(
				'role'       => array(),
				'user_login' => 'u3',
				'user_email' => 'u3@email.com',
			)
		);

		$this->assertContains( 'subscriber', $user_1->roles );
		$this->assertNotContains( 'editor', $user_2->roles );
		$this->assertNotContains( 'administrator', $user_3->roles );

		$data = array(
			array(
				'ID'               => $user_1->ID,
				// Should default to subscriber role, should be skipped because user already has subscriber role
				'user_option_test' => 'user_1',
			),
			array(
				'user_login'       => 'u2',
				'role'             => 'editor',
				'user_option_test' => 'user_2',
			),
			array(
				'user_email'       => 'u3@email.com',
				'role'             => 'administrator',
				'user_option_test' => 'user_3',
			),
			array(
				'role'             => 'administrator', // Should fail because no id is provided
				'user_option_test' => 'user_3',
			),
		);

		$result = $this->importer->import( $data, $this->importer->get_supported_mode() );

		$this->assertCount( 1, $result['errors'] );
		$this->assertEquals( 3, $result['imported'] );

		$user_1 = get_user_by( 'id', $user_1->ID );
		$user_2 = get_user_by( 'id', $user_2->ID );
		$user_3 = get_user_by( 'id', $user_3->ID );

		$this->assertContains( 'subscriber', $user_1->roles );
		$this->assertContains( 'editor', $user_2->roles );
		$this->assertContains( 'administrator', $user_3->roles );

		$this->assertEquals( 'user_1', get_option( 'user_option_test_' . $user_1->ID ) );
		$this->assertEquals( 'user_2', get_option( 'user_option_test_' . $user_2->ID ) );
		$this->assertEquals( 'user_3', get_option( 'user_option_test_' . $user_3->ID ) );

		$this->assertCount( 1, $result['errors'] );
	}
}