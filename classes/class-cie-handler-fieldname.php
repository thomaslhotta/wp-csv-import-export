<?php
require_once  dirname( __FILE__ ) . '/class-cie-handler-abstract.php';

/**
 * Replace numberic the column numbers with field names. 
 * 
 * @package   WP_CSV_User_Import
 * @author    Thomas Lhotta
 * @license   GPL-2.0+
 * @link      http://example.com
 * @copyright 2013 Thomas Lhotta
 *
 */
class CIE_Handler_Fieldname extends CIE_Handler_Abstract
{
	/**
	 * An array of field names.
	 * 
	 * @var array
	 */
	protected $fieldnames;
	
	/**
	 * An array of rename sets
	 * 
	 * @var array
	 */
	protected $renames = array();
	
	/**
	 * Creates a rename handler.
	 * 
	 * @param array $renames
	 */
	public function __construct( $renames = null )
	{
		if ( is_array( $renames ) ) {
			$this->renames = $renames;
		}
	} 
	
	/**
	 * @param ArrayObject $row
	 * @throws CIE_Hander_Exception
	 * @return boolean
	 */
	public function __invoke( ArrayObject $row, $number )
	{
		// Extract field names from first row
		if ( !is_array( $this->fieldnames ) ) {
			foreach ( $row  as $col => $fieldname ) {
				if ( !is_string( $fieldname ) ) {
					$this->throw_exception( 'No fieldname set for column ' . intval( $col ) . '!'  );
				}
				
				if ( in_array( $fieldname, $this->renames ) ) {
					$this->throw_exception( 'Cannot rename to field "'. $fieldname . '" as this already exists in CSV!' );
				}
				
			}
			$this->fieldnames = $row->getArrayCopy();
			return true;
		}
		
		// Rename array keys
		$iterator = $row->getIterator();
		$new = array();
		
		foreach ( $iterator as $col => $value ) {
			// Only include columns that have a title
			if ( isset( $this->fieldnames[$col] ) ) {
				
				$name = $this->fieldnames[$col];
				
				// Maybe rename field.
				if ( isset( $this->renames[$name] ) ) {
					$name = $this->renames[$name];
				}
				$new[$name] = $value;
			}
		}
		
		$row->exchangeArray( $new );
		return false;
	}
}