<?php
/**
 * Container class for importing and exporting elements
 */
class CIE_Element
{
	/**
	 * @var WP_Post|WP_User|object
	 */
	protected $element = null;

	/**
	 * @var int
	 */
	protected $element_id = null;

	/**
	 * @var int
	 */
	protected $user_id = null;

	/**
	 * @var string
	 */
	protected $error = null;

	/**
	 * Set the element in this container. The element ID is always required. The user ID must be set if this
	 * element is associated with a WP_User
	 *
	 * @param $element
	 * @param $element_id
	 * @param $user_id
	 *
	 * @return $this
	 * @throws Exception
	 */
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

	/**
	 * @return object|WP_Post|WP_User
	 */
	public function get_element()
	{
		return $this->element;
	}

	/**
	 * @return int
	 */
	public function get_element_id()
	{
		return $this->element_id;
	}

	/**
	 * @return int
	 */
	public function get_user_id()
	{
		return $this->user_id;
	}

	/**
	 * @param string $error
	 *
	 * @return $this
	 */
	public function set_error( $error )
	{
		$this->error = strip_tags( $error );
		return $this;
	}

	/**
	 * @return string
	 */
	public function get_error()
	{
		return $this->error;
	}

	/**
	 * Returns true if an error has been set
	 *
	 * @return bool
	 */
	public function has_error()
	{
		return ! empty( $this->error );
	}
}