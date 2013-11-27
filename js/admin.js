(function ( $ ) {
	"use strict";

	var batchSize = 10;
	
	$(function () {
	    var form = $( '#csv-import-form' );
	    batchSize = $( 'input[name="batch_size"]' ).val();

	    form.submit( function( event ) {
	        if ( 0 === $( '#use-ajax:checked', form ).length ) {
	            return;
	        }
	        event.preventDefault();
	        if (!window.File || !window.FileReader ) {
	            alert( 'You cannot use ajax with this browser.' );
	        }
	        
	        var reader = new FileReader();
	        reader.onload = handleCsv;
	        reader.readAsText( $( '#csv', form ).get(0).files[0] );
	    });

	});

	
	var rowCount = 0;
	
	
	function handleCsv( e ) {
	    var reader = e.target;
	    var csv = reader.result;
	    
	    // Deal with JSON in cells
	    //csv = csv.replace( new RegExp('""', "g"), '\\"'  );
	    csv = csv.split( "\n" )
	    
	    
	    if ( 1 === csv.length ) {
	        alert( 'No data found.' );
	        return;
	    }
	    
	    var rowNames = csv.shift();
	    
	    rowCount = csv.length;
	    
	    $( '#ajax-progress' ).addClass( 'button-primary' ).text( '0%' ).css({
	        "text_align": "center",
	        "width":      "auto",
	    });
	    clearErrors();
	    
	    post_csv( csv, rowNames, {} )
	}
	
	function post_csv( csv, rowNames, resumeData ) {
	    if ( 1 > csv.length ) {
	        $('#submit-csv', this).removeAttr('disabled');
	        return;
	    } else {
	        $('#submit-csv', this).attr('disabled', 'disabled');
	    }
	    
	    var rowOffset = rowCount - csv.length
	    
	    var csvPart = csv.splice( 0, batchSize );
	    csvPart = rowNames + "\n"  + csvPart.join( "\n" )
	    
	    var data = {
	        renames:    $('#renames').val(),
	        transforms: $('#transforms').val(),
	        csv: csvPart,
	        action: $('#use-ajax').val(),
	        rowOffset: rowOffset,
	        typenow: typenow,
	        resume_data: resumeData
	    };
	    
	    $.post( ajaxurl, data, function( result ) {
	        var progress = 100 - ( csv.length * 100 / rowCount );
	        
	        addErrors( result.errors );
	        
	        $( '#ajax-progress' ).css({
	            display:   'block',
	            width:     progress + '%'
	        }).text( Math.round(progress) + '%' );
	        post_csv( csv, rowNames, result.resume_data );
	    });
	    
	}
	
	function addErrors( errors )
	{
	    var table = $( '#errors' );
	    table.css( 'display', 'block' );
	    table = $( 'tbody', table );
	    
	    if ( 0 === table.length ) {
	        return;
	    }
	    
	    $.each( errors, function( row, message ) {
	        var row = $('<tr></tr>').append( $('<td></td>').text( row ) ).append( $('<td></td>').text( message ) );
	        table.append( row );
	    });
	}
	
	function clearErrors()
	{
	    var table = $( '#errors' ).css( 'display', 'none' ).find('tbody').empty();
	}
	
}(jQuery));