<?php

/**
 * Main class for CSV Import Export
 *
 * @package   WP_CSV_User_Import
 * @author    Thomas Lhotta
 * @license   GPL-2.0+
 * @link      https://github.com/thomaslhotta/wp-csv-import-export/
 * @copyright 2019 Thomas Lhotta
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

	/**
	 * @var array
	 */
	protected $modules = array();

	/**
	 * Initialize the plugin by loading admin scripts & styles and adding a
	 * settings page and menu.
	 */
	private function __construct() {
		$this->init();
	}

	public function init() {

		// Check user rights
		if ( ! current_user_can( 'export' ) && ! current_user_can( 'import' ) ) {
			return;
		}

		// Init auto loader
		require_once __DIR__ . DIRECTORY_SEPARATOR . 'class-autoloader.php';
		$autoloader = new CIE_Autoloader();
		$autoloader->register();

		add_action( 'network_admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );

		add_action( 'init', array( $this, 'load_plugin_textdomain' ) );

		// Register AJAX actions
		foreach ( $this->get_modules() as $module ) {
			// @var CIE_Module_Abstract $module
			$module->register_ajax();
		}
	}

	/**
	 * Return an instance of this class.
	 *
	 * @since     1.0.0
	 *
	 * @return    $this    A single instance of this class.
	 */
	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @todo Improve conditional loading of text domain
	 *
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain(
			'cie',
			true,
			basename( dirname( __DIR__ ) ) . '/lang/'
		);
	}

	/**
	 * Adds admin menus
	 */
	public function admin_menu() {
		foreach ( $this->get_modules() as $module ) {
			// @var CIE_Module_Abstract $module
			$module->register_menus();
		}
	}

	/**
	 * Register and enqueue admin-specific script.
	 *
	 * @return null Return early if no settings page is registered.
	 */
	public function enqueue_admin_scripts() {
		$script_url = basename( dirname( dirname( __FILE__ ) ) );

		wp_register_script(
			'backbone-localstorage',
			plugins_url( $script_url . '/js/backbone.localStorage.min.js' ),
			array( 'backbone' )
		);

		wp_register_script(
			'backbone-paginator',
			plugins_url( $script_url . '/js/backbone.paginator.min.js' ),
			array( 'backbone' )
		);

		wp_register_script(
			'cie-admin-script',
			plugins_url( $script_url . '/js/main.js' ),
			array( 'backbone-localstorage', 'backbone-paginator' ),
			'1.1',
			true
		);

		wp_localize_script(
			'cie-admin-script',
			'cieAdminLocales',
			array(
				'renameFiles'       => __( 'Should downloaded attachments be renamed?', 'cie' ),
				'renameDescription' => __( 'Enter the file name format. The following column names separated by "-" may be used', 'cie' ),
				'example'           => __( 'Example', 'cie' ),
			)
		);
	}

	/**
	 * Returns module objects
	 *
	 * @return array
	 */
	public function get_modules() {
		if ( empty( $this->modules ) ) {
			$this->modules = array(
				'comments' => new CIE_Module_Comments(),
				'users'    => new CIE_Module_Users(),
				'posts'    => new CIE_Module_Posts(),
				'gforms'   => new CIE_Module_Gforms(),
			);
		}

		return $this->modules;
	}
}
