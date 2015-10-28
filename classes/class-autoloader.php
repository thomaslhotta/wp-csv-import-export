<?php

/**
 * Auto loader class
 */
class CIE_Autoloader {
	/**
	 * Creates and registers the auto loader
	 */
	public function register() {
		spl_autoload_register( array( $this, 'autoload' ) );
	}

	/**
	 * Auto loads classes
	 *
	 * @param $class
	 *
	 * @return bool
	 */
	public function autoload( $class ) {
		if ( 0 !== strpos( $class, 'CIE' ) ) {
			return false;
		}

		$class = strtolower( str_replace( 'CIE_', '', $class ) );

		$parts = explode( '_', $class );

		$path = __DIR__ . DIRECTORY_SEPARATOR;

		$file_name = 'class-' . array_pop( $parts ) . '.php';

		if ( ! empty( $parts ) ) {
			$path .= implode( DIRECTORY_SEPARATOR, $parts ) . DIRECTORY_SEPARATOR;
		}

		$path .= $file_name;

		if ( ! file_exists( $path ) ) {
			return false;
		}

		require_once $path;

		return true;
	}
}