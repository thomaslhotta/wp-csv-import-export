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

$ps = $this->get_plugin_slug()

?>

<div class="wrap">
	<?php screen_icon(); ?>
	<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>
	
	<form method="post" enctype="multipart/form-data">
		<ul>
			<?php if( !isset( $stopped_at ) ||  0 === $stopped_at ):?>
				<li>
					<label for="renames"><?php _e( 'Rename fields', $ps )?></label></br>
					<textarea rows="15" cols="150" id="renames" name="renames"></textarea>
				</li>
				<li>
					<label for="transforms"><?php _e( 'Transform fields', $ps )?></label></br>
					<textarea rows="15" cols="150" id="transforms" name="transforms"></textarea>
				</li>
				
			<?php else :?>
				<li>
					<?php ?>
				</li>
				<input type="hidden" name="renames" value="<?php echo esc_attr( $renames ) ?>" />
				<input type="hidden" name="transforms" value="<?php echo esc_attr( $transforms ) ?>" />
				<input type="hidden" name="stopped_at" value="<?php echo esc_attr( $stopped_at ) ?>" />
				<input type="hidden" name="checksum" value="<?php echo esc_attr( $checksum ) ?>" />
		
			<?php endif;?>
			<li>
				<label for="upload_image"><?php _e( 'CSV', $ps )?></label></br>
	     		<input type="file" name="csv" id="uploadfiles" size="35" class="csv" required/>
			</li>
		</ul>
    	<?php wp_nonce_field( 'upload-csv', 'verify' )?>
	    <p class="submit"><input class="button" type="submit" value="<?php _e( 'Import', $ps )?>"></input></p>
    </form>

    <?php 
		if ( isset( $success_count ) ) {
			printf( __( '%d successfull insertions in %s seconds', $ps ) , $success_count, $execution_time );
		}
    ?>
    
    <?php if( !empty( $errors ) ):?>
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
    <?php endif;?>
</div>