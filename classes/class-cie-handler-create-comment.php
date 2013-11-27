<?php 
require_once  dirname( __FILE__ ) . '/class-cie-handler-creator-abstract.php';

/**
 * Imports comments.
 * 
 * @author Thomas Lhotta
 */
class CIE_Handler_Create_Comment extends CIE_Handler_Creator_Abstract
{
	/*
	 * An array of import ids. Used to identify already imported comments.
	 */
	protected $import_ids = array();

	/**
	 * An array of user emails and their user ids.
	 * 
	 * @var array
	 */
	protected $user_ids = array();
	
	/**
	 * An array of post names or titles and post ids.
	 * 
	 * @var array
	 */
	protected $post_ids = array();
	
	protected $overwrite;
	
	public function __construct( $overwrite = false )
	{
		$this->overwrite = $overwrite;
		
		global $wpdb;
		
		$query = $wpdb->query( "select commend_id, meta_value FROM $wpdb->commentmeta WHERE meta_key = 'import_id' " );
		
		foreach ( $wpdb->last_result as $result ) {
			$this->import_ids[ $result->meta_value ] = $result->comment_id;
		}
		
		//$this->get_db_wrapper()->add_allowed('INSERT INTO `wp_commentmeta`');
		
	}
	
	/**
	 * (non-PHPdoc)
	 * @see CIE_Handler_Abstract::__invoke()
	 */
	public function __invoke( ArrayObject $row, $number )
	{

		// Check if import ID exists
		if( $row->offsetExists( 'import_id' ) && !$row->offsetExists( 'comment_id' ) ) {
			if ( isset( $this->import_ids[$row['import_id']] ) ) {
				if ( $this->overwrite ) {
					$row['commend_id'] = $this->import_ids[$row['import_id']];
				} else {
					return true;
				}
			}
		}
		
		// Find the post
		if ( !$row->offsetExists( 'comment_post_ID' ) || !is_numeric( $row['comment_post_ID'] ) ) {
			if ( $row->offsetExists( 'comment_post_name' ) || $row->offsetExists( 'comment_post_title' ) ) {
				if ( $row->offsetExists( 'comment_post_name' ) ) {
					$post_id = $this->get_post_id( $row['comment_post_name'], 'post_name' );
				} else {
					$post_id = $this->get_post_id( $row['comment_post_title'], 'post_title' );
				}
				
				
				if ( !is_numeric( $post_id ) ) {
					$this->throw_exception( 'Comment_post_name or title does not exist.' );
				}
				
				$row['comment_post_ID'] = $post_id;
				
			} else {
				$this->throw_exception( 'Comment contains neither comment_post_id nor comment_post_name.' );
			}
			
		}  
		
		// Find comment parent
		if ( $row->offsetExists( 'import_parent_id' ) && is_numeric( $row['import_parent_id'] ) ) {
			$parent_id = $row['import_parent_id'] ;
			if ( isset( $this->import_ids[$row['import_parent_id']] ) ) {
				$row['comment_parent'] = $this->import_ids[$row['import_parent_id']];
			} else {
				$this->throw_exception( 'Could not find comment parent with import_id ' . $parent_id . '.' );
			}
		}
		
		// Find comment user
		if ( !$row->offsetExists('user_id') && $row->offsetExists( 'comment_author_email' ) ) {
			
			if ( isset ( $this->user_ids[$row['comment_author_email']] ) ) {
				$row['user_id'] = $this->user_ids[$row['comment_author_email']];
			} else {
				$user = get_user_by( 'email', $row['comment_author_email'] );
					
				if ( $user instanceof WP_User ) {
					$user_id = $user->ID;
					$row['user_id'] = $user_id;
				
					$this->user_ids[$row['comment_author_email']] = $user_id;
				} else {
					$this->user_ids[$row['comment_author_email']] = false;
				}
				
				
			}
		}
		
		$comment_id = wp_insert_comment( $row->getArrayCopy() );
		
		$this->success_count ++;
		
		
		if ( $row->offsetExists( 'import_id' ) ) {
			$this->import_ids[ $row['import_id'] ] = $comment_id;
			update_comment_meta( $comment_id , 'import_id', $row['import_id'] );
		}		
		
		
		// Extract metadata
		foreach ( $row as $name => $value ) {
			if ( $this->compare_prefix( 'meta' , $name ) && !empty( $value ) ) {
				update_comment_meta( $comment_id ,  $this->remove_prefix( 'meta' , $name ) , $value );
			} 
		}
		
		// Flush cache to prevent memory leaks
		wp_cache_flush();
	}
	
	/**
	 * Returns the post if for a post title or name.
	 * 
	 * @param string $name
	 * @param string $type
	 * @return multitype:|Ambigous <multitype:, unknown>
	 */
	public function get_post_id( $name, $type = 'post_name' )
	{
		if ( isset( $this->post_ids[$name] ) ) {
			return $this->post_ids[$name];
		}
		
		global $wpdb;
		
		$query = $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE $type = %s", $name );
		
		$wpdb->query($query);
		
		$id = $wpdb->get_var( $query );

		if ( !is_numeric( $id ) ) {
			die( print_r(array( $id, $query )) );
		}
		
		
		$this->post_ids[$id] = $id;
		return $id;		
	}
	
}