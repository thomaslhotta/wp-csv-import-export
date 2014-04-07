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

	public function print_export( array $fields, $search = array(), $offset = '', $limit = '' )
	{
		$role = 'Subscriber';

		$params = array(
			'offset'      => $offset,
			'number'      => $limit,
			'fields'      => 'all',
			'count_total' => true,
		);

		// Only export subscribers if not in network admin
		if ( is_multisite() && !is_network_admin() ) {
			$params['role'] = $role;
		}

		$query = new WP_User_Query( $params );

		$query->prepare_query();
		$query->query();

		// Calculate range header
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			$total = count_users();
			$total = $total['avail_roles'][strtolower( $role )];

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
		if ( !empty( $fields['user'] ) ) {
			foreach ( $fields['user'] as $field ) {
				$first_row[] = $field;
			}
		}
		if ( !empty( $fields['meta'] ) ) {
			foreach ( $fields['meta'] as $field ) {
				$first_row[] = $field;
			}
		}
		if ( !empty( $fields['buddypress'] ) ) {
			foreach ( $fields['buddypress'] as $field ) {
				$first_row[] = xprofile_get_field( $field )->name;
			}
		}

		// Open an output handle
		$handle = fopen( 'php://output', 'w' );
		fputcsv( $handle , $first_row );


		foreach ( $query->get_results() as $user ) {
			$row = array();
			foreach ( $fields['user'] as $field ) {
				if ( isset( $user->$field ) ) {
					$row[$field] = @$user->$field;
				} else {
					$row[$field] = '';
				}
			}

			if ( !empty( $fields['meta'] ) ) {
				foreach ( $fields['meta'] as $meta ) {
					$value = get_user_meta( $user->ID, $meta );

					if ( 1 < count( $value ) ) {
						$row[$meta] = json_encode( $value );
					} else {
						$value = reset( $value );

						if ( is_array( $value ) ) {
							$value = json_encode( $value );
						}
						$row[$meta] = $value;
					}
				}
			}

			if ( !empty( $fields['buddypress'] ) ) {
				$xprofile_data = new BP_XProfile_ProfileData();

				$xprofile_data = $xprofile_data->get_all_for_user( $user->ID );


				foreach ( $xprofile_data  as $name => $data ) {
					if ( isset( $data['field_id'] ) && in_array( $data['field_id'], $fields['buddypress'] ) ) {
						$value = $data['field_data'];
						$value = maybe_unserialize( $value );

						if ( is_array( $value ) ) {
							$row[$name] = json_encode( $value );
						} else {
							$row[$name] = $value;
						}
					}
				}
			}

			$output = array();

			foreach ( $first_row as $title ) {
				$output[] = $row[$title];
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
		if ( is_multisite() && !is_network_admin() ) {
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

			$meta[$key->meta_key] = $key->meta_key;
		}

		$buddypress = array();
		if ( function_exists( 'buddypress' ) ) {
			$bp = buddypress();

			$wpdb->query( "SELECT id, name FROM {$bp->profile->table_name_fields} WHERE NOT type = 'option'" );

			foreach ( $wpdb->last_result as $key ) {
				$buddypress[$key->name] = $key->id;
			}
		}

		return array(
			'buddypress'  => $buddypress,
		    'user'        => $fields,
			'meta'        => $meta,
		);
	}
}