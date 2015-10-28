<?php
class Field_Buddypress_Test extends BP_UnitTestCase {
	/**
	 * @var CIE_Field_Buddypress
	 */
	protected $field;

	protected $checkBox;

	public function setUp() {
		parent::setUp();
		$this->field = new CIE_Field_Buddypress();

		$group = $this->factory->xprofile_group->create();

		$this->checkBox = $this->factory->xprofile_field->create_and_get( array(
			'field_group_id' => $group,
			'type'           => 'checkbox',
			'name'           => 'Checkbox field',
		) );

		$this->checkBox = $this->factory->xprofile_field->create_and_get( array(
			'field_group_id' => $group,
			'type'           => 'textbox',
			'name'           => 'Name',
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
}