<?php
/**
 * Date: 15.01.15
 * Time: 10:21
 */
/**
 * Date: 14.01.15
 * Time: 14:21
 */
class Module_Users_Exporter_Test extends WP_UnitTestCase
{
	/**
	 * @var CIE_Module_Users_Exporter
	 */
	protected $exporter;

	protected $users = array();

	protected $second_blog;

	public function setUp()
	{
		parent::setUp();
		$this->exporter = new CIE_Module_Users_Exporter();

		$blog_id = $this->factory->blog->create();
		$this->second_blog = $blog_id;

		$users = array(
			array(
				'user_login' => 'user1',
				'user_email' => 'user1@email.com',
				'user_pass'  => 'pw1',
				'blogs'      => array( $blog_id ),
			),
			array(
				'user_login' => 'user2',
				'user_email' => 'user2@email.com',
				'user_pass'  => 'pw2'
			),
			array(
				'user_login' => 'user3',
				'user_email' => 'user3@email.com',
				'user_pass'  => 'pw3',
				'blogs'      => array( $blog_id ),
			),
			array(
				'user_login' => 'user4',
				'user_email' => 'user4@email.com',
				'user_pass'  => 'pw4'
			),
			array(
				'user_login' => 'user5',
				'user_email' => 'user5@email.com',
				'user_pass'  => 'pw5'
			),
			array(
				'user_login' => 'user6',
				'user_email' => 'user6@email.com',
				'user_pass'  => 'pw6',
				'blogs'      => array( $blog_id ),
			),
			array(
				'user_login' => 'user7',
				'user_email' => 'user7@email.com',
				'user_pass'  => 'pw7'
			),
		);

		foreach ( $users as $key => $user ) {
			$id = wp_create_user( $user['user_login'], $user['user_pass'], $user['user_email'] );
			$user['ID'] = $id;
			$this->users[$id] = $user;

			if ( empty( $user['blogs'] ) ) {
				continue;
			}

			foreach ( $user['blogs'] as $blog ) {
				switch_to_blog( $blog );
				$user = get_user_by( 'id', $id );
				$user->add_role( 'subscriber' );
				restore_current_blog();
			}
		}
	}

	public function test_export_from_network_admin()
	{
		$this->assertTrue( is_multisite() );
		$exporter = $this->getMockBuilder( 'CIE_Module_Users_Exporter' )
			->setMethods( array( 'is_network_admin' ) )
			->getMock();
		$exporter->expects( $this->any() )
		     ->method( 'is_network_admin' )
		     ->will( $this->returnValue( true ) );


		$this->assertTrue( $exporter->is_network_admin() );

		$elements = $exporter->get_main_elements( array(), 0, 300 );

		$this->assertEquals( count( $this->users ) + 1, $elements['total'] ); // All users + one admin
	}

	/**
	 * Test if only the users of a specific blog are exported
	 */
	public function test_export_second_blog()
	{
		switch_to_blog( $this->second_blog );

		$elements = $this->exporter->get_main_elements( array(), 0, 300 );

		$this->assertEquals( 3, $elements['total'] );

		foreach ( $elements['elements'] as $element ) {
			$user = $element->get_element();
			$this->assertContains(
				get_current_blog_id(),
				$this->users[ $user->ID ]['blogs']
			);
		}

		restore_current_blog();
	}

	/**
	 * Test if limiting the range works
	 */
	public function test_export_range()
	{
		$elements = $this->exporter->get_main_elements( array(), 2, 3 );
		$this->assertEquals( count( $this->users ), $elements['total'] );
		$this->assertCount( 3, $elements['elements'] );

		foreach ( $elements['elements'] as $element ) {
			$user = $element->get_element();
			$this->assertEquals( $this->users[ $element->get_element_id() ]['ID'], $user->ID );
			$this->assertEquals( $this->users[ $element->get_element_id() ]['user_login'], $user->user_login );
		}
	}

	public function test_get_available_fields()
	{
		$fields = $this->exporter->get_available_fields();

		// Test if supported fields are within the available fields array
		foreach ( $this->exporter->get_supported_fields() as $field_type ) {
			$this->assertArrayHasKey( $field_type, $fields );
		}
	}

	public function test_export()
	{
		$fields = $this->exporter->get_available_fields();

		$user = reset( $this->users );
		$user = get_user_by( 'id', $user['ID'] );

		$element = new CIE_Element();
		$element->set_element( $user, $user->ID, $user->ID );

		$row = $this->exporter->create_row( $element, $fields );

	}
}