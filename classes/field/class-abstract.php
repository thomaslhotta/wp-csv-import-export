<?php
/**
 * Abstract class for field handlers
 */
abstract class CIE_Field_Abstract
{
	/**
	 * Returns an array of available field names
	 *
	 * @param array $search
	 *
	 * @return array
	 */
	abstract public function get_available_fields( array $search = array() );

	/**
	 * Returns field values
	 *
	 * @param array       $fields
	 * @param CIE_Element $element
	 *
	 * @return array
	 */
	abstract public function get_field_values( array $fields, CIE_Element $element );

	/**
	 * Set field values for this field type. Returns an array of errors
	 *
	 * @param array       $fields
	 * @param CIE_Element $element
	 *
	 * @return array
	 */
	abstract public function set_field_values( array $fields, CIE_Element $element );
}