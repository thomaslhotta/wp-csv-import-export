<?php
/**
 * 
 * @package   WP_CSV_User_Import
 * @author    Thomas Lhotta
 * @license   GPL-2.0+
 * @link      http://example.com
 * @copyright 2013 Thomas Lhotta
 *
 */
class CIE_WPDB_Wrapper
{
	/**
	 * @var wpdb
	 */
	protected $wpdb;
	
	/**
	 * An array of sql parts that should be warpped.
	 * 
	 * @var array
	 */
	protected $allowed = array();
	
	/**
	 * Substitution values for allowe SQL parts.
	 * 
	 * @var array
	 */
	protected $substitutes = array();
	
	/**
	 * Select result cache.
	 * 
	 * @var array
	 */
	protected $select_cache = array();
	
	/**
	 * Queued SQL queries
	 * 
	 * @var array
	 */
	protected $queue = array();
	
	/**
	 * The length after which the queue is flushed to the database.
	 * 
	 * @var integer
	 */
	protected $queue_length = 200;
	
	/**
	 * Create the database wrapper.
	 * 
	 * @param wpdb $wpdb
	 */
	public function __construct( wpdb $wpdb )
	{
		$this->wpdb = $wpdb;
	}

	/**
	 * Replaces the Wordpress database object with the wrapped one.
	 * 
	 * @throws Exception
	 */
	public function replace_wpdb()
	{
		global $wpdb;
		
		if ( $wpdb instanceof self ) {
			return;
		}
		
		if ( $wpdb !== $this->wpdb ) {
			throw new Exception( '$wpdb is not in the expected state.' );
		}
		
		$wpdb = $this;
	}

	/**
	 * Restores the original Wordpress database object.
	 * 
	 */
	public function restore_wpdb()
	{
		global $wpdb;
		
		if ( $wpdb !== $this ) {
			return;
		}
		
		$wpdb = $this->wpdb;
	}

	/**
	 * Wrap queries
	 * 
	 * @param strubg $query
	 * @return number
	 */
	public function query( $query )
	{
		$match = null;
		
		foreach ( $this->allowed as $part ) {
			if ( 0 === strpos( $query, $part ) ) {
				$match = crc32( $part );
				break;
			}
		}
	
		if ( !$match ) {
			return $this->wpdb->query( $query );
		}
	
		if ( array_key_exists( $match, $this->substitutes ) ) {
			$query = str_replace( array( 'INSERT', 'UPDATE' ) , $this->substitutes[$match], $query );
		}
		
		if ( in_array( trim( substr( $query, 0, 7 ) ), array( 'INSERT', 'REPLACE' ) ) ) {
			$this->enqueue( $query , $part );
			return 1;		
		}
		
		return $this->wpdb->query( $query );
	}
	
	/**
	 * Wrap get row requests.
	 * 
	 * @param string $query
	 * @param string $output
	 * @param number $y
	 * @return multitype:|unknown
	 */
	public function get_row( $query = null, $output = OBJECT, $y = 0 )
	{
		$match = null;
		
		foreach ( $this->allowed as $part ) {
			if ( 0 === strpos( $query, $part ) ) {
				$match = crc32( $part );
				break;
			}
		}
		
		if ( !$match ) {
			return $this->wpdb->get_row( $query, $output, $y );
		}

		
		if ( array_key_exists( $match, $this->substitutes ) ) {
			return $this->substitutes[$match];
		}
		
		$hash = crc32( $query );
		
		if ( isset( $this->select_cache[$hash] ) && isset( $this->select_cache[$hash][$y] ) ) {
			return $this->select_cache[$hash][$y];
		}
		
		$result = $this->wpdb->get_row( $query, $output, $y );
		
		$this->select_cache[$hash][$y] = $result;
		return $result;
	}
	
	public function get_col( $query, $y = 0 )
	{
		$match = null;
		
		foreach ( $this->allowed as $part ) {
			if ( 0 === strpos( $query, $part ) ) {
				$match = crc32( $part );
				break;
			}
		}
		
		if ( !$match ) {
			return $this->wpdb->get_col( $query, $y );
		}
		
		
		if ( array_key_exists( $match, $this->substitutes ) ) {
			return $this->substitutes[$match];
		}
		
		$hash = crc32( $query );
		
		if ( isset( $this->select_cache[$hash] ) && isset( $this->select_cache[$hash][$y] ) ) {
			return $this->select_cache[$hash][$y];
		}
		
		$result = $this->wpdb->get_col( $query, $y );
		
		$this->select_cache[$hash][$y] = $result;
		return $result;
	}
	
	
	function get_var( $query = null, $x = 0, $y = 0 ) {
		$match = null;
		
		foreach ( $this->allowed as $part ) {
			if ( 0 === strpos( $query, $part ) ) {
				$match = crc32( $part );
				break;
			}
		}
		
		if ( !$match ) {
			return $this->wpdb->get_var( $query, $x, $y );
		}
		
		if ( array_key_exists( $match, $this->substitutes ) ) {
			return $this->substitutes[$match];
		}
		
		$hash = crc32( $query );
		
		if ( isset( $this->select_cache[$hash] ) && isset( $this->select_cache[$hash][$x] ) && isset( $this->select_cache[$hash][$x][$y] ) ) {
			return $this->select_cache[$hash][$x][$y];
		}
		
		$result = $this->wpdb->get_var( $query, $x, $y );
		
		$this->select_cache[$hash][$x][$y] = $result;
		return $result;
	}

	/**
	 * 
	 * @param string $query_part
	 * @return CIE_WPDB_Wrapper
	 */
	public function add_allowed( $query_part, $substitute = false, $value = null )
	{
		$this->allowed[] = $query_part;
		
		if ( $substitute ) {
			$this->substitutes[crc32( $query_part )] = $value;
		}
		
		return $this;
	}
	
	/**
	 * Enqueue an INSERT, REPLACE query.
	 * 
	 * @param unknown $query
	 * @param unknown $part
	 */
	public function enqueue( $query, $part )
	{
		$value_pos = strpos( $query, 'VALUES' );
		
		if ( !$value_pos ) {
			return;
		}
		
		$first_br = strpos( $query , '(', $value_pos );

		$stripped = trim( substr( $query, 0, $value_pos ) );
		$values = rtrim( trim( substr( $query, $first_br ) ), ';' );
		
		$this->queue[$stripped][] = $values;
		
		if ( count( $this->queue[$stripped] ) >= $this->queue_length ) {
			$this->flush_queue( $stripped );
		} 
	}
	
	/**
	 * Flush enqueued entries.
	 * 
	 * @param string $part
	 */
	public function flush_queue( $part = null )
	{
		if ( $part ) {
			if ( !isset( $this->queue[$part] ) ) {
				return;
			}
			
			$flush = array(
				$part => $this->queue[$part],
			);

			unset( $this->queue[$part] );
		} else {
			$flush = $this->queue;
			$this->queue = array();
		}
		foreach ( $flush as $sql => $values ) {
			$sql .= ' VALUES ' . implode( ',', $values ) . ';';
			$this->wpdb->query( $sql );
		}
	}
	
	/*
	 * Proxy all class for original Wordpress database object.
	 */
	
	public function __call( $method, $args )
	{
		return call_user_func_array( array( $this->wpdb, $method ) , $args );
	}
	
	public function __set( $name, $value )
	{
		$this->wpdb->$name = $value;
	} 
	
	public function __get( $name )
	{
		return $this->wpdb->$name;
	}
	
	
}