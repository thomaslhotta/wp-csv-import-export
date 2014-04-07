<?php 
require_once  dirname( __FILE__ ) . '/class-cie-handler-creator-abstract.php';

/**
 * Handler that creates users
 * 
 * @author Thomas Lhotta
 *
 */
class CIE_Handler_Create_User extends CIE_Handler_Creator_Abstract
{
	/**
	 * Overwrite mode
	 * 
	 * @var true
	 */
	protected $overwrite;
	
	/**
	 * @var array 
	 */
	protected $xprofile_fields = array();

	/**
	 * @var array
	 */
	protected $xprofile_children = array();

	/**
	 * @var array
	 */
	protected $xprofile_queue = array();
	
	/**
	 * Maximum length of the user xprofile data insert queue before it is flushed. 
	 * 
	 * @var integer
	 */
	protected $xprofile_queue_length = 100;
	
	public function __construct( $overwrite = true )
	{
		$this->overwrite = $overwrite;
		
		if ( $overwrite ) {
			// Remove expensive filters
			remove_filter( 'pre_option_gmt_offset','wp_timezone_override_offset' );
			remove_all_filters( 'pre_user_display_name' );
			remove_all_filters( 'pre_user_first_name' );
			remove_all_filters( 'pre_user_last_name' );
			remove_all_filters( 'pre_user_nickname' );
			
			// Prevent registration hooks.
			remove_all_filters( 'user_register' );
			
			if ( !defined( 'WP_IMPORTING' ) ) {
				define( 'WP_IMPORTING', true );
			}
		}
		
		global $wpdb;
		
		$tables = $wpdb->tables();
		 
		// User login is checked manually
		$this->get_db_wrapper()->add_allowed( "SELECT * FROM {$tables['users']} WHERE user_login", true, null );
		
		if ( $overwrite ) {
			// We never check for existing nice names.
			$this->get_db_wrapper()->add_allowed( "SELECT ID FROM {$tables['users']} WHERE user_nicename", true, false );
		}
	
		// We never need actual user data
		$this->get_db_wrapper()->add_allowed( "SELECT * FROM {$tables['users']} WHERE ID =", true, array() );
		
		if ( !function_exists( 'buddypress' ) ) {
			return;
		}
		
		$bp = buddypress();
		
		// Prevent very expensive BuddyPress filters
		remove_filter( 'pre_user_login' , 'bp_core_strip_username_spaces' );
		
		// Metadata should not be required during bulk inserts.
		$this->get_db_wrapper()->add_allowed( "SELECT meta_value FROM {$bp->profile->table_name_meta} WHERE object_id", true,  1 );
		
		
		if ( !$overwrite ) {
			return;
		}
		
		$this->get_db_wrapper()->add_allowed( 'SELECT user_id, meta_key, meta_value FROM wp_usermeta WHERE user_id IN', true , array() );
	}
	
	/**
	 * (non-PHPdoc)
	 * @see CIE_Handler_Abstract::__invoke()
	 */
	public function __invoke( ArrayObject $row, $number )
	{
		if ( $this->overwrite ) {
			// Add special metadata import functions
			add_filter( 'add_user_metadata', array( $this, 'meta_update' ), 10, 5 );
			add_filter( 'update_user_metadata', array( $this, 'meta_update' ), 10, 5 );
			add_filter( 'get_user_metadata', array( $this, 'return_dummy_meta' ), 10, 4 );
		}
		
		$user = $this->get_wp_user( $row, $number );
		
		$metas = array();
		$xprofile = array();
		
		// Extract meta data and xprofile data from row.
		foreach ( $row as $name => $value ) {
			if ( $this->compare_prefix( 'meta' , $name ) ) {
				$metas[ $this->remove_prefix( 'meta' , $name ) ] = $value;
			} elseif ( function_exists( 'xprofile_get_field_id_from_name' ) && $this->compare_prefix( 'xprofile' , $name ) ) {
				$name = $this->remove_prefix( 'xprofile' , $name );
				$this->get_xprofile_field( $name );
				
				$prepared_value = null;
				
				// Validate and prepare xprofile data
				if ( is_array( $value ) ) {
					foreach ( $value as $key => $el ) {
						$prepared_value[$key] = $this->prepare_xprofile_field_value( $name , $el ); 
					}
				} else {
					$prepared_value = $this->prepare_xprofile_field_value( $name, $value );
				}
				$xprofile[ $name ] = $prepared_value;
			}
		}

		// Insert metadata
		foreach ( $metas as $name => $value ) {
			$this->add_meta( $user, $name, $value );
		}
		
		// Insert xprofile data
		if ( function_exists( 'xprofile_get_field_id_from_name' ) ) {
			foreach ( $xprofile as $name => $value ) {
				$this->add_xprofile( $user, $name, $value );
			}
		}
		// Clean up cache to reduce memory consumption
		wp_cache_delete( $user, 'user_meta' );
		
		$this->success_count ++;
		
		if ( $this->overwrite ) {
			remove_filter( 'add_user_metadata', array( $this, 'meta_update' ) );
			remove_filter( 'update_user_metadata', array( $this, 'meta_update' ) );
			remove_filter( 'get_user_metadata', array( $this, 'return_dummy_meta' ) );
		}
	}
	
