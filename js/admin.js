// the semi-colon before the function invocation is a safety
// net against concatenated scripts and/or other plugins
// that are not closed properly.
;(function ( $, window, document, undefined ) {
	"use strict";

	// Create the defaults once
	var pluginName = 'cieexporter',
		defaults = {};

	// The actual plugin constructor
	function Plugin( element, options ) {
		this.element = $(element);
		this._defaults = defaults;
		this._name = pluginName;
		if('string' !== $.type(options)) {
			this.options = $.extend( {}, defaults, options) ;
		}
	}

	Plugin.prototype = {
		download: function() {
			var element = this.element,
				target = $(element.data('target')),
			 	data = {},
				csv = [];

			target.find('input').each( function() {
				var input = $(this);
				if ( input.is(':checkbox') && !input.prop('checked') ) {
					return;
				}
				data[input.attr('name')] = input.val();
			});

			var progress = element.find('.progress');
			if ( 0 === progress.length ) {
				progress = $('<span class="progress"></span>');
				element.append(progress);
			}

			var addPart = function() {
				var time = Date.now();

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					dataType: 'json',
					data: data,
					success: function( result ) {
						element.prop('disabled',true);
						var total = result.total;
						var current = result.offset + result.elements.length;

						$.merge( csv, result.elements );

						time = Math.round( ( 300 / ( ( Date.now() - time ) / 1000 ) ) );

						var progressText = ' ' + Math.round( current / total * 100 ) + '%';
						progressText = progressText + ' (' + time + '\/s)';
						progress.text( progressText );

						if ( current < total ) {
							data.offset = current;
							data.limit  = 100;
							addPart();
						} else {
							var blob = new Blob([Papa.unparse(csv)], {type: "text/csv;charset=utf-8"});
							saveAs(blob,'export.csv');
							element.prop('disabled',false);
							progress.remove();
						}
					},
				});
			};

			addPart(data);
		},
		'import': function() {
			var that = this,
				textarea = $(this.element).find('textarea'),
				file = $(this.element).find('input[type=file]'),
				// Converted CSV data
				data = [],
				// The column titles
				firstRow = [],

				// CSV Parser configuration
			 	config = {
					step: function(results, handle) {
						// Extract the first row
						if (0 === firstRow.length) {
							firstRow = results.data[0];
							return;
						}

						// Process data rows
						var row = {};
						$.each(firstRow, function( i, name ) {
							row[name] = results.data[0][i];
						});

						data.push(row);

						// Send batch for every 100 rows
						if (100 === data.length) {
							that.sendData(data,handle);
						}
					},
					complete: function(results, file) {
						// Flush any remaining data
						if (0 < data.length) {
							that.sendData(data);
						}
					},
					error: function(err, file, inputElem, reason)
					{
						//@todo Do something on errors
					}
				}

			this.resetErrors();


			if ($.trim(textarea.val())) {
				Papa.parse( textarea.val(), config );
			} else if ( window.FileReader && 0 < file[0].files.length ) {
				file.parse({config:config});
			} else {
				alert('No file selected');
			}
		},

		sendData: function(data, handle) {
			var that = this;
			if(handle) {
				handle.pause();
			}

			var batch = data;
			if ( JSON ) {
				batch = JSON.stringify(data);
			}

			$.post(
				ajaxurl,
				{
					action: this.element.data('action'),
					data: batch,
					mode: this.element.find('[name=mode]').val()
				},
				function(response) {
					data = [];

					that.addErrors(response.data.errors);

					if(handle) {
						handle.resume();
					}
				}
			);
		},
		addErrors: function(errors) {
			var table = this.element.find('#errors').show().slideDown().find('tbody');
			$.each(errors, function( rowNumber, rowErrors ) {
				table.show();
				var row = $('<tr>');
				var ul = $('<ul>');
				row.append($('<td>').text(rowNumber));

				$.each(rowErrors, function(i, error){
					ul.append($('<li>').text(error));
				});
				row.append($('<td>').append(ul));
				table.append(row);
			});
		},
		resetErrors: function()	{
			this.element.find('#errors').hide().find('tbody').html('');
		}
	};

	$.fn[pluginName] = function ( options ) {
		return this.each(function () {
			if (!$.data(this, "plugin_" + pluginName)) {
				$.data(this, "plugin_" + pluginName,
					new Plugin( this, options ));
			}

			if('string' === $.type(options)) {
				var instance = $.data(this,"plugin_" + pluginName);

				if(instance[options] && $.isFunction(instance[options])) {
					instance[options]();
				}
			}
		});
	};

	// Data API
	$(document).on('click', '[data-toggle="export"]', function (e) {
		e.preventDefault()
		$(this).cieexporter('download');
	});

	$(document).on('submit', '[data-toggle="import-csv"]', function (e) {
		if (window.FileReader || $.trim($(this).find('textarea').val())) {
			e.preventDefault();
			$(this).cieexporter('import');
		}
	});

})(jQuery, window, document );

(function ( $, document ) {
	"use strict";
	$.fn['checkAll'] = function () {
		return this.each(function () {
			var target = $(this).data('target');
			$(target).find('input[type=checkbox]').prop('checked',$(this).prop('checked'));
		});
	};

	// Data API
	$(document).on('click', '[data-toggle="checked"]', function() {
		$(this).checkAll();
	});
})(jQuery, document);

