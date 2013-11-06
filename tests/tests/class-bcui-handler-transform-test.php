<?php

require_once dirname( __FILE__ ) . '/../../classes/class-cie-handler-transform.php';

/**
 * 
 * @author Thomas Lhotta
 */
class CIE_Handler_Transform_Test extends PHPUnit_Framework_TestCase
{
    protected $handler;
    
    
    public function setUp()
    {
    	$this->handler = new CIE_Handler_Transform();
    }

   public function test_no_transforms()
    {
    	$handler = $this->handler;
    	$test = new ArrayObject( array( 'username' => 'user' ) );
    	
    	$this->assertFalse( $handler( $test, 0 ) );
    	$this->assertFalse( $handler( $test, 1 ) );
    }
    
    /**
     * @expectedException CIE_Hander_Exception
     */
    public function test_invalid_json()
    {
    	$handler = $this->handler;
    	$test = new ArrayObject( array( 'username' => '{"""}' ) );
    	$handler( $test, 0 );
    }
    
    /**
     * @expectedException CIE_Hander_Exception
     */
    public function test_invalid_type()
    {
    	$handler = $this->handler;
    	
    	$json = json_encode( array( 'type' => 'something' ) );
    	
    	$trans = new ArrayObject( array( 'username' => $json) );
    	$handler( $trans, 0 );
    }
    
    /**
     * @expectedException CIE_Hander_Exception
     */
    public function test_invalid_string_function()
    {
    	$handler = $this->handler;
    	 
    	$json = json_encode( 
    		array(
    			'function' => 'something',
    			'type' => 'string_function', 
    		)
    	 );
    	 
    	$trans = new ArrayObject( array( 'username' => $json ) );
    	$handler( $trans, 0 );
    }
    
    public function test_replace_transform()
    {
    	$handler = $this->handler;
    	
    	$trans = array(
    		'username' => json_encode(
		    	array(
		    	    'type' => 'replace',
		    		'values' => array(
		    	    	'a' => 'First',
		    			'b' => 'Second',
		    		)
		    	)
    		),
    		'email' => json_encode(
    			array(
    				'type' => 'replace',
    				'values' => array(
    					'.com' => '.net',
    				)
    			)
    		)
		);
    	 
    	$trans = new ArrayObject( $trans );
    	
    	$this->assertTrue( $handler( $trans, 0 ) );
    	
    	$row = new ArrayObject(
    		array( 
	    		'username' => 'a',
	    		'email' => '.com',
    		)
    	);
    	$this->assertFalse( $handler( $row, 1 ) );
    	$this->assertEquals( 'First', $row['username'] );
    	$this->assertEquals( '.net', $row['email'] );
    	
    	$row = new ArrayObject( array( 'username' => 'b' ) );
    	$this->assertFalse( $handler( $row, 2 ) );
    	$this->assertEquals( 'Second', $row['username'] );
    }
    
    public function test_multi_transform()
    {
    	$handler = $this->handler;
    	 
    	$json = json_encode(
    		array(
    			'type' => 'multi_replace',
    			'delimiter' => ',',
    			'values' => array(
    				'a' => 'First',
    				'b' => 'Second',
    			)
    		)
    	);
    	 
    	 
    	$trans = new ArrayObject( array( 'username' => $json) );
    
    	$this->assertTrue( $handler( $trans, 0 ) );
    	 
    	$row = new ArrayObject( array( 'username' => 'a,b' ) );
    	$this->assertFalse( $handler( $row, 1 ) );
    	$this->assertEquals( 'First,Second', $row['username'] );
    }
    
    public function test_to_array_delimited_transform()
    {
    	$handler = $this->handler;
    
    	$json = json_encode(
    		array(
    			'type'         => 'to_array',
    			'delimiter'    => ',',
    			'index_offset' => 1, 
    		)
    	);
    
    
    	$trans = new ArrayObject( array( 'field' => $json) );
    
    	$this->assertTrue( $handler( $trans, 0 ) );
    
    	$row = new ArrayObject( array( 'field' => 'a,b' ) );
    	
    	$this->assertFalse( $handler( $row, 1 ) );
    	$this->assertEquals( array( 1 => 'a', 2 => 'b' ), $row['field'] );
    }
    
    public function test_to_array_json_transform()
    {
    	$handler = $this->handler;
    
    	$json = json_encode(
    		array(
    			'type' => 'to_array',
    		)
    	);
    
    	$trans = new ArrayObject( array( 'field' => $json) );
    
    	$this->assertTrue( $handler( $trans, 0 ) );
    
    	$row = new ArrayObject( array( 'field' => json_encode( array( 'a', 'b' ) ) ) );
    	 
    	$this->assertFalse( $handler( $row, 1 ) );
    	$this->assertEquals( array( 'a', 'b' ), $row['field'] );
    }
    
