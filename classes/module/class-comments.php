<?php

/**
 * Handles comments import and export
 */
class CIE_Module_Comments extends CIE_Module_Abstract {

	/**
	 * @var CIE_Module_Comments_Exporter
	 */
	protected $exporter;
	/**
	 * @var CIE_Module_Comments_Importer
	 */
	protected $importer;

	public function register_menus() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 10, 2 );
		add_comments_page(
			__( 'Import CSV', 'cie' ),
			__( 'Import CSV', 'cie' ),
			$this->get_import_capability(),
			'import-comments',
			array( $this, 'display_import_ui' )
		);

		add_comments_page(
			__( 'Export CSV', 'cie' ),
			__( 'Export CSV', 'cie' ),
			$this->get_export_capability(),
			'export-comments',
			array( $this, 'display_export_ui' )
		);
	}

	/**
	 * Adds comment export to every post
	 *
	 * @param         $post_type
	 * @param WP_Post $post
	 */
	public function add_meta_boxes( $post_type, $post ) {
		if ( ! post_type_supports( $post_type, 'comments' ) ) {
			return;
		}

		// Only for posts
		if ( ! $post instanceof WP_Post ) {
			return;
		}

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

	public function display_metabox( WP_Post $post ) {
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

	public function display_import_ui() {
		echo $this->render_import_ui( 'import_comments' );
	}

	public function display_export_ui() {
		$fields = $this->get_exporter()->get_available_fields();

		echo $this->render_export_ui(
			$fields,
			array(
				'ajax-action' => 'export_comments',
			),
			$this->get_exporter()->get_available_searches()
		);
	}

	/**
	 * @return CIE_Module_Comments_Exporter
	 */
	public function get_exporter() {
		if ( ! $this->exporter instanceof CIE_Module_Comments_Exporter ) {
			$this->exporter = new CIE_Module_Comments_Exporter();
		}

		return $this->exporter;
	}

	/**
	 * @return CIE_Importer
	 */
	public function get_importer() {
		if ( ! $this->exporter instanceof CIE_Importer ) {
			$this->importer = new CIE_Module_Comments_Importer();
		}

		return $this->importer;
	}
}
