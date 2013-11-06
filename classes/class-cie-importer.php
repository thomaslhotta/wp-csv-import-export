<?php
/**
 * Imports CSV files
 * 
 * @author Thomas Lhotta
 */
class CIE_Importer
{
	/**
	 * @var SplPriorityQueue
	 */
	protected $handlers;
	
	/**
	 * Stores the import execution time.
	 * 
	 * @var number
	 */
	protected $execution_time = null;
	
	protected $stopped_at = 0;
	
	/**
	 * Imports a CSV handle. 
	 * 
	 * @param $handle
	 * @return array An array of import errors.
	 */
	public function import( $file , $start_at = 0 )
	{
		if ( !file_exists( $file ) ) {
			throw new Exception( 'File "' . htmlspecialchars( $file ) . '" does not exist!' );
		}
		
	    $handle = fopen( $file , 'r' );
		
		$count = 0;
		
		$errors = array();
		
		$max_ex_time = intval( ini_get('max_execution_time') ) - 15; 
		$interrupted = false;
		
		$start = microtime( true );
		
		
		//memprof_enable();
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
			
			if ( $start_at > 0 && 1 === $count ) {
				fseek( $handle, $start_at );
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
	 * Process one row.
	 * 
	 * @param array $row
	 * @param integer $number
	 */
	public function handle_row( array $row, $number ) 
	{
		$row = new ArrayObject( $row );
		
		foreach ( clone $this->get_handlers() as $handler ) {
			// Stop loop if handler returns true.
			if ( $handler( $row, $number ) ) {
				break;
			}
		}
	}
	
	/**
	 * Get hander queue.
	 * 
	 * @return SplPriorityQueue
	 */
	public function get_handlers()
	{
		if ( !$this->handlers instanceof SplPriorityQueue ) {
			$this->handlers = new SplPriorityQueue();
		}
		
		return $this->handlers;
	}
	
	/**
	 * Returns the execution time for performed imports.
	 * 
	 * @param number $round
	 * @return number
	 */
	public function get_exexution_time( $round = 2 )
	{
		if ( !$round ) {
			return $this->execution_time;
		}
		
		return round( $this->execution_time, $round );
	}
	
	public function get_stopped_at()
	{
		return $this->stopped_at;
	}
}