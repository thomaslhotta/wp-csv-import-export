<?php
/**
 * Date: 14.01.15
 * Time: 17:50
 */
class CIE_Element
{
	protected $element = null;

	protected $element_id = null;

	protected $user_id = null;

	protected $error = null;

	public function set_element( $element, $element_id, $user_id )
	{
		$this->element = $element;

		if ( ! is_numeric( $element_id ) ) {
			throw new Exception( 'Invalid element id type given.' );
		}

		$this->element_id = intval( $element_id );

		if ( is_numeric( $user_id ) ) {
			$this->user_id = intval( $user_id );
		}

		return $this;
	}

	public function get_element()
	{
		return $this->element;
	}

	public function get_element_id()
	{
		return $this->element_id;
	}

	public function get_user_id()
	{
		return $this->user_id;
	}

	public function set_error( $error )
	{
		$this->error = strip_tags( $error );
		return $this;
	}

	public function get_error()
	{
		return $this->error;
	}

	public function has_error()
	{
		return ! empty( $this->error );
	}
}