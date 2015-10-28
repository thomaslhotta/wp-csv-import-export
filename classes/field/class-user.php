<?php

/**
 * Handles user data
 */
class CIE_Field_User extends CIE_Field_Abstract {
	public function get_available_fields( array $search = array() ) {
		$fields = array(
			'ID'                  => 'ID',
			'user_login'          => 'user_login',
			'user_nicename'       => 'user_nicename',
			'user_email'          => 'user_email',
			'user_url'            => 'user_url',
			'user_registered'     => 'user_registered',
			'user_activation_key' => 'user_activation_key',
			'user_status'         => 'user_status',
			'display_name'        => 'display_name',
			'deleted'             => 'deleted',
		);

		if ( is_super_admin() ) {
			// Uncomment this if you want to export password hashes
			//$fields[] = 'user_pass';
		}

		return $fields;
	}

	public function get_field_values( array $fields, CIE_Element $element ) {
		if ( $element->get_element() instanceof WP_User ) {
			$user = $element->get_element();
		} else {
			$user = get_user_by( 'id', $element->get_user_id() );
		}

		$data = array();

		foreach ( $fields as $field_id ) {
			$value = '';

			if ( $user instanceof WP_User ) {
				$value = $user->get( $field_id );
			}

			$data[] = $value;
		}

		return $data;
	}

	public function set_field_values( array $fields, CIE_Element $element ) {

	}
}
