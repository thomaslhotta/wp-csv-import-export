<?php
/**
 * Exports Users
 *
 * Class CIE_Exporter_User
 */
class CIE_Module_Users_Exporter extends CIE_Exporter
{
	public function get_supported_fields()
	{
		return array (
			'user',
			'usermeta',
			'buddypress',
		);
	}

	public function get_main_elements( array $search, $offset, $limit )
	{
		$role = 'Subscriber';
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

		$return = array(
			'total' => $query->total_users,
		);

		foreach ( $query->get_results() as $user ) {
			$element = new CIE_Element();
			$element->set_element( $user, $user->ID, $user->ID );

			$return['elements'][] = $element;
		}

		return $return;
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