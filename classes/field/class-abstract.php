<?php
/**
 * Date: 08.01.15
 * Time: 10:12
 */
abstract class CIE_Field_Abstract
{
	abstract public function get_available_fields( array $search = array() );

	abstract public function get_field_values( array $fields, CIE_Element $element );

	abstract public function set_field_values( array $fields, CIE_Element $element );
}