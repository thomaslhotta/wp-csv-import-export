<?php
require_once  dirname( __FILE__ ) . '/class-cie-handler-abstract.php';

/**
 * 
 * @package   WP_CSV_User_Import
 * @author    Thomas Lhotta
 * @license   GPL-2.0+
 * @link      http://example.com
 * @copyright 2013 Thomas Lhotta
 *
 */
class CIE_Handler_Transform extends CIE_Handler_Abstract
{
	/**
	 * An array of transform definitions.
	 * 
	 * @var array
	 */
	protected $field_transforms = null;
	
	/**
	 * Transform defaults
	 * 
	 * @var array
	 */
	protected $defaults = array(
	    'replace'         => array( 'values' => array() ),
		'hash'            => array(),
		'multi_replace'   => array( 'delimiter' => ',' ),
		'extract'         => array(
			'delimiter' => ',',
			'rename'    => array(),
		),
		'string_function' => array( 'functions' => ',' ),
		'generate'        => array( 
			'parts' => array(
		    	'from' => 'csv_row',
				'function' => array(), 
			)	    
		),
		'to_array' => array( 
			'delimiter'   => '',
			'index_offset' => 0, 
		), 
	);

	/**
	 * Allowed string functions
	 * 
	 * @var array
	 */
	protected $string_functions = array(
	    'trim',
		'rtrim',
		'ltrim',
		'ucfirst',
		'ucwords',
		'lcfirst',
		'lcwords',
		'strtoupper',
		'strtolower',
		'strval',
		'substr',
		'str_pad',
		'str_replace',
		'crc32',
		'md5',
	);
	
	public function __construct( $transforms = null )
	{
		if ( is_array( $transforms ) ) {
			foreach ( $transforms as $field_name => $transform ) {
				$this->add_field_transforms( $field_name, $transform );
			}
		}
	} 
	
	/**
	 * (non-PHPdoc)
	 * @see CIE_Handler_Abstract::__invoke()
	 */
	public function __invoke( ArrayObject $row, $number )
	{
		// Do nothing if no transforms were found
		if ( false === $this->field_transforms ) {
			return false;
		}
		
		// Find transform defintions
		if ( null === $this->field_transforms ) {
			$this->read_field_transforms( $row );
			
			if ( is_array( $this->field_transforms ) ) {
				return true;
			} else {
				return false;
			}
		}
		
		$iterator = $row->getIterator();

		// Preform extraction transforms
		foreach ( $this->field_transforms as $name => $transforms ) {
			foreach ( $transforms as $transform ) {
				if ( 'extract' !== $transform['type'] ) {
					continue;
				}
				$this->transform_extract( $row, $name, $transform );
			}
		}
		
		$iterator->rewind();
		
		// Preform transforms
		foreach ( $iterator as $name => $value ) {
			if ( !isset( $this->field_transforms[$name] ) ) {
				continue;
			}
			
			foreach ( $this->field_transforms[$name] as $transform ) {
				switch ( $transform['type'] ) {
				    case 'hash':
				    	$this->transform_hash( $iterator, $name, $transform );
				    	break;
				    case 'multi_replace':
				    	$this->transform_multi_replace( $iterator, $name, $transform );
				    	break;
				    case 'replace':
				    	$this->transform_replace( $iterator, $name, $transform );
				    	break;
				    case 'string_function':
				    	$this->transform_string_function( $iterator, $name, $transform );
				    	break;
				    case 'to_array':
				    	$this->transform_to_array( $iterator, $name, $transform );
				    	break;
				}
			}
		}
		
		// Preform generations transforms.
		foreach ( $this->field_transforms as $name => $transforms ) {
			foreach ( $transforms as $transform ) {
				if ( 'generate' !== $transform['type'] ) {
					continue;
				}
				$this->transform_generate( $row, $name, $transform, $number );
			}
		}
		
		return false;
	}
	
