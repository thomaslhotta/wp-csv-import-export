<?php
/**
 * Date: 08.01.15
 * Time: 15:42
 */
abstract class CIE_Field_Meta extends CIE_Field_Abstract
{
	public function get_meta_values( array $fields, $type, $id )
	{
		$data = array();
		foreach ( $fields as $field_id ) {
			$meta = (array) get_metadata( $type, $id, $field_id, false );

			if ( empty( $meta ) ) {
				$data[] = '';
				continue;
			}

			$contains_array = false;
			foreach ( $meta as $value ) {
				if ( is_array( $value ) ) {
					$contains_array = true;
					break;
				}
			}

			if ( $contains_array ) {
				$meta = json_encode( $meta );
			} else {
				$meta = join( ';', $meta );
			}

			$data[] = $meta;
		}
		return $data;
	}
}
