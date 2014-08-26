<?php
require_once dirname( __FILE__ ) . '/class-cie-csv-processor-abstract.php';

/**
 * Exports Users
 *
 * Class CIE_Exporter_User
 */
class CIE_Exporter_User extends CIE_CSV_Processor_Abstract
{
	protected $batch_size = 100;

	protected $xprofile_fields = array();

	public function print_export( array $fields, $search = array(), $offset = '', $limit = '' )
	{
		$role = 'Subscriber';
		//wp_cache_close();
		$params = array(
			'offset'      => $offset,
			'number'      => $limit,
			'fields'      => 'all',
			'count_total' => true,
		);


		// Only export subscribers if not in network admin
		if ( is_multisite() && ! $this->is_network_admin() ) {
			$params['role'] = $role;
		} else {
			$params['blog_id'] = 0;
		}

		$query = new WP_User_Query( $params );

		$query->prepare_query();
		$query->query();

		// Calculate range header
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			$total = $query->total_users;

			$range_end = $offset + $limit;
			if ( $range_end > $total ) {
				$range_end = $total;
			}

			header(
				sprintf(
					'Content-Range: %d-%d/%d ',
					$offset,
					$range_end,
					$total
				),
				true
			);
		}

		$first_row = array();
		if ( ! empty( $fields['user'] ) ) {
			foreach ( $fields['user'] as $field ) {
				$first_row[] = $field;
			}
		}
		if ( ! empty( $fields['meta'] ) ) {
			foreach ( $fields['meta'] as $field ) {
				$first_row[] = $field;
			}
		}
		if ( ! empty( $fields['buddypress'] ) ) {
			foreach ( $fields['buddypress'] as $field_id ) {
				$first_row[] = $this->get_bp_field_name( $field_id );
			}
		}

		// Open an output handle
		$handle = fopen( 'php://output', 'w' );
		fputcsv( $handle , $first_row );


		foreach ( $query->get_results() as $user ) {
			$row = array();
			foreach ( $fields['user'] as $field ) {
				if ( isset( $user->$field ) ) {
					$row[ $field ] = @$user->$field;
				} else {
					$row[ $field ] = '';
				}
			}

			if ( ! empty( $fields['meta'] ) ) {
				foreach ( $fields['meta'] as $meta ) {
					$value = get_user_meta( $user->ID, $meta );

					if ( 1 < count( $value ) ) {
						$row[ $meta ] = json_encode( $value );
					} else {
						$value = reset( $value );

						if ( is_array( $value ) ) {
							$value = json_encode( $value );
						}
						$row[ $meta ] = $value;
					}
				}
			}

			if ( ! empty( $fields['buddypress'] ) ) {
				$xprofile_data = new BP_XProfile_ProfileData();

				$xprofile_ids = $fields['buddypress'];

				// Ensure that we are only using integers
				array_walk( $xprofile_ids, 'intval' );

				$xprofile_data = $xprofile_data->get_data_for_user( $user->ID, $xprofile_ids );

				foreach ( $xprofile_data as $data ) {
					$value = maybe_unserialize( $data->value );

					if ( is_array( $value ) ) {
						$value = json_encode( $value );
					}

					$row[ $this->get_bp_field_name( $data->field_id ) ] = $value;
				}
			}

			$output = array();

			foreach ( $first_row as $title ) {
				$output[] = $row[ $title ];
			}

			fputcsv( $handle, $output );
		}
		die();
	}

	public function get_available_fields()
	{
		$fields = array(
			'ID'                   => 'ID',
			'user_login'	       => 'user_login',
			'user_nicename'        => 'user_nicename',
			'user_email'           => 'user_email',
			'user_url'             => 'user_url',
			'user_registered'      => 'user_registered',
			'user_activation_key'  => 'user_activation_key',
			'user_status'          => 'user_status',
			'display_name'         => 'display_name',
			'deleted'              => 'deleted',
		);

		if ( is_super_admin() ) {
			//$fields[] = 'user_pass';
		}

		global $wpdb;

		$wpdb->query( "SELECT DISTINCT(meta_key) FROM $wpdb->usermeta;" );

		$meta = array();


		$ignore_meta = '';
		if ( is_multisite() && ! is_network_admin() ) {
			$ignore_meta = 'wp_' . get_current_blog_id() . '_';
		}


		foreach ( $wpdb->last_result as $key ) {
			// Ignore metas that are specific for a blog on multisite installs to prevent
			// options clutter
			if ( $ignore_meta
				&& false !== strpos( $key->meta_key, 'wp_' )
				&& $ignore_meta !== substr( $key->meta_key, 0, strlen( $ignore_meta ) ) ) {
				continue;
			}

			$meta[ $key->meta_key ] = $key->meta_key;
		}

		$buddypress = array();
		if ( function_exists( 'buddypress' ) ) {
			$bp = buddypress();

			$wpdb->query( "SELECT id, name FROM {$bp->profile->table_name_fields} WHERE NOT type = 'option'" );

			foreach ( $wpdb->last_result as $key ) {
				$buddypress[ $key->name ] = $key->id;
			}
		}

		return array(
			'buddypress'  => $buddypress,
			'user'        => $fields,
			'meta'        => $meta,
		);
	}

	protected function get_bp_field( $id )
	{
		if ( ! isset( $this->xprofile_fields[ $id ] ) ) {
			$this->xprofile_fields[ $id ] = xprofile_get_field( $id );
		}
		return $this->xprofile_fields[ $id ];
	}

	protected function get_bp_field_name( $id )
	{
		return $this->get_bp_field( $id )->name;
	}

	protected function get_bp_field_type( $id )
	{
		return $this->get_bp_field( $id )->type;
	}


	/**
	 * Detect network admin in an AJAX safe way 
	 */
	public function is_network_admin()
	{
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return ( is_multisite() && preg_match( '#^' . network_admin_url(). '#i', $_SERVER['HTTP_REFERER'] ) );
		}

		return is_network_admin();
	}
}
