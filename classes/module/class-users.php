<?php

/**
 * Handles import and export
 */
class CIE_Module_Users extends CIE_Module_Abstract {

	/**
	 * @var CIE_Module_Users_Exporter
	 */
	protected $exporter;

	/**
	 * @var CIE_Importer
	 */
	protected $importer;

	public function register_menus() {
		add_users_page(
			__( 'Import CSV', 'cie' ),
			__( 'Import CSV', 'cie' ),
			$this->get_import_capability(),
			'import',
			array( $this, 'display_user_import_page' )
		);

		add_users_page(
			__( 'Export CSV', 'cie' ),
			__( 'Export CSV', 'cie' ),
			$this->get_export_capability(),
			'export',
			array( $this, 'display_user_export_page' )
		);
	}

	public function get_export_capability() {
		return is_multisite() ? 'manage_network_users' : 'export';
	}

	public function add_meta_boxes( $post_type, WP_Post $post ) {
		// Only useful for posts that have comments
		if ( empty( $post->comment_count ) ) {
			return;
		}

		add_meta_box(
			'export_comments',
			__( 'Export comments', 'cie' ),
			array( $this, 'display_metabox' )
		);
	}

	public function display_user_export_page() {
		$fields = $this->get_exporter()->get_available_fields( array() );

		printf(
			'<div class="wrap"><h2>%s</h2>%s</div>',
			esc_html( get_admin_page_title() ),
			$this->render_export_ui(
				$fields,
				array(
					'ajax-action' => 'export_users',
				)
			)
		);
	}

	public function display_user_import_page() {
		echo $this->render_import_ui( 'import_users' );
	}

	/**
	 * @return CIE_Module_Users_Exporter
	 */
	public function get_exporter() {
		if ( ! $this->exporter instanceof CIE_Module_Users_Exporter ) {
			$this->exporter = new CIE_Module_Users_Exporter();
		}

		return $this->exporter;
	}

	/**
	 * @return CIE_Importer
	 */
	public function get_importer() {
		if ( ! $this->exporter instanceof CIE_Importer ) {
			if ( is_multisite() && ! $this->is_network_admin() ) {
				$this->importer = new CIE_Module_Users_Adder();
			} else {
				$this->importer = new CIE_Module_Users_Creator();
			}
		}

		return $this->importer;
	}
}