	/**
	 * Reads and sanitizes the transform definitions.
	 * 
	 * @param ArrayObject $row
	 */
	public function read_field_transforms( ArrayObject $row )
	{
		$found = false;
		
		foreach ( $row as $name => $transform ) {
			if ( '{' !== substr( $transform , 0 , 1 ) ) {
				continue;
			}
			
			$transform = json_decode( $transform, true );
			
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				$this->throw_exception( 'Invalid transform json!' );
			}			
			
			$this->add_field_transforms( $name , $transform );
			$found = true;
		}
			
		// Prevent transform handler from running if no transform definitions where found.
		if ( false === $found ) {
			$this->field_transforms = false;
		}
	}
	
	public function add_field_transforms( $field_name, array $transforms )
	{
		if ( !array_key_exists( 0, $transforms ) ) {
			$transforms = array( $transforms );
		}
		
		foreach ( $transforms  as $transform ) {
			$transform = new ArrayObject( $transform );
				
			if ( !isset( $transform['type'] ) ) {
				$transform['type'] = 'replace';
			}
				
			// Check for valid types
			if ( !in_array( $transform['type'], array_keys( $this->defaults ) ) ) {
				$this->throw_exception( 'Invalid transform type "' . $transform['type'] . '"!'  );
			}
			// Special sanitizing
				
			switch ( $transform['type'] ) {
			    case 'generate':
			    	$this->sanitize_generate( $transform );
			    	break;
			    case 'string_function':
			    	$this->sanitize_string_function( $transform );
			    	break;
			}
				
				
			// Set defaults
			$this->field_transforms[$field_name][] = array_merge( $this->defaults[$transform['type']], $transform->getArrayCopy() );
		}
	}
	
	/**
	 * Extracts fields from JSON or a delimited string.
	 * 
	 * @param ArrayObject $row
	 * @param string $name
	 * @param array $transform
	 */
	protected function transform_extract( ArrayObject $row, $name, array $transform )
	{
		$value = ltrim( $row[$name] );
		
		$row->offsetUnset( $name );

		if ( empty( $value ) ) {
			return;
		}
		
		$rename = $transform['rename'];

		// Detect json
		if ( '{' === substr( $value, 0 ,1 ) ) {
			$extracted = json_decode( $value, true );
		} elseif ( 'address' === $transform['delimiter'] ) {
			preg_match( '/^\D*(?=\d)/', $value, $m );
			if ( empty( $m ) ) {
				$numpos = strlen( $value ) - 1;
			} else {
				$numpos = strlen( $m[0] );
			}
			
			$extracted[0] = trim( substr( $value, 0, $numpos - 1 ) );
			$extracted[1] = trim( substr( $value, $numpos ) );
		} else {
			$extracted = explode( $transform['delimiter'] , $value );
		}
		
		// Nothing to extract found
		if ( !is_array( $extracted ) ) {
			return;
		}
		
		foreach ( $extracted as $extr_name => $value ) {
			// Maybe rename
			if ( isset( $rename[$extr_name] ) ) {
				$extr_name = $rename[$extr_name];
			}
			
			$row->offsetSet( $extr_name, $value );
		}
	}

	/**
	 * Hashes the field with the standard Wordpress hashing function.
	 * 
	 * @param ArrayIterator $row
	 * @param string $name
	 * @param array $transform
	 */
	protected function transform_hash( ArrayIterator $row, $name, array $transform )
	{
		$row[$name] = wp_hash_password( $row[$name] ); 
	}
	
	/**
	 * Replaces the field value according to the definition.
	 * 
	 * @param ArrayIterator $row
	 * @param string $name
	 * @param array $transform
	 */
	function transform_replace( ArrayIterator $row, $name, array $transform )
	{
		$value = $row[$name];
		if ( isset( $transform['values'][$value] ) ) {
			$row->offsetSet( $name, $transform['values'][$value] );
		}
	}
	
	/**
	 * Sanitizes the string_function transform defintion
	 * 
	 * @param ArrayObject $transform
	 */
	public function sanitize_string_function( ArrayObject $transform )
	{
		if ( !isset( $transform['function'] ) ) {
			$transform['function'] = array();
		}
		
		
		$transform['function'] = $this->sanitize_functions( $transform['function'] );
	}
	
	/**
	 * Performs php string functions on a field.
	 * 
	 * @param ArrayIterator $row
	 * @param string $name
	 * @param array $transform
	 */
	protected function transform_string_function( ArrayIterator $row, $name, array $transform ) 
	{
		$functions = (array) $transform['function'];
		
		$value = $row[$name];
 		
		foreach ( $functions as $function => $params ) {
			$value = $this->call_string_function( $value, $function, $params );
		}
		
		$row->offsetSet( $name , $value );
	}
	
	/**
	 * Replaces values in a delimited string
	 * 
	 * @param ArrayIterator $row
	 * @param string $name
	 * @param array $transform
	 */
	protected function transform_multi_replace( ArrayIterator $row, $name, array $transform )
	{
		$values = explode( $transform['delimiter'], $row[$name] );
	
		foreach ( $values as $pos => $val ) {
			if ( isset( $transform['values'][$val] ) ) {
				$values[$pos] = $transform['values'][$val];
			}
		}
		
		$row[$name] = implode( $transform['delimiter'], $values );
	}
	
	protected function transform_to_array( ArrayIterator $row, $name, array $transform )
	{
		if ( '' === $transform['delimiter'] ) {
			$row->offsetSet( $name , json_decode( $row->offsetGet( $name ), true ) );
			
			if ( JSON_ERROR_NONE !== json_last_error() ) {
				$this->throw_exception( 'Could not convert JSON to Array on field "' . $name .  '".' );
			}
			return;
		} 
		
		$delimited = array();
		
		foreach ( explode( $transform['delimiter'], $row->offsetGet( $name ) ) as $key => $value ) {
			$delimited[ $key + $transform['index_offset'] ] = $value;
		}
		
		$row->offsetSet( $name, $delimited );
	}
	
	/**
	 * Sanitizes generate transform definitions.
	 * 
	 * @param ArrayObject $transform
	 * @return ArrayObject
	 */
	public function sanitize_generate( ArrayObject $transform )
	{
		if ( isset( $transform['parts'] ) ) {
			$sanitized = array();

			foreach ( $transform['parts'] as $part ) {
				if ( !isset( $part['function'] ) ) {
					$part['function'] = array();
				} 
				
				$part['function'] = $this->sanitize_functions( $part['function'] );
				$sanitized[] = $part;
			}
		}
		
		$transform->offsetSet( 'parts' , $sanitized );
		return $transform;
	}
	
	/**
	 * Generates a field value.
	 * 
	 * @param ArrayObject $row
	 * @param string $name
	 * @param array $transform
	 * @param number $number
	 */
	protected function transform_generate( ArrayObject $row, $name, array $transform, $number )
	{
		$value = null;
		foreach ( $transform['parts'] as $part ) {
			if ( isset ( $part['string'] ) ) {
				$value .= $part['string'];
				continue;
			}
		
			$from = $part['from'];
			
			if ( 'csv_row' === $from ) {
				$value .= $number;
				continue;
			}
			
			if ( !$row->offsetExists( $from ) ) {
				continue;
			}
			
			$new = $row[$from];
			
			if ( isset( $part['function'] ) ) {
				foreach ( $part['function'] as $function => $params ) {
					$new = $this->call_string_function( $new, $function, $params );
				}
			}
			$value .= $new;
		}
		$row->offsetSet( $name , $value );
	}
	
	/**
	 * Sanitizes a functions definition
	 * 
	 * @param unknown $functions
	 * @return multitype:multitype: unknown
	 */
	public function sanitize_functions( $functions )
	{
		$functions = (array) $functions;
		
		$sanitized = array();
		
		foreach ( $functions as $function => $params ) {
			if ( !is_array( $params ) ) {
				$sanitized[$params] = array();
			} else {
				$sanitized[$function] = $params;
			}
		}
		
		foreach ( $sanitized as $function => $params ) {
			if ( !in_array( $function, $this->string_functions ) ) {
				$this->throw_exception( 'Invalid string function "' . $function . '"!'  );
			}
		}
		
		return $sanitized;
	}
	
	protected function call_string_function( $string, $function, $params )
	{
		if ( 'str_replace' === $function ) {
			$params[] = $string;
		} else {
			array_unshift( $params, $string );
		}
		
		return call_user_func_array( $function, $params );
	}
}