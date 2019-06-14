<?php

/**
 * Adds existing users to a blog/site
 */
class CIE_Module_Users_Adder extends CIE_Importer {

	public function get_required_fields( $mode ) {
		return array(
			array(
				'columns'     => array( 'ID', 'user_login', 'user_email' ),
				'description' => __( 'User ID, login name or email.', 'cie' ),
			),
		);
	}

	public function get_supported_fields() {
		return array(
			'option',
		);
	}

	public function get_supported_mode() {
		return parent::MODE_UPDATE;
	}

	public function create_element( array $data, $mode = parent::MODE_UPDATE ) {
		$element = new CIE_Element();

		$user = null;
		if ( ! empty( $data['ID'] ) ) {
			$user = get_user_by( 'id', $data['ID'] );
		} elseif ( ! empty( $data['createdby'] ) ) {
			$user = get_user_by( 'id', $data['createdby'] );
		} elseif ( ! empty( $data['user_email'] ) ) {
			$user = get_user_by( 'email', $data['user_email'] );
		} elseif ( ! empty( $data['user_login'] ) ) {
			$user = get_user_by( 'login', $data['user_login'] );
		}

		if ( $user instanceof WP_User ) {
			// Use 'subscriber' if none is given.
			$role = 'subscriber';
			if ( ! empty( $data['role'] ) ) {
				$role = $data['role'];
			}

			// Only allow adding new users, but don't mark as error to allow other imports on this user to occur.
			if ( empty( $user->roles ) ) {
				$user->add_role( $role );
			}

			$element->set_element( $user, $user->ID, $user->ID );
		} else {
			$element->set_error( __( 'User could be found', 'cie' ) );
		}

		return $element;
	}
}
