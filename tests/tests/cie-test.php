<?php

class CIE_Test extends WP_UnitTestCase {
	public function test_construct_no_privileges() {
		$user = $this->factory->user->create_and_get( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $user->ID );
		$this->assertTrue( user_can( $user, 'read' ) );
		$cie = CSV_Import_Export::get_instance();
		$cie->init();

		$this->assertFalse( has_action( 'network_admin_menu', array( $cie, 'admin_menu' ) ) );
	}

	public function test_construct_import_privileg() {
		$user = $this->factory->user->create_and_get();
		$user->add_cap( 'import' );
		wp_set_current_user( $user->ID );
		$this->assertTrue( user_can( $user, 'import' ) );

		$cie = CSV_Import_Export::get_instance();
		$cie->init();

		$this->assertNotFalse( has_action( 'network_admin_menu', array( $cie, 'admin_menu' ) ) );
	}

	public function test_construct_export_privileg() {
		$user = $this->factory->user->create_and_get();
		$user->add_cap( 'export' );
		wp_set_current_user( $user->ID );

		$cie = CSV_Import_Export::get_instance();
		$cie->init();

		$this->assertNotFalse( has_action( 'network_admin_menu', array( $cie, 'admin_menu' ) ) );
	}

	public function test_construct_export_export_privileg() {
		$user = $this->factory->user->create_and_get();
		$user->add_cap( 'export' );
		$user->add_cap( 'import' );
		wp_set_current_user( $user->ID );

		$cie = CSV_Import_Export::get_instance();
		$cie->init();

		$this->assertNotFalse( has_action( 'network_admin_menu', array( $cie, 'admin_menu' ) ) );
	}
}