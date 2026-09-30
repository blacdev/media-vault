/* Secure Media Vault — front end: lightbox, playlists, upload forms. No dependencies. */
/* Starting with "void" (not "(") keeps this file safe when optimisation plugins concatenate scripts
 * and the previous file doesn't end with a semicolon (otherwise the browser would treat the files
 * as one broken statement and stop every script after it, including the theme's). */
void function () {
	'use strict';
	try {

	// AJAX page loaders (e.g. Pro Radio) may execute this file again on every page change.
	if ( window.smvFrontLoaded ) { return; }
	window.smvFrontLoaded = true;

	var cfg = window.smvFront || {};

	// Mark an element as initialised; returns false if it already was.
	function claim( el, what ) {
		var key = 'smvReady' + what;
		if ( el.dataset[ key ] ) { return false; }
		el.dataset[ key ] = '1';
		return true;
	}

	var i18n = ( window.smvFront && window.smvFront.i18n ) || {};

	function qsa( sel, root ) { return Array.prototype.slice.call( ( root || document ).querySelectorAll( sel ) ); }

	function svg( d, size ) {
		return '<svg viewBox="0 0 24 24" width="' + ( size || 22 ) + '" height="' + ( size || 22 ) + '" aria-hidden="true"><path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="' + d + '"/></svg>';
	}

	function formatBytes( b ) {
		if ( b < 1024 ) { return b + ' B'; }
		var u = [ 'KB', 'MB', 'GB' ];
		var i = -1;
		do { b /= 1024; i++; } while ( b >= 1024 && i < u.length - 1 );
		return b.toFixed( b < 10 ? 1 : 0 ) + ' ' + u[ i ];
	}

	/* ------------------------------------------------------------------
	 * Lightbox
	 * ---------------------------------------------------------------- */

	function Lightbox( links ) {
		var idx = 0;
		var box, img, cap, counter, previous, touchX = null;

		function build() {
			box = document.createElement( 'div' );
			box.className = 'smv-lightbox';
			box.setAttribute( 'role', 'dialog' );
			box.setAttribute( 'aria-modal', 'true' );
			box.innerHTML =
				'<span class="smv-lightbox__counter" aria-live="polite"></span>' +
				'<img class="smv-lightbox__img" alt="">' +
				'<div class="smv-lightbox__caption"></div>' +
				'<button type="button" class="smv-lightbox__close" aria-label="' + ( i18n.close || 'Close' ) + '">' + svg( 'M6 6l12 12M18 6 6 18' ) + '</button>' +
				( links.length > 1 ? '<button type="button" class="smv-lightbox__prev" aria-label="' + ( i18n.prev || 'Previous' ) + '">' + svg( 'm15 6-6 6 6 6' ) + '</button>' +
				'<button type="button" class="smv-lightbox__next" aria-label="' + ( i18n.next || 'Next' ) + '">' + svg( 'm9 6 6 6-6 6' ) + '</button>' : '' );
			img = box.querySelector( 'img' );
			cap = box.querySelector( '.smv-lightbox__caption' );
			counter = box.querySelector( '.smv-lightbox__counter' );

			box.addEventListener( 'click', function ( e ) {
				if ( e.target === box || e.target.closest( '.smv-lightbox__close' ) ) { close(); }
				else if ( e.target.closest( '.smv-lightbox__prev' ) ) { go( -1 ); }
				else if ( e.target.closest( '.smv-lightbox__next' ) ) { go( 1 ); }
			} );
			box.addEventListener( 'touchstart', function ( e ) { touchX = e.touches[ 0 ].clientX; }, { passive: true } );
			box.addEventListener( 'touchend', function ( e ) {
				if ( touchX === null ) { return; }
				var dx = e.changedTouches[ 0 ].clientX - touchX;
				if ( Math.abs( dx ) > 50 ) { go( dx < 0 ? 1 : -1 ); }
				touchX = null;
			} );
		}

		function show() {
			var a = links[ idx ];
			img.src = a.href;
			img.alt = a.querySelector( 'img' ) ? a.querySelector( 'img' ).alt : '';
			cap.textContent = a.getAttribute( 'data-smv-caption' ) || '';
			counter.textContent = links.length > 1 ? ( idx + 1 ) + ' / ' + links.length : '';
			// Preload neighbours.
			[ 1, -1 ].forEach( function ( d ) {
				var n = links[ ( idx + d + links.length ) % links.length ];
				if ( n ) { ( new Image() ).src = n.href; }
			} );
		}

		function go( d ) {
			idx = ( idx + d + links.length ) % links.length;
			show();
		}

		function onKey( e ) {
			if ( e.key === 'Escape' ) { close(); }
			else if ( e.key === 'ArrowRight' ) { go( 1 ); }
			else if ( e.key === 'ArrowLeft' ) { go( -1 ); }
			else if ( e.key === 'Tab' ) {
				var f = qsa( 'button', box );
				var i = f.indexOf( document.activeElement );
				e.preventDefault();
				f[ ( i + ( e.shiftKey ? -1 : 1 ) + f.length ) % f.length ].focus();
			}
		}

		function open( i ) {
			if ( ! box ) { build(); }
			previous = document.activeElement;
			idx = i;
			show();
			document.body.appendChild( box );
			document.body.classList.add( 'smv-noscroll' );
			document.addEventListener( 'keydown', onKey );
			box.querySelector( '.smv-lightbox__close' ).focus();
		}

		function close() {
			box.remove();
			document.body.classList.remove( 'smv-noscroll' );
			document.removeEventListener( 'keydown', onKey );
			if ( previous ) { previous.focus(); }
		}

		links.forEach( function ( a, i ) {
			a.addEventListener( 'click', function ( e ) {
				if ( e.metaKey || e.ctrlKey || e.shiftKey ) { return; }
				e.preventDefault();
				// Stop theme AJAX navigation / other lightboxes (Elementor, theme) from also reacting.
				e.stopPropagation();
				open( i );
			} );
		} );
	}

	function initLightboxes( root ) {
		qsa( '.smv-collection', root ).filter( function ( col ) { return claim( col, 'Lightbox' ); } ).forEach( function ( col ) {
			var links = qsa( '[data-smv-lightbox] .smv-gallery__link, .smv-gallery__link', col ).filter( function ( a, i, arr ) {
				return arr.indexOf( a ) === i && a.closest( '[data-smv-lightbox]' );
			} );
			if ( links.length ) { Lightbox( links ); }
		} );
	}

	/* ------------------------------------------------------------------
	 * Playlists
	 * ---------------------------------------------------------------- */

	function initPlaylists( root ) {
		qsa( '.smv-playlist', root ).filter( function ( pl ) { return claim( pl, 'Playlist' ); } ).forEach( function ( pl ) {
			var media = pl.querySelector( '.smv-playlist__media' );
			var title = pl.querySelector( '.smv-playlist__title' );
			var tracks = qsa( '.smv-playlist__track', pl );
			var auto = pl.hasAttribute( 'data-smv-autoplay' );
			var loop = pl.hasAttribute( 'data-smv-loop' );
			var current = 0;

			function select( i, play ) {
				current = i;
				tracks.forEach( function ( tr, j ) {
					tr.classList.toggle( 'is-active', j === i );
					tr.classList.remove( 'is-playing' );
					if ( j === i ) { tr.setAttribute( 'aria-current', 'true' ); } else { tr.removeAttribute( 'aria-current' ); }
				} );
				media.src = tracks[ i ].getAttribute( 'data-src' );
				if ( title ) { title.textContent = tracks[ i ].getAttribute( 'data-title' ); }
				if ( play ) {
					var p = media.play();
					if ( p && p.catch ) { p.catch( function () {} ); }
				}
			}

			tracks.forEach( function ( tr, i ) {
				tr.addEventListener( 'click', function () {
					if ( i === current && ! media.paused ) { media.pause(); return; }
					if ( i === current ) { media.play(); return; }
					select( i, true );
				} );
			} );

			media.addEventListener( 'play', function () { if ( tracks[ current ] ) { tracks[ current ].classList.add( 'is-playing' ); } } );
			media.addEventListener( 'pause', function () { if ( tracks[ current ] ) { tracks[ current ].classList.remove( 'is-playing' ); } } );
			media.addEventListener( 'ended', function () {
				if ( ! auto && ! loop ) { return; }
				if ( current < tracks.length - 1 ) { select( current + 1, true ); }
				else if ( loop ) { select( 0, true ); }
			} );

		} );
	}

	/* ------------------------------------------------------------------
	 * Upload forms
	 * ---------------------------------------------------------------- */

	function initForms( root ) {
		qsa( 'form.smv-form', root ).filter( function ( f ) { return claim( f, 'Form' ); } ).forEach( function ( form ) {
			var input = form.querySelector( '.smv-drop__input' );
			var drop = form.querySelector( '[data-smv-drop]' );
			var queue = form.querySelector( '.smv-form__queue' );
			var statusWrap = form.querySelector( '.smv-form__status-wrap' );
			var progress = form.querySelector( '.smv-form__progress' );
			var bar = form.querySelector( '.smv-form__bar' );
			var submit = form.querySelector( '.smv-form__submit' );
			var maxFiles = parseInt( form.getAttribute( 'data-max-files' ), 10 ) || 1;
			var maxBytes = parseInt( form.getAttribute( 'data-max-bytes' ), 10 ) || 0;
			var types = ( form.getAttribute( 'data-types' ) || '' ).split( ',' ).filter( Boolean );
			var files = [];
			var canSetFiles = typeof DataTransfer !== 'undefined';

			function status( msg, ok ) {
				statusWrap.innerHTML = '';
				if ( ! msg ) { return; }
				var div = document.createElement( 'div' );
				div.className = 'smv-form__status is-' + ( ok ? 'success' : 'error' );
				div.textContent = msg;
				statusWrap.appendChild( div );
			}

			function problem( f ) {
				var ext = ( f.name.split( '.' ).pop() || '' ).toLowerCase();
				if ( types.length && types.indexOf( ext ) === -1 ) { return f.name + ' ' + ( i18n.badType || 'is not allowed.' ); }
				if ( maxBytes && f.size > maxBytes ) { return f.name + ' ' + ( i18n.tooBig || 'is too large.' ); }
				return '';
			}

			function sync() {
				if ( canSetFiles ) {
					var dt = new DataTransfer();
					files.forEach( function ( f ) { dt.items.add( f ); } );
					input.files = dt.files;
				}
				queue.innerHTML = '';
				files.forEach( function ( f, i ) {
					var li = document.createElement( 'li' );
					var err = problem( f );
					if ( err ) { li.className = 'is-invalid'; li.title = err; }
					li.innerHTML = '<span class="smv-form__qname"></span><span class="smv-form__qsize"></span>' +
						( canSetFiles ? '<button type="button" class="smv-form__qremove" aria-label="' + ( i18n.remove || 'Remove' ) + '">' + svg( 'M6 6l12 12M18 6 6 18', 16 ) + '</button>' : '' );
					li.querySelector( '.smv-form__qname' ).textContent = f.name;
					li.querySelector( '.smv-form__qsize' ).textContent = err ? err.replace( f.name + ' ', '' ) : formatBytes( f.size );
					var rm = li.querySelector( 'button' );
					if ( rm ) {
						rm.setAttribute( 'aria-label', ( i18n.remove || 'Remove' ) + ' ' + f.name );
						rm.addEventListener( 'click', function () { files.splice( i, 1 ); sync(); } );
					}
					queue.appendChild( li );
				} );
			}

			function addFiles( list ) {
				Array.prototype.forEach.call( list, function ( f ) {
					var dup = files.some( function ( x ) { return x.name === f.name && x.size === f.size; } );
					if ( ! dup ) { files.push( f ); }
				} );
				if ( files.length > maxFiles ) {
					files = files.slice( 0, maxFiles );
					status( i18n.tooMany || 'Too many files.', false );
				} else {
					status( '' );
				}
				sync();
			}

			input.addEventListener( 'change', function () {
				if ( canSetFiles ) { addFiles( input.files ); } else { files = Array.prototype.slice.call( input.files ); sync(); }
			} );

			[ 'dragenter', 'dragover' ].forEach( function ( ev ) {
				drop.addEventListener( ev, function () { drop.classList.add( 'is-over' ); } );
			} );
			[ 'dragleave', 'drop' ].forEach( function ( ev ) {
				drop.addEventListener( ev, function () { drop.classList.remove( 'is-over' ); } );
			} );
			if ( canSetFiles ) {
				drop.addEventListener( 'drop', function ( e ) {
					e.preventDefault();
					if ( e.dataTransfer && e.dataTransfer.files ) { addFiles( e.dataTransfer.files ); }
				} );
				drop.addEventListener( 'dragover', function ( e ) { e.preventDefault(); } );
			}

			form.addEventListener( 'submit', function ( e ) {
				e.preventDefault();
				e.stopPropagation(); // Theme AJAX loaders must not hijack this upload.
				status( '' );

				// Client-side validation (server re-validates everything).
				var firstInvalid = null;
				qsa( '[required]', form ).forEach( function ( el ) {
					var bad;
					if ( el.type === 'file' ) { bad = ! files.length; }
					else if ( el.type === 'checkbox' ) { bad = ! el.checked; }
					else { bad = ! el.value.trim() || ( el.type === 'email' && ! /^\S+@\S+\.\S+$/.test( el.value ) ); }
					el.setAttribute( 'aria-invalid', bad ? 'true' : 'false' );
					if ( bad && ! firstInvalid ) { firstInvalid = el; }
				} );
				// Required radio / checkbox groups: at least one choice.
				qsa( 'fieldset[data-smv-required]', form ).forEach( function ( fs ) {
					var bad = ! fs.querySelector( 'input:checked' );
					fs.setAttribute( 'aria-invalid', bad ? 'true' : 'false' );
					if ( bad && ! firstInvalid ) { firstInvalid = fs.querySelector( 'input' ); }
				} );
				// Light format checks (the server re-validates everything).
				qsa( 'input[type="url"], input[type="tel"]', form ).forEach( function ( el ) {
					if ( ! el.value.trim() ) { return; }
					var bad = el.type === 'url' ? ! /^https?:\/\/\S+\.\S+/.test( el.value.trim() ) : ! /^[0-9+().\-\s]{3,40}$/.test( el.value.trim() );
					el.setAttribute( 'aria-invalid', bad ? 'true' : 'false' );
					if ( bad && ! firstInvalid ) { firstInvalid = el; }
				} );
				if ( firstInvalid ) {
					firstInvalid.focus();
					return;
				}
				var invalid = files.map( problem ).filter( Boolean );
				if ( invalid.length ) {
					status( invalid.join( ' ' ), false );
					return;
				}

				var fd = new FormData( form );
				if ( canSetFiles ) {
					fd.delete( 'smv_files[]' );
					files.forEach( function ( f ) { fd.append( 'smv_files[]', f ); } );
				}
				fd.append( 'smv_ajax', '1' );

				var xhr = new XMLHttpRequest();
				submit.disabled = true;
				var label = submit.textContent;
				submit.textContent = i18n.uploading || 'Uploading…';
				progress.hidden = false;
				bar.style.width = '0';

				xhr.upload.addEventListener( 'progress', function ( ev ) {
					if ( ev.lengthComputable ) { bar.style.width = Math.round( ( ev.loaded / ev.total ) * 100 ) + '%'; }
				} );
				xhr.addEventListener( 'load', function () {
					var json = null;
					try { json = JSON.parse( xhr.responseText ); } catch ( err ) {}
					var ok = json && json.success;
					var msg = json && json.data && json.data.message ? json.data.message : ( xhr.status === 413 ? ( i18n.tooBig || 'Too large.' ) : 'Upload failed.' );
					status( msg, ok );
					if ( ok ) {
						form.reset();
						files = [];
						sync();
					}
					done( label );
				} );
				xhr.addEventListener( 'error', function () {
					status( 'Upload failed. Please check your connection.', false );
					done( label );
				} );
				// Not form.action: the hidden <input name="action"> shadows that property.
				xhr.open( 'POST', form.getAttribute( 'action' ) );
				xhr.send( fd );
			} );

			function done( label ) {
				submit.disabled = false;
				submit.textContent = label;
				setTimeout( function () { progress.hidden = true; }, 400 );
				statusWrap.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
			}
		} );
	}

	/* ------------------------------------------------------------------
	 * One sound at a time (works with theme players such as Pro Radio's)
	 * ---------------------------------------------------------------- */

	function isOurs( el ) { return !! ( el.closest && el.closest( '.smv' ) ); }

	// 'play' doesn't bubble, so listen in the capture phase to see every media element on the page.
	document.addEventListener( 'play', function ( e ) {
		var target = e.target;
		if ( ! target || ! /^(AUDIO|VIDEO)$/.test( target.tagName ) ) { return; }
		var ours = isOurs( target );
		if ( ours ) {
			document.dispatchEvent( new CustomEvent( 'smv:play', { detail: { media: target } } ) );
		}
		if ( cfg.pauseOthers === false ) { return; }
		qsa( 'audio, video' ).forEach( function ( m ) {
			if ( m === target || m.paused ) { return; }
			// Our player started: pause everything else (e.g. the theme's radio player).
			// Something else started: pause only our players.
			if ( ours || isOurs( m ) ) { m.pause(); }
		} );
	}, true );

	/* ------------------------------------------------------------------
	 * Download links: let the browser download, keep AJAX loaders out
	 * ---------------------------------------------------------------- */

	document.addEventListener( 'click', function ( e ) {
		var a = e.target.closest && e.target.closest( '.smv-files__btn' );
		if ( a ) { e.stopPropagation(); }
	}, true );

	/* ------------------------------------------------------------------
	 * Boot + watch for content added later (AJAX page loads, Elementor
	 * popups/tabs/editor preview, infinite scroll…)
	 * ---------------------------------------------------------------- */

	// Safety net: if an optimisation plugin stripped the stylesheet, add it back when needed.
	function ensureStyles() {
		if ( ! cfg.cssUrl || document.getElementById( 'smv-frontend-css' ) || ! document.querySelector( '.smv' ) ) { return; }
		var present = Array.prototype.some.call( document.styleSheets || [], function ( sh ) {
			try {
				return !! sh.href && sh.href.indexOf( 'secure-media-vault' ) !== -1 && sh.href.indexOf( 'frontend' ) !== -1;
			} catch ( e ) { return false; }
		} ) || !! document.querySelector( 'link[href*="secure-media-vault"][href*="frontend"], #smv-frontend-css, style[data-smv]' );
		if ( present ) { return; }
		var l = document.createElement( 'link' );
		l.id = 'smv-frontend-css';
		l.rel = 'stylesheet';
		l.href = cfg.cssUrl;
		document.head.appendChild( l );
	}

	function scan( root ) {
		root = root || document;
		ensureStyles();
		initLightboxes( root );
		initPlaylists( root );
		initForms( root );
	}

	function boot() {
		scan( document );
		if ( typeof MutationObserver === 'undefined' ) { return; }
		var queued = false;
		new MutationObserver( function ( mutations ) {
			if ( queued ) { return; }
			var relevant = mutations.some( function ( m ) {
				return Array.prototype.some.call( m.addedNodes, function ( n ) {
					return n.nodeType === 1 && ( n.classList.contains( 'smv' ) || n.querySelector( '.smv' ) );
				} );
			} );
			if ( ! relevant ) { return; }
			queued = true;
			( window.requestAnimationFrame || setTimeout )( function () { queued = false; scan( document ); } );
		} ).observe( document.documentElement, { childList: true, subtree: true } );
	}

	// Public hook for themes/plugins that want to re-scan explicitly.
	window.smvInit = scan;

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
	} catch ( err ) {
		// Never let a problem in this file affect the rest of the site.
		if ( window.console && console.warn ) { console.warn( 'Secure Media Vault:', err ); }
	}
}();