	/**
	 * (non-PHPdoc)
	 * @see CIE_Handler_Abstract::end()
	 */
	public function end()
	{
		$this->get_db_wrapper()->flush_queue();
		$this->flush_queue();
		$this->flush_meta_queue();
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
	
	/**
	 * Returns a valid user object or an error object on failure.
	 * 
	 * @param ArrayObject $row
	 * @param integer $number
	 * @return integer|WP_Error
	 */
	public function get_wp_user( ArrayObject $row, $number )
	{
		
		$password = @$row->offsetGet( 'user_pass' );
		$login = @$row->offsetGet( 'user_login' );
		$email = @trim( $row->offsetGet( 'user_email' ) );
		
		if ( !$login ) {
			$this->throw_exception( 'No user_login given. ' );
		}
		
		if ( !is_email( $email ) ) {
    		$this->throw_exception( "Email '$email' is invalid." );
		}
		
		$user = null;
		
		// Ensure the user login is correctly formated.
		$login = sanitize_user( $login );
		
		// Find existing user
		$where = "WHERE user_login = '" . esc_sql( $login ) . "'";
		
		if ( $email ) {
			$where .= " OR user_email = '" . esc_sql( $email ) . "'";
		}
		
		$query = new WP_User_Query();
		$query->set( 'fields', 'all' );
		$query->prepare_query();
		
		$query->query_where = $where;
		$query->query();
		
		$result = $query->get_results();
		
		if ( count( $result ) > 1 ) {
			$this->throw_exception( 'Could not update existing user because email and login do not match' );
		}
		
		foreach ( $query->get_results() as $possible_user ) {
			if ( $possible_user->get( 'user_login' ) === $login ) {
				$user = $possible_user;
			}
		}

		// Stop here if a user was found
		if ( $user instanceof WP_User ) {
			return $user->ID;
		}
		
		if ( $email && $password ) {
			// Replace the hasher
			global $wp_hasher;
			$org_hasher = $wp_hasher;
			$wp_hasher = $this;
			
			$this->get_db_wrapper()->replace_wpdb();
			
			$user = wp_insert_user( $row->getArrayCopy() );
			
			$this->get_db_wrapper()->restore_wpdb();
			
			// Restore the hasher
			$wp_hasher = $org_hasher;
		}
		
		// Check if WordPress generated an error when inserting a new user.
		if ( $user instanceof WP_Error ) {
			$this->throw_exception( implode( '.', $user->get_error_messages() ) );
		}
		
		
		if ( !is_int( $user ) && !$user instanceof WP_Error ) {
			$error = 'No user could be found or created for an unknown reason.';
			
			global $EZSQL_ERROR;

			if ( is_array( $EZSQL_ERROR ) && !empty( $EZSQL_ERROR ) ) {
				$error .= 'Possible database error: ' . implode( '.', end( $EZSQL_ERROR ) );
			}
			
			$this->throw_exception( $error );
		}
		
		return $user;
	}
	
	public function add_xprofile( $user, $name, $value )
	{
		$field = $this->get_xprofile_field( $name );
		
		// We never expect to get anything other than arrays to serialize.
		if ( is_array( $value ) ) {
			$value = serialize( $value );
		}
		
		// Fast insert.
		if ( $this->overwrite ) {
			return $this->xprofile_update( $user, $field, $value );
		}
		

		// Todo finish this
		
		$data = new BP_XProfile_ProfileData();
		$data->field_id = $field->id;
		$data->user_id = $user;
	
		$data->value = $value;
	}
	
	public function get_xprofile_field( $name )
	{
		if ( isset( $this->xprofile_fields[$name] ) ) {
			return $this->xprofile_fields[$name];
		}	
		
		if ( !is_numeric( $name ) ) {
			$field_id = xprofile_get_field_id_from_name( $name );
		} else {
			$field_id = intval( $name );
		}
		
		$this->get_db_wrapper()->replace_wpdb();
		
		$field = new BP_XProfile_Field( $field_id, null, false );

		$this->get_db_wrapper()->restore_wpdb();
		
		if ( !$field->type ) {
			$this->throw_exception( 'Xprofile field name or ID "' . $name . '" does not exist.' );
		}
		
		$children = $field->get_children();
		if ( !empty( $children ) ) {
			foreach ( $children as $child ) {
				$this->xprofile_children[$field_id][] = $child;
			}	
		}
		
		$this->xprofile_fields[$name] = $field;
		return $field;
	}
	
	public function prepare_xprofile_field_value( $name, $value ) 
	{
		if ( !isset( $this->xprofile_children[$name] ) ) {
			return $value;
		}
		
		foreach ( $this->xprofile_children[$name] as $child ) {
			if ( $child->name === $value ) {
				return $value;
			}
		}
		
		if ( is_numeric( $value ) && array_key_exists( intval( $value ) - 1, $this->xprofile_children[$name] ) ) {
			return $this->xprofile_children[$name][intval( $value ) -1 ]->name;
		}		
		
		$this->throw_exception( 'Invalid value "' . $value . '" on field "' . $name . '".' );
	}
	
	
	public function xprofile_update( $user, BP_XProfile_Field $field, $value )
	{
		$this->xprofile_queue[$user . '-' . $field->id ] = array(
		    'user_id'  => $user,
			'field_id' => $field->id,
			'value'    => $value,
 		);
		
		if ( count( $this->xprofile_queue_length ) > $this->xprofile_queue_length ) {
			$this->flush_queue();
		}
	}
	
	/**
	 * Flushes insert queue
	 */
	public function flush_queue()
	{
		if ( empty( $this->xprofile_queue ) ) {
			// Nothing to do
			return;
		}
		
		global $wpdb;
		$bp = buddypress();
		
		$field_ids = array();
		$user_ids = array();
		
		foreach ( $this->xprofile_queue as $query ) {
			$field_ids[intval( $query['field_id'] )] = true;
			$user_ids[intval( $query['user_id'] )]   = true;
		}
		
		/*$query = sprintf(
			'SELECT id, field_id, user_id FROM %s WHERE field_id IN (%s) AND user_id IN (%s) ',
			$bp->profile->table_name_data,
			implode( ',', $field_ids ),
			implode( ',', $user_ids )
		);
		*/
		
		$query = sprintf(
			'DELETE FROM %s WHERE field_id IN (%s) AND user_id IN (%s)',
			$bp->profile->table_name_data,
			implode( ',', array_keys( $field_ids ) ),
			implode( ',', array_keys( $user_ids ) )
		);
		
		$wpdb->query( $query );

		$values = '';
		
		/*foreach ( $wpdb->last_result as $field ) {
			$key_name = $field->user_id . '-' . $field->field_id;
			if ( array_key_exists( $key_name, $this->xprofile_queue  ) ) {
				$values[] = array(
					$field->id,
					$field->field_id,
					$field->user_id,
					$this->xprofile_queue[$key_name]['value'],
					current_time( 'mysql' )
				);
				unset( $this->xprofile_fields[$key_name] );
			}	
		}
		*/
		
		foreach ( $this->xprofile_queue as $field ) {
			$values[] = array(
				0,
				$field['field_id'],
				$field['user_id'],
				$field['value'],
				$this->get_current_mysql_time(),
			);
		}
	
		$value_string = '';
		foreach ( $values as $value ) {
			$value_string .= vsprintf( '( %d, %d, %d, \'%s\', \'%s\'),', $value );
		}
		
		$value_string = rtrim( $value_string, ',' );
		
		$query = 'INSERT INTO ' . esc_sql( $bp->profile->table_name_data ) . ' ( id, field_id, user_id, value, last_updated ) VALUES ' . $value_string . ';';
		
		$wpdb->query( $query );
		
		$this->xprofile_queue = array();
	}
	
	/**
	 * Dummy password hasher.
	 * 
	 * @param string $pwd
	 * @return string
	 */
	public function HashPassword( $pwd )
	{
		return $pwd;
	}
}