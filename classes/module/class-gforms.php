<?php
/**
 * Handles gforms import
 */
class CIE_Module_Gforms extends CIE_Module_Abstract
{
	protected $exporter;

	public function register_menus()
	{
		$that = $this;

		add_filter(
			'gform_addon_navigation',
			function($array) use( $that ) {
				$array[] = array(
					'name'       => 'import_csv',
					'label'      => __( 'Import CSV', 'cie' ),
					'callback'   => array( $that, 'display_import_ui' ),
					'permission' => 'activate_plugins',
				);
				return $array;
			}
		);

		return;
	}

	public function register_ajax()
	{
		add_action( 'wp_ajax_import_gforms', array( $this, 'process_import' ) );
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
		$fields = $this->get_exporter()->get_available_fields( array( 'post_id' => $post->ID ) );

		echo $this->render_export_ui(
			$fields,
			array(
				'search[post_id]' => $post->ID,
				'ajax-action'     => 'export_comments',
			)
		);
	}

	public function display_import_ui()
	{
		echo $this->render_import_ui( 'import_gforms' );
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
			$this->importer = new CIE_Module_Gforms_Importer();
		}

		return $this->importer;
	}

	public function process_import()
	{
		$this->get_importer()->import_json();
	}
}