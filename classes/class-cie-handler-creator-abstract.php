<?php 
require_once  dirname( __FILE__ ) . '/class-cie-handler-abstract.php';

abstract class CIE_Handler_Creator_Abstract extends CIE_Handler_Abstract
{
	/**
	 * Current time in SQL format.
	 * 
	 * @var string
	 */
	protected $current_time;
	
	/**
	 * @var CIE_WPDB_Wrapper
	 */
	protected $db_wrapper;

	/**
	 * @var array
	 */
	protected $meta_queue = array();
	
	/**
	 * Maximum length of the user meta data insert queue before it is flushed. 
	 * 
	 * @var integer
	 */
	protected $meta_queue_length = 100;
	
	/**
	 * Cache forprefix name evaluations
     *
	 * @var array
	 */
	protected $prefixes = array();
	
	/**
	 * Number of successfully processed users.
	 * 
	 * @var integer
	 */
	protected $success_count = 0;
	
	
	/**
	 * (non-PHPdoc)
	 * @see CIE_Handler_Abstract::end()
	 */
	public function end()
	{
		$this->flush_meta_queue();
		$this->get_db_wrapper()->flush_queue();
	}
	
	/**
	 * Returns the number of successfull users processed.
	 * 
	 * @return number
	 */
	public function get_success_count()
	{
		return $this->success_count;
	}
	
	
	public function add_meta( $user, $name, $value )
	{
		// Multi value metas
		if ( $this->compare_prefix( '[]' , $name ) ) {
			add_user_meta( $user, $this->remove_prefix( '[]' , $name ), $value, false );
			return;
		}
		
		if ( $this->overwrite ) {
			add_user_meta( $user , $name, $value, true );
		} else {
			update_user_meta( $user, $name, $value );
		}
	}
	
	public function meta_update( $check, $user, $meta_key, $value, $unique )
	{
		if ( false === $unique ) {
			return $check;
		}
		
		$this->meta_queue[ /*$user . '-' . $meta_key*/ ] = array(
		    'user_id'  => $user,
			'meta_key' => $meta_key,
			'value'    => $value, 
		);
		
		if ( count( $this->meta_queue ) > $this->meta_queue_length ) {
			$this->flush_meta_queue();
		}
		
		return true;
	}
		
	public function flush_meta_queue()
	{
		if ( empty( $this->meta_queue ) ) {
			return;
		}

		global $wpdb;
		
		$table = esc_sql( _get_meta_table( 'user' ) );
		
		$user_ids = array();
		$meta_keys = array();
		
		foreach ( $this->meta_queue as $query ) {
			$user_ids[intval( $query['user_id'] )]     = true;
			$meta_keys[esc_sql( $query['meta_key'] ) ] = true;
		}
		
		$query = sprintf(
			'DELETE FROM %s WHERE user_id IN ( %s ) AND meta_key IN ( %s );',
			$table,
			implode( ',', array_keys( $user_ids ) ),
			"'" . implode( "','", array_keys( $meta_keys ) ) . "'"
		);
		
		$wpdb->query( $query );
		
		$values = array();
		
		foreach ( $this->meta_queue as $meta ) {
			$values[] = array(
				$meta['user_id'],
				$meta['meta_key'],
				$meta['value'],
			);
		}
		
		$value_string = '';
		foreach ( $values as $value ) {
			$value_string .= vsprintf( '( %d, \'%s\', \'%s\'),', $value );
		}
		
		$value_string = rtrim( $value_string, ',' );
		
		$query = 'INSERT INTO ' . $table . ' ( user_id, meta_key, meta_value ) VALUES ' . $value_string . ';';
		
		$wpdb->query( $query );
		
		$this->meta_queue = array();
	}	
	
	
	/**
	 * Returns the DB wrapper.
	 * 
	 * @return CIE_WPDB_Wrapper
	 */
	public function get_db_wrapper()
	{
		if ( !$this->db_wrapper ) {
			require_once  dirname( __FILE__ ) . '/class-cie-wpdb-wrapper.php';
			global $wpdb;
			$this->db_wrapper = new CIE_WPDB_Wrapper( $wpdb );
		}
		
		return $this->db_wrapper;
	}
	
	
	public function return_dummy_meta( $check, $object_id, $meta_key, $single )
	{
		if ( $single ) {
			return true;
		} else {
			return array();
		}
	}
	
	public function compare_prefix( $prefix, $name )
	{
		$key = $prefix . $name;
		
		if ( isset( $this->prefixes[ $key ] ) ) {
			return $this->prefixes[ $key ];
		}
		
		$prefix .= '_';
		$return = 0 === strncmp( $prefix , $name, strlen( $prefix ) );
		$this->prefixes[ $key ] = $return;
		
		return $return;
	}
	
	public function remove_prefix( $prefix, $name )
	{
		return str_replace( $prefix . '_' , '', $name );
	}
	
	public function get_current_mysql_time()
	{
		if ( !isset( $this->current_time ) ) {
			$this->current_time = current_time( 'mysql' );
		}
		
		return $this->current_time;
	}
	
	/**
	 * 
	 * @return CIE_Attachment_Processor
	 */
	public function get_attachment_processor()
	{
		if ( !$this->attachment_processor ) {
			require_once dirname( __FILE__ ) . '/class-cie-attachment-processor.php';
			$this->attachment_processor = new CIE_Attachment_Processor();
		}
		
		return $this->attachment_processor;
	}
}