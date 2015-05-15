<?php
/**
 * Date: 14.01.15
 * Time: 14:21
 */
class Field_Commentmeta_Test extends WP_UnitTestCase
{
	/**
	 * @var CIE_Field_Buddypress
	 */
	protected $field;

	protected $element;

	public function setUp()
	{
		parent::setUp();
		$this->field = new CIE_Field_Commentmeta();

		$this->element = new CIE_Element();



		$comment = $this->factory->comment->create_and_get();

		$this->element->set_element( $comment, $comment->comment_ID, $comment->user_id );


	}

	public function test_set_field_values()
	{
		$fields = array(
			'meta_meta1'    => 'value1',
			'meta_meta2[1]' => 'value22',
			'meta_meta2[0]' => 'value21',
			'othervalue'    => 'shouldbeempty',
		);

		$this->field->set_field_values( $fields, $this->element );

		$this->assertEquals(
			array( 'value1' ),
			get_comment_meta( $this->element->get_element_id(), 'meta1', false )
		);

		$this->assertEquals(
			array( 'value21', 'value22' ),
			get_comment_meta( $this->element->get_element_id(), 'meta2', false )
		);
	}
}