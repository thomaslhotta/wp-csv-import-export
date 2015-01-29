<?php
/**
 * Handles posts import and export
 *
 * @todo Implement importer
 */
class CIE_Module_Posts extends CIE_Module_Abstract
{
	protected $exporter;

	public function register_menus()
	{
		// Import/Export Posts
		foreach ( get_post_types() as $post_type ) {
			$slugs[] = add_submenu_page(
				'edit.php?post_type=' . $post_type,
				__( 'Import CSV', 'cie' ),
				__( 'Import CSV', 'cie' ),
				'activate_plugins',
				'import-' . $post_type . '',
				array( $this, 'display_post_import_page' )
			);

			$slugs[] = add_submenu_page(
				'edit.php?post_type=' . $post_type,
				__( 'Export CSV', 'cie' ),
				__( 'Export CSV', 'cie' ),
				'activate_plugins',
				'export-' . $post_type . '',
				array( $this, 'display_post_export_page' )
			);
		}

		// Extra handling for media pages
		add_media_page(
			__( 'Export CSV', 'cie' ),
			__( 'Export CSV', 'cie' ),
			'activate_plugins',
			'export-' . $post_type . '',
			array( $this, 'display_post_export_page' )
		);

		do_action( 'cie_register_post_menus', $this );
	}

	public function register_ajax()
	{
		add_action( 'wp_ajax_export_posts', array( $this, 'process_export' ) );
	}

	public function display_post_export_page( $searches = array() )
	{
		if ( ! is_array( $searches ) ) {
			$searches = array();
		}

		$post_type = 'attachment';
		if ( ! empty( $_GET['post_type'] ) )  {
			$post_type = $_GET['post_type'];
		}

		$fields = $this->get_exporter()->get_available_fields(
			array( 'post_type' => $post_type )
		);

		$hidden = array(
			'search[post_type]' => $post_type,
			'ajax-action'       => 'export_posts',
		);

		foreach ( $searches as $name => $value ) {
			$hidden[ 'search[' . $name . ']' ] = $value;
		}

		echo $this->render_export_ui(
			$fields,
			$hidden
		);
	}

	/**
	 * @return CIE_Module_Posts_Exporter
	 */
	public function get_exporter()
	{
		if ( ! $this->exporter instanceof CIE_Module_Posts_Exporter ) {
			$this->exporter = new CIE_Module_Posts_Exporter();
		}

		return $this->exporter;
	}

	public function process_export()
	{
		$this->get_exporter()->process_ajax();
		die();
	}
}