<?php
/**
 *
 * Hooks that might need to be removed
 * // Prevent registration hooks.
 * remove_all_filters( 'user_register' );
 *
 * // Remove expensive filters
 *	remove_filter( 'pre_option_gmt_offset','wp_timezone_override_offset' );
 *	remove_all_filters( 'pre_user_display_name' );
 *	remove_all_filters( 'pre_user_first_name' );
 *	remove_all_filters( 'pre_user_last_name' );
 *	remove_all_filters( 'pre_user_nickname' );
 *
 *	// Prevent registration hooks.
 *	remove_all_filters( 'user_register' );
 *  remove_filter( 'pre_user_login' , 'bp_core_strip_username_spaces' );
 *
 * Date: 08.01.15
 * Time: 16:39
 */
class CIE_Module_Users_Creator extends CIE_Importer
{
	/**
	 * Overwrite mode
	 *
	 * @var true
	 */
	protected $overwrite;

	public function get_supported_fields()
	{
		return array(
			'buddypress',
			'usermeta',
			'option',
		);
	}

	public function get_supported_mode()
	{
		return self::MODE_BOTH;
	}

	public function get_required_fields( $mode )
	{
		if ( parent::MODE_UPDATE === $mode ) {
			return array(
				array(
					'columns'     => array( 'ID', 'user_login', 'user_email' ),
					'description' => __( 'User ID, login name or email.', 'cie' )
				),
			);
		}

		return array(
			array(
				'columns'     => array( 'user_login' ),
				'description' => __( 'User login name.', 'cie' )
			),
			array(
				'columns'     => array( 'user_email' ),
				'description' => __( 'User email address.', 'cie' )
			),
			array(
				'columns'     => array( 'user_pass' ),
				'description' => __( 'Hashed user passwords', 'cie' )
			),
		);
	}

	public function create_element( array $data, $mode = parent::MODE_IMPORT )
	{
		$element = new CIE_Element();

		// Replace hasher as we only accept pre hashed passwords. Nobody should have a list of unhashed passwords!
		global $wp_hasher;
		$org_hasher = $wp_hasher;
		$wp_hasher = $this;

		if ( parent::MODE_IMPORT === $mode ) {
			$user = wp_insert_user( $data );
		} else {
			$user = $this->get_existing_user( $data );

			if ( ! $user instanceof WP_User ) {
				$element->set_error(  __( 'No user could be found', 'cie' ) );
				return $element;
			}

			$data['ID'] = $user->ID;

			$user = wp_update_user( $data );
		}

		// Restore the hasher
		$wp_hasher = $org_hasher;

		if ( is_numeric( $user ) ) {
			$user = get_user_by( 'id', $user );
		}

		if ( $user instanceof WP_Error ) {
			// Return any WordPress Errors
			$element->set_error( $user->get_error_message() );
			return $element;
		} elseif ( $user instanceof WP_User ) {
			$element->set_element( $user, $user->ID, $user->ID );

			return $element;

		}

		return $element->set_error( __( 'User could not be created for an unknown reason', 'cie' ) );
	}

	/**
	 * Tries to find user by his ID, username or email address in this order
	 *
	 * @param array $row
	 *
	 * @return int|null
	 */
	protected function get_existing_user( array $row )
	{
		// Find user by ID
		if ( ! empty( $row['ID'] ) && is_numeric( $row['ID'] ) ) {
			$user = get_user_by( 'id', $row['ID'] );
			if ( $user instanceof WP_User ) {
				return $user;
			}
		}

		// Find user by login name
		if ( ! empty( $row['user_login'] ) ) {
			$user = get_user_by( 'login', $row['user_login'] );

			if ( $user instanceof WP_User ) {
				return $user;
			}
		}

		if ( ! empty( $row['user_email'] ) ) {
			$user = get_user_by( 'email', $row['user_email'] );

			if ( $user instanceof WP_User ) {
				return $user;
			}
		}

		return null;
	}

	/**
	 * Dummy password hasher.
	 *
	 * @param string $pwd
	 * @return string
	 */
	public function HashPassword( $pwd )
	{
		return $pwd;
	}
}