<?php

require_once dirname( __FILE__ ) . '/../../classes/class-cie-importer.php';

/**
 * 
 * @author Thomas Lhotta
 */
class CIE_Importer_Test extends PHPUnit_Framework_TestCase
{
    protected $importer;
    
    protected $file;
    
    protected $test_keys = array(
        'email',
    	'user',
    	'password',
    );
    
    
    public function setUp()
    {
    	$this->importer = new CIE_Importer();
    	$this->file = dirname( __FILE__ ) . '/testdata.csv';
    	
    }

    public function test_import_field_names()
    {
		$this->importer->import_file( $this->file );    	
    }
    
    public function test_handler_break()
    {
    	$first = function () { return true; };
    	$second = function() { throw new Exception(); };
    	
    	$this->importer->get_handlers()->insert( $first , 2 );
    	$this->importer->get_handlers()->insert( $second , 1 );
    	
    	$row = array();
    	$this->importer->handle_row( $row, 0 );
    }
    
    /**
     * @expectedException Exception
     */
    public function test_handler_continue()
    {
    	$first = function () { return false; };
    	$second = function() { throw new Exception(); };
    	 
    	$this->importer->get_handlers()->insert( $first , 2 );
    	$this->importer->get_handlers()->insert( $second , 1 );
    	 
    	$row = array();
    	$this->importer->handle_row( $row, 0 );
    }
    
    
    
}