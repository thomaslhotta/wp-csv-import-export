<?php
// Set timezone to prevent warnings
date_default_timezone_set( 'Europe/Berlin' );

$base_dir = realpath( __DIR__ . '/../' );
require_once $base_dir . '/classes/class-autoloader.php';

//define( 'WP_TESTS_DIR', $base_dir . '/vendor/wordpress-developer/tests/phpunit/' );
define( 'BASE_DIR', $base_dir );
putenv( 'WP_TESTS_DIR=' . WP_TEST_DIR );

require_once WP_TEST_DIR . '/includes/functions.php';


function _manually_load_plugin() {
	//require_once BP_TESTS_DIR . '/includes/loader.php';

	//require_once BASE_DIR . '/vendor/buddypress-test-suite/tests/phpunit/includes/loader.php';
	////require_once BASE_DIR . '/vendor/buddypress-test-suite/tests/phpunit/includes/loader.php';
	//require_once BASE_DIR . '/includes/testcase.php';
	require_once BASE_DIR . '/wp-csv-import-export.php';
	require_once BASE_DIR . '/classes/class-csv-import-export.php' ;

	$autoloader = new CIE_Autoloader();
	$autoloader->register();
}
tests_add_filter( 'plugins_loaded', '_manually_load_plugin' );

//require WP_TEST_DIR . '/includes/bootstrap.php';
require BP_TESTS_DIR . '/bootstrap.php';
