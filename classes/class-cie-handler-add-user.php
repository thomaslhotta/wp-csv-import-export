<?php 
require_once  dirname( __FILE__ ) . '/class-cie-handler-creator-abstract.php';

class CIE_Handler_Add_User extends CIE_Handler_Creator_Abstract
{
	/**
	 * Overwrite mode
	 * 
	 * @var true
	 */
	protected $overwrite;

	public function __construct( $overwrite = true )
	{
		$this->overwrite = $overwrite;
	}

	/**
	 * (non-PHPdoc)
	 * @see CIE_Handler_Abstract::__invoke()
	 */
	public function __invoke( ArrayObject $row, $number )
	{
		// Gravity forms export compatibility
		if ( $row->offsetExists( 'created_by' ) && ! $row->offsetExists( 'ID' ) ) {
			$row->offsetSet( 'ID', $row->offsetGet( 'created_by' ) );
		}

		if ( $row->offsetExists( 'ID' ) ) {
			$user = get_user_by( 'id' , $row->offsetGet( 'ID' ) );
		} elseif ( $row->offsetExists( 'user_email' ) ) {
			$user = get_user_by( 'email', $row->offsetGet( 'user_email' ) );
		} elseif ( $row->offsetExists( 'user_login' ) ) {
			$user = get_user_by( 'login', $row->offsetGet( 'user_login' ) );
		} else {
			require_once __DIR__ . '/class-cie-handler-exception.php';
			throw new CIE_Hander_Exception(
				__( 'No user identification column found. Valid columns are ID, user_email or user_login' )
			);
		}

		if ( ! $user instanceof WP_User ) {
			require_once __DIR__ . '/class-cie-handler-exception.php';
			throw new CIE_Hander_Exception( 'User not found' );
		}

		// Use 'subscriber' if none is given.
		$role = 'subscriber';
		if ( $row->offsetExists( 'role' ) ) {
			$role = $row->offsetGet( 'role' );
		}

		$user->add_role( $role );

		wp_cache_delete( $user->get( 'ID' ), 'user_meta' );

		$this->success_count ++;
	}
}