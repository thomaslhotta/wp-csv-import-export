<?php
/**
 * Main class for CSV Import Export
 *
 * @package   WP_CSV_User_Import
 * @author    Thomas Lhotta
 * @license   GPL-2.0+
 * @link      http://example.com
 * @copyright 2013 Thomas Lhotta
 */
class CSV_Import_Export {

	/**
	 * Instance of this class.
	 *
	 * @since    1.0.0
	 *
	 * @var      object
	 */
	protected static $instance = null;
	
	protected $plugin_slug = 'cie';

	/**
	 * Slug of the plugin screen.
	 *
	 * @since    1.0.0
	 *
	 * @var      array
	 */
	protected $plugin_screen_hook_suffix = array();

	/**
	 * Initialize the plugin by loading admin scripts & styles and adding a
	 * settings page and menu.
	 */
	private function __construct() {
		if ( !is_admin() || !is_super_admin() ) {
			return;
		}

		add_action( 'network_admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		
		add_action( 'wp_ajax_import_user', array( $this, 'display_user_import_page' ) );
		add_action( 'wp_ajax_import_comments', array( $this, 'display_comment_import_page' ) );
		add_action( 'wp_ajax_import_posts', array( $this, 'display_post_import_page' ) );
	}

	/**
	 * Return an instance of this class.
	 *
	 * @since     1.0.0
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	public function get_plugin_slug()
	{
		return $this->plugin_slug;
	}
	

	public function admin_menu()
	{
		$slugs = array();
		$slugs[] = $this->plugin_screen_hook_suffix[] = add_users_page( 
			__('Import CSV', 'cie'),
			__('Import CSV', 'cie'),
			'activate_plugins',
			'import',
			array( $this, 'display_user_import_page' ) 
		);
		
		/*$slugs[] = $this->plugin_screen_hook_suffix[] = add_users_page(
			__('Export CSV', 'cie'),
			__('Export CSV', 'cie'),
			'activate_plugins',
			'export',
			array( $this, 'display_user_export_page' )
		);
		*/
		
		foreach ( get_post_types() as $post_type ) {
			$slugs[] = add_submenu_page( 
				'edit.php?post_type=' . $post_type,
				__('Import CSV', 'cie'),
				__('Import CSV', 'cie'),
				'activate_plugins',
				'import-' . $post_type . '',
				array( $this, 'display_post_import_page' )
			);
		}
		
		$slugs[] = add_comments_page( 
			__('Import CSV', 'cie'),
			__('Import CSV', 'cie'),
			'activate_plugins',
			'import-comments',
			array( $this, 'display_comment_import_page' )
		);
		
		$this->plugin_screen_hook_suffix = $slugs;
	}
	
	/**
	 * Render the settings page for this plugin.
	 *
	 * @since    1.0.0
	 */
	public function display_user_import_page() {
		$use_ajax = 'import_user';	
		
		if ( !$this->is_post() ) {
			include_once( dirname( dirname( __FILE__ ) ) . '/views/import.php' );
			return;
		} 
		
		$this->include_class( 'CIE_Handler_Create_User' );
		$user_handler = new CIE_Handler_Create_User();
		
		$this->maybe_ajax( $user_handler );
		
		
		if ( isset( $_FILES['csv'] ) && check_admin_referer( 'upload-csv', 'verify' ) ) {
			extract( $this->import( $_FILES['csv']['tmp_name'], $user_handler ) );
		}
		
		include_once( dirname( dirname( __FILE__ ) ) . '/views/import.php' );
	}
	
	/**
	 * Render the settings page for this plugin.
	 *
	 * @since    1.0.0
	 */
	public function display_user_export_page() 
	{
		$this->include_class( 'CIE_Exporter_User' );
		$this->include_class( 'CIE_Exporter' );
		
		$user = new CIE_Exporter_User( );
		
		$exporter = new CIE_Exporter( $user->get_available_fields() );
		$exporter->print_export();
		
		
		print_r($user->get_available_fields());
		die();
		
		global $wpdb;
		
		
		include_once( dirname( dirname( __FILE__ ) ) . '/views/import.php' );
	}

	/**
	 * Render the settings page for this plugin.
	 *
	 * @since    1.0.0
	 */
	public function display_post_import_page() {
		$use_ajax = 'import_posts';

		if ( !$this->is_post() ) {
			include_once( dirname( dirname( __FILE__ ) ) . '/views/import.php' );
			return;
		}
		
		if ( isset( $_POST['typenow'] ) ) {
			$typenow = $_POST['typenow'];
		} else {
			global $typenow;
		}
		
		$this->include_class( 'CIE_Handler_Create_Post' );
		$posts_handler = new CIE_Handler_Create_Post( $typenow );

		$this->maybe_ajax( $posts_handler );
		
		if ( isset( $_FILES['csv'] ) && check_admin_referer( 'upload-csv', 'verify' ) ) {
			extract( $this->import( $_FILES['csv']['tmp_name'], $posts_handler ) );
		}
		
		include_once( dirname( dirname( __FILE__ ) ) . '/views/import.php' );
	}
	
	
	/**
	 * Render the settings page for this plugin.
	 *
	 * @since    1.0.0
	 */
	public function display_comment_import_page() {
		$use_ajax = 'import_comments';
		
		if ( !$this->is_post() ) {
			include_once( dirname( dirname( __FILE__ ) ) . '/views/import.php' );
			return;
		}
		
		$this->include_class( 'CIE_Handler_Create_Comment' );
		$comments_handler = new CIE_Handler_Create_Comment( false );
		
		$this->maybe_ajax( $comments_handler );
		
		if ( isset( $_FILES['csv'] ) && check_admin_referer( 'upload-csv', 'verify' ) ) {
			extract( $this->import( $_FILES['csv']['tmp_name'] , $comments_handler ) );
		}
	
		include_once( dirname( dirname( __FILE__ ) ) . '/views/import.php' );
	}
	
