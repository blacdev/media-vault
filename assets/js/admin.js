/* Secure Media Vault — admin UI */
void function ( $ ) {
	'use strict';

	var cfg = window.smvAdmin || {};
	var t = cfg.i18n || {};

	/* ------------------------------------------------------------------
	 * Utilities
	 * ---------------------------------------------------------------- */

	function qs( sel, root ) { return ( root || document ).querySelector( sel ); }
	function qsa( sel, root ) { return Array.prototype.slice.call( ( root || document ).querySelectorAll( sel ) ); }

	function esc( str ) {
		return String( str == null ? '' : str ).replace( /[&<>"']/g, function ( c ) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ c ];
		} );
	}

	function sprintf( str, n ) { return String( str ).replace( '%d', n ); }

	function ajax( action, data ) {
		var body = data instanceof FormData ? data : new FormData();
		if ( ! ( data instanceof FormData ) && data ) {
			Object.keys( data ).forEach( function ( k ) {
				var v = data[ k ];
				if ( Array.isArray( v ) ) {
					v.forEach( function ( item ) { body.append( k + '[]', item ); } );
				} else {
					body.append( k, v );
				}
			} );
		}
		body.append( 'action', 'smv_' + action );
		body.append( 'nonce', cfg.nonce );
		return fetch( cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin' } )
			.then( function ( r ) { return r.json().catch( function () { return { success: false, data: { message: t.error } }; } ); } )
			.then( function ( json ) {
				if ( ! json || ! json.success ) {
					throw new Error( ( json && json.data && json.data.message ) || t.error );
				}
				return json.data;
			} );
	}

	var toastRoot;
	function toast( msg, type ) {
		if ( ! toastRoot ) {
			toastRoot = document.createElement( 'div' );
			toastRoot.className = 'smv-toasts';
			toastRoot.setAttribute( 'role', 'status' );
			toastRoot.setAttribute( 'aria-live', 'polite' );
			( qs( '.smv-admin' ) || document.body ).appendChild( toastRoot );
		}
		var el = document.createElement( 'div' );
		el.className = 'smv-toast' + ( type === 'error' ? ' smv-toast--error' : '' );
		el.textContent = msg;
		toastRoot.appendChild( el );
		setTimeout( function () {
			el.classList.add( 'is-leaving' );
			setTimeout( function () { el.remove(); }, 250 );
		}, type === 'error' ? 5000 : 2200 );
	}

	function copyText( text, trigger ) {
		var done = function () {
			toast( t.copied );
			if ( trigger ) {
				trigger.classList.add( 'is-copied' );
				setTimeout( function () { trigger.classList.remove( 'is-copied' ); }, 1200 );
			}
		};
		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( text ).then( done, function () { fallbackCopy( text ) ? done() : toast( t.copyFailed ); } );
		} else {
			fallbackCopy( text ) ? done() : toast( t.copyFailed );
		}
	}

	function fallbackCopy( text ) {
		var ta = document.createElement( 'textarea' );
		ta.value = text;
		ta.setAttribute( 'readonly', '' );
		ta.style.position = 'fixed';
		ta.style.opacity = '0';
		document.body.appendChild( ta );
		ta.select();
		var ok = false;
		try { ok = document.execCommand( 'copy' ); } catch ( e ) {}
		ta.remove();
		return ok;
	}

	function busy( btn, on ) {
		if ( ! btn ) { return; }
		btn.disabled = !! on;
		btn.classList.toggle( 'is-busy', !! on );
	}

	var ICONS = {
		image: 'M4 5h16v14H4zM4 16l5-5 4 4 3-3 4 4M15 9h.01',
		audio: 'M9 18V5l11-2v13M9 18a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm11-2a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z',
		video: 'M4 4h16v16H4zM8 4v16M16 4v16M4 8h4M4 16h4M16 8h4M16 16h4',
		document: 'M14 3H6v18h12V7l-4-4Zm0 0v4h4',
		check: 'm5 12 5 5 9-10',
		x: 'M6 6l12 12M18 6 6 18'
	};
	function icon( name, size ) {
		size = size || 16;
		return '<svg class="smv-icon" viewBox="0 0 24 24" width="' + size + '" height="' + size + '" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="' + ( ICONS[ name ] || ICONS.document ) + '"/></svg>';
	}

	function mediaHtml( f ) {
		var cls = 'smv-tile__media smv-tile__media--' + esc( f.group );
		if ( f.group === 'image' && f.thumb ) {
			return '<span class="' + cls + '"><img src="' + esc( f.thumb ) + '" alt="" loading="lazy"></span>';
		}
		return '<span class="' + cls + '">' + icon( f.group, 32 ) + '<span class="smv-tile__ext">' + esc( ( f.ext || '' ).toUpperCase() ) + '</span></span>';
	}

	// Focus trap + Escape for dialogs.
	function openDialog( root, onClose ) {
		var previous = document.activeElement;
		root.hidden = false;
		document.body.classList.add( 'smv-lock' );
		var focusables = function () {
			return qsa( 'a[href],button:not([disabled]),input:not([disabled]):not([type=hidden]),textarea,select,[tabindex]:not([tabindex="-1"])', root ).filter( function ( el ) { return el.offsetParent !== null; } );
		};
		var first = focusables()[ 0 ];
		if ( first ) { setTimeout( function () { first.focus(); }, 30 ); }

		function onKey( e ) {
			if ( e.key === 'Escape' ) { e.preventDefault(); close(); }
			if ( e.key === 'Tab' ) {
				var f = focusables();
				if ( ! f.length ) { return; }
				if ( e.shiftKey && document.activeElement === f[ 0 ] ) { e.preventDefault(); f[ f.length - 1 ].focus(); }
				else if ( ! e.shiftKey && document.activeElement === f[ f.length - 1 ] ) { e.preventDefault(); f[ 0 ].focus(); }
			}
		}
		function close() {
			root.hidden = true;
			document.body.classList.remove( 'smv-lock' );
			document.removeEventListener( 'keydown', onKey );
			if ( previous && previous.focus ) { previous.focus(); }
			if ( onClose ) { onClose(); }
		}
		document.addEventListener( 'keydown', onKey );
		return close;
	}

	/* ------------------------------------------------------------------
	 * Global: click-to-copy
	 * ---------------------------------------------------------------- */

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '[data-smv-copy]' );
		if ( btn ) {
			e.preventDefault();
			copyText( btn.getAttribute( 'data-smv-copy' ), btn );
			return;
		}
		var from = e.target.closest( '[data-smv-copy-from]' );
		if ( from ) {
			var input = qs( from.getAttribute( 'data-smv-copy-from' ) );
			if ( input ) { copyText( input.value, from ); }
		}
	} );

	/* ------------------------------------------------------------------
	 * Uploader
	 * ---------------------------------------------------------------- */

	function Uploader( zone, input, queue, onUploaded ) {
		var allowed = ( cfg.allowed || [] ).map( function ( e ) { return e.toLowerCase(); } );
		var pending = [];
		var active = 0;
		var MAX_PARALLEL = 3;

		input.setAttribute( 'accept', cfg.accept || '' );

		zone.addEventListener( 'click', function () { input.click(); } );
		zone.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Enter' || e.key === ' ' ) { e.preventDefault(); input.click(); }
		} );
		input.addEventListener( 'change', function () {
			add( input.files );
			input.value = '';
		} );

		[ 'dragenter', 'dragover' ].forEach( function ( ev ) {
			zone.addEventListener( ev, function ( e ) { e.preventDefault(); zone.classList.add( 'is-over' ); } );
		} );
		[ 'dragleave', 'drop' ].forEach( function ( ev ) {
			zone.addEventListener( ev, function ( e ) { e.preventDefault(); zone.classList.remove( 'is-over' ); } );
		} );
		zone.addEventListener( 'drop', function ( e ) {
			if ( e.dataTransfer && e.dataTransfer.files ) { add( e.dataTransfer.files ); }
		} );

		function row( file ) {
			var li = document.createElement( 'li' );
			li.className = 'smv-queue__item';
			li.innerHTML = '<span class="smv-queue__name">' + esc( file.name ) + '</span><span class="smv-queue__status">0%</span><span class="smv-queue__bar"><span></span></span>';
			queue.prepend( li );
			return li;
		}

		function fail( li, msg ) {
			li.classList.add( 'is-error' );
			qs( '.smv-queue__status', li ).textContent = msg;
		}

		function add( files ) {
			Array.prototype.forEach.call( files, function ( file ) {
				var li = row( file );
				var ext = ( file.name.split( '.' ).pop() || '' ).toLowerCase();
				if ( allowed.indexOf( ext ) === -1 ) { return fail( li, t.badType ); }
				if ( file.size > cfg.maxBytes ) { return fail( li, t.tooBig + ' (' + cfg.maxHuman + ')' ); }
				pending.push( { file: file, li: li } );
			} );
			pump();
		}

		function pump() {
			while ( active < MAX_PARALLEL && pending.length ) {
				send( pending.shift() );
			}
		}

		function send( job ) {
			active++;
			var fd = new FormData();
			fd.append( 'action', 'smv_upload' );
			fd.append( 'nonce', cfg.nonce );
			fd.append( 'file', job.file );

			var xhr = new XMLHttpRequest();
			var bar = qs( '.smv-queue__bar span', job.li );
			var status = qs( '.smv-queue__status', job.li );

			xhr.upload.addEventListener( 'progress', function ( e ) {
				if ( e.lengthComputable ) {
					var pct = Math.round( ( e.loaded / e.total ) * 100 );
					bar.style.width = pct + '%';
					status.textContent = pct < 100 ? pct + '%' : ( cfg.storageMode !== 'local' ? 'Processing…' : '100%' );
				}
			} );
			xhr.addEventListener( 'load', function () {
				var json = null;
				try { json = JSON.parse( xhr.responseText ); } catch ( e ) {}
				if ( json && json.success ) {
					job.li.classList.add( 'is-done' );
					bar.style.width = '100%';
					status.textContent = t.uploaded;
					setTimeout( function () { job.li.remove(); }, 2500 );
					if ( onUploaded ) { onUploaded( json.data.file ); }
				} else {
					fail( job.li, ( json && json.data && json.data.message ) || ( xhr.status === 413 ? t.tooBig : t.uploadFailed ) );
				}
				done();
			} );
			xhr.addEventListener( 'error', function () { fail( job.li, t.uploadFailed ); done(); } );
			xhr.open( 'POST', cfg.ajaxUrl );
			xhr.send( fd );
		}

		function done() {
			active--;
			pump();
		}
	}

	/* ------------------------------------------------------------------
	 * Library grid (manage + picker modes)
	 * ---------------------------------------------------------------- */

	function LibraryGrid( root, opts ) {
		opts = opts || {};
		var grid = qs( '[data-smv-grid]', root );
		var empty = qs( '[data-smv-empty]', root );
		var emptyText = qs( '[data-smv-empty-text]', root );
		var more = qs( '[data-smv-more]', root );
		var search = qs( '[data-smv-search]', opts.toolbar || root );
		var state = { group: opts.group || 'all', search: '', page: 1, total: 0, items: [] };
		var selected = new Set();
		var selecting = !! opts.picker;
		var byId = {};
		var timer;
		var reqId = 0;

		function tile( f ) {
			var el = document.createElement( 'button' );
			el.type = 'button';
			el.className = 'smv-tile' + ( selected.has( f.id ) ? ' is-selected' : '' );
			el.setAttribute( 'data-id', f.id );
			if ( selecting ) { el.setAttribute( 'aria-pressed', selected.has( f.id ) ? 'true' : 'false' ); }
			var sync = f.storage !== 'local' || f.syncStatus ? '<span class="smv-sync smv-sync--' + esc( f.syncStatus || 'none' ) + '" title="' + esc( ( t.storage && t.storage[ f.storage ] ) || f.storage ) + '"></span>' : '';
			el.innerHTML = mediaHtml( f ) +
				'<span class="smv-tile__check">' + icon( 'check', 14 ) + '</span>' +
				'<span class="smv-tile__body"><span class="smv-tile__title">' + esc( f.title || f.name ) + '</span>' +
				'<span class="smv-tile__meta">' + sync + esc( f.sizeHuman ) + ' · ' + esc( ( f.ext || '' ).toUpperCase() ) + '</span></span>';
			el.setAttribute( 'aria-label', ( f.title || f.name ) + ', ' + f.sizeHuman );
			return el;
		}

		function render( append ) {
			if ( ! append ) { grid.innerHTML = ''; }
			var start = append ? grid.children.length : 0;
			state.items.slice( start ).forEach( function ( f ) { grid.appendChild( tile( f ) ); } );
			grid.setAttribute( 'aria-busy', 'false' );
			var none = ! state.items.length;
			empty.hidden = ! none;
			if ( none ) { emptyText.textContent = state.search || state.group !== 'all' ? t.noResults : t.emptyLibrary; }
			more.hidden = state.items.length >= state.total;
		}

		function load( append ) {
			var mine = ++reqId;
			if ( ! append ) { state.page = 1; grid.setAttribute( 'aria-busy', 'true' ); }
			busy( more, true );
			return ajax( 'library', { group: state.group, search: state.search, page: state.page } ).then( function ( data ) {
				if ( mine !== reqId ) { return; }
				state.total = data.total;
				data.items.forEach( function ( f ) { byId[ f.id ] = f; } );
				state.items = append ? state.items.concat( data.items ) : data.items;
				render( append );
				if ( opts.onCounts ) { opts.onCounts( data.counts ); }
			} ).catch( function ( err ) {
				toast( err.message, 'error' );
			} ).then( function () { busy( more, false ); } );
		}

		more.addEventListener( 'click', function () { state.page++; load( true ); } );

		if ( search ) {
			search.addEventListener( 'input', function () {
				clearTimeout( timer );
				timer = setTimeout( function () { state.search = search.value.trim(); load(); }, 250 );
			} );
		}

		qsa( '[data-smv-filter]', opts.toolbar || root ).forEach( function ( chip ) {
			chip.classList.toggle( 'is-active', chip.getAttribute( 'data-smv-filter' ) === state.group );
			chip.setAttribute( 'aria-selected', chip.getAttribute( 'data-smv-filter' ) === state.group ? 'true' : 'false' );
			chip.addEventListener( 'click', function () {
				qsa( '[data-smv-filter]', opts.toolbar || root ).forEach( function ( c ) {
					c.classList.remove( 'is-active' );
					c.setAttribute( 'aria-selected', 'false' );
				} );
				chip.classList.add( 'is-active' );
				chip.setAttribute( 'aria-selected', 'true' );
				state.group = chip.getAttribute( 'data-smv-filter' );
				load();
			} );
		} );

		grid.addEventListener( 'click', function ( e ) {
			var el = e.target.closest( '.smv-tile' );
			if ( ! el ) { return; }
			var id = parseInt( el.getAttribute( 'data-id' ), 10 );
			if ( selecting ) {
				if ( selected.has( id ) ) { selected.delete( id ); } else { selected.add( id ); }
				el.classList.toggle( 'is-selected', selected.has( id ) );
				el.setAttribute( 'aria-pressed', selected.has( id ) ? 'true' : 'false' );
				if ( opts.onSelection ) { opts.onSelection( selected ); }
			} else if ( opts.onOpen ) {
				opts.onOpen( byId[ id ] );
			}
		} );

		return {
			load: load,
			get: function ( id ) { return byId[ id ]; },
			selected: function () { return Array.from( selected ).map( function ( id ) { return byId[ id ]; } ).filter( Boolean ); },
			clearSelection: function () {
				selected.clear();
				qsa( '.smv-tile.is-selected', grid ).forEach( function ( el ) { el.classList.remove( 'is-selected' ); el.setAttribute( 'aria-pressed', 'false' ); } );
				if ( opts.onSelection ) { opts.onSelection( selected ); }
			},
			setSelecting: function ( on ) {
				selecting = on;
				root.classList.toggle( 'smv-selecting', on );
				if ( ! on ) { this.clearSelection(); }
			},
			prepend: function ( f ) {
				byId[ f.id ] = f;
				if ( state.group !== 'all' && state.group !== f.group ) { return; }
				state.items.unshift( f );
				state.total++;
				grid.prepend( tile( f ) );
				empty.hidden = true;
			},
			replace: function ( f ) {
				byId[ f.id ] = f;
				state.items = state.items.map( function ( i ) { return i.id === f.id ? f : i; } );
				var old = qs( '.smv-tile[data-id="' + f.id + '"]', grid );
				if ( old ) { old.replaceWith( tile( f ) ); }
			},
			remove: function ( ids ) {
				ids.forEach( function ( id ) {
					selected.delete( id );
					var el = qs( '.smv-tile[data-id="' + id + '"]', grid );
					if ( el ) { el.remove(); }
				} );
				state.items = state.items.filter( function ( i ) { return ids.indexOf( i.id ) === -1; } );
				state.total -= ids.length;
				render();
			},
			select: function ( id ) {
				selected.add( id );
				var el = qs( '.smv-tile[data-id="' + id + '"]', grid );
				if ( el ) { el.classList.add( 'is-selected' ); el.setAttribute( 'aria-pressed', 'true' ); }
				if ( opts.onSelection ) { opts.onSelection( selected ); }
			}
		};
	}

	function updateCounts( counts ) {
		qsa( '[data-smv-count]' ).forEach( function ( el ) {
			var k = el.getAttribute( 'data-smv-count' );
			if ( k === 'bytes' ) { return; }
			el.textContent = counts[ k ] || 0;
		} );
	}

	/* ------------------------------------------------------------------
	 * Library page
	 * ---------------------------------------------------------------- */

	function initLibrary() {
		var root = qs( '[data-smv-library]' );
		if ( ! root ) { return; }
		var toolbar = qs( '.smv-toolbar' );
		var bulkbar = qs( '[data-smv-bulkbar]' );
		var bulkCount = qs( '[data-smv-bulk-count]' );
		var selectBtn = qs( '[data-smv-select-mode]' );
		var drawer = qs( '[data-smv-drawer]' );
		var current = null;
		var closeDrawer = null;

		var lib = LibraryGrid( root, {
			toolbar: toolbar,
			onCounts: updateCounts,
			onOpen: openFile,
			onSelection: function ( set ) {
				bulkCount.textContent = sprintf( t.selected, set.size );
				bulkbar.hidden = ! set.size;
			}
		} );
		lib.load();

		// Uploader toggle.
		var uploader = qs( '[data-smv-uploader]' );
		var toggle = qs( '[data-smv-toggle-upload]' );
		Uploader( qs( '[data-smv-dropzone]', uploader ), qs( '[data-smv-file-input]', uploader ), qs( '[data-smv-queue]', uploader ), function ( f ) {
			lib.prepend( f );
		} );
		toggle.addEventListener( 'click', function () {
			uploader.hidden = ! uploader.hidden;
			toggle.setAttribute( 'aria-expanded', uploader.hidden ? 'false' : 'true' );
			if ( ! uploader.hidden ) { qs( '[data-smv-dropzone]', uploader ).focus(); }
		} );
		// Show uploader straight away on an empty library; also accept drops anywhere on the page.
		document.addEventListener( 'dragenter', function ( e ) {
			if ( e.dataTransfer && Array.prototype.indexOf.call( e.dataTransfer.types || [], 'Files' ) !== -1 ) { uploader.hidden = false; }
		} );
		if ( qs( '[data-smv-count="all"]' ) && qs( '[data-smv-count="all"]' ).textContent.trim() === '0' ) { uploader.hidden = false; }

		// Selection mode.
		selectBtn.addEventListener( 'click', function () {
			var on = selectBtn.getAttribute( 'aria-pressed' ) !== 'true';
			selectBtn.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
			selectBtn.classList.toggle( 'smv-btn--secondary', on );
			lib.setSelecting( on );
		} );
		qs( '[data-smv-bulk-cancel]' ).addEventListener( 'click', function () { selectBtn.click(); } );
		qs( '[data-smv-bulk-delete]' ).addEventListener( 'click', function () {
			var ids = lib.selected().map( function ( f ) { return f.id; } );
			if ( ! ids.length || ! window.confirm( sprintf( t.confirmDeleteN, ids.length ) ) ) { return; }
			var btn = this;
			busy( btn, true );
			ajax( 'delete_files', { ids: ids } ).then( function () {
				lib.remove( ids );
				bulkbar.hidden = true;
				toast( t.deleted );
				lib.load();
			} ).catch( function ( err ) { toast( err.message, 'error' ); } ).then( function () { busy( btn, false ); } );
		} );

		// Drawer.
		function openFile( f ) {
			current = f;
			var preview = qs( '[data-smv-d-preview]', drawer );
			if ( f.group === 'image' ) {
				preview.innerHTML = '<img src="' + esc( f.url ) + '" alt="">';
			} else if ( f.group === 'audio' ) {
				preview.innerHTML = '<audio controls preload="metadata" src="' + esc( f.url ) + '"></audio>';
			} else if ( f.group === 'video' ) {
				preview.innerHTML = '<video controls preload="metadata" src="' + esc( f.url ) + '"></video>';
			} else {
				preview.innerHTML = '<span class="smv-tile__media smv-tile__media--document" style="width:100%;height:180px">' + icon( 'document', 40 ) + '</span>';
			}
			qs( '[data-smv-d-title]', drawer ).value = f.title;
			qs( '[data-smv-d-caption]', drawer ).value = f.caption;
			qs( '[data-smv-d-url]', drawer ).value = f.url;
			var meta = [
				[ 'File', f.name ],
				[ 'Type', f.mime ],
				[ 'Size', f.sizeHuman ],
				f.width ? [ 'Dimensions', f.width + ' × ' + f.height + ' px' ] : null,
				[ 'Stored in', ( t.storage && t.storage[ f.storage ] ) || f.storage ],
				[ 'Uploaded', f.date ]
			].filter( Boolean );
			qs( '[data-smv-d-meta]', drawer ).innerHTML = meta.map( function ( m ) { return '<dt>' + esc( m[ 0 ] ) + '</dt><dd>' + esc( m[ 1 ] ) + '</dd>'; } ).join( '' );
			var syncBox = qs( '[data-smv-d-sync]', drawer );
			var retry = qs( '[data-smv-d-retry]', drawer );
			var failed = f.syncStatus === 'failed';
			var unsynced = cfg.dropbox && cfg.storageMode !== 'local' && f.storage === 'local';
			syncBox.hidden = ! failed;
			syncBox.textContent = failed ? f.syncError : '';
			retry.hidden = ! ( failed || unsynced );
			closeDrawer = openDialog( drawer, function () {
				qs( '[data-smv-d-preview]', drawer ).innerHTML = '';
			} );
		}

		qsa( '[data-smv-drawer-close]', drawer ).forEach( function ( b ) {
			b.addEventListener( 'click', function () { if ( closeDrawer ) { closeDrawer(); } } );
		} );

		qs( '[data-smv-d-save]', drawer ).addEventListener( 'click', function () {
			var btn = this;
			busy( btn, true );
			ajax( 'update_file', { id: current.id, title: qs( '[data-smv-d-title]', drawer ).value, caption: qs( '[data-smv-d-caption]', drawer ).value } )
				.then( function ( data ) {
					lib.replace( data.file );
					current = data.file;
					toast( t.saved );
					closeDrawer();
				} ).catch( function ( err ) { toast( err.message, 'error' ); } ).then( function () { busy( btn, false ); } );
		} );
		qs( '[data-smv-d-form]', drawer ).addEventListener( 'submit', function ( e ) { e.preventDefault(); qs( '[data-smv-d-save]', drawer ).click(); } );

		qs( '[data-smv-d-delete]', drawer ).addEventListener( 'click', function () {
			if ( ! window.confirm( t.confirmDelete ) ) { return; }
			var btn = this;
			busy( btn, true );
			ajax( 'delete_files', { ids: [ current.id ] } ).then( function () {
				lib.remove( [ current.id ] );
				toast( t.deleted );
				closeDrawer();
				lib.load();
			} ).catch( function ( err ) { toast( err.message, 'error' ); } ).then( function () { busy( btn, false ); } );
		} );

		qs( '[data-smv-d-retry]', drawer ).addEventListener( 'click', function () {
			var btn = this;
			busy( btn, true );
			ajax( 'retry_sync', { id: current.id } ).then( function ( data ) {
				lib.replace( data.file );
				toast( t.synced );
				closeDrawer();
			} ).catch( function ( err ) { toast( err.message, 'error' ); } ).then( function () { busy( btn, false ); } );
		} );
	}

	/* ------------------------------------------------------------------
	 * Collections list
	 * ---------------------------------------------------------------- */

	function initCollectionsList() {
		document.addEventListener( 'click', function ( e ) {
			var del = e.target.closest( '[data-smv-del-collection]' );
			var dup = e.target.closest( '[data-smv-dup-collection]' );
			if ( del ) {
				if ( ! window.confirm( t.confirmDelCol ) ) { return; }
				busy( del, true );
				ajax( 'delete_collection', { id: del.getAttribute( 'data-smv-del-collection' ) } ).then( function () {
					var row = del.closest( 'tr' );
					row.style.opacity = '0';
					setTimeout( function () {
						row.remove();
						if ( ! qs( '[data-smv-collection-row]' ) ) { window.location.reload(); }
					}, 200 );
					toast( t.deleted );
				} ).catch( function ( err ) { toast( err.message, 'error' ); busy( del, false ); } );
			}
			if ( dup ) {
				busy( dup, true );
				ajax( 'duplicate_collection', { id: dup.getAttribute( 'data-smv-dup-collection' ) } ).then( function ( data ) {
					window.location.href = cfg.collectionsUrl + '&edit=' + data.id;
				} ).catch( function ( err ) { toast( err.message, 'error' ); busy( dup, false ); } );
			}
		} );
	}

	/* ------------------------------------------------------------------
	 * Collection editor
	 * ---------------------------------------------------------------- */

	function initEditor() {
		var form = qs( '[data-smv-editor]' );
		var dataEl = qs( '#smv-collection-data' );
		if ( ! form || ! dataEl ) { return; }

		var model = JSON.parse( dataEl.textContent );
		var list = qs( '[data-smv-c-items]' );
		var emptyEl = qs( '[data-smv-c-empty]' );
		var countEl = qs( '[data-smv-c-count]' );
		var stateEl = qs( '[data-smv-c-state]' );
		var saveBtn = qs( '[data-smv-c-save]' );
		var titleEl = qs( '[data-smv-c-title]' );
		var slugEl = qs( '[data-smv-c-slug]' );
		var dirty = false;
		var slugTouched = !! model.slug;

		titleEl.value = model.title;
		slugEl.value = model.slug;
		if ( ! model.id ) { titleEl.focus(); }

		function markDirty() {
			dirty = true;
			stateEl.textContent = t.confirmLeave;
			stateEl.className = 'smv-savebar__state is-dirty';
		}

		function slugify( s ) {
			return s.toLowerCase().normalize( 'NFKD' ).replace( /[̀-ͯ]/g, '' ).replace( /[^a-z0-9]+/g, '-' ).replace( /^-+|-+$/g, '' ).slice( 0, 190 );
		}

		titleEl.addEventListener( 'input', function () {
			if ( ! slugTouched ) { slugEl.value = slugify( titleEl.value ); }
			markDirty();
		} );
		slugEl.addEventListener( 'input', function () {
			slugTouched = slugEl.value !== '';
			slugEl.value = slugify( slugEl.value.replace( /\s/g, '-' ) ) + ( /-$/.test( slugEl.value ) ? '-' : '' );
			markDirty();
		} );

		// Options.
		function syncOptionsUI() {
			qsa( '[data-smv-opt]' ).forEach( function ( el ) {
				var k = el.getAttribute( 'data-smv-opt' );
				if ( el.type === 'checkbox' ) { el.checked = !! model.settings[ k ]; } else { el.value = model.settings[ k ]; }
				var out = qs( '[data-smv-out="' + k + '"]' );
				if ( out ) { out.textContent = k === 'gap' ? model.settings[ k ] + 'px' : model.settings[ k ]; }
			} );
			qsa( '[data-smv-show-for]' ).forEach( function ( el ) {
				el.hidden = el.getAttribute( 'data-smv-show-for' ).split( ' ' ).indexOf( model.type ) === -1;
			} );
		}
		qsa( '[data-smv-opt]' ).forEach( function ( el ) {
			el.addEventListener( el.type === 'range' ? 'input' : 'change', function () {
				var k = el.getAttribute( 'data-smv-opt' );
				model.settings[ k ] = el.type === 'checkbox' ? el.checked : ( el.type === 'range' ? parseInt( el.value, 10 ) : el.value );
				syncOptionsUI();
				markDirty();
			} );
		} );
		qsa( '[data-smv-c-type]' ).forEach( function ( r ) {
			r.addEventListener( 'change', function () {
				model.type = r.value;
				syncOptionsUI();
				renderItems();
				markDirty();
			} );
		} );

		// Items.
		function expectedGroup() {
			return ( cfg.types[ model.type ] && cfg.types[ model.type ].group ) || '';
		}

		function renderItems() {
			var want = expectedGroup();
			list.innerHTML = '';
			model.items.forEach( function ( f, i ) {
				var li = document.createElement( 'li' );
				li.className = 'smv-item';
				li.tabIndex = 0;
				li.setAttribute( 'data-id', f.id );
				li.setAttribute( 'aria-label', ( i + 1 ) + '. ' + ( f.title || f.name ) + ' — use Alt + arrow keys to move' );
				if ( want && f.group !== want ) {
					li.classList.add( 'is-mismatch' );
					li.setAttribute( 'data-warning', 'Not shown in this type' );
				}
				li.innerHTML = mediaHtml( f ) +
					'<span class="smv-item__num">' + ( i + 1 ) + '</span>' +
					'<button type="button" class="smv-item__remove" aria-label="' + esc( t.remove ) + ' ' + esc( f.title ) + '">' + icon( 'x', 14 ) + '</button>' +
					'<span class="smv-item__name">' + esc( f.title || f.name ) + '</span>';
				list.appendChild( li );
			} );
			emptyEl.hidden = model.items.length > 0;
			countEl.textContent = model.items.length;
		}

		list.addEventListener( 'click', function ( e ) {
			var rm = e.target.closest( '.smv-item__remove' );
			if ( ! rm ) { return; }
			var id = parseInt( rm.closest( '.smv-item' ).getAttribute( 'data-id' ), 10 );
			model.items = model.items.filter( function ( f ) { return f.id !== id; } );
			renderItems();
			markDirty();
		} );

		// Keyboard reordering (Alt + arrows) for accessibility.
		list.addEventListener( 'keydown', function ( e ) {
			var li = e.target.closest( '.smv-item' );
			if ( ! li ) { return; }
			var idx = Array.prototype.indexOf.call( list.children, li );
			if ( e.key === 'Delete' || e.key === 'Backspace' ) {
				e.preventDefault();
				qs( '.smv-item__remove', li ).click();
				var next = list.children[ Math.min( idx, list.children.length - 1 ) ];
				if ( next ) { next.focus(); }
				return;
			}
			if ( ! e.altKey ) { return; }
			var to = null;
			if ( e.key === 'ArrowLeft' || e.key === 'ArrowUp' ) { to = idx - 1; }
			if ( e.key === 'ArrowRight' || e.key === 'ArrowDown' ) { to = idx + 1; }
			if ( to === null || to < 0 || to >= model.items.length ) { return; }
			e.preventDefault();
			var moved = model.items.splice( idx, 1 )[ 0 ];
			model.items.splice( to, 0, moved );
			renderItems();
			list.children[ to ].focus();
			markDirty();
		} );

		$( list ).sortable( {
			items: '> .smv-item',
			placeholder: 'smv-item smv-item-placeholder',
			tolerance: 'pointer',
			forcePlaceholderSize: true,
			update: function () {
				var order = qsa( '.smv-item', list ).map( function ( li ) { return parseInt( li.getAttribute( 'data-id' ), 10 ); } );
				var map = {};
				model.items.forEach( function ( f ) { map[ f.id ] = f; } );
				model.items = order.map( function ( id ) { return map[ id ]; } ).filter( Boolean );
				renderItems();
				markDirty();
			}
		} );

		// Picker.
		var picker = qs( '[data-smv-picker]' );
		var pickerAdd = qs( '[data-smv-picker-add]' );
		var pickerCount = qs( '[data-smv-picker-count]' );
		var closePicker = null;
		var pickerLib = null;

		function onPickerSelection( set ) {
			pickerAdd.disabled = ! set.size;
			pickerAdd.textContent = set.size ? sprintf( t.addN, set.size ) : pickerAdd.getAttribute( 'data-label' );
			pickerCount.textContent = set.size ? sprintf( t.selected, set.size ) : '';
		}
		pickerAdd.setAttribute( 'data-label', pickerAdd.textContent );

		qs( '[data-smv-open-picker]' ).addEventListener( 'click', function () {
			var body = qs( '.smv-modal__body', picker );
			if ( ! pickerLib ) {
				pickerLib = LibraryGrid( body, {
					picker: true,
					toolbar: qs( '.smv-modal__toolbar', picker ),
					group: expectedGroup() || 'all',
					onSelection: onPickerSelection
				} );
				Uploader( qs( '[data-smv-dropzone]', body ), qs( '[data-smv-file-input]', body ), qs( '[data-smv-queue]', body ), function ( f ) {
					pickerLib.prepend( f );
					pickerLib.select( f.id );
				} );
			}
			pickerLib.clearSelection();
			pickerLib.load();
			closePicker = openDialog( picker );
		} );
		qsa( '[data-smv-picker-close]', picker ).forEach( function ( b ) {
			b.addEventListener( 'click', function () { if ( closePicker ) { closePicker(); } } );
		} );
		pickerAdd.addEventListener( 'click', function () {
			var existing = model.items.map( function ( f ) { return f.id; } );
			pickerLib.selected().forEach( function ( f ) {
				if ( existing.indexOf( f.id ) === -1 ) { model.items.push( f ); }
			} );
			renderItems();
			markDirty();
			closePicker();
		} );

		// Save.
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			if ( ! titleEl.value.trim() ) { titleEl.focus(); titleEl.reportValidity && titleEl.reportValidity(); return; }
			busy( saveBtn, true );
			stateEl.textContent = t.saving;
			stateEl.className = 'smv-savebar__state';
			ajax( 'save_collection', {
				id: model.id,
				title: titleEl.value,
				slug: slugEl.value,
				type: model.type,
				settings: JSON.stringify( model.settings ),
				items: JSON.stringify( model.items.map( function ( f ) { return f.id; } ) )
			} ).then( function ( data ) {
				var created = ! model.id;
				model.id = data.id;
				slugEl.value = data.slug;
				slugTouched = true;
				dirty = false;
				stateEl.textContent = t.saved;
				stateEl.className = 'smv-savebar__state is-saved';
				var wrap = qs( '[data-smv-c-shortcode-wrap]' );
				var sc = qs( '[data-smv-c-shortcode]' );
				wrap.hidden = false;
				sc.setAttribute( 'data-smv-copy', data.shortcode );
				qs( 'code', sc ).textContent = data.shortcode;
				if ( created ) {
					window.history.replaceState( null, '', data.editUrl );
					saveBtn.textContent = t.saveChanges;
					toast( t.createdCopied );
					copyText( data.shortcode, sc );
				} else {
					toast( t.saved );
				}
			} ).catch( function ( err ) {
				toast( err.message, 'error' );
				stateEl.textContent = '';
			} ).then( function () { busy( saveBtn, false ); } );
		} );

		// Ctrl/Cmd+S saves.
		document.addEventListener( 'keydown', function ( e ) {
			if ( ( e.ctrlKey || e.metaKey ) && e.key.toLowerCase() === 's' ) {
				e.preventDefault();
				form.requestSubmit ? form.requestSubmit() : form.dispatchEvent( new Event( 'submit' ) );
			}
		} );

		window.addEventListener( 'beforeunload', function ( e ) {
			if ( dirty ) { e.preventDefault(); e.returnValue = ''; }
		} );

		syncOptionsUI();
		renderItems();
	}

	/* ------------------------------------------------------------------
	 * Submissions
	 * ---------------------------------------------------------------- */

	function initSubmissions() {
		document.addEventListener( 'click', function ( e ) {
			var retry = e.target.closest( '[data-smv-retry]' );
			if ( retry ) {
				busy( retry, true );
				ajax( 'retry_sync', { id: retry.getAttribute( 'data-smv-retry' ) } ).then( function () {
					toast( t.synced );
					setTimeout( function () { window.location.reload(); }, 600 );
				} ).catch( function ( err ) { toast( err.message, 'error' ); busy( retry, false ); } );
				return;
			}
			var pub = e.target.closest( '[data-smv-publish]' );
			var del = e.target.closest( '[data-smv-del-sub]' );
			if ( pub ) {
				busy( pub, true );
				ajax( 'publish_file', { id: pub.getAttribute( 'data-smv-publish' ) } ).then( function () {
					toast( t.published );
					setTimeout( function () { window.location.reload(); }, 600 );
				} ).catch( function ( err ) { toast( err.message, 'error' ); busy( pub, false ); } );
			}
			if ( del ) {
				if ( ! window.confirm( t.confirmDelSub ) ) { return; }
				busy( del, true );
				ajax( 'delete_submission', { id: del.getAttribute( 'data-smv-del-sub' ) } ).then( function () {
					var card = del.closest( '.smv-sub' );
					card.style.transition = 'opacity .2s';
					card.style.opacity = '0';
					setTimeout( function () { card.remove(); }, 200 );
					toast( t.deleted );
				} ).catch( function ( err ) { toast( err.message, 'error' ); busy( del, false ); } );
			}
		} );

	}

	/* ------------------------------------------------------------------
	 * Upload form builder
	 * ---------------------------------------------------------------- */

	function initFormBuilder() {
		var fb = qs( '[data-smv-form-builder]' );
		if ( ! fb ) { return; }
		var out = qs( '[data-fb-output]', fb );
		var allTypes = qsa( '[data-fb-type]', fb ).map( function ( c ) { return c.getAttribute( 'data-fb-type' ); } );
		var maxInput = qs( '[data-fb="max_files"]', fb );
		var defaultMax = maxInput.value;

		function attr( name, value ) {
			return ' ' + name + '="' + String( value ).replace( /["\[\]]/g, '' ) + '"';
		}

		function build() {
			var sc = '[smv_upload_form';
			var label = qs( '[data-fb="label"]', fb ).value.trim();
			var title = qs( '[data-fb="title"]', fb ).value.trim();
			var button = qs( '[data-fb="button"]', fb ).value.trim();
			if ( label ) { sc += attr( 'label', label ); }
			if ( title ) { sc += attr( 'title', title ); }

			var fields = qsa( '[data-fb-field]', fb ).filter( function ( c ) { return c.checked; } ).map( function ( c ) { return c.getAttribute( 'data-fb-field' ); } );
			var req = qsa( '[data-fb-req]', fb ).filter( function ( c ) { return c.checked && fields.indexOf( c.getAttribute( 'data-fb-req' ) ) !== -1; } ).map( function ( c ) { return c.getAttribute( 'data-fb-req' ); } );
			qsa( '[data-fb-req]', fb ).forEach( function ( c ) { c.disabled = fields.indexOf( c.getAttribute( 'data-fb-req' ) ) === -1; } );
			if ( fields.join( ',' ) !== 'name,email,message' ) { sc += attr( 'fields', fields.join( ',' ) ); }
			if ( req.join( ',' ) !== 'name,email' ) { sc += attr( 'required', req.join( ',' ) ); }

			var types = qsa( '[data-fb-type]', fb ).filter( function ( c ) { return c.checked; } ).map( function ( c ) { return c.getAttribute( 'data-fb-type' ); } );
			if ( types.length && types.length !== allTypes.length ) { sc += attr( 'types', types.join( ',' ) ); }
			if ( maxInput.value && maxInput.value !== defaultMax ) { sc += attr( 'max_files', parseInt( maxInput.value, 10 ) || 1 ); }
			var custom = customSpec();
			if ( custom ) { sc += attr( 'custom', custom ); }
			if ( button ) { sc += attr( 'button', button ); }
			sc += ']';
			out.setAttribute( 'data-smv-copy', sc );
			qs( 'code', out ).textContent = sc;
		}
		// Custom field rows.
		var cfList = qs( '[data-fb-custom]', fb );
		var cfTpl = qs( 'template[data-fb-row]', fb );

		function clean( str ) {
			// Characters used by the shortcode syntax can't appear in labels or choices.
			return String( str ).replace( /[,:()|\[\]"]/g, '' ).replace( /\s+/g, ' ' ).trim();
		}

		function syncRow( li ) {
			var type = qs( '[data-cf="type"]', li ).value;
			var hasChoices = [ 'select', 'radio', 'checkbox' ].indexOf( type ) !== -1;
			qs( '[data-cf="options"]', li ).hidden = ! hasChoices;
			qs( '[data-cf-hint]', li ).hidden = type !== 'checkbox';
		}

		function customSpec() {
			return qsa( '.smv-cf', cfList ).map( function ( li ) {
				var label = clean( qs( '[data-cf="label"]', li ).value );
				if ( ! label ) { return ''; }
				var type = qs( '[data-cf="type"]', li ).value;
				var part = label;
				if ( type !== 'text' ) { part += ':' + type; }
				if ( [ 'select', 'radio', 'checkbox' ].indexOf( type ) !== -1 ) {
					var opts = qs( '[data-cf="options"]', li ).value.split( /[,|]/ ).map( clean ).filter( Boolean );
					if ( opts.length ) { part += '(' + opts.join( '|' ) + ')'; }
				}
				if ( qs( '[data-cf="required"]', li ).checked ) { part += ':required'; }
				if ( qs( '[data-cf="half"]', li ).checked ) { part += ':half'; }
				return part;
			} ).filter( Boolean ).join( ', ' );
		}

		qs( '[data-fb-add]', fb ).addEventListener( 'click', function () {
			var li = cfTpl.content.firstElementChild.cloneNode( true );
			cfList.appendChild( li );
			syncRow( li );
			qs( '[data-cf="label"]', li ).focus();
			build();
		} );
		cfList.addEventListener( 'click', function ( e ) {
			var rm = e.target.closest( '[data-cf-remove]' );
			if ( rm ) {
				rm.closest( '.smv-cf' ).remove();
				build();
			}
		} );
		cfList.addEventListener( 'change', function ( e ) {
			var li = e.target.closest( '.smv-cf' );
			if ( li ) { syncRow( li ); }
		} );

		fb.addEventListener( 'input', build );
		fb.addEventListener( 'change', build );
		build();
	}

	/* ------------------------------------------------------------------
	 * Settings
	 * ---------------------------------------------------------------- */

	function initSettings() {
		var form = qs( '[data-smv-settings-form]' );
		if ( ! form ) { return; }
		var referer = qs( '[data-smv-referer]' );
		var saveRow = qs( '[data-smv-save-row]' );
		var tabs = qsa( '[data-smv-tab]' );

		function show( key, focus ) {
			tabs.forEach( function ( tab ) {
				var on = tab.getAttribute( 'data-smv-tab' ) === key;
				tab.classList.toggle( 'is-active', on );
				tab.setAttribute( 'aria-selected', on ? 'true' : 'false' );
				tab.tabIndex = on ? 0 : -1;
				if ( on && focus ) { tab.focus(); }
			} );
			qsa( '[data-smv-panel]' ).forEach( function ( p ) { p.hidden = p.getAttribute( 'data-smv-panel' ) !== key; } );
			saveRow.hidden = key === 'status';
			var url = new URL( window.location.href );
			url.searchParams.set( 'tab', key );
			url.searchParams.delete( 'smv_notice' );
			url.searchParams.delete( 'settings-updated' );
			window.history.replaceState( null, '', url.toString() );
			referer.value = url.pathname + url.search;
		}

		tabs.forEach( function ( tab, i ) {
			tab.addEventListener( 'click', function () { show( tab.getAttribute( 'data-smv-tab' ) ); } );
			tab.addEventListener( 'keydown', function ( e ) {
				var dir = ( e.key === 'ArrowDown' || e.key === 'ArrowRight' ) ? 1 : ( e.key === 'ArrowUp' || e.key === 'ArrowLeft' ) ? -1 : 0;
				if ( ! dir ) { return; }
				e.preventDefault();
				var next = tabs[ ( i + dir + tabs.length ) % tabs.length ];
				show( next.getAttribute( 'data-smv-tab' ), true );
			} );
		} );
		var active = qs( '[data-smv-tab].is-active' );
		if ( active ) {
			tabs.forEach( function ( tab ) { tab.tabIndex = tab === active ? 0 : -1; } );
			var u = new URL( window.location.href );
			referer.value = u.pathname + '?page=smv-settings&tab=' + active.getAttribute( 'data-smv-tab' );
		}

		qsa( '[data-smv-toggle-all]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				var boxes = qsa( '[data-smv-group="' + btn.getAttribute( 'data-smv-toggle-all' ) + '"] input' );
				var allOn = boxes.every( function ( b ) { return b.checked; } );
				boxes.forEach( function ( b ) { b.checked = ! allOn; } );
			} );
		} );

		var test = qs( '[data-smv-dropbox-test]' );
		if ( test ) {
			test.addEventListener( 'click', function () {
				var out = qs( '[data-smv-dropbox-result]' );
				busy( test, true );
				out.innerHTML = '<div class="smv-muted">' + esc( t.checking ) + '</div>';
				ajax( 'dropbox_test' ).then( function ( d ) {
					out.innerHTML = '<div class="smv-alert smv-alert--success">✓ ' + esc( d.account ) + ( d.used ? ' · ' + esc( d.used ) + ' / ' + esc( d.total ) : '' ) + '</div>';
				} ).catch( function ( err ) {
					out.innerHTML = '<div class="smv-alert smv-alert--error">' + esc( err.message ) + '</div>';
				} ).then( function () { busy( test, false ); } );
			} );
		}

		var health = qs( '[data-smv-health]' );
		if ( health ) {
			health.addEventListener( 'click', function () {
				var out = qs( '[data-smv-health-result]' );
				busy( health, true );
				out.textContent = t.checking;
				ajax( 'health_check' ).then( function ( d ) {
					var cls = d.status === 'protected' ? 'success' : ( d.status === 'exposed' ? 'error' : 'warning' );
					out.className = '';
					out.innerHTML = '<div class="smv-alert smv-alert--' + cls + '">' + esc( d.message ) + ( d.writable ? '' : '<br><strong>Upload folder is not writable.</strong>' ) + '</div>';
					if ( d.status === 'exposed' ) { qs( '.smv-nginx' ).open = true; }
				} ).catch( function ( err ) {
					out.innerHTML = '<div class="smv-alert smv-alert--error">' + esc( err.message ) + '</div>';
				} ).then( function () { busy( health, false ); } );
			} );
		}
	}

	/* ------------------------------------------------------------------
	 * Boot
	 * ---------------------------------------------------------------- */

	document.addEventListener( 'DOMContentLoaded', function () {
		switch ( cfg.page ) {
			case 'smv-library': initLibrary(); break;
			case 'smv-collections': initCollectionsList(); initEditor(); break;
			case 'smv-forms': initFormBuilder(); break;
			case 'smv-submissions': initSubmissions(); break;
			case 'smv-settings': initSettings(); break;
		}
	} );
}( jQuery );
