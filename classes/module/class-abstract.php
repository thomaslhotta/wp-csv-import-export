<?php
/**
 * Base class for modules
 */
abstract class CIE_Module_Abstract
{
	/**
	 * Registers admin menus
	 */
	public function register_menus() {}

	/**
	 * Registers AJAX handlers
	 */
	public function register_ajax() {}

	/**
	 * Renders the export UI
	 *
	 * @param array $fields
	 * @param array $hidden_fields
	 *
	 * @return string
	 */
	public function render_export_ui( array $fields, array $hidden_fields = array(), array $searchable = array() )
	{
		$hidden_fields = $this->flatten_hidden( $hidden_fields );

		$html = '';
		foreach ( $fields as $group_name => $group ) {
			$options = '';

			foreach ( $group as $field_id => $field_name ) {
				$string = '<div><label title="%s"><input type="checkbox" name="fields[%s][%s]" value="1">%s</label></div>';

				$options .= sprintf(
					$string,
					$field_name,
					esc_attr( $group_name ),
					esc_attr( $field_id ),
					esc_attr( $field_name )
				);
			}

			$id = sanitize_title( $group_name ) . '-options';

			$html .= sprintf(
				'<tr><th>%s<br><label><input type="checkbox" data-toggle="checked" data-target="#%s">%s</label></th><td id="%s">%s</td></tr>',
				esc_html( ucfirst( $group_name ) ),
				$id,
				__( 'All' ),
				$id,
				$options
			);
		}

		$search_html = '';
		foreach ( $searchable as $group => $fields ) {
			foreach ( $fields as $id => $label ) {
				$key = sprintf( 'search[%s][%s]', esc_attr( $group ), esc_attr( $id ) );
				if ( array_key_exists( $key, $hidden_fields ) ) {
					// Don't show search fields for keys that are already present as hidden values
					continue;
				}

				$search_html .= sprintf(
					'<tr><td>%s</td><td><input name="%s" type="text"></td></tr>',
					esc_html( $label ),
					$key
				);
			}
		}

		if ( ! empty( $search_html ) ) {
			$html .= sprintf(
				'<tr><th>Search</th><td><table>%s</table></td></tr>',
				$search_html
			);
		}

		$html = sprintf(
			'<table class="form-table">%s</table>',
			$html
		);

		foreach ( $hidden_fields as $name => $value ) {
			$html .= sprintf( '<input type="hidden" name="%s" value="%s">', esc_attr( $name ), esc_attr( $value ) );
		}

		$html = sprintf(
			'<div id="export-settings">%s</div>',
			$html
		);

		$html .= sprintf(
			'<button type="button" class="button-secondary export" data-toggle="export" data-target="#export-settings">%s</button>',
			__( 'Export' )
		);

		$html = sprintf(
			'<div id="csv-export">%s</div>',
			$html
		);

		wp_enqueue_script( 'cie-admin-script' );


		return $html;
	}

	public function flatten_hidden( array $hidden )
	{
		$return = array();

		foreach ( $hidden as $k1 => $v1 ) {
			if ( is_array( $v1 ) ) {
				foreach ( $v1 as $k2 => $v2 ) {
					$key = $k1 .  '[' . $k2 . ']';
					dump( $key );
					if ( is_array( $v2 ) ) {
						foreach ( $v2 as $k3 => $v3 ) {
							$return[ $key . '[' . $k3 . ']' ] = $v3;
						}
					} else {
						$return[ $key ] = $v2;
					}
				}
			} else {
				$return[ $k1 ] = $v1;
			}
		}

		return $return;
	}

