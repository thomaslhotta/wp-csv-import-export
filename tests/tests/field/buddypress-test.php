<?php
class Field_Buddypress_Test extends BP_UnitTestCase {
	/**
	 * @var CIE_Field_Buddypress
	 */
	protected $field;

	protected $checkBox;
	protected $name;
	protected $number;

	public function setUp() {
		parent::setUp();
		$this->field = new CIE_Field_Buddypress();

		$group = $this->factory->xprofile_group->create();

		$this->checkBox = $this->factory->xprofile_field->create_and_get( array(
			'field_group_id' => $group,
			'type'           => 'checkbox',
			'name'           => 'Checkbox field',
		) );

		$this->name = $this->factory->xprofile_field->create_and_get( array(
			'field_group_id' => $group,
			'type'           => 'textbox',
			'name'           => 'Name',
		) );

		$this->number = $this->factory->xprofile_field->create_and_get( array(
			'field_group_id' => $group,
			'type'           => 'number',
			'name'           => 'Number',
		) );

	}

	public function test_set_field_values() {
		$user_id = wp_create_user( 'hanswurst', 'pw', 'hans@email.com' );
		$user    = get_user_by( 'id', $user_id );

		$element = new CIE_Element();
		$element->set_element( $user, $user_id, $user_id );

		$data = array(
			'bp_Name'           => 'Hans Wurst II',
			'bp_Checkbox field' => 'option1;option2',
		);

		$this->field->set_field_values( $data, $element );

		$this->assertEquals( 'Hans Wurst II', xprofile_get_field_data( 'Name', $user->ID ) );
		$this->assertEquals( array( 'option1', 'option2' ), xprofile_get_field_data( 'Checkbox field', $user->ID ) );
	}

	public function test_get_field_values() {
		$user = $this->factory->user->create_and_get();

		$element = new CIE_Element();
		$element->set_element( $user, $user->ID, $user->ID );

		xprofile_set_field_data( $this->checkBox->id, $user->ID, array( 'o1', 'o2' ) );
		xprofile_set_field_data( $this->name->id, $user->ID, '012345' );
		xprofile_set_field_data( $this->number->id, $user->ID, 12345 );

		$r = $this->field->get_field_values(
			array(
				$this->checkBox->id,
				$this->name->id,
				$this->number->id,
			),
			$element
		);

		$this->assertEquals( 'o1;o2', $r[0] );
		$this->assertEquals( '012345', $r[1] );
		$this->assertEquals( '012345', $r[1] );

		$this->assertEquals( 'Hans Wurst II', xprofile_get_field_data( 'Name', $user->ID ) );
		$this->assertEquals( array( 'option1', 'option2' ), xprofile_get_field_data( 'Checkbox field', $user->ID ) );
	}
}