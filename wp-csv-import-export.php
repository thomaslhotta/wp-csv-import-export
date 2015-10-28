<?php
/**
 * A Wordpress plugin that allows import and export data in the CSV format.
 *
 * @package   WP_CSV_User_Import
 * @author    Thomas Lhotta
 * @license   GPL-2.0+
 * @link      http://example.com
 * @copyright 2013 Thomas Lhotta
 *
 * @wordpress-plugin
 * Plugin Name:       CSV Import Export
 * Plugin URI:        https://github.com/thomaslhotta/wp-csv-import-export
 * Description:       Import and Export data as CSV
 * Version:           1.0.0
 * Author:            Thomas Lhotta
 * Author URI:        https://github.com/thomaslhotta
 * Text Domain:       cie
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:       /languages
 * GitHub Plugin URI: https://github.com/thomaslhotta/wp-csv-import-export
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// This plugin is only action in admin
if ( ! is_admin() ) {
	return;
}

require_once( plugin_dir_path( __FILE__ ) . '/classes/class-csv-import-export.php' );

add_action( 'plugins_loaded', array( 'CSV_Import_Export', 'get_instance' ) );
