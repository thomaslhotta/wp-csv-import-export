<?php

/**
 * Abstract processor class
 */
abstract class CIE_Processor {
	/**
	 * @var array
	 */
	protected $field_type_objects = array();

	/**
	 * Returns the supported field types
	 *
	 * @return mixed
	 */
	abstract public function get_supported_fields();

	/**
	 * Return the field type object for the given field group name
	 *
	 * @param $field_type
	 *
	 * @return mixed
	 * @throws Exception
	 */
	protected function get_field_type_object( $field_type ) {
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