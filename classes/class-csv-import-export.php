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
	 * @var      string
	 */
	protected $plugin_screen_hook_suffix = null;

	/**
	 * Initialize the plugin by loading admin scripts & styles and adding a
	 * settings page and menu.
	 */
	private function __construct() {
		if ( !is_admin() ) {
			return;
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		add_action( 'network_admin_menu', array( $this, 'admin_menu' ) );
		
		// Add the options page and menu item.

		// Add an action link pointing to the options page.
		$plugin_basename = plugin_basename( plugin_dir_path( __FILE__ ) . $this->plugin_slug . '.php' );
		//add_filter( 'plugin_action_links_' . $plugin_basename, array( $this, 'add_action_links' ) );


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
	
	/**
	 * Register and enqueue admin-specific style sheet.
	 *
	 * @since     1.0.0
	 *
	 * @return    null    Return early if no settings page is registered.
	 */
	public function enqueue_admin_styles() {

		if ( ! isset( $this->plugin_screen_hook_suffix ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( $this->plugin_screen_hook_suffix == $screen->id ) {
			wp_enqueue_style( $this->plugin_slug .'-admin-styles', plugins_url( 'css/admin.css', __FILE__ ), array(), Plugin_Name::VERSION );
		}
	}

	/**
	 * Register and enqueue admin-specific JavaScript.
	 * 
	 * TODO:
	 *
	 * - Rename "Plugin_Name" to the name your plugin
	 *
	 * @since     1.0.0
	 *
	 * @return    null    Return early if no settings page is registered.
	 */
	public function enqueue_admin_scripts() {

		if ( ! isset( $this->plugin_screen_hook_suffix ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( $this->plugin_screen_hook_suffix == $screen->id ) {
			wp_enqueue_script( $this->plugin_slug . '-admin-script', plugins_url( 'js/admin.js', __FILE__ ), array( 'jquery' ), Plugin_Name::VERSION );
		}

	}

	public function admin_menu()
	{
		add_users_page( __('Import/Export', 'cie'), __('Import/Export', 'cie'),'activate_plugins', 'import', array( $this, 'display_plugin_admin_page' ) );
	}
	
	/**
	 * Register the administration menu for this plugin into the WordPress Dashboard menu.
	 *
	 * @since    1.0.0
	 */
	public function add_user_menu() {

		$this->plugin_screen_hook_suffix = add_options_page(
			__( 'Page Title', $this->plugin_slug ),
			__( 'Menu Text', $this->plugin_slug ),
			'manage_options',
			$this->plugin_slug,
			array( $this, 'display_plugin_admin_page' )
		);

	}

	/**
	 * Render the settings page for this plugin.
	 *
	 * @since    1.0.0
	 */
	public function display_plugin_admin_page() {
		if ( isset( $_FILES['csv'] ) && check_admin_referer( 'upload-csv', 'verify' ) ) {
			define('SAVEQUERIES', true);
			extract( $this->import_users( $_FILES['csv']['tmp_name'] ) );
			global $wpdb;
			
			$time = 0;
			
			foreach ( $wpdb->queries as $q ) {
				$time += $q[1];
			}

			echo 'Time ' . $time;
			
			echo "<pre>";
			
			print_r($wpdb->queries);
			echo "</pre>";
		}
		
		include_once( dirname( dirname( __FILE__ ) ) . '/views/admin.php' );
	}

	public function import_users( $file )
	{
		$renames = $this->get_json( 'renames' );
		$transforms = $this->get_json( 'transforms' );
		
		$return = array(
			'transforms' => json_encode( $transforms ),
			'renames'    => json_encode( $renames ),
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
		$this->include_class( 'CIE_Handler_Create_User' );
		
		
		if ( isset( $_POST['stopped_at'] ) ) {
			$stopped_at = intval( $_POST['stopped_at'] );
		} else {
			$stopped_at = 0;
		}
		

		$importer = new CIE_Importer();
		
		$user_handler = new CIE_Handler_Create_User();
		
		$importer->get_handlers()->insert(new CIE_Handler_Fieldname( $renames ), 3);
		$importer->get_handlers()->insert(new CIE_Handler_Transform( $transforms ), 2);
		$importer->get_handlers()->insert($user_handler , 1);
		
		return array_merge( 
			$return,
			array(
			    'errors'         => $importer->import( $file, $stopped_at ),
				'success_count'  => $user_handler->get_success_count(),
				'execution_time' => $importer->get_exexution_time(),
				'stopped_at'     => $importer->get_stopped_at(),
				'checksum'       => md5_file( $file ),
			)
		);
	}
	
	/**
	 * Add settings action link to the plugins page.
	 *
	 * @since    1.0.0
	 */
	public function add_action_links( $links ) {

		return array_merge(
			array(
				'settings' => '<a href="' . admin_url( 'options-general.php?page=' . $this->plugin_slug ) . '">' . __( 'Settings', $this->plugin_slug ) . '</a>'
			),
			$links
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

}
