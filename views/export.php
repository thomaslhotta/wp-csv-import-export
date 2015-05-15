<?php 
  $ps = $this->get_plugin_slug();
?>

<div class="wrap">
	<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>

	<form id="export" method="post">
		<table class="form-table">
			<tbody>
			<?php foreach ( $exporter->get_available_fields() as $name => $fields  ):  ?>
				<tr>
					<th>
						<?php echo esc_html( $name ) ?><br/><br/>
						<label>
							<input type="checkbox" class="select-all" value="<?php echo esc_attr( $name ) ?>">
							<?php _e( 'Select all', $ps ) ?>
						</label>
					</th>
					<td id="options-<?php echo esc_attr( $name )?>">
					<?php foreach ( $fields as $field_name => $field_value ): ?>
						<div style="width: 32%; float:left;">
							<label>
								<input type="checkbox" name="<?php echo esc_attr( $name ) ?>[]" value="<?php echo esc_html( $field_value )?>">
								<?php echo esc_html( $field_name )?>
							</label>
						</div>
					<?php endforeach;?>
					</td>
				</tr>
			<?php endforeach;?>
			</tbody>
		</table>
	</form>

	<p class="submit">
		<button id="export-csv" name="submit-csv" class="button-primary" type="submit">
			<?php _e( 'Export', $ps )?>
		</button>
	</p>
	<div id="progressbar"></div>
</div>