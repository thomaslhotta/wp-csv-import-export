<?php 
require_once  dirname( __FILE__ ) . '/class-cie-handler-creator-abstract.php';

class CIE_Handler_Create_Post extends CIE_Handler_Creator_Abstract
{
	protected $post_type;
	
	protected $attachment_processor;
	
	protected $previous_post = 8290 ;
	
	public function __construct( $post_type )
	{
		$this->post_type = $post_type;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see CIE_Handler_Abstract::__invoke()
	 */
	public function __invoke( ArrayObject $row, $number )
	{
		$using_previous = false;
		
		$row['post_type'] = $this->post_type;
		
		$metas = array();
		$attachments = array();
		
	    // Extract metadata 
		foreach ( $row as $name => $value ) {
			if ( $this->compare_prefix( 'meta' , $name ) ) {
				$metas[ $this->remove_prefix( 'meta' , $name ) ] = $value;
			} elseif ( $this->compare_prefix( 'attachment' , $name ) ) {
				$attachments[ $this->remove_prefix( 'attachment' , $name ) ] = $value;
			}
		}
		
		if ( !$row->offsetExists( 'post_title' ) || '' == trim( $row['post_title'] ) ) {
			if( is_int( $this->previous_post ) ) {
				$post_id = $this->previous_post;
				$using_previous = true;
			} else {
				$this->throw_exception( 'No post_title given.' );
			}
		} else {
			$post_id = wp_insert_post( $row->getArrayCopy() );
		}
		
		if ( $post_id instanceof WP_Error ) {
			$this->throw_exception( implode( '.', $id->get_error_messages() ) );
		}
		
		$this->previous_post = $post_id;
		
		
		if ( !empty( $attachments ) ) {
			$import = $this->get_attachment_processor();
			foreach ( $attachments as $meta_key => $url ) {
				
				// Skip empty urls
				if ( '' == $url ) {
					continue;
				}
				
				$details = array(
					'post_title'  => basename( $url ),
					'post_parent' => $post_id,
					'upload_date' => $this->get_current_mysql_time(),
				);
				$attachment_id = $import->process_attachment( $details, $url );
						
				if ( $attachment_id instanceof WP_Error ) {
					continue;
				}
				
				$metas[ $meta_key ] = $attachment_id;
			}
		
		}
		
		
		foreach ( $metas as $meta_key => $meta_value ) {
			if ( $using_previous ) {
				add_post_meta( $post_id, $meta_key, $meta_value, false );
			} else {
				update_post_meta( $post_id , $meta_key, $meta_value );
			}
		}
		
		$this->success_count ++;
		
	}
	
	public function get_resume_data()
	{
		return $this->previous_post;
	}
}