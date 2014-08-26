<?php
/**
 * Abstract processor class
 * 
 * @author Thomas Lhotta
 *
 */
class CIE_CSV_Processor_Abstract
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

	/**
	 * Process one row.
	 *
	 * @param array $row
	 * @param integer $number
	 */
	public function handle_row( $row, $number )
	{
		if ( ! $row instanceof ArrayObject ) {
			$row = new ArrayObject( $row );
		}

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
		if ( ! $this->handlers instanceof SplPriorityQueue ) {
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
		if ( ! $round ) {
			return $this->execution_time;
		}

		return round( $this->execution_time, $round );
	}
}