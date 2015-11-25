<?php

/**
 * Imports GravityForms entries
 */
class CIE_Module_Gforms_Importer extends CIE_Importer {
	protected $posts = array();

	protected $users = array();

	public function get_supported_mode() {
		return self::MODE_IMPORT;
	}

	public function get_supported_fields() {
		return array();
	}

	public function get_required_fields( $mode ) {
		return array(
			array(
				'columns'     => array( 'form_id' ),
				'description' => __( 'Form ID', 'cie' ),
			),
		);
	}

	public function create_element( array $data, $mode = parent::MODE_IMPORT ) {
		$element = new CIE_Element();

		$entry = array(
			'form_id' => $data['form_id'],
		);

		$user_id = null;
		if ( ! empty( $data['user'] ) ) {
			$user = null;
			if ( is_numeric( $data['user'] ) ) {
				$user = get_user_by( 'id', $data['user'] );
			} elseif ( filter_var( $data['user'], FILTER_VALIDATE_EMAIL ) ) {
				$user = get_user_by( 'email', $data['user'] );
			} else {
				$user = get_user_by( 'slug', $data['user'] );
			}

			if ( ! $user instanceof WP_User ) {
				$element->set_error( 'No user found' );

				return $element;
			}

			$user_id             = $user->ID;
			$entry['created_by'] = $user->ID;
		}


		foreach ( $data as $name => $value ) {
			if ( false === strpos( $name, 'input_' ) ) {
				continue;
			}

			$entry[ str_replace( 'input_', '', $name ) ] = $value;
		}

		$id   = GFAPI::add_entry( $entry );
		$lead = GFAPI::get_entry( $id );

		$element->set_element( $lead, $id, $user_id );

		return $element;
	}
}
