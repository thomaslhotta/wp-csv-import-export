<?php
require_once  dirname( __FILE__ ) . '/class-cie-exporter-abstract.php';


class CIE_Exporter_User extends CIE_Exporter_Abstract
{
	public function get_available_fields()
	{
		$user_fields = array(
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
			$user_fields[] = 'user_pass';
		}
		
		$meta_keys = array();
		
		global $wpdb;
		
		$wpdb->query( "SELECT DISTINCT(meta_key) FROM $wpdb->usermeta;" );
		
		foreach ( $wpdb->last_result as $key ) {
			$meta_keys[] = mysql_escape_string( $key->meta_key );
		}
		
		$metas = array(
			array(
		    	'table'     => $wpdb->usermeta,
				'fields'    => $meta_keys,
				'main_id'   => 'user_id',
				'field_key' => 'meta_key',
				'value_key' => 'meta_value',
			)  
		); 
		
		if ( isset( $GLOBALS['bp'] ) ) {
			global $bp;
			
			$wpdb->query( "SELECT id, name FROM {$bp->profile->table_name_fields} WHERE NOT type = 'option' ");
			
			$meta_keys = array();
			foreach ( $wpdb->last_result as $key ) {
				$meta_keys[$key->name] = $key->id;
			}
			
			
			$buddypress = array(
			    'table'     => $bp->profile->table_name_data,
				'main_id'   => 'user_id',
				'value_key' => 'value',
				'field_key' => 'field_id',
				'fields'    => $meta_keys,
			);
			
			$metas[] = $buddypress;
			
		}
		
		return array(
			'main' => array(
				'main_id' => 'ID' ,
		    	'table'   => $wpdb->users,
				'fields'  => $user_fields,
			),
			'meta' => $metas,
		);
			
		
		
		
		
		
		
	}
}