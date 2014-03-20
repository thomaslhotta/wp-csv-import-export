<?php
require_once dirname( __FILE__ ) . '/class-cie-csv-processor-abstract.php';

/**
 * Exports users
 * 
 * @toto Finish this
 *
 * @package   WP_CSV_User_Import
 * @author    Thomas Lhotta
 * @license   GPL-2.0+
 * @link      https://github.com/thomaslhotta/wp-csv-import-export/
 * @copyright 2013 Thomas Lhotta
 */
class CIE_Exporter extends CIE_CSV_Processor_Abstract
{
	protected $fields;
	
	protected $batch_size = 100;
	
	
	protected $db;
	
	public function __construct( array $fields )
	{
		$this->fields = $fields;
		
		global $wpdb;
		
		$this->db = $wpdb;
	}  
	
	public function print_export(  )
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