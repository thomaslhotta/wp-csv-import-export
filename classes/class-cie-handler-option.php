<?php
require_once  dirname( __FILE__ ) . '/class-cie-handler-creator-abstract.php';

class CIE_Handler_Option extends CIE_Handler_Abstract
{
	protected $type;

	public function __construct( $type )
	{
		$this->type = $type;
	}

	/**
	 * (non-PHPdoc)
	 * @see CIE_Handler_Abstract::__invoke()
	 */
	public function __invoke( ArrayObject $row, $number )
	{
		if ( empty( $row['ID'] ) ) {
			require_once __DIR__ . '/class-cie-handler-exception.php';
			throw new CIE_Hander_Exception( 'User not found' );
		}

		$options = array();
		$prefix = 'option_';
		$len = strlen( $prefix );

		foreach ( $row as $name => $value ) {
			if ( substr( $name, 0 , $len ) === $prefix ) {
				$options[ $name ] = $value;
			}
		}

		foreach ( $options as $name => $value ) {
			$option_name = strtolower( $this->type . '_' . $name . '_' . $row['ID'] );
			$added = add_option( $option_name, $value, '' , 'no' );

			if ( ! $added ) {
				update_option( $option_name, $value );
			}
		}
	}
}