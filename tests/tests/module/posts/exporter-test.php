<?php

/**
 * Date: 16.01.15
 * Time: 09:51
 */
class Module_Posts_Exporter_Test extends BP_UnitTestCase {
	/**
	 * @var CIE_Module_Posts_Exporter
	 */
	protected $exporter;

	protected $user_1;
	protected $user_2;
	protected $user_3;
	protected $user_4;

	protected $posts = array();

	public function setUp() {
		parent::setUp();
		$this->exporter = new CIE_Module_Posts_Exporter();

		$this->user_1 = $this->factory->user->create_and_get();
		xprofile_set_field_data( 'Name', $this->user_1->ID, 'BP User 1' );
		update_user_meta( $this->user_1->ID, 'user_meta_1', 'User meta 1' );

		$this->user_2 = $this->factory->user->create_and_get();
		xprofile_set_field_data( 'Name', $this->user_2->ID, 'BP User 2' );
		update_user_meta( $this->user_2->ID, 'user_meta_2', 'User meta 2' );

		$this->user_3 = $this->factory->user->create_and_get();
		xprofile_set_field_data( 'Name', $this->user_3->ID, 'BP User 3' );
		update_user_meta( $this->user_3->ID, 'user_meta_3', 'User meta 3' );

		$this->user_4 = $this->factory->user->create_and_get();
		xprofile_set_field_data( 'Name', $this->user_4->ID, 'BP User 4' );
		update_user_meta( $this->user_4->ID, 'user_meta_4', 'User meta 4' );

		$this->posts[] = $this->factory->post->create_and_get( array(
			'post_author' => $this->user_1->ID,
			'post_type'   => 'post',
		) );
		$this->posts[] = $this->factory->post->create_and_get( array(
			'post_author' => $this->user_2->ID,
			'post_type'   => 'post',
		) );
		$this->posts[] = $this->factory->post->create_and_get( array(
			'post_author' => $this->user_3->ID,
			'post_type'   => 'post',
		) );
		$this->posts[] = $this->factory->post->create_and_get( array(
			'post_author' => $this->user_4->ID,
			'post_type'   => 'post',
		) );

		$this->posts[] = $this->factory->post->create_and_get( array(
			'post_author' => $this->user_2->ID,
			'post_type'   => 'page',
		) );
		$this->posts[] = $this->factory->post->create_and_get( array(
			'post_author' => $this->user_2->ID,
			'post_type'   => 'page',
		) );
		$this->posts[] = $this->factory->post->create_and_get( array(
			'post_author' => $this->user_2->ID,
			'post_type'   => 'page',
		) );
		$this->posts[] = $this->factory->post->create_and_get( array(
			'post_author' => $this->user_2->ID,
			'post_type'   => 'page',
		) );
	}

	public function test_get_main_elements() {
		$elements = $this->exporter->get_main_elements(
			array(
				'post' => array(
					'post_type' => 'post',
				),
			),
			0,
			100
		);

		$this->assertEquals( 4, $elements['total'] );
		$this->assertCount( 4, $elements['elements'] );

		$this->check_posts( $elements, 'post' );

		$this->assertEquals( $elements['elements'][0]->get_user_id(), $this->user_1->ID );
		$this->assertEquals( $elements['elements'][1]->get_user_id(), $this->user_2->ID );
		$this->assertEquals( $elements['elements'][2]->get_user_id(), $this->user_3->ID );
		$this->assertEquals( $elements['elements'][3]->get_user_id(), $this->user_4->ID );
	}

	public function test_get_main_elements_offset() {
		$elements = $this->exporter->get_main_elements(
			array(
				'post' => array(
					'post_type' => 'post',
				),
			),
			1,
			2
		);

		$this->assertEquals( 4, $elements['total'] );
		$this->assertCount( 2, $elements['elements'] );

		$this->check_posts( $elements, 'post' );

		$this->assertEquals( $elements['elements'][0]->get_user_id(), $this->user_2->ID );
		$this->assertEquals( $elements['elements'][1]->get_user_id(), $this->user_3->ID );
	}

	public function test_process_row() {
		$element = new CIE_Element();
		$element->set_element( $this->posts[4], $this->posts[4]->ID, $this->posts[4]->post_author );

		// Add and set a BuddyPress field
		$bp_field = $this->factory->xprofile_field->create_and_get(
			array(
				'field_group_id' => 1,
				'type'           => 'textbox',
				'name'           => 'Name',
			)
		);

		xprofile_set_field_data( $bp_field->id, $element->get_user_id(), 'Name of user' );

		$fields = array(
			'post'       => array(
				'ID'          => 'ID',
				'post_title'  => 'post_title',
				'post_author' => 'post_author',
			),
			'user'       => array(
				'ID' => 'ID',
			),
			'buddypress' => array(
				xprofile_get_field_id_from_name( 'Name' ) => 'Name',
			),
			'usermeta'   => array(
				'user_meta_2' => 'user_meta_2',
			),
		);

		$row = $this->exporter->create_row( $element, $fields );

		$this->assertCount( 6, $row );

		$this->assertEquals( $this->posts[4]->ID, $row[0] );
		$this->assertEquals( 'Name of user', $row[4] );
		$this->assertEquals( 'User meta 2', $row[5] );
	}

	public function check_posts( array $elements, $post_type ) {
		foreach ( $elements['elements'] as $element ) {
			$this->check_post( $element, $post_type );
		}
	}

	public function check_post( CIE_Element $element, $post_type ) {
		$post = $element->get_element();
		$this->assertInstanceOf( 'WP_Post', $post );
		$this->assertEquals( $post->ID, $element->get_element_id() );
		$this->assertEquals( $post->post_author, $element->get_user_id() );
	}
}