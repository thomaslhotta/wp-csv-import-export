<?php
/**
 * Abstract processor class
 * 
 * @author Thomas Lhotta
 *
 */
abstract class CIE_Processor
{
	protected $field_type_objects = array();

	abstract public function get_supported_fields();

	protected function get_field_type_object( $field_type )
	{
		if ( empty( $this->field_type_objects[ $field_type ] ) ) {
			$class = 'CIE_Field_' . ucfirst( $field_type );
			if ( ! class_exists( $class ) ) {
				throw new Exception(
					'No class found for field type ' . strip_tags( $class )
				);
			}

			$this->field_type_objects[ $field_type ] = $class;
		}

		return new $this->field_type_objects[ $field_type ];
	}
}