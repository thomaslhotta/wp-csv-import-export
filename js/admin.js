// the semi-colon before the function invocation is a safety
// net against concatenated scripts and/or other plugins
// that are not closed properly.
;(function ( $, Backbone, window, document, wp, zip, undefined ) {
	"use strict";

	// Setup zip.js
	var requestFileSystem = window.webkitRequestFileSystem || window.mozRequestFileSystem || window.requestFileSystem;

	var createTempFile = function (callback) {
			var tmpFilename = 'tmp.zip';
			requestFileSystem( TEMPORARY, 4 * 1024 * 1024 * 1024, function( filesystem ) {
				function create() {
					filesystem.root.getFile(tmpFilename, {
						create : true
					}, function(zipFile) {
						callback(zipFile);
					});
				}
				filesystem.root.getFile( tmpFilename, null, function() {}, create);
			});
		};

	var getLocation = function(href) {
		var l = document.createElement("a");
		l.href = href;
		return l.pathname.replace('admin.js','');
	};

	zip.workerScriptsPath = getLocation( document.currentScript.src ) + 'zip.js/';

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

	/**
	 * Errors of a single row/element
	 */
	wp.csvie.model.Error = Backbone.Model.extend({
	});

	/**
	 * Errors collection
	 */
	wp.csvie.model.Errors = Backbone.Collection.extend({
		model : wp.csvie.model.Error
	});

	/**
	 * Model that holds a single element that is to be converted to a CSV row
	 */
	wp.csvie.model.Element = Backbone.Model.extend({
		/**
		 * Fetches attachments of the current element as array buffers
		 *
		 * @todo Error handling when downloading attachment fails
		 * @returns {*}
		 */
		getAttachments: function() {
			var calls = [],
				deferred = $.Deferred();

			if ( this.get( 'Attachments' ) ) {
				_.each( this.get( 'Attachments' ).split( ';' ), function( url ) {
					// Use HTTPS if available
					if ( 'https:' === window.location.protocol ) {
						url = url.replace( 'http://', 'https://' );
					}

					var callback = $.Deferred();
					// Use the file name from the url
					this.collection.zipWriter.add( url.replace( /^.*[\\\/]/, '' ), new zip.HttpReader( url ), function () {
						callback.resolve();
					});

					calls.push( callback.promise() );
				}, this );

				// Resolve the current defer once all
				$.when.apply( $, calls ).always(function() {
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
		},
		reset: function() {
			this.state.currentPage = 0;
			return Backbone.PageableCollection.prototype.reset.apply( this );
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

				if ( input.is( ':checkbox' ) ) {
					input.prop( 'checked', true );
					return;
				}

				that.model.set( input.attr( 'name' ), input.val() );
			});

			return this;
		},
		/**
		 * Read and store settings
		 */
		readOptions: function() {
			var that = this,
				id = this.model.id;

			// Reset model and restore id
			this.model.clear().set( 'id', id );

			this.$el.find( 'input' ).each( function() {
				var input = $( this );
				if ( input.is( ':checkbox' ) && !input.prop( 'checked' ) ) {
					return;
				}

				that.model.set( input.attr( 'name' ), input.val() );
			});

			this.model.save();
			return this;
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
			this.$el.text( ' (' + this.model.getPercent() + '%)' );
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
			return this;
		}
	});

	/**
	 * Main view for CSV import export
	 */
	wp.csvie.view.View = Backbone.View.extend({
		events: {
			'click .export': 'startExport'
		},
		initialize: function() {
			this.csv = [];

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
		 * Starts the CSV export
		 *
		 * @returns {wp.csvie.view.View}
		 */
		startExport: function() {
			var that = this;
			// If attachments should be exported start the ZIP saving process
			if ( this.settingsView.model.get( 'fields[attachment][attachment_attachments]' ) ) {
				if ( requestFileSystem ) {
					// If available use temporary file
					createTempFile( function( tmpFilename ) {
						zip.createWriter(new zip.FileWriter( tmpFilename ), function( writer ) {
							that.model.zipWriter = writer;
							that.exportCSV();
						});
					});
				} else {
					// Else use blobs
					zip.createWriter(new zip.BlobWriter(), function( writer ) {
						that.model.zipWriter = writer;
						that.exportCSV();
					});
				}
			} else {
				this.exportCSV();
			}
			return this;
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
			$.merge( this.csv,collection.toJSON() );
			return this;
		},
		/**
		 * Saves the current export
		 */
		saveCSV: function() {
			var name = 'export',
				csv  = Papa.unparse( this.csv );

			if ( this.model.zipWriter ) {
				var that = this;
				this.model.zipWriter.add( name + '.csv', new zip.TextReader( csv ), function() {
					that.model.zipWriter.close( function( blob ) {
						saveAs( blob, name + '.zip' );
						that.progressView.reset();
					});
				});

			} else {
				// Else save as csv
				saveAs( new Blob( [ csv ], { type: 'text/csv;charset=utf-8' } ), name + '.csv' );
				this.reset();
			}
		},
		/**
		 * Resets the export view
		 *
		 * @returns {wp.csvie.view.View}
		 */
		reset: function() {
			this.csv = [];
			this.model.reset();
			this.progressView.reset();
			return this;
		}
	});

	/**
	 * Handles errors display
	 */
	wp.csvie.view.Errors = Backbone.View.extend({
		initialize: function() {
			this.model = new wp.csvie.model.Errors();
			this.model.on( 'add', _.bind( this.appendError, this ) );
		},
		/**
		 * Resets the progress indicator
		 *
		 * @returns {wp.csvie.view.Errors}
		 */
		reset: function() {
			this.model.reset();
			this.$el.hide().find( 'tr' ).remove();
			return this;
		},
		/**
		 * Appends an error to the current list
		 *
		 * @param model
		 * @returns {wp.csvie.view.Errors}
		 */
		appendError: function( model ) {
			var row = $( '<tr>' ),
			    ul = $( '<ul>' );

			row.append( $( '<td>' ).text( model.get( 'row' ) ) );

			_.each( model.get( 'errors' ), function( error ){
				ul.append( $( '<li>' ).text( error ) );
			});
			row.append( $( '<td>' ).append( ul ) );

			this.$el.show().find('table').append(row);
			return this;
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
		/**
		 * Starts CSV import
		 * @param e
		 */
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
				// @todo Translate this
				alert ('No file selected' );
			}
		},
		/**
		 * Sends data by iterating over this.elements
		 */
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
					mode: this.$el.find( '[name=mode]' ).val()
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

	// Instanciate views

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

})(jQuery, Backbone, window, document, wp, zip );

(function ( $, document ) {
	"use strict";
	$.fn['checkAll'] = function () {
		return this.each(function () {
			var target = $( this ).data( 'target' );
			$( target ).find( 'input[type=checkbox]' ).prop( 'checked', $( this ).prop( 'checked' ) ).trigger( 'change' );
		});
	};

	// Data API
	$(document).on( 'click', '[data-toggle="checked"]', function() {
		$( this ).checkAll();
	});
})(jQuery, document);