	public function maybe_ajax( $handler ) 
	{
		if ( !defined('DOING_AJAX') || !DOING_AJAX) {
			return;
		}
		
		if ( !isset( $_POST['csv'] ) ) {
			wp_send_json_error(array());
		}
		
		
		$file = fopen('php://memory','r+');
		
		fwrite( $file, stripslashes( $_POST['csv'] ) );
		
		// Make sure we start at the beginning
		if ( is_resource( $file ) ) {
			rewind( $file );
		}

		$result = $this->import( $file , $handler );
		
		if ( isset( $_POST['rowOffset'] ) ) {
			$offset = intval( $_POST['rowOffset'] );

			$errors = $result['errors'];
			$new_errors = array();
			
			foreach ( $errors as $row => $message ) {
				$new_errors[$row + $offset] = $message;
			}
			
			$result['errors'] = $new_errors;
		}
		
		wp_send_json( $result );
		
		die();
	}
	
	public function import( $file, $handler )
	{
		
		$renames = $this->get_json( 'renames' );
		$transforms = $this->get_json( 'transforms' );
		$resume_data = $this->get_json( 'resume_data' );
		
		$return = array(
			'transforms'  => json_encode( $transforms ),
			'renames'     => json_encode( $renames ),
			'resume_data' => json_encode( $resume_data ),
		);
		
		if ( isset( $_POST['checksum'] ) ) {
			if ( md5_file( $file ) !==  $_POST['checksum'] ) {
				return array_merge( $return, array(
					'checksum'   => $_POST['checksum'],
					'errors'      => array( __( 'Wrong file uploaded. Please upload the correct file', $this->get_plugin_slug() ) ),
					'stopped_at' => $_POST['stopped_at'],
				));
			}
		}
		
		$this->include_class( 'CIE_Importer' );
		$this->include_class( 'CIE_Handler_Fieldname' );
		$this->include_class( 'CIE_Handler_Transform' );
		
		$importer = new CIE_Importer();
		
		if ( isset( $_POST['stopped_at'] ) ) {
			$importer->set_stopped_at( intval( $_POST['stopped_at'] ) );
		}
		
		
		$importer->get_handlers()->insert(new CIE_Handler_Fieldname( $renames ), 3);
		$importer->get_handlers()->insert(new CIE_Handler_Transform( $transforms ), 2);
		
		$importer->get_handlers()->insert( $handler , 1 );
	
		if ( is_array( $resume_data ) ) {
			$importer->set_resume_data( $resume_data );
		}
		
		
		if ( is_resource( $file ) ) {
			$return['checksum'] = md5( stream_get_contents( $file ) );
		} else {
			$return['checksum'] = md5_file( $file );
		}
		
		rewind( $file );
		
		return array_merge(
			$return,
			array(
				'errors'         => $importer->import( $file ),
				'success_count'  => $handler->get_success_count(),
				'execution_time' => $importer->get_exexution_time(),
				'stopped_at'     => $importer->get_stopped_at(),
				'resume_data'    => json_encode( $importer->get_resume_data() ),
			)
		);
	}
	
	
	protected function include_class( $class )
	{
		require_once dirname( __FILE__ ) . '/class-' . strtolower( str_replace( '_' , '-' , $class ) ) . '.php';
	} 
	
	protected function get_json( $key )
	{
		if ( !isset( $_POST[$key] ) ) {
			return null;
		}
		
		$json = stripcslashes( $_POST[$key] );
		
		return json_decode( $json, true );
	}
	
	protected function is_post()
	{
		return (isset( $_POST['csv'] ) || isset( $_FILES['csv'] ) ) ;
	}
	
	/**
	 * Register and enqueue admin-specific script.
	 *
	 *
	 * @return null Return early if no settings page is registered.
	 */
	public function enqueue_admin_scripts() {
		if ( empty( $this->plugin_screen_hook_suffix ) ) {
			return;
		}
		
		$screen = str_replace( '-network', '', get_current_screen()->id );
		if ( in_array( $screen, $this->plugin_screen_hook_suffix ) ) {
			wp_enqueue_script(
				$this->plugin_slug .'-admin-script',
				plugins_url( basename( dirname( dirname( __FILE__ ) ) ) . '/js/admin.js' ),
				array()
			);
		}
	
	}

}
