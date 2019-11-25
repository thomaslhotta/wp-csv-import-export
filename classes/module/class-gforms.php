<?php

/**
 * Handles GravityForms import
 */
class CIE_Module_Gforms extends CIE_Module_Abstract {

	/**
	 * @var CIE_Module_Gforms_Importer
	 */
	protected $importer;

	public function register_menus() {
		if ( ! current_user_can( $this->get_import_capability() ) ) {
			return;
		}

		add_filter( 'gform_export_menu', array( $this, 'gform_export_menu' ) );
		add_action( 'gform_export_page_import_entry', array( $this, 'display_import_ui' ) );
	}

	public function gform_export_menu( array $entries ) {
		$entries[11] = array(
			'name'  => 'import_entry',
			'label' => __( 'Import entries', 'cie' ),
		);

		return $entries;
	}

	public function display_import_ui() {
		if ( ! GFCommon::current_user_can_any( 'gravityforms_export_entries' ) ) {
			wp_die( 'You do not have permission to access this page ' );
		}

		// Using GForms functions to display markup
		GFExport::page_header( __( 'Import entries', 'cie' ) );

		echo $this->render_import_ui( false );

		GFExport::page_footer( __( 'Import entries', 'cie' ) );
	}

	/**
	 * @return CIE_Importer
	 */
	public function get_importer() {
		if ( ! $this->importer instanceof CIE_Importer ) {
			$this->importer = new CIE_Module_Gforms_Importer();
		}

		return $this->importer;
	}
}