	/**
	 * Renders the import UI
	 *
	 * @param $action
	 *
	 * @return string
	 */
	public function render_import_ui( $action )
	{
		$csv = '';
		$csv_valid = true;
		if ( ! empty( $_FILES['csv'] ) && check_admin_referer( 'upload-csv', 'nonce' ) ) {
			$finfo = finfo_open( FILEINFO_MIME_TYPE );
			finfo_file( $finfo, $_FILES['csv']['tmp_name'] );
			$csv_valid = 0 === strpos( finfo_file( $finfo, $_FILES['csv']['tmp_name'] ), 'text/' );

			if ( $csv_valid ) {
				$csv = file_get_contents( $_FILES['csv']['tmp_name'] );
			}
		}

		$url = remove_query_arg( 'nonce' );

		$html = '';

		$html .= sprintf(
			'<h2>%s</h2>',
			esc_html( get_admin_page_title() )
		);

		$mode = CIE_Importer::MODE_IMPORT;
		if ( ! empty( $_GET['mode'] ) ) {
			$mode = intval( $_GET['mode'] );
		}

		if ( CIE_Importer::MODE_BOTH === $this->get_importer()->get_supported_mode() ) {
			$html .= sprintf(
				'<h2 class="nav-tab-wrapper"><a href="%s" class="nav-tab%s">%s</a><a href="%s" class="nav-tab%s">%s</a></h2>',
				esc_url( add_query_arg( 'mode', CIE_Importer::MODE_IMPORT, $url )  ),
				CIE_Importer::MODE_IMPORT === $mode ? ' nav-tab-active' : '',
				__( 'Import', 'cie' ),
				esc_url( add_query_arg( 'mode', CIE_Importer::MODE_UPDATE, $url )  ),
				CIE_Importer::MODE_UPDATE === $mode  ? ' nav-tab-active' : '',
				__( 'Update', 'cie' )
			);
		}

		if ( empty( $csv ) || empty ( $csv_valid ) ) {
			// If no file was uploaded show default UI
			$required = '';
			foreach ( $this->get_importer()->get_required_fields( $mode ) as $field ) {
				$required .= sprintf(
					'<tr><td>%s</td><td>%s</td></tr>',
					join( ', ', $field['columns'] ),
					$field['description']
				);
			}

			$html .= sprintf(
				'<tr><th>%s</th><td><table>%s</table></td></tr>',
				__( 'Required columns', 'cie' ),
				$required
			);

			$html .= sprintf(
				'<tr><th>%s</th><td><input type="file" name="csv" id="file" class="csv" accept=".csv"/><p class="description">%s</p></td></tr>',
				__( 'Select a CSV file', 'cie' ),
				$csv_valid ? '' : __( 'The uploaded file is not a valid CSV file.', 'cie' )
			);

			$html .= __(
				'<tr><th>or copy & paste the CSV data from your spreadsheet software.</th>'
			);

			$html .= '<td><textarea class="widefat" name="csv_fallback" rows="5"></textarea></td></tr>';


		} else {
			// Show fallback UI for non FileReader browsers if file was uploaded
			$html .= sprintf( '<tr><th>%s</th>', __( 'Uploaded CSV', 'cie' ) );

			$html .= sprintf(
				'<td><textarea class="widefat" name="csv_fallback" rows="5" readonly>%s</textarea></td></tr>',
				esc_textarea( $csv )
			);
		}

		$errors = '<table><thead><tr><td>%s</td><td>%s</td></tr></thead><tbody></tbody></table>';
		$errors = '<tr id="errors" style="display: none"><th>%s</th><td><div style="max-height: 400px; overflow: scroll">' . $errors . '</div></td></tr>';

		$html .= sprintf(
			$errors,
			__( 'Errors' ),
			__( 'Errors' ),
			__( 'Row number', 'csv' )
		);

		$html = sprintf(
			'<table class="form-table">%s</table>',
			$html
		);

		$html .= sprintf( '<input name="mode" value="%d" type="hidden">', $mode );

		// Import button
		$html .= sprintf(
			'<button type="submit" class="button-primary">%s</button>',
			empty( $csv ) ? __( 'Import' ) : __( 'Continue' )
		);

		// Reset form button
		$html .= sprintf(
			'&nbsp;<a href="%s" class="button-secondary">%s</a>',
			esc_url( $url ),
			__( 'Clear' )
		);



		$html = sprintf(
			'<form id="csv-import-form" class="wrap" action="%s" method="post" enctype="multipart/form-data" data-toggle="import-csv" data-action="%s">%s</form>',
			esc_url( wp_nonce_url( $_SERVER['REQUEST_URI'], 'upload-csv', 'nonce' ) ),
			esc_attr( $action ),
			$html
		);

		wp_enqueue_script( 'cie-admin-script' );

		return $html;
	}

	/**
	 * Detect network admin in an AJAX safe way
	 */
	public function is_network_admin()
	{
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			return ( is_multisite() && false !== strpos( $_SERVER['HTTP_REFERER'], network_admin_url() ) );
		}

		return is_network_admin();
	}
}