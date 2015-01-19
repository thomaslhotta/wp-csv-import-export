<?php

/**
 * Abstract handler class
 * 
 * @package   WP_CSV_User_Import
 * @author    Thomas Lhotta
 * @license   GPL-2.0+
 * @copyright 2013 Thomas Lhotta
 */
abstract class CIE_Handler
{
	/**
	 * Perform operations in row.
	 * 
	 * If true is returned the execution of other handlers is stoped for the current row.
	 * 
	 * @param ArrayObject $row
	 * @param boolean $number
	 */
	abstract public function __invoke( ArrayObject $row, $number );

	/**
	 * Called when all rows have been processed.
	 */
	public function end() {}

	/**
	 * Should return any data that is required to resume the importing process.
	 * 
	 * @return multitype
	 */
	public function get_resume_data()
	{
		return null;
	}

	public function set_resume_date( $data ) {}

	/**
	 * Throws a handler exception.
	 * 
	 * @param string $text Message text
	 * @throws CIE_Hander_Exception
	 */
	protected function throw_exception( $text )
	{
		require_once  dirname( __FILE__ ) . '/class-cie-handler-exception.php';
		throw new CIE_Hander_Exception( htmlspecialchars( strip_tags( $text ) ) );
	}

	protected function get_field_type_object( $field_type )
	{
		if ( empty( $this->field_type_objects[ $field_type ] ) ) {
			$class = 'CIE_Field_' . ucfirst( $field_type );
			if ( ! class_exists( $class ) ) {
				throw new Exception(
					'No class found for field type ' . strip_tags( $class )
				);
			}

			$this->field_type_objects[ $field_type ] = $class;
		}

		return new $this->field_type_objects[ $field_type ];
	}
}