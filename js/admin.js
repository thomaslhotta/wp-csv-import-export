// the semi-colon before the function invocation is a safety
// net against concatenated scripts and/or other plugins
// that are not closed properly.
;(function ( $, window, document, undefined ) {
	"use strict";

	/**
	 * Register ajax transports for blob send/recieve and array buffer send/receive via XMLHttpRequest Level 2
	 * within the comfortable framework of the jquery ajax request, with full support for promises.
	 *
	 * Notice the +* in the dataType string? The + indicates we want this transport to be prepended to the list
	 * of potential transports (so it gets first dibs if the request passes the conditions within to provide the
	 * ajax transport, preventing the standard transport from hogging the request), and the * indicates that
	 * potentially any request with any dataType might want to use the transports provided herein.
	 *
	 * Remember to specify 'processData:false' in the ajax options when attempting to send a blob or arraybuffer -
	 * otherwise jquery will try (and fail) to convert the blob or buffer into a query string.
	 */
	$.ajaxTransport("+*", function(options, originalOptions, jqXHR){
		// Test for the conditions that mean we can/want to send/receive blobs or arraybuffers - we need XMLHttpRequest
		// level 2 (so feature-detect against window.FormData), feature detect against window.Blob or window.ArrayBuffer,
		// and then check to see if the dataType is blob/arraybuffer or the data itself is a Blob/ArrayBuffer
		if (window.FormData && ((options.dataType && (options.dataType === 'blob' || options.dataType === 'arraybuffer')) ||
			(options.data && ((window.Blob && options.data instanceof Blob) ||
			(window.ArrayBuffer && options.data instanceof ArrayBuffer)))
			))
		{
			return {
				/**
				 * Return a transport capable of sending and/or receiving blobs - in this case, we instantiate
				 * a new XMLHttpRequest and use it to actually perform the request, and funnel the result back
				 * into the jquery complete callback (such as the success function, done blocks, etc.)
				 *
				 * @param headers
				 * @param completeCallback
				 */
				send: function(headers, completeCallback){
					var xhr = new XMLHttpRequest(),
						url = options.url || window.location.href,
						type = options.type || 'GET',
						dataType = options.dataType || 'text',
						data = options.data || null,
						async = options.async || true,
						key;

					xhr.addEventListener('load', function(){
						var response = {}, status, isSuccess;

						isSuccess = xhr.status >= 200 && xhr.status < 300 || xhr.status === 304;

						if (isSuccess) {
							response[dataType] = xhr.response;
						} else {
							// In case an error occured we assume that the response body contains
							// text data - so let's convert the binary data to a string which we can
							// pass to the complete callback.
							response.text = String.fromCharCode.apply(null, new Uint8Array(xhr.response));
						}

						completeCallback(xhr.status, xhr.statusText, response, xhr.getAllResponseHeaders());
					});

					xhr.open(type, url, async);
					xhr.responseType = dataType;

					for (key in headers) {
						if (headers.hasOwnProperty(key)) xhr.setRequestHeader(key, headers[key]);
					}
					xhr.send(data);
				},
				abort: function(){
					jqXHR.abort();
				}
			};
		}
	});


	wp.csvie = {
		model: {},
		view: {}
	};

	/**
	 * Model for handling and persisting settings
	 */
	wp.csvie.model.Settings = Backbone.Model.extend({
		localStorage: new Backbone.LocalStorage( 'csv-setting' ),
		initialize: function() {
			this.bind( 'change:ajax-action', function() {
				this.set( 'action', this.get( 'ajax-action' ) );
			});
		}
	});

	wp.csvie.model.Error = Backbone.Model.extend({
	});

	wp.csvie.model.Errors = Backbone.Collection.extend({
		model : wp.csvie.model.Error
	});

	/**
	 * Model that holds a single element that is to be converted to a CSV row
	 */
	wp.csvie.model.Element = Backbone.Model.extend({
		defaults: {
			blobs: {}
		},
		/**
		 * Fetchs attachments of the current element as array buffers
		 * @returns {*}
		 */
		getAttachments: function() {
			var that = this,
				calls = [],
				deferred = $.Deferred();

			if ( this.get('Attachments') ) {
				_.each( this.get( 'Attachments' ).split( ';' ), function( url ) {
					if( 'https:' === window.location.protocol ){
						url = url.replace( 'http://', 'https://' );
					}

					calls.push($.ajax({
						dataType:'arraybuffer',
						type:'GET',
						url: url,
						async: false,
						success: function( data ) {
							// Use the name of the current image as index
							that.get( 'blobs' )[ url.replace( /^.*[\\\/]/, '' ) ] = data;
						}
					}));
				}, this );

				// Resolve the current defer once all
				$.when.apply( $, calls ).done(function() {
					deferred.resolve( this );
				});
			} else {
				deferred.resolve( this );
			}

			return deferred;
		}
	});

	/**
	 * Collection for element models. Handles pagination
	 */
	wp.csvie.model.CSV = Backbone.PageableCollection.extend({
		settings: null,
		model: wp.csvie.model.Element,
		mode: 'server',
		state: {
			pageSize: 100,
			currentPage: 1
		},
		initialize: function( models, options ){
			// Manually add settings variable
			this.settings = options.settings;
			// Setup correct url
			this.url = ajaxurl + '?action=' + options.settings.get( 'ajax-action' );
		},

		/**
		 * Overwrites original fetch to process all elements after loading
		 * @param options
		 * @returns {*}
		 */
		fetch: function( options ) {
			if ( ! $.isPlainObject( options ) ) {
				options = {};
			}

			var that = this;
			this.processElementsDeferred = $.Deferred();

			options.data = this.settings.toJSON();

			Backbone.PageableCollection.prototype.fetch.apply( this, [ options ] ).done(function(){
				that.processElements();
			});

			return this.processElementsDeferred.promise();
		},
		/**
		 * Starts iterating elements after load
		 */
		processElements: function() {
			this.currentElement = -1;
			this.processElement();
		},
		/**
		 * Processes single element
		 *
		 * @returns {wp.csvie.model.CSV}
		 */
		processElement: function() {
			this.currentElement += 1;
			if ( this.at( this.currentElement ) ) {
				this.trigger( 'processElement' );
				this.at( this.currentElement ).getAttachments().done( $.proxy( this.processElement, this ) );
			} else {
				this.processElementsDeferred.resolve();
			}
			return this;
		}
	});

	/**
	 * Model for handling the progress indicator
	 */
	wp.csvie.model.Progress = Backbone.Model.extend({
		defaults: function() {
			return {
				total: 1,
				current: 0,
				time: Date.now(),
				rate: 0
			};
		},
		/**
		 * Increments to progress indicator by 1
		 */
		tick: function() {
			this.set( 'current', this.get( 'current' ) + 1 );
		},
		/**
		 * Returns the progress percentage
		 *
		 * @returns {number}
		 */
		getPercent: function() {
			if ( ! this.get( 'current' ) ) {
				return 0;
			}

			return Math.round( this.get( 'current' ) / this.get( 'total' ) * 100 );
		}
	});

	/**
	 * View for handling settings. Restores settings from model
	 */
	wp.csvie.view.Settings = Backbone.View.extend({
		events: {
			'change': 'readOptions'
		},
		initialize: function() {
			// Create model with current action as id
			this.model = new wp.csvie.model.Settings({
				id: this.$el.find( '[name=ajax-action]' ).val()
			});

			this.model.fetch();
			// Restore options
			this.render();
			// Read back options
			this.readOptions();
		},
		/**
		 * Restores all settings to the ones stored in the model
		 */
		render: function() {
			var that = this;
			var settings = this.model.toJSON();

			this.$el.find( 'input' ).each( function() {
				var input = $(this);
				// Skip hidden inputs
				if ( 'hidden' === input.attr( 'type' ) || ! settings[ input.attr( 'name' ) ]  ) {
					return;
				}

				if ( input.is(':checkbox') ) {
					input.prop('checked',true);
					return;
				}

				that.model.set( input.attr( 'name' ), input.val() );
			});
		},
		/**
		 * Read and store settings
		 */
		readOptions: function() {
			var that = this,
				id = this.model.id;

			// Reset model and restore id
			this.model.clear().set('id', id);

			this.$el.find('input').each( function() {
				var input = $( this );
				if ( input.is( ':checkbox' ) && !input.prop( 'checked' ) ) {
					return;
				}

				that.model.set( input.attr( 'name' ), input.val() );
			});

			this.model.save();
		}
	});

	/**
	 * Handles the progress indicator
	 */
	wp.csvie.view.Progress = Backbone.View.extend({
		tagName: 'span',
		className: 'progress',
		initialize: function() {
			this.model.bind( 'change', _.bind( this.render, this ) );
		},
		/**
		 * Renders the progress indicator
		 *
		 * @returns {wp.csvie.view.Progress}
		 */
		render: function() {
			this.$el.text( ' (' + this.model.getPercent() + '%)');
			this.$el.parent().prop( 'disabled', true );
			return this;
		},
		/**
		 * Resets to progress indicator state
		 */
		reset: function() {
			this.model.clear();
			this.$el.text('');
			this.$el.parent().prop( 'disabled', false );
		}
	});

	/**
	 * Main view for CSV import export
	 */
	wp.csvie.view.View = Backbone.View.extend({
		events: {
			'click .export': 'exportCSV'
		},
		initialize: function() {
			this.csv = [];
			this.attachments = null;

			// Initialize settings
			this.settingsView = new wp.csvie.view.Settings({
				el: this.$el.find( '#export-settings' )
			});

			// Initializes main collection
			this.model = new wp.csvie.model.CSV([],{
				settings: this.settingsView.model
			});

			// Setup progress indicator
			this.progressModel = new wp.csvie.model.Progress();
			this.progressView = new wp.csvie.view.Progress({
				model: this.progressModel
			});

			this.$el.find( '.export' ).append( this.progressView.$el );

			var that = this;
			this.model.on( 'processElement', function() {
				that.progressModel.tick();
			} );

			this.model.on( 'sync', function() {
				that.progressModel.set( 'total', that.model.state.totalRecords );
			} );
		},
		/**
		 * Runs CSV export
		 */
		exportCSV: function() {
			var that = this;
			this.attachments = null;

			var page = this.model.state.currentPage + 1;
			if ( null === this.model.state.totalPages ) {
				page = 1;
			}

			this.model.getPage(page).done(function(){
				that.addElements( that.model );
					if (that.model.hasNextPage()) {
						that.exportCSV();
					} else {
						that.saveCSV();
					}
			});
		},
		/**
		 * Adds elements from the given page of the collection
		 *
		 * @param collection
		 * @returns {wp.csvie.view.View}
		 */
		addElements: function( collection ) {
			var that = this;
			$.each( collection.models, function() {
				that.addAttachments( this.get( 'blobs' ) );
			});

			$.merge( this.csv,collection.toJSON() );
			return this;
		},
		/**
		 * A
		 *
		 * @param attachments
		 * @returns {wp.csvie.view.View}
		 */
		addAttachments: function( attachments ){
			if ($.isEmptyObject( attachments ) ) {
				return this;
			}

			if ( !this.attachments ) {
				this.attachments = new JSZip();
			}

			var that = this;
			$.each( attachments, function( name,blob ) {
				that.attachments.file( 'attachments/'+ name, blob );
			});

			return this;
		},
		/**
		 * Saves the current export
		 */
		saveCSV: function() {
			var name = 'export.csv',
				csv  = Papa.unparse( this.csv),
				blob;

			if ( this.attachments ) {
				// If attachments where downloaded save the files as zip
				this.attachments.file( name, csv );
				name = name + '.zip';
				blob = this.attachments.generate( { type:'blob' } );
			} else {
				// Else save as namel text
				 blob = new Blob([ csv ], { type: 'text/csv;charset=utf-8' });
			}
			saveAs(blob,name);

			this.progressView.reset();
		}
	});

	wp.csvie.view.Errors = Backbone.View.extend({
		initialize: function() {
			this.model = new wp.csvie.model.Errors();
			this.model.on( 'add', _.bind( this.appendError, this ) );
		},
		reset: function() {
			this.model.reset();
			this.$el.hide().find( 'tr' ).remove();
		},
		appendError: function( model ) {
			var row = $( '<tr>' ),
			    ul = $( '<ul>' );

			row.append( $( '<td>' ).text( model.get( 'row' ) ) );

			_.each( model.get( 'errors' ), function( error ){
				ul.append( $( '<li>' ).text( error ) );
			});
			row.append( $( '<td>' ).append( ul ) );

			this.$el.show().find('table').append(row);
		}

	});


	/**
	 * Main view for CSV import export
	 */
	wp.csvie.view.ImportView = Backbone.View.extend({
		events: {
			'click button' : 'importCSV'
		},
		initialize: function() {
			this.elements = [];

			this.errorsView = new wp.csvie.view.Errors({
				el: this.$el.find( '#errors' )
			});

			// Setup progress indicator
			this.progressModel = new wp.csvie.model.Progress();
			this.progressView = new wp.csvie.view.Progress({
				model: this.progressModel
			});

			this.$el.find( 'button' ).append( this.progressView.$el );
		},
		importCSV: function(e) {
			var that = this,
				textarea = this.$el.find( 'textarea' ),
				file = this.$el.find( 'input[type=file]' ),

				// CSV Parser configuration
				config = {
					header: true,
					complete: function(results, file) {
						that.elements = results.data;
						that.progressModel.set( 'total', that.elements.length );
						that.sendData();
					},
					error: function(err, file, inputElem, reason)
					{
						//@todo Do something on errors
					}
				};

			if ( ! window.FileReader && ! $.trim( textarea.val() ) ) {
				return;
			}

			e.preventDefault();

			this.errorsView.reset();

			if ( $.trim( textarea.val() ) ) {
				Papa.parse( textarea.val(), config );
			} else if ( window.FileReader && 0 < file[0].files.length ) {
				file.parse( { config:config } );
			} else {
				alert ('No file selected' );
			}
		},
		sendData: function() {
			var that = this,
				batch;

			batch = this.elements.slice( 0, 100 );
			this.elements = this.elements.slice( 100 );
			this.progressModel.set( 'current', this.progressModel.get( 'current' ) + batch.length );
			if ( JSON ) {
				batch = JSON.stringify( batch );
			}

			$.ajax({
				url: ajaxurl,
				data: {
					action: this.$el.data( 'action' ),
					data: batch,
					mode: this.$el.find('[name=mode]').val()
				},
				dataType: 'json',
				type: 'POST',
				success: function(response) {
					_.each( response.data.errors, function( errors, row ){
						this.errorsView.model.add( new wp.csvie.model.Error({
							row: row,
							errors : errors
						}));
					}, that );

					if ( 0 < that.elements.length ) {
						that.sendData();
					} else {
						that.progressView.reset();
					}

				}
			});
		}
	});

	var importPage = $( '#csv-import-form' );
	if ( 0 < importPage.length ) {
		var view = new wp.csvie.view.ImportView({
			el: importPage
		});
	}

	var exportPage = $( '#csv-export' );
	if ( 0 < exportPage.length ) {
		var view = new wp.csvie.view.View({
			el: exportPage
		});
	}
})(jQuery, window, document );

(function ( $, document ) {
	"use strict";
	$.fn['checkAll'] = function () {
		return this.each(function () {
			var target = $(this).data('target');
			$(target).find('input[type=checkbox]').prop('checked',$(this).prop('checked')).trigger('change');
		});
	};

	// Data API
	$(document).on('click', '[data-toggle="checked"]', function() {
		$(this).checkAll();
	});
})(jQuery, document);