    /**
     * @expectedException CIE_Hander_Exception
     */
    public function test_to_array_invalid_json_transform()
    {
    	$handler = $this->handler;
    
    	$json = json_encode(
    		array(
    			'type' => 'to_array',
    		)
    	);
    
    	$trans = new ArrayObject( array( 'field' => $json) );
    
    	$this->assertTrue( $handler( $trans, 0 ) );
    
    	$row = new ArrayObject( array( 'field' => '{xxx' ) );
    	$handler( $row, 1 );
    }
    
    
    public function test_string_function_transform()
    {
    	$handler = $this->handler;
    	 
    	$trans = array(
    		'username' => json_encode(
    			array(
    				'type' => 'string_function',
    				'function' => array('ltrim', 'ucfirst'),
    			)
    		),
    		'email' => json_encode(
    			array(
    				'type' => 'string_function',
    				'function' => 'strtoupper',
    			)
    		)
    	);
    
    	$trans = new ArrayObject( $trans );
    	 
    	$this->assertTrue( $handler( $trans, 0 ) );
    	 
    	$row = new ArrayObject(
    		array(
    			'username' => ' user1',
    			'email' => 'test@test.com',
    		)
    	);
    	$this->assertFalse( $handler( $row, 1 ) );
    	$this->assertEquals( 'User1', $row['username'] );
    	$this->assertEquals( 'TEST@TEST.COM', $row['email'] );
    }
    
    
    public function test_extract_transform()
    {
    	$handler = $this->handler;
    
    	$trans = new ArrayObject( 
    		array( 
    			'username' => json_encode(
    				array(
    					'type' => 'extract',
    				)
    			), 
    			'address' => json_encode(
    				array(
    					'type' => 'extract',
    					'delimiter' => 'address',
    					'rename' => array( 'street', 'number' ) 
    				)
    			)
    		) 
    	);
    
    	$this->assertTrue( $handler( $trans, 0 ) );
    
    	$row = new ArrayObject( 
    		array(
    			'username' => json_encode(
		    	    array(
		    	    	'field1' => 'value1',
		    	    	'field2' => 'value2',
		    		)
    			),
    			'address' => "teststreet 112",
    		)
    	);
    	$this->assertFalse( $handler( $row, 1 ) );
    	$this->assertEquals( 'value1', $row['field1'] );
    	$this->assertEquals( 'value2', $row['field2'] );
    	$this->assertEquals( 'teststreet', $row['street'] );
    	$this->assertEquals( '112', $row['number'] );
    }
    
    public function test_generate()
    {
    	$handler = $this->handler;
    	 
    	$trans = array(
    		'username' => json_encode(
    			array(
    				'type' => 'generate',
    				'parts' => array(
    	    			array(
    				    	'from' => 'email'
    					)
    				)
    			)
    		),
    	);
    }
    
    public function test_transform_chain()
    {
    	$handler = $this->handler;
    	
    	$trans = array( 
    		'username' => json_encode(
    			array(
    				'type' => 'extract',
    			)
    		),
    		'field1' => json_encode( 
    	    	array(
    	    		'type' => 'replace', 
    	    		'values' => array(
    	    			'value1' => 'repl1',
    				)
    			)
    		)
    	);
    	
    	$trans = new ArrayObject( $trans );
    	
    	$this->assertTrue( $handler( $trans, 0 ) );
    	
    	$testdata = json_encode(
    		array(
    			'field1' => 'value1',
    			'field2' => 'value2',
    		)
    	);
    	 
    	$row = new ArrayObject( array( 'username' => $testdata ) );
    	$this->assertFalse( $handler( $row, 1 ) );
    	$this->assertEquals( 'repl1', $row['field1'] );
    	$this->assertEquals( 'value2', $row['field2'] );
    }
    
    public function test_sanitize_generate()
    {
    	$def = array(
    	    'type' => 'generate',
    		'parts' => array(
    			array(
    				'from' => 'csv_row',
    				'function' => 'ltrim',
    			),
    			array(
    				'from' => 'csv_row',
    				'function' => array(
    		    		'substr' => array( 1, 2 )
    				)
    			)
    		)
    	);
    	
    	$san = $this->handler->sanitize_generate( new ArrayObject( $def ) );
    	
    	$this->assertEquals( 'generate', $san['type'] );
    	$this->assertEquals( 'csv_row', $san['parts'][0]['from'] );
    	$this->assertEquals( 'csv_row', $san['parts'][1]['from'] );
    	$this->assertEquals( array( 'ltrim' => array() ), $san['parts'][0]['function'] );
    	$this->assertEquals( array( 'substr' => array( 1, 2 ) ), $san['parts'][1]['function'] );
    }
    
    public function test_transform_generate()
    {
    	$def = array(
    		'type' => 'generate',
    		'parts' => array(
    			array(
    				'from' => 'csv_row',
    				'function' => 'ltrim',
    			),
    			array(
    				'from' => 'first_name',
    				'function' => array(
    					'lcfirst',
    				)
    			),
    			array(
    				'from' => 'last_name',
    				'function' => array(
    					'substr' => array( 0, 1 ),
    					'ucfirst',
    				)
    			),
    		),
    	);
    	
    	$handler = $this->handler;
    	
    	$def = new ArrayObject( 
    		array(
    	    	'username' => json_encode( $def )
    		)
    	);
    	
    	$this->assertTrue( $handler( $def, 0 ) );
    	
    	$row = new ArrayObject(
    	    array(
    	    	'first_name' => 'Hans',
    	    	'last_name'  => 'huber',
    		)
    	);
    	
    	$this->assertFalse( $handler( $row, 1 ) );
    	
    	$this->assertEquals( '1hansH', $row['username'] );
    }
    
    
}