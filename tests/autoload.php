<?php
// Set timezone to prevent warnings
date_default_timezone_set( 'Europe/Berlin' );

$base_dir = realpath( __DIR__ . '/../' );
require_once $base_dir . '/vendor/autoload.php';
require_once $base_dir . '/classes/class-autoloader.php';

define( 'BP_TESTS_DIR', $base_dir . '/vendor/buddypress-test-suite/tests/phpunit' );

$wp_tests_dir = $base_dir . '/vendor/wordpress-developer/tests/phpunit/';
//define( 'WP_TESTS_DIR', $base_dir . '/vendor/wordpress-developer/tests/phpunit/' );
define( 'BASE_DIR', $base_dir );
putenv( 'WP_TESTS_DIR=' . $wp_tests_dir );

require_once $wp_tests_dir . 'includes/functions.php';


function _manually_load_plugin() {
	//require_once BASE_DIR . '/vendor/buddypress-test-suite/tests/phpunit/includes/loader.php';
	//require_once BASE_DIR . '/vendor/buddypress-test-suite/tests/phpunit/includes/loader.php';
	//require_once BASE_DIR . '/vendor/buddypress-test-suite/tests/phpunit/includes/testcase.php';
	require_once BASE_DIR . '/wp-csv-import-export.php';

	$autoloader = new CIE_Autoloader();
	$autoloader->register();
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

require_once BASE_DIR . '/vendor/buddypress-test-suite/tests/phpunit/bootstrap.php';
error_reporting( E_STRICT );