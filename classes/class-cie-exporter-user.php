<?php
require_once dirname( __FILE__ ) . '/class-cie-csv-processor-abstract.php';


class CIE_Exporter_User extends CIE_CSV_Processor_Abstract
{
	protected $fields;
	
	protected $batch_size = 100;
	
	
	protected $db;
	
	public function __construct( )
	{
	}  
	
	public function print_export( array $fields, $search = array(), $offset = '' , $limit = '' ) 
	{
		$query = new WP_User_Query();	
		
		$query->set( 'offset', $offset );
		$query->set( 'number', $limit );
		$query->set( 'fields', 'all' );
		$query->set( 'count_total', true );
		
		
		$query->prepare_query();
		$query->query();
		
		$result = array();
		
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			header(
				sprintf(
					'Content-Range: %d-%d/%d ',
					$offset,
					$offset + $limit,
					$query->total_users
				),
				true
			);
		}
		
		$first_row = array();
		if ( !empty( $fields['user'] ) ) {
			foreach ( $fields['user'] as $field ) {
				$first_row[] = $field;
			}
		}
		if ( !empty( $fields['meta'] ) ) {
			foreach ( $fields[$meta] as $field ) {
				$first_row[] = $field;
			}
		}
		if ( !empty( $fields['buddypress'] ) ) {
			foreach ( $fields['buddypress'] as $field ) {
				$first_row[] = xprofile_get_field( $field )->name;
			}
		}
		
		$handle = fopen("php://output", 'w');
		fputcsv( $handle , $first_row );
		
		
		foreach ( $query->get_results() as $user ) {
			$row = array();
			foreach ( $fields['user'] as $field ) {
				if ( isset( $user->$field ) ) {
					$row[$field] = @$user->$field;
				} else {
					$row[$field] = '';
				}
			}
			
			if ( !empty( $fields['meta'] ) ) {
				foreach ( $fields['meta'] as $meta ) {
					$value = get_user_meta( $user->ID, $meta );
					
					if ( 1 < count( $value ) ) {
						$row[$meta] = json_encode( $value );
					} else {
						$row[$meta] = reset( $value ); 
					}
				}
			}
			
			if ( !empty( $fields['buddypress'] ) ) {
				
				$xprofile_data = new BP_XProfile_ProfileData();
				
				$xprofile_data = $xprofile_data->get_all_for_user( $user->ID );

				
				foreach ( $xprofile_data  as $name => $data ) {
					if ( isset( $data['field_id'] ) && in_array( $data['field_id'], $fields['buddypress'] ) ) {
						$value = $data['field_data'];
						$value = maybe_unserialize( $value );
							
						if ( is_array( $value ) ) {
							$row[$name] = json_encode( $value );
						} else {
							$row[$name] = $value;
						}
						
					
					}
				}
				
			}
			
			$output = array(); 
				
			foreach ( $first_row as $title ) {
				$output[] = $row[$title];
			}
			
			
			fputcsv( $handle, $output );
		}
		
		
		
		die();
		
