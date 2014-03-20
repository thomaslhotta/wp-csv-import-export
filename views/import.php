<?php
/**
 * Represents the view for the administration dashboard.
 *
 * This includes the header, options, and other information that should provide
 * The User Interface to the end user.
 *
 * @package   Plugin_Name
 * @author    Your Name <email@example.com>
 * @license   GPL-2.0+
 * @link      http://example.com
 * @copyright 2013 Your Name or Company Name
 */

$ps = $this->get_plugin_slug();

$url = get_admin_url( get_current_blog_id(), get_current_screen()->parent_file );
$url = add_query_arg( 'import' , '', $url );

if ( !isset( $batch_size ) ) {
	$batch_size = 50;
}


if ( !isset( $errors ) ) {
	$errors = array();
}

?>

<div class="wrap">
	<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>
	
	<form id="csv-import-form" method="post" enctype="multipart/form-data">
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row"><label for="csv"><?php _e( 'CSV File', $ps )?></label></th>
					<td><input type="file" name="csv" id="csv" size="35" class="csv" required/></td>
				</tr>
				<?php if ( isset( $use_ajax ) ):?>
				<tr valign="top">
					<th scope="row"><label for="csv"><?php _e( 'Use AJAX', $ps )?></label></th>
					<td>
						<label for="use-ajax"><input type="checkbox" name="use_ajax" id="use-ajax" value="<?php echo esc_attr( $use_ajax )?>"/><?php _e( 'Use AJAX', $ps )?></label></br>
						<p class="description">
							<?php _e( 'Experimental: ', $ps ) ?> 
							<?php _e( 'Tick this option if you are importing very large CSV files', $ps ) ?> 
						</p>
						<input type="hidden" name="batch_size" value="<?php echo esc_attr( $batch_size ) ?>" />
					</td>
				</tr>
				<?php endif;?>
				<?php if( !isset( $stopped_at ) ||  0 === $stopped_at ):?>
				<tr valign="top">
					<th scope="row"><label for="renames"><?php _e( 'Rename fields', $ps )?></label></th>
					<td>
						<textarea rows="5" cols="50" id="renames" name="renames">{}</textarea>
					</td>
				</tr>
				<tr valign="top">
					<th <label for="transforms"><?php _e( 'Transform fields', $ps )?></label></th>
					<td>
						<textarea rows="5" cols="50" id="transforms" name="transforms">{}</textarea>
					</td>
				</tr>
						
					<?php else :?>
						<li>
							<?php ?>
						</li>
						<input type="hidden" name="renames" value="<?php echo esc_attr( $renames ) ?>" />
						<input type="hidden" name="transforms" value="<?php echo esc_attr( $transforms ) ?>" />
						<input type="hidden" name="stopped_at" value="<?php echo esc_attr( $stopped_at ) ?>" />
						<input type="hidden" name="checksum" value="<?php echo esc_attr( $checksum ) ?>" />
						<input type="hidden" name="resume_data" value="<?php echo esc_attr( $resume_data ) ?>" />
				
					<?php endif;?>
		    	<?php wp_nonce_field( 'upload-csv', 'verify' )?>
			    
		    </tbody>
	    </table>
	    <p class="submit"><input id="submit-csv" name="submit-csv" class="button-primary" type="submit" value="<?php _e( 'Import', $ps )?>"></input></p>
    </form>
    
    <div id="ajax-progress">
    </div>

    <?php 
		if ( isset( $success_count ) ) {
			printf( __( '%d successfull insertions in %s seconds', $ps ) , $success_count, $execution_time );
		}
    ?>
    
    <?php if( !empty( $errors ) || isset( $use_ajax ) ):?>
    	<div id="errors"<? echo empty( $errors) ? ' style="display:none;"' : ''?>>
	    	<h3><?php _e( 'Errors', $ps )?></h3>
		    <table class="widefat">
		    	<thead>
		    		<tr>
		    			<th><?php _e( 'Row', $ps )?></th>
		    			<th><?php _e( 'Message', $ps )?></th>
		    		</tr>
		    	</thead>
				<tbody>
					<?php foreach ($errors as $row => $message):?>
					<tr>
						<td><?php echo esc_html( $row )?></td>
						<td><?php echo esc_html( $message )?></td>
					</tr>
					<?php endforeach;?>
				</tbody>
		    	
		    </table>
	    </div>
    <?php endif;?>
</div>