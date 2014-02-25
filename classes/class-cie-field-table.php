<?php
class CIE_Field_Table extends WP_List_Table 
{

	protected $fields = array();
	
	protected $name = '';
	
	
	/**
	 * Constructor, we override the parent to pass our own arguments
	 * We usually focus on three parameters: singular and plural labels, as well as whether the class supports AJAX.
	 */
	function __construct( $name, $fields ) {
		
		foreach ( $fields as $field ) {
			if ( !is_array( $field ) ) {
				$field = array(
				    'name' => $field,
					'id'   => $field,
				);
			}
			$this->items[] = $field;
			
		}
		
		$this->name = $name;
		parent::__construct( array(
				'singular'=> 'wp_list_text_link', //Singular label
				'plural' => 'wp_list_test_links', //plural label, also this well be one of the table css class
				'ajax'	=> false //We won't support Ajax for this table
		) );
		
		$columns = $this->get_columns();
		$sortable = $this->get_sortable_columns();
		
		$this->_column_headers = array( $columns, array(), $sortable );
	}

	function get_columns()
	{
		return array(
			'box'=> '<input type="checkbox" />',
			'field_name'=>__('Name'),
			//'col_field_search'=>__('Url'),
		);
	}
	
	public function get_sortable_columns()
	{
		return $sortable = array(
			'col_field_name'=>'link_name',
		);
	}
	
	protected function column_box( $item ) 
	{
		return '<input type="checkbox" name="' . $this->name . '[]" value="' . $item['id'] . '" />';
	}
	
	protected function column_field_name( $item )
	{
		return esc_html( $item['name'] );
	}
	
	
	public function ydisplay() 
	{
		print_r( $this->get_column_info() );
		print_r(get_column_headers( $this->screen ));
		print_r(  apply_filters( 'manage_' . $this->screen->id . '_columns', array() ) );
		die();
	}
	
	
}