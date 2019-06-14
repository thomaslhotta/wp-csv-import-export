<?php
/**
 * Date: 14.01.15
 * Time: 14:21
 */
class Module_Users_Creator_Test extends WP_UnitTestCase
{
	/**
	 * @var CIE_Module_Users_Creator
	 */
	protected $importer;

	public function setUp()
	{
		$this->importer = new CIE_Module_Users_Creator();
		parent::setUp();
	}

	/**
	 * Test user import
	 */
	public function test_import_user()
	{
		$data = array(
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

		$result = $this->importer->import( $data, CIE_Importer::MODE_IMPORT );
		$this->assertEmpty( $result['errors'] );
		$this->assertEquals( 3, $result['imported'] );

		foreach ( $data as $user_def ) {
			$user = get_user_by( 'login', $user_def['user_login'] );

			// Check if all values have been set correctly
			foreach ( $user_def as $name => $value ) {
				$this->assertEquals( $value, $user->get( $name ) );
			}
		}

		global $phpmailer;

		// No emails should be sent when importing
		$this->assertEmpty( $phpmailer->mock_sent );

	}

	/**
	 * Test import failing on existing users
	 */
	public function test_import_existing_user()
	{
		wp_create_user( 'testuser1', 'password', 'testUserEmail1@mail.com' );
		wp_create_user( 'someuser', 'password', 'testUserEmail2@mail.com' );

		$data = array(
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

		$result = $this->importer->import( $data, CIE_Importer::MODE_IMPORT );

		$this->assertCount( 2 , $result['errors'] );
		$this->assertEquals( 1, $result['imported'] );
	}

	/**
	 * Test update users
	 */
	public function test_update_user()
	{
		// Test if users can be updated
		// not that user login names/passwords cannot be updated
		$data = array(
			array(
				'user_email' => 'testUserEmail1@mail.com',
			),
			array(
				'user_email' => 'testUserEmail2@mail.com',
			),
			array(
				'user_email' => 'testUserEmail3@mail.com',
			)
		);

		foreach ( $data as $key => $user_def ) {
			$data[ $key ]['ID'] = wp_create_user( 'user' .$key , 'pw', 'email' . $key . '@email.com' );
		}

		$result = $this->importer->import( $data, CIE_Importer::MODE_UPDATE );

		$this->assertEmpty( $result['errors'] );
		$this->assertEquals( 3, $result['imported'] );

		foreach ( $data as $user_def ) {

			$user = get_user_by( 'id', $user_def['ID'] );

			// Check if all values have been set correctly
			foreach ( $user_def as $name => $value ) {
				$this->assertEquals( $value, $user->get( $name ) );
			}
		}
	}

	/**
	 * Test error messages if user could not be found for update
	 */
	public function test_update_non_existing_user()
	{
		$data = array(
			array(
				'user_email' => 'testUserEmail1@mail.com',
				'ID'         => 99999999999,
			),
		);

		$result = $this->importer->import( $data, CIE_Importer::MODE_UPDATE );

		// Should generate an error message
		$this->assertNotEmpty( $result['errors'][1][0] );
		$this->assertEquals( 0, $result['imported'] );
	}

	/**
	 * Test importing meta
	 */
	public function test_import_user_meta()
	{
		$data = array(
			array(
				'user_login' => 'testuser1',
				'user_pass'  => 'password1',
				'user_email' => 'testUserEmail1@mail.com',
				'meta_test' => 'meta_value_0',
			),
			array(
				'user_login' => 'testuser2',
				'user_pass'  => 'password2',
				'user_email' => 'testUserEmail2@mail.com',
				'meta_test' => 'meta_value_1',
			),
			array(
				'user_login' => 'testuser3',
				'user_pass'  => 'password3',
				'user_email' => 'testUserEmail3@mail.com',
				'meta_test' => 'meta_value_2',
			)
		);

		$result = $this->importer->import( $data, CIE_Importer::MODE_IMPORT );

		$this->assertEmpty( $result['errors'] );
		$this->assertEquals( 3, $result['imported'] );

		foreach ( $data as $key => $user_def ) {
			$user = get_user_by( 'login', $user_def['user_login'] );

			$this->assertEquals(
				array(
					'meta_value_' . $key
				),
				get_user_meta( $user->ID, 'test' )
			);
		}
	}
}