<?php

require_once dirname( __FILE__ ) . '/class-cie-csv-processor-abstract.php';

/**
 * Import processor class
 *
 * @package   WP_CSV_User_Import
 * @author    Thomas Lhotta
 * @license   GPL-2.0+
 * @link      https://github.com/thomaslhotta/wp-csv-import-export/
 * @copyright 2013 Thomas Lhotta
 */
class CIE_Importer extends CIE_CSV_Processor_Abstract
{
	/**
	 * The stream position the import stopped at
	 * 
	 * @var integer
	 */
	protected $stopped_at = 0;
	
	/**
	 * Imports a CSV handle. 
	 * 
	 * @param $handle
	 * @return array An array of import errors.
	 */
	public function import( $file)
	{
		if ( !is_resource( $file ) ) {
			if ( !file_exists( $file ) ) {
				throw new Exception(
					'File "' . htmlspecialchars( $file ) . '" does not exist!' 
				);
			}
		
	    	$handle = fopen( $file , 'r' );
		} else {
			$handle = $file; 
		}
	    	
	    	
		$count = 0;
		
		$errors = array();
		$max_ex_time = intval( ini_get( 'max_execution_time' ) ) - 6; 
		$interrupted = false;
		
		$start = microtime( true );
		
		while ( false !== ($data = fgetcsv( $handle ) ) ) {
			$count ++;
			
			try {
				$this->handle_row( $data, $count );
			} catch (CIE_Hander_Exception $e) {
				if ( $count < 3 ) {
					$errors[$count] = $e->getMessage();
					break;
				} else {
					$errors[$count] = $e->getMessage();
				}
			}
			
			if ( ( microtime( true ) - $start ) > $max_ex_time ) {
				$this->stopped_at = ftell( $handle );
				break;
			}
			
			if ( $this->stopped_at > 0 && 1 === $count ) {
				fseek( $handle, $this->stopped_at );
			}
			
		}

		// Call end functions
		foreach ( clone $this->get_handlers() as $handler ) {
			$handler->end();
		}
		
		$this->execution_time = microtime( true ) - $start;
		fclose( $handle );
		return $errors;
	}
	
	/**
	 * Returns the stream position the import stopped at.
	 * 
	 * @return number
	 */
	public function get_stopped_at()
	{
		return $this->stopped_at;
	}
	
	/**
	 * Set the stream position the import stopped at.
	 * 
	 * @param unknown $offset
	 */
	public function set_stopped_at( $offset )
	{
		$this->stopped_at = $offset;		
	}
	
	public function get_resume_data()
	{
		$handlers = clone $this->get_handlers();
		
		$return = array();
		
		foreach ( $handlers as $id => $handler ) {
			$return[$id] = $handler->get_resume_data();
		}
		
		return $return;
	}
	
	public function set_resume_data( array $data )
	{
		$handlers = clone $this->get_handlers();
		
		foreach ( $handlers as $id => $handler ) {
			$handler->set_resume_date( $data[$id] );
		}
		
		return $this;
	}
}