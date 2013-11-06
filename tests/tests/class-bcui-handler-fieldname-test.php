<?php

require_once dirname( __FILE__ ) . '/../../classes/class-cie-handler-fieldname.php';

/**
 * 
 * @author Thomas Lhotta
 */
class CIE_Handler_Fieldname_Test extends PHPUnit_Framework_TestCase
{
    protected $handler;
    
    
    public function setUp()
    {
    	$this->handler = new CIE_Handler_Fieldname(
    		array(
    	    	'username' => 'uname',
    			'password' => 'pwd',
    		) 
    	);
    }

    /**
     * @expectedException CIE_Hander_Exception
     */
    public function test_invalid_columns()
    {
		$test = new ArrayObject( array( null ) );
		$handler = $this->handler;
    	$handler( $test, 0 );
    }
    
    public function test_read_fieldnames_keys()
    {
    	$handler = $this->handler;
    	
    	$header = new ArrayObject(
    		array( 
	    	    'username',
	    		'password' ,
    		)
    	);
    	
    	$header_test = $header;
    	
    	$this->assertTrue( $handler( $header_test, 0 ) );
    	$this->assertEquals( $header, $header_test );
    	
    	$row = new ArrayObject( array( 'user1', 'password1' ) );
    	
    	$this->assertFalse( $handler( $row, 1) );
    	
    	$this->assertCount( 2, $row );
    	$this->assertEquals( 'user1', $row['uname'] );
    	$this->assertEquals( 'password1', $row['pwd'] );
    }
    
    /**
     * @expectedException CIE_Hander_Exception
     */
    public function test_dublicated_keys()
    {
    	$handler = $this->handler;
    	$header = new ArrayObject(
    		array(
    			'username',
    			'password' ,
    			'pwd',
    		)
    	);
    	 
    	$handler( $header, 0 );
    	 
    }
}