		return $result;
		
		
	}
	
	
	
	public function get_available_fields()
	{
		$fields = array(
				'ID',
				'user_login',
				'user_nicename',
				'user_email',
				'user_url',
				'user_registered',
				'user_activation_key',
				'user_status',
				'display_name',
				'deleted',
		);
	
		if ( is_super_admin() ) {
			//$fields[] = 'user_pass';
		}
	
		global $wpdb;
	
		$wpdb->query( "SELECT DISTINCT(meta_key) FROM $wpdb->usermeta;" );
	
		$meta = array();
		foreach ( $wpdb->last_result as $key ) {
			$meta[] = $key->meta_key;
		}
	
		$buddypress = array();
		if ( isset( $GLOBALS['bp'] ) ) {
				
			global $bp;
				
			$wpdb->query( "SELECT id, name FROM {$bp->profile->table_name_fields} WHERE NOT type = 'option' ");
				
			
			foreach ( $wpdb->last_result as $key ) {
				$buddypress[] = array(
				    'name' => $key->name,
					'id'   => $key->id,
				);
			}
		}
		
		return array(
		    'user'        => $fields,
			'meta'        => $meta,
			'buddypress'  => $buddypress	
		);
	
	}
	
	
	public function xprint_export(  )
	{
		$handle = fopen("php://output", 'w');
		
		$offset = 0;
		$count = 1;
		$main_id =$this->fields['main']['main_id'];
		
		$wpdb = $this->db;
		
		$header = $this->get_head_row();
		
		$named_header = array();
		
		foreach ( $header as $key => $value ) {
			if ( is_numeric( $key ) ) {
				$named_header[] = $value;
			} else {
				$named_header[] = $key;
			}
		}
		
		
		fputcsv( $handle, $named_header );
		
		while (  0 < $wpdb->query( $this->build_main_query( $offset ) ) ) {
			
			$mains = new ArrayObject( array() );
			
			foreach ( $wpdb->last_result as $row ) {
				$row_array = get_object_vars( $row );
				$mains->offsetSet( intval( $row_array[$main_id] ) , new ArrayObject( $row_array ) );
			}
			
			$wpdb->flush();
			$this->merge_metas( $mains );
			
			foreach ( $mains->getIterator() as $row ) {
				$this->handle_row( $row , $count );
				
				$output = array();
				
				
				foreach ( $header as $name ) {
					if ( $row->offsetExists( $name ) ) {
						$output[$name] = $row->offsetGet( $name );
					} else {
						$output[$name] = null;
					}
					
				}

				
				fputcsv( $handle , $output );
				$count ++;
			}
			
			$offset += $this->batch_size;
			
		}
		
		
		
	}
	
	public function get_head_row()
	{
		$header = $this->fields['main']['fields'];

		foreach ( $this->fields['meta'] as $meta ) {
			$header = array_merge( $header, $meta['fields']  );
		} 
		
		return $header;
		
	}
	
	public function build_main_query( $offset  = 0 )
	{
		$fields = $this->fields['main']['fields'];
		$main = $this->fields['main'];
		
		$field_string = '';
		
		foreach ( $fields as $field ) {
			$field_string .= mysql_real_escape_string( $field ) . ', ' ;
		}
		
		$field_string = rtrim( $field_string, ', ' );
		
		$main_query = sprintf( 
			'SELECT %s FROM %s LIMIT %d,%d;',
			$field_string,
			$main['table'],
			$offset,
			$this->batch_size
	    );
		
		
		return $main_query;
		
		
	}
	
	public function merge_metas( ArrayObject $mains )  
	{
		$main_ids = array_keys( $mains->getArrayCopy() );

		$wpdb = $this->db;
		
		foreach ( $this->fields['meta'] as $def ) {
			$wpdb->query( $this->build_meta_query( $def, $main_ids) );
			
			
			foreach ( $wpdb->last_result as $result ) {
				$result = get_object_vars( $result );
				
				$main_id = $def['main_id'];
				
				
				if ( !$mains->offsetExists( $result[ $main_id ] ) ) {
					continue;
				}
				
				$main = $mains->offsetGet( $result[ $main_id ] );
				
				if ( isset( $def['field_key'] ) ) {
					$main->offsetSet( $result[$def['field_key']], $result[$def['value_key']] );
				} else {
					foreach ( $result as $key => $value ) {
						if ( $key === $main_id ) {
							continue;
						}
						
						$main->offsetSet( $key, $value );
					}
				}
				
			}
		}
	}
	
	public function build_meta_query ( array $def, array $ids )
	{
		$ids = implode( ',' , $ids );
		
		if ( isset( $def['field_key'] ) ) {
			$fields = $def['field_key'] . ',' .  $def['value_key'];
			$where = ' AND ' . $def['field_key'] . " IN ( '" . implode( "','", $def['fields'] ) . "')";
		} else {
			$fields = implode( ',', $def['fields'] );
			$where = '';
		}
		
		
		return sprintf( 
			'SELECT %s%s FROM %s WHERE %s IN ( %s )%s;',
			$def['main_id'],
			',' . $fields,
			$def['table'],
			$def['main_id'],
			$ids,
			$where
		);
		
		
	}
	
}