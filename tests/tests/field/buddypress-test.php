<?php
/**
 * Date: 14.01.15
 * Time: 14:21
 */
class Field_Buddypress_Test extends WP_UnitTestCase
{
	/**
	 * @var CIE_Field_Buddypress
	 */
	protected $field;

	public function setUp()
	{
		$this->field = new CIE_Field_Buddypress();
		parent::setUp();
	}

	public function test_set_field_values()
	{
		$user_id = wp_create_user( 'hanswurst', 'pw', 'hans@email.com' );
		$user = get_user_by( 'id', $user_id );

		$element = new CIE_Element();
		$element->set_element( $user, $user_id, $user_id );

		$data = array(
			'bp_Name' => 'Hans Wurst II',
		);

		$this->field->set_field_values( $data, $element );

		$this->assertEquals( 'Hans Wurst II', xprofile_get_field_data( 'Name', $user->ID ) );
	}
}