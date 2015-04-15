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
		add_comments_page(
			__( 'Import CSV', 'cie' ),
			__( 'Import CSV', 'cie' ),
			'activate_plugins',
			'export-comments',
			array( $this, 'display_import_ui' )
		);
	}

	public function register_ajax()
	{
		add_action( 'wp_ajax_export_comments', array( $this, 'process_ajax' ) );
		add_action( 'wp_ajax_import_comments', array( $this, 'process_import' ) );
	}

	/**
	 * Adds comment export to every post
	 *
	 * @param         $post_type
	 * @param WP_Post $post
	 */
	public function add_meta_boxes( $post_type, $post )
	{
		// Only for posts
		if ( ! $post instanceof WP_Post ) {
			return;
		}

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
		$search = array(
			'post' => array(
				'post_id' => $post->ID,
			),
		);

		$fields = $this->get_exporter()->get_available_fields( $search );


		echo $this->render_export_ui(
			$fields,
			array(
				'search'      => $search,
				'ajax-action' => 'export_comments',
			),
			$this->get_exporter()->get_available_searches( $search )
		);
	}

	public function display_import_ui()
	{
		echo $this->render_import_ui( 'import_comments' );
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

	/**
	 * @return CIE_Importer
	 */
	public function get_importer()
	{
		if ( ! $this->exporter instanceof CIE_Importer ) {
			$this->importer = new CIE_Module_Comments_Importer();
		}

		return $this->importer;
	}

	public function process_import()
	{
		$this->get_importer()->import_json();
	}
}