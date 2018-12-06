/* global jQuery, document, Backbone, window, wp, ajaxurl, _, Papa, csvieSettings, saveAs, Blob, Uint8Array */

( function( $, Backbone, window, document, wp ) {
	'use strict';

	wp.csvie = {
		model: {},
		view: {},
		views: {},
		settings: csvieSettings,
		importPage: null,
		exportPage: null,
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
		},
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
		model: wp.csvie.model.Error,
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
					var callback = $.Deferred(),
					    i = 1,
						// Follow the WordPress structure for directory naming
						name = url.split( '/' ).slice( -3 ).join( '/' ),
					    extension = '.' + url.split( '.' ).pop(),
						generatedName = '';

					// Strip the extension from the name
					name = name.substring( 0, name.length - ( extension.length ) );

					if ( 0 < this.collection.rename.length ) {
						name = _.map( this.collection.rename, function( col ) {
							return this.get( col );
						}, this );
						name = name.join( '-' );
					}

					generatedName = name + extension;
					while ( -1 !== this.collection.addedFileNames.indexOf( generatedName ) ) {
						generatedName = name + '-' + i + extension;
						i ++;
					}

					this.collection.addedFileNames.push( generatedName );

					// Use HTTPS if available
					if ( 'https:' === window.location.protocol ) {
						url = url.replace( 'http://', 'https://' );
					}

					try {
						window.fetch( url ).then( _.bind(function( response ) {
							if ( response.ok ) {
								this.collection.zipWriter.file( generatedName, response.blob() );
							}

							callback.resolve();
						}, this ) );

						calls.push( callback.promise() );
					} catch ( error ) {
						console.log( 'Error', error );
					}
				}, this );

				// Resolve the current defer once all
				$.when.apply( $, calls ).always(function() {
					deferred.resolve( this );
				});
			} else {
				deferred.resolve( this );
			}

			return deferred;
		},
	});

	/**
	 * Collection for element models. Handles pagination
	 */
	wp.csvie.model.CSV = Backbone.PageableCollection.extend({
		file_name: 'export',
		rename: [],
		addedFileNames:[],
		settings: null,
		model: wp.csvie.model.Element,
		mode: 'server',
		state: {
			pageSize: 1000,
			currentPage: 1
		},
		initialize: function( models, options ) {
			// Manually add settings variable
			this.settings = options.settings;
			// Setup correct url
			this.url = ajaxurl;
		},

		/**
		 * Injects additional data into the requests
		 *
		 * @param method
		 * @param object
		 * @param options
		 */
		sync: function( method, object, options ) {
			if ( 'undefined' === typeof options.data ) {
				options.data = {};
			}

			options.data.nonce = wp.csvie.settings.nonce;
			options.data.action = wp.csvie.settings.action;

			var json = this.toJSON(),
				formattedJSON = {};

			if ( json instanceof Array ) {
				formattedJSON.models = json;
			} else {
				formattedJSON.model = json;
			}

			_.extend( options.data, formattedJSON );

			options.emulateJSON = true;

			return Backbone.sync.call( this, 'create', object, options );
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

			this.processElementsDeferred = $.Deferred();

			options.data = this.settings.toJSON();

			Backbone.PageableCollection.prototype.fetch.apply( this, [ options ] ).done( _.bind(function() {
				this.currentElement = 0;
				this.processElements();
			}, this ) );

			return this.processElementsDeferred.promise();
		},

		parse: function( data ) {
			this.file_name = data[0].file_name;
			return Backbone.PageableCollection.prototype.parse.apply( this, [ data ]);
		},

		/**
		 * Starts iterating elements after load
		 */
		processElements: function() {
			var currentDef,
			    defers = [];

			// Stop when we reached the end of the collection
			if ( 'undefined' === typeof this.at( this.currentElement ) ) {
				this.processElementsDeferred.resolve();
			}

			this.at( this.currentElement ).getAttachments().done( _.bind( function() {
				this.currentElement += 1;
				this.trigger( 'processElement' );
				this.processElements();
			}, this ));
		},

		reset: function() {
			this.state.currentPage = 0;
			return Backbone.PageableCollection.prototype.reset.apply( this );
		},
	});

	/**
	 * Model for handling the progress indicator
	 */
	wp.csvie.model.Progress = Backbone.Model.extend({
		defaults: {
			total: 0,
			current: 0,
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
			var percent = 0;

			if ( this.get( 'current' ) ) {
				percent = Math.round( this.get( 'current' ) / this.get( 'total' ) * 100 );
			}

			return percent;
		},
	});

	/**
	 * View for handling settings. Restores settings from model
	 */
	wp.csvie.view.Settings = Backbone.View.extend({
		events: {
			change: 'readOptions',
		},
		initialize: function() {
			// Create model with current action as id
			this.model = new wp.csvie.model.Settings({
				id: wp.csvie.settings.action,
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
			var settings = this.model.toJSON();

			this.$el.find( 'input' ).each(function() {
				var input = $( this );
				// Skip hidden inputs
				if ( 'hidden' === input.attr( 'type' ) || ! settings[ input.attr( 'name' ) ] ) {
					return;
				}

				if ( input.is( ':checkbox' ) ) {
					input.prop( 'checked', true );
					return;
				}

				input.val( settings[ input.attr( 'name' ) ] );
			});

			return this;
		},

		/**
		 * Read and store settings
		 */
		readOptions: function() {
			var model = this.model;

			// Reset model and restore id
			this.model.clear();
			this.model.id = wp.csvie.settings.action;

			this.$el.find( 'input' ).each(function() {
				var input = $( this );
				if ( input.is( ':checkbox' ) && ! input.prop( 'checked' ) ) {
					return;
				}

				if ( input.val() ) {
					model.set( input.attr( 'name' ), input.val() );
				}
			});

			model.save();
			return this;
		},
		
		getSelectedNames: function() {
			var names = {};

			this.$el.find( 'input:checked' ).each(function() {
				names[ this.name ] = this.parentNode.textContent;
			});

			return names;
		},
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
			if ( 0 === this.model.get( 'total' ) ) {
				this.$el.text( ' (...)' );
			} else {
				this.$el.text( ' (' + this.model.getPercent() + '%)' );
			}

			this.$el.parent().prop( 'disabled', true );
			return this;
		},

		/**
		 * Resets to progress indicator state
		 */
		reset: function() {
			this.model.clear();
			this.$el.text( '' );
			this.$el.parent().prop( 'disabled', false );
			return this;
		},
	});

	/**
	 * Main view for CSV import export
	 */
	wp.csvie.view.View = Backbone.View.extend({
		events: {
			'click .export': 'startExport',
		},
		initialize: function() {
			this.csv = [];
			// Initialize settings
			this.settingsView = new wp.csvie.view.Settings({
				el: this.$el.find( '#export-settings' ),
			});

			// Initializes main collection
			this.model = new wp.csvie.model.CSV([], {
				settings: this.settingsView.model,
			});

			// Setup progress indicator
			this.progressModel = new wp.csvie.model.Progress();
			this.progressView = new wp.csvie.view.Progress({
				model: this.progressModel,
			});

			this.$el.find( '.export' ).append( this.progressView.$el );
			this.progressModel.listenTo( this.model, 'request', this.progressModel.tick );
			this.progressModel.listenTo( this.model, 'processElement', this.progressModel.tick );

			this.model.on( 'sync', _.bind(function() {
				this.progressModel.set( 'total', this.model.state.totalRecords );
			}, this ) );
		},

		/**
		 * Starts the CSV export
		 *
		 * @returns {wp.csvie.view.View}
		 */
		startExport: function() {
			var renameMsg, available;

			// If attachments should be exported start the ZIP saving process
			if ( this.settingsView.model.get( 'fields[attachment][attachment_attachments]' ) ) {
				if ( window.confirm( window.cieAdminLocales.renameFiles ) ) {
					available = Object.values( this.settingsView.getSelectedNames() );

					renameMsg = window.cieAdminLocales.renameDescription + ":\n\n"
						+ available.join( ', ' ) + "\n\n"
						+ window.cieAdminLocales.example + ': '
						+ available.slice( 0, 3 ) .join( '-' ) + "\n\n";

 					this.model.rename = window.prompt( renameMsg ).split( '-' );
				}

				this.model.zipWriter = window.JSZip();
				this.exportCSV();
			} else {
				this.exportCSV();
			}
			return this;
		},

		/**
		 * Runs CSV export
		 */
		exportCSV: function() {
			var page = this.model.state.currentPage + 1;
			if ( null === this.model.state.totalPages ) {
				page = 1;
			}

			this.attachments = null;

			this.model.getPage( page ).done( _.bind(function() {
				this.addElements( this.model );
				if ( this.model.hasNextPage() ) {
					this.exportCSV();
				} else {
					this.saveCSV();
				}
			}, this ) );
		},

		/**
		 * Adds elements from the given page of the collection
		 *
		 * @param collection
		 * @returns {wp.csvie.view.View}
		 */
		addElements: function( collection ) {
			$.merge( this.csv, collection.toJSON() );
			return this;
		},

		/**
		 * Saves the current export
		 */
		saveCSV: function() {
			var name = this.model.file_name,
				csv = Papa.unparse( this.csv ),
				now = new Date();

			// Append current date and time to string
			name = name + '-' + now.toISOString().substring( 0, 19 ).replace( 'T', '-' );

			if ( this.model.zipWriter ) {
				this.model.zipWriter.file( name + '.csv', csv );

				this.model.zipWriter.generateAsync({ type: 'blob' } ).then( _.bind( function( blob ) {
				    saveAs( blob, name + '.zip' );
				    this.progressView.reset();
				}, this ));
			} else {
				// Else save as csv
				saveAs( new Blob([ csv ], { type: 'text/csv;charset=utf-8' }), name + '.csv' );
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
		},
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

			_.each( model.get( 'errors' ), function( error ) {
				ul.append( $( '<li>' ).text( error ) );
			});
			row.append( $( '<td>' ).append( ul ) );

			this.$el.show().find( 'table' ).append( row );
			return this;
		},
	});

	/**
	 * Main view for CSV import export
	 */
	wp.csvie.view.ImportView = Backbone.View.extend({
		events: {
			'click button': 'importCSV',
		},
		initialize: function() {
			this.elements = [];

			this.errorsView = new wp.csvie.view.Errors({
				el: this.$el.find( '#errors' ),
			});

			// Setup progress indicator
			this.progressModel = new wp.csvie.model.Progress();
			this.progressView = new wp.csvie.view.Progress({
				model: this.progressModel,
			});

			this.$el.find( 'button' ).append( this.progressView.$el );
		},

		/**
		 * Starts CSV import
		 * @param e
		 */
		importCSV: function( e ) {
			var that = this,
				textarea = this.$el.find( 'textarea' ),
				file = this.$el.find( 'input[type=file]' ),

				// CSV Parser configuration
				config = {
					header: true,
					skipEmptyLines: true,
					complete: function( results ) {
						that.elements = results.data;
						that.progressModel.set( 'total', that.elements.length );
						that.sendData();
					},
					error: function( err, file, inputElem, reason ) {
						//@todo Do something on errors
					},
				};

			if ( ! window.FileReader && ! $.trim( textarea.val() ) ) {
				return;
			}

			e.preventDefault();

			this.errorsView.reset();

			if ( $.trim( textarea.val() ) ) {
				Papa.parse( textarea.val(), config );
			} else if ( window.FileReader && 0 < file[0].files.length ) {
				file.parse({ config: config });
			} else {
				// @todo Translate this
				window.alert( 'No file selected' );
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
					action: wp.csvie.settings.action,
					nonce: wp.csvie.settings.nonce,
					data: batch,
					mode: this.$el.find( '[name=mode]' ).val()
				},
				dataType: 'json',
				type: 'POST',
				success: function( response ) {
					_.each( response.data.errors, function( errors, row ) {
						this.errorsView.model.add( new wp.csvie.model.Error({
							row: row,
							errors: errors,
						}) );
					}, that );

					if ( 0 < that.elements.length ) {
						that.sendData();
					} else {
						that.progressView.reset();
					}
				},
			});
		},
	});

	// Instantiates views

	wp.csvie.importPage = $( '#csv-import-form' );
	if ( 0 < wp.csvie.importPage.length ) {
		wp.csvie.views.import = new wp.csvie.view.ImportView({
			el: wp.csvie.importPage,
		});
	}

	wp.csvie.exportPage = $( '#csv-export' );
	if ( 0 < wp.csvie.exportPage.length ) {
		wp.csvie.views.export = new wp.csvie.view.View({
			el: wp.csvie.exportPage,
		});
	}

})( jQuery, Backbone, window, document, wp );

// Misc UI stuff
( function( $, document ) {
	'use strict';
	$.fn.checkAll = function() {
		return this.each(function() {
			var target = $( this ).data( 'target' );
			$( target ).find( 'input[type=checkbox]' )
				.prop( 'checked', $( this ).prop( 'checked' ) )
				.trigger( 'change' );
		});
	};

	// Data API
	$( document ).on( 'click', '[data-toggle="checked"]', function() {
		$( this ).checkAll();
	});
})( jQuery, document );
