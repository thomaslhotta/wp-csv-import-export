<?php
/**
 * Main class for CSV Import Export
 *
 * @package   WP_CSV_User_Import
 * @author    Thomas Lhotta
 * @license   GPL-2.0+
 * @link      https://github.com/thomaslhotta/wp-csv-import-export/
 * @copyright 2013 Thomas Lhotta
 */
class CSV_Import_Export {

	/**
	 * Instance of this class.
	 *
	 * @since    1.1.0
	 *
	 * @var      object
	 */
	protected static $instance = null;

	protected $plugin_slug = 'cie';

	protected $modules = array();

	/**
	 * Slug of the plugin screen.
	 *
	 * @since    1.0.0
	 * @var      array
	 */
	protected $plugin_screen_hook_suffix = array();

	/**
	 * Initialize the plugin by loading admin scripts & styles and adding a
	 * settings page and menu.
	 */
	private function __construct() {
		// @todo Add capabilities
		if ( ! is_admin() || ! is_super_admin() ) {
			return;
		}

		require __DIR__ . DIRECTORY_SEPARATOR . 'class-autoloader.php';
		$autoloader = new CIE_Autoloader();
		$autoloader->register();

		add_action( 'network_admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'network_admin_menu', array( $this, 'admin_menu' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		add_action( 'admin_init', array( $this, 'load_plugin_textdomain' ) );

		// Only allow ajax for super admins for now
		if ( is_super_admin() ) {
			add_action( 'wp_ajax_import_user', array( $this, 'display_user_import_page' ) );
			add_action( 'wp_ajax_import_comments', array( $this, 'display_comment_import_page' ) );
			add_action( 'wp_ajax_import_posts', array( $this, 'display_post_import_page' ) );

			//add_action( 'wp_ajax_export_users', array( $this, 'display_user_export_page' ) );
		}

		foreach ( $this->get_modules() as $module ) {
			$module->register_ajax();
		}
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

	/**
	 * Returns the plugin slug
	 * 
	 * @return string
	 */
	public function get_plugin_slug()
	{
		return $this->plugin_slug;
	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since	1.0.0
	 */
	public function load_plugin_textdomain()
	{
		if ( empty( $_GET['page'] ) || ! ( 'import' === $_GET['page'] || 'export' === $_GET['page'] )  ) {
			return;
		}

		$domain = $this->get_plugin_slug();
		load_plugin_textdomain(
			$domain,
			true,
			basename( dirname( __DIR__ ) ) . '/lang/'
		);
	}

	/**
	 * Adds admin menus
	 */
	public function admin_menu()
	{
		foreach ( $this->get_modules() as $module ) {
			$module->register_menus();
		}
	}

	/**
	 * Register and enqueue admin-specific script.
	 *
	 * @return null Return early if no settings page is registered.
	 */
	public function enqueue_admin_scripts()
	{
		$script_url = basename( dirname( dirname( __FILE__ ) ) );

		wp_register_script(
			'papaparse',
			plugins_url( $script_url . '/js/papaparse.min.js' ),
			array( 'cie-polyfill' )
		);

		wp_register_script(
			'cie-admin-script',
			plugins_url( $script_url . '/js/admin.js' ),
			array( 'jquery-ui-progressbar', 'cie-filesaver', 'papaparse' )
		);

		wp_register_script(
			'cie-filesaver',
			plugins_url( $script_url . '/js/FileSaver.js' )
		);

		wp_register_script(
			'cie-polyfill',
			plugins_url( $script_url . '/js/polyfill.min.js' )
		);

		if ( empty( $this->plugin_screen_hook_suffix ) ) {
			return;
		}

		$screen = str_replace( '-network', '', get_current_screen()->id );
		if ( in_array( $screen, $this->plugin_screen_hook_suffix ) ) {
			wp_enqueue_script( $this->plugin_slug .'-admin-script' );
			wp_enqueue_script( $this->plugin_slug .'-filesaver' );
			wp_enqueue_style(
				$this->plugin_slug .'-jquery-ui',
				'http://ajax.googleapis.com/ajax/libs/jqueryui/1.10.1/themes/base/minified/jquery-ui.min.css'
			);
		}
	}

	public function get_modules()
	{
		if ( empty( $this->modules ) ) {
			$this->modules = array(
				'comments' => new CIE_Module_Comments(),
				'users' => new CIE_Module_Users(),
				'posts' => new CIE_Module_Posts(),
			);
		}

		return $this->modules;
	}
}