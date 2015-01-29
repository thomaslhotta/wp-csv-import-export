<?php
/**
 * Handles comments import and export
 */
class CIE_Module_Comments extends CIE_Module_Abstract
{
	protected $exporter;

	public function register_menus()
	{
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 10, 2 );
	}

	public function register_ajax()
	{
		add_action( 'wp_ajax_export_comments', array( $this, 'process_ajax' ) );
	}

	/**
	 * Adds comment export to every post
	 *
	 * @param         $post_type
	 * @param WP_Post $post
	 */
	public function add_meta_boxes( $post_type, WP_Post $post )
	{
		// Only useful for posts that have comments
		if  ( empty( $post->comment_count ) ) {
			return;
		}

		add_meta_box(
			'export_comments',
			__( 'Export comments', 'cie' ),
			array( $this, 'display_metabox' )
		);
	}

	public function display_metabox( WP_Post $post )
	{
		$fields = $this->get_exporter()->get_available_fields( array( 'post_id' => $post->ID ) );

		echo $this->render_export_ui(
			$fields,
			array(
				'search[post_id]' => $post->ID,
				'ajax-action'     => 'export_comments'
			)
		);
	}

	/**
	 * @return CIE_Module_Comments_Exporter
	 */
	public function get_exporter()
	{
		if ( ! $this->exporter instanceof CIE_Module_Comments_Exporter ) {
			$this->exporter = new CIE_Module_Comments_Exporter();
		}

		return $this->exporter;
	}

	public function process_ajax()
	{
		$this->get_exporter()->process_ajax();
		die();
	}
}