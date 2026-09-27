/*
 * Front-end behaviour for legacy (5.x) content. `__PREFIX__` is replaced with the neutral class
 * prefix when the file is published, exactly as in legacy.css, so the page carries no product
 * name. Loaded (deferred) only on a post that still carries legacy markup AND a marker one of
 * these behaviours needs. No dependencies. Every behaviour below reproduces what the 5.x
 * front-end scripts did to SAVED content (editor-only code is not reproduced); the notes on each
 * say where this deliberately differs and why.
 *
 * The 5.x FREE edition's behaviours only: the carousel, the countdown and the "force overflow"
 * setting were premium modules in 5.x and live in the premium layer's own script.
 *
 * Reduced motion: when the visitor asks for it, nothing moves on its own — scroll animations show
 * their end state, counters show their number, video players are placed paused (no autoplay,
 * no loop), Ken Burns does not play and parallax is a static background.
 */
( function ( win, doc ) {
	'use strict';

	var P = '__PREFIX__-l-';
	var D = 'data-__PREFIX__-l-';
	var CFG = win.__PREFIX__LegacyConfig || {};
	var root = doc.documentElement;
	var reduced = !! (
		win.matchMedia && win.matchMedia( '(prefers-reduced-motion: reduce)' ).matches
	);
	var raf =
		win.requestAnimationFrame ||
		function ( fn ) {
			return win.setTimeout( fn, 16 );
		};

	/* ── helpers ─────────────────────────────────────────────────────────────────────────── */

	function all( selector, scope ) {
		return Array.prototype.slice.call( ( scope || doc ).querySelectorAll( selector ) );
	}

	function isRTL() {
		return 'rtl' === root.getAttribute( 'dir' );
	}

	// The 5.x check for "a phone or a tablet": no video backgrounds there.
	function isMobile() {
		return /(Mobi|Android)/.test( win.navigator.userAgent );
	}

	// The scroll-animation library disabled itself on phones only.
	function isPhone() {
		return /(iPhone|iPod|Android.+Mobile|Windows Phone|IEMobile|BlackBerry|Opera Mini)/i.test(
			win.navigator.userAgent
		);
	}

	function directChild( el, cls ) {
		var i;
		for ( i = 0; i < el.children.length; i++ ) {
			if ( el.children[ i ].classList.contains( cls ) ) {
				return el.children[ i ];
			}
		}
		return null;
	}

	function cssUrl( value ) {
		var m = /url\(\s*(['"]?)(.*?)\1\s*\)/i.exec( value || '' );
		return m ? m[ 2 ] : '';
	}

	/**
	 * A converted row (`<prefix>-row`) does not carry the legacy row class the compat stylesheet
	 * hangs the background-layer rules on, so a layer this script adds to one is styled inline.
	 *
	 * @param {Element} host  The element the layer belongs to.
	 * @param {Element} layer The layer.
	 */
	function layer( host, layer ) {
		var s = layer.style;
		if ( host.classList.contains( P + 'row' ) ) {
			return;
		}
		if ( ! host.style.position || 'static' === host.style.position ) {
			host.style.position = 'relative';
		}
		host.style.overflow = 'hidden';
		if ( ! host.style.transform ) {
			host.style.transform = 'translate(0)';
		}
		s.position = 'absolute';
		s.top = s.right = s.bottom = s.left = '0';
		s.width = s.height = '100%';
		s.zIndex = '-100';
		s.pointerEvents = 'none';
		s.backgroundSize = s.backgroundSize || 'cover';
		s.backgroundPosition = 'center';
		s.backgroundRepeat = 'no-repeat';
	}

	/* ── full-width rows ─────────────────────────────────────────────────────────────────── */
	// 5.x widened `data-width` rows to the body with `left` and `width`; the compat stylesheet
	// keeps such a row hidden until its style carries `left:`. The offsets are measured from
	// physical rectangles, so they are written as physical properties.

	function rowReset( el ) {
		el.style.width = '';
		el.style.position = '';
		el.style.maxWidth = '';
		el.style[ isRTL() ? 'right' : 'left' ] = '';
	}

	function fullWidth( el, fit ) {
		var rtl = isRTL();
		var side = rtl ? 'right' : 'left';
		var s = el.style;
		var bodyWidth, rect, bodyRect, start, end;

		s.width = 'auto';
		s.position = 'relative';
		s.maxWidth = 'none';
		s.marginLeft = '0px';
		s.marginRight = '0px';
		if ( fit ) {
			s.paddingLeft = '';
			s.paddingRight = '';
		}
		// Make sure the parent does not clip the row.
		el.parentNode.style.overflowX = 'visible';
		s[ side ] = '0px';
		// Placed from here on: this also switches off the pre-positioning CSS (its rules skip a
		// row whose style carries `visibility`), so its padding cannot inflate the measurement.
		// 5.x left a right-to-left row hidden (its rule looks for `left:`); show it either way.
		s.visibility = 'visible';

		bodyWidth = doc.body.clientWidth;
		rect = el.getBoundingClientRect();
		bodyRect = doc.body.getBoundingClientRect();
		start = rtl ? bodyRect.right - rect.right : rect.left - bodyRect.left;

		s.width = bodyWidth + 'px';
		s.maxWidth = bodyWidth + 'px';
		s[ side ] = -start + 'px';

		if ( ! fit ) {
			return;
		}
		// Pad the row so its content keeps following the content column.
		end = bodyWidth - rect.width - start;
		if ( rect.width > bodyWidth ) {
			start = 0;
			end = 0;
		}
		s[ rtl ? 'paddingRight' : 'paddingLeft' ] = start + 'px';
		s[ rtl ? 'paddingLeft' : 'paddingRight' ] = end + 'px';
	}

	function fixRow( el ) {
		var width = el.getAttribute( 'data-width' );
		// Nested rows cannot be full width.
		if ( ! width || el.parentNode.classList.contains( P + 'col' ) ) {
			rowReset( el );
		} else {
			fullWidth( el, 'full-width' !== width );
		}
	}

	function fixRows() {
		all( '.' + P + 'row[data-width]' ).forEach( fixRow );
	}

	/* ── per-breakpoint margins ──────────────────────────────────────────────────────────── */
	// Desktop above 800px, phone below 400px, tablet between — the 5.x breakpoints. Inline top and
	// bottom margins inside legacy content are swapped for the stored per-breakpoint value (a
	// phone falls back to the tablet value); the desktop value is remembered and put back, as 5.x did.

	var MARGINS = [ 'margin-top', 'margin-bottom' ];

	function screenSize() {
		if ( win.innerWidth > 800 ) {
			return 'desktop';
		}
		return win.innerWidth < 400 ? 'phone' : 'tablet';
	}

	function switchMargins( size, oldSize ) {
		var w = '.' + P + 'main-wrapper';
		if ( ! doc.querySelector( w ) || size === oldSize ) {
			return;
		}
		MARGINS.forEach( function ( prop ) {
			var selector =
				w + ' [style*="margin:"], ' + w + ' [style*="' + prop + ':"], [' + D + 'tablet-' + prop + '], [' + D + 'phone-' + prop + ']';
			all( selector ).forEach( function ( el ) {
				var current = el.style.getPropertyValue( prop );
				var next = el.getAttribute( D + size + '-' + prop );
				if ( current ) {
					el.setAttribute( D + oldSize + '-' + prop, current );
				}
				if ( ! next && 'phone' === size ) {
					next = el.getAttribute( D + 'tablet-' + prop );
				}
				if ( ! next ) {
					next = el.getAttribute( D + 'desktop-' + prop );
				}
				if ( next ) {
					el.style.setProperty( prop, next );
				} else {
					el.style.removeProperty( prop );
				}
			} );
			if ( 'desktop' === size ) {
				all( '[' + D + 'desktop-' + prop + ']' ).forEach( function ( el ) {
					el.removeAttribute( D + 'desktop-' + prop );
				} );
			}
		} );
	}

	/* ── parallax ────────────────────────────────────────────────────────────────────────── */

	var parallaxWorking = false;

	function initParallax( el ) {
		var div = directChild( el, P + 'parallax' );
		if ( ! div ) {
			div = doc.createElement( 'div' );
			div.className = P + 'parallax';
			el.insertBefore( div, el.firstChild );
		}
		div.style.backgroundColor = el.style.backgroundColor;
		div.style.backgroundImage = el.style.backgroundImage;
		div.style.backgroundSize = el.style.backgroundSize;
		div.style.backgroundRepeat = el.style.backgroundRepeat;
		div.setAttribute( 'data-speed', el.getAttribute( D + 'parallax' ) );
		layer( el, div );
	}

	function parallaxOffset( div ) {
		var rowRect = div.parentNode.getBoundingClientRect();
		var speed = parseFloat( div.getAttribute( 'data-speed' ) ) || 0;
		var height = parseInt( rowRect.height, 10 );
		var scrollY = win.scrollY || win.pageYOffset || 0;
		var windowHeight = win.innerHeight;
		var max = parseInt( rowRect.bottom, 10 ) + scrollY;
		var min = parseInt( rowRect.top, 10 ) - windowHeight + scrollY;
		var swap, percentage, based;

		if ( speed < 0 ) {
			swap = max;
			max = min;
			min = swap;
		}
		percentage = max === min ? 0 : ( scrollY - min ) / ( max - min );
		based = height > windowHeight ? height : windowHeight;
		div.style.height = ( 1 + Math.abs( speed ) ) * based + 'px';
		// 10px of bleed, 5px top and bottom.
		return -percentage * ( Math.abs( speed ) * based - 10 ) - 5;
	}

	function updateParallax() {
		all( '[' + D + 'parallax] > .' + P + 'parallax' ).forEach( function ( div ) {
			div.style.position = 'fixed';
			div.style.transform = 'translate3d(0, ' + parallaxOffset( div ) + 'px, 0)';
		} );
		parallaxWorking = false;
	}

	function initAllParallax() {
		var rows = all( '[' + D + 'parallax]' );
		rows.forEach( initParallax );
		// Placed where 5.x placed it on every view; under reduced motion it is placed once and
		// does not follow the scroll (the same rule as the paused video layer). A still, full-row
		// background instead differed from 5.x on every parallax row (measured, restaurant).
		if ( rows.length ) {
			updateParallax();
		}
		if ( rows.length && ! reduced ) {
			win.addEventListener(
				'scroll',
				function () {
					if ( ! parallaxWorking ) {
						parallaxWorking = true;
						raf( updateParallax );
					}
				},
				{ passive: true }
			);
		}
	}

	/* ── video backgrounds ───────────────────────────────────────────────────────────────── */
	// 5.x drove YouTube through its iframe API and Vimeo through a postMessage library to mute and
	// loop them. Both players do that from URL parameters, so a plain iframe is enough.

	function videoData( url ) {
		var m;
		url = String( url ).trim();
		m =
			url.match( /^.*youtube\.com\/watch\?v=([^&?/]+).*$/i ) ||
			url.match( /^.*youtube\.com\/embed\/([^&?/]+).*$/i ) ||
			url.match( /^.*youtube\.com\/v\/([^&?/]+).*$/i ) ||
			url.match( /^.*youtu\.be\/([^&?/]+).*$/i );
		if ( m ) {
			return { type: 'youtube', id: m[ 1 ] };
		}
		m = url.match( /^.*vimeo\.com\/(\w*\/)*(\d+).*$/i ) || url.match( /^(.*?)(\d{6,})(.*?)$/i );
		if ( m ) {
			return { type: 'vimeo', id: m[ 2 ] };
		}
		return { type: 'youtube', id: url };
	}

	// Under reduced motion the same player is placed where 5.x placed it, paused: no autoplay, no
	// loop (WCAG 2.2.2), so the row still shows the video's frame rather than an empty layer.
	function embedSrc( data ) {
		var id = encodeURIComponent( data.id );
		if ( 'vimeo' === data.type ) {
			return reduced
				? 'https://player.vimeo.com/video/' + id + '?autoplay=0&loop=0&muted=1&controls=0&title=0&byline=0&portrait=0&dnt=1'
				: 'https://player.vimeo.com/video/' + id + '?background=1&autoplay=1&loop=1&muted=1&dnt=1';
		}
		return (
			'https://www.youtube-nocookie.com/embed/' +
			id +
			( reduced ? '?autoplay=0&mute=1&loop=0' : '?autoplay=1&mute=1&loop=1&playlist=' + id ) +
			'&controls=0&playsinline=1&rel=0&iv_load_policy=3&disablekb=1&fs=0&modestbranding=1'
		);
	}

	// Cover the row with a 16:9 player, centred.
	function resizeEmbed( el ) {
		var box = directChild( el, P + 'video-bg' );
		var rect, w, h;
		if ( ! box || ! box.querySelector( 'iframe' ) ) {
			return;
		}
		rect = el.getBoundingClientRect();
		if ( ( 16 / 9 ) * rect.height >= rect.width ) {
			h = rect.height;
			w = ( 16 / 9 ) * rect.height;
		} else {
			w = rect.width;
			h = ( rect.width * 9 ) / 16;
		}
		box.style.width = w + 'px';
		box.style.height = h + 'px';
		box.style.marginTop = -( h - rect.height ) / 2 + 'px';
		box.style.setProperty( 'margin-inline-start', -( w - rect.width ) / 2 + 'px' );
	}

	var VIDEO_HOSTS = '[' + D + 'video-webm], [' + D + 'video-mp4], [' + D + 'video-url]';

	function initVideo( el ) {
		var webm = el.getAttribute( D + 'video-webm' );
		var mp4 = el.getAttribute( D + 'video-mp4' );
		var url = el.getAttribute( D + 'video-url' );
		var box, inner, source, tint, bg;

		// Stale players saved into the content by the 5.x editor are replaced — including one a
		// conversion moved into a column of this row (but not one belonging to a nested row).
		all( '.' + P + 'video-bg', el ).forEach( function ( stale ) {
			if ( stale.parentNode.closest( VIDEO_HOSTS ) === el ) {
				stale.parentNode.removeChild( stale );
			}
		} );
		if ( ( ! webm && ! mp4 && ! url ) || isMobile() ) {
			return;
		}
		box = doc.createElement( 'div' );
		box.className = P + 'video-bg';
		box.setAttribute( 'aria-hidden', 'true' );

		if ( webm || mp4 ) {
			inner = doc.createElement( 'video' );
			inner.muted = true;
			inner.setAttribute( 'muted', '' );
			inner.setAttribute( 'playsinline', '' );
			inner.setAttribute( 'preload', reduced ? 'metadata' : 'auto' );
			inner.setAttribute( 'tabindex', '-1' );
			if ( cssUrl( el.style.backgroundImage ) ) {
				inner.setAttribute( 'poster', cssUrl( el.style.backgroundImage ) );
			}
			if ( ! reduced ) {
				inner.setAttribute( 'autoplay', '' );
				inner.setAttribute( 'loop', '' );
			}
			// Smaller WebM first, MP4 second.
			[
				[ webm, 'video/webm' ],
				[ mp4, 'video/mp4' ],
			].forEach( function ( pair ) {
				if ( pair[ 0 ] ) {
					source = doc.createElement( 'source' );
					source.setAttribute( 'src', pair[ 0 ] );
					source.setAttribute( 'type', pair[ 1 ] );
					inner.appendChild( source );
				}
			} );
		} else {
			inner = doc.createElement( 'iframe' );
			inner.setAttribute( 'src', embedSrc( videoData( url ) ) );
			inner.setAttribute( 'title', CFG.videoTitle || '' );
			inner.setAttribute( 'tabindex', '-1' );
			inner.setAttribute( 'allow', 'autoplay; encrypted-media; picture-in-picture' );
			inner.setAttribute( 'frameborder', '0' );
		}
		box.appendChild( inner );

		// A tinted row (an rgba() overlay in its background) tints its video the same way.
		if ( /rgba\(/i.test( el.style.backgroundImage ) ) {
			tint = /(rgba\(\s*[\d.]+\s*,\s*[\d.]+\s*,\s*[\d.]+\s*,\s*)([\d.]+)(.*)/i;
			bg = el.style.backgroundColor;
			box.style.backgroundColor = bg.replace( tint, '$11$3' );
			inner.style.opacity = tint.test( bg ) ? String( 1 - parseFloat( bg.replace( tint, '$2' ) ) ) : '0';
		} else {
			inner.style.opacity = '1';
		}

		el.insertBefore( box, el.firstChild );
		layer( el, box );
		if ( 'VIDEO' === inner.tagName ) {
			if ( ! reduced ) {
				try {
					inner.play().catch( function () {} );
				} catch {
					// Not every environment can play; the poster stays.
				}
			}
		} else {
			resizeEmbed( el );
		}
	}

	function videoRows() {
		return all( VIDEO_HOSTS );
	}

	/* ── Ken Burns ───────────────────────────────────────────────────────────────────────── */

	function initKenBurns( el ) {
		var i, bg, bgs, count = 0;
		for ( i = 5; i > 0; i-- ) {
			if ( ! el.getAttribute( D + 'kenburns-' + i ) ) {
				continue;
			}
			if ( ! count ) {
				count = i;
			}
			while ( all( '.' + P + 'kenburns-bg', el ).length < count ) {
				bg = doc.createElement( 'div' );
				bg.className = P + 'kenburns-bg';
				el.insertBefore( bg, el.firstChild );
			}
			while ( ( bgs = all( '.' + P + 'kenburns-bg', el ) ).length > count ) {
				bgs[ bgs.length - 1 ].parentNode.removeChild( bgs[ bgs.length - 1 ] );
			}
			bg = all( '.' + P + 'kenburns-bg', el )[ i - 1 ];
			bg.className = P + 'kenburns-bg ' + P + 'kenburns-bg-' + count;
			bg.style.backgroundImage = 'url("' + el.getAttribute( D + 'kenburns-' + i ).replace( /["\\]/g, '' ) + '")';
			bg.setAttribute( 'aria-hidden', 'true' );
			layer( el, bg );
			if ( reduced ) {
				bg.style.animation = 'none';
			}
		}
	}

	/* ── scroll animations ───────────────────────────────────────────────────────────────── */
	// A port of the scroll-animation library 5.x bundled (offset 120, `data-aos-offset`,
	// `-anchor`, `-anchor-placement`, `-once`), which the compat stylesheet's `[data-aos]` rules
	// expect: those elements stay invisible until they get `aos-animate`.

	var aos = [];

	function pageTop( el ) {
		var y = 0;
		while ( el && ! isNaN( el.offsetTop ) ) {
			y += el.offsetTop - ( 'BODY' !== el.tagName ? el.scrollTop : 0 );
			el = el.offsetParent;
		}
		return y;
	}

	function aosPosition( el ) {
		var wh = win.innerHeight;
		var offset = el.getAttribute( 'data-aos-offset' );
		var anchor = el.getAttribute( 'data-aos-anchor' );
		var placement = el.getAttribute( 'data-aos-anchor-placement' );
		var extra = 0;
		var top, a;
		if ( offset && ! isNaN( offset ) ) {
			extra = parseInt( offset, 10 );
		}
		if ( anchor ) {
			try {
				a = doc.querySelector( anchor );
			} catch {
				a = null;
			}
			el = a || el;
		}
		top = pageTop( el );
		top +=
			{
				'center-bottom': el.offsetHeight / 2,
				'bottom-bottom': el.offsetHeight,
				'top-center': wh / 2,
				'bottom-center': wh / 2 + el.offsetHeight,
				'center-center': wh / 2 + el.offsetHeight / 2,
				'top-top': wh,
				'bottom-top': el.offsetHeight + wh,
				'center-top': el.offsetHeight / 2 + wh,
			}[ placement ] || 0;
		if ( ! placement && ! offset ) {
			extra = 120;
		}
		return top + extra;
	}

	function aosRefresh() {
		aos.forEach( function ( item ) {
			item.position = aosPosition( item.node );
		} );
	}

	function aosScroll() {
		var top = win.innerHeight + ( win.pageYOffset || 0 );
		aos.forEach( function ( item ) {
			if ( top > item.position ) {
				if ( item.node.hasAttribute( 'data-aos-js' ) && ! item.fired ) {
					item.fired = true;
					if ( item.node.getAttribute( D + 'count-up' ) ) {
						countUp( item.node );
					}
				}
				item.node.classList.add( 'aos-animate' );
			} else if ( 'true' !== item.node.getAttribute( 'data-aos-once' ) ) {
				item.node.classList.remove( 'aos-animate' );
			}
		} );
	}

	function initAos() {
		var nodes = all( '[data-aos], [data-aos-js]' );
		var timer = null;
		if ( ! nodes.length ) {
			return;
		}
		if ( isPhone() ) {
			nodes.forEach( function ( el ) {
				[ 'data-aos', 'data-aos-easing', 'data-aos-duration', 'data-aos-delay' ].forEach( function ( a ) {
					el.removeAttribute( a );
				} );
			} );
			return;
		}
		doc.body.setAttribute( 'data-aos-easing', 'ease' );
		doc.body.setAttribute( 'data-aos-duration', '400' );
		doc.body.setAttribute( 'data-aos-delay', '0' );
		// A saved count-up can carry the editor's `visibility: visible`; the stylesheet decides.
		all( '[' + D + 'count-up]' ).forEach( function ( el ) {
			el.style.visibility = '';
		} );
		aos = nodes.map( function ( node ) {
			node.classList.add( 'aos-init' );
			return { node: node, position: 0, fired: false };
		} );
		if ( reduced ) {
			aos.forEach( function ( item ) {
				item.node.style.transition = 'none';
				if ( /^loop-/.test( item.node.getAttribute( 'data-aos' ) || '' ) ) {
					item.node.style.animation = 'none';
				}
				item.node.classList.add( 'aos-animate' );
			} );
			return;
		}
		aosRefresh();
		// One frame with the start state computed, so the transition runs.
		raf( function () {
			raf( aosScroll );
		} );
		win.addEventListener(
			'scroll',
			function () {
				if ( ! timer ) {
					timer = win.setTimeout( function () {
						timer = null;
						aosScroll();
					}, 99 );
				}
			},
			{ passive: true }
		);
		win.addEventListener( 'load', function () {
			aosRefresh();
			aosScroll();
		} );
	}

	/* ── count-up numbers ────────────────────────────────────────────────────────────────── */
	// Counts every number inside the element up from zero in `data-cu-time` ms, one step every
	// `data-cu-delay` ms, keeping thousands separators and decimals; the last step is the
	// original markup, byte for byte.

	function countUp( el ) {
		var lang = root.getAttribute( 'lang' ) || undefined;
		var time = parseFloat( el.getAttribute( 'data-cu-time' ) );
		var delay = parseFloat( el.getAttribute( 'data-cu-delay' ) );
		var divisions = Math.floor( time / delay );
		var original = el.innerHTML;
		var parts, nums, i, k, num, isComma, isFloat, places, val, next, step;

		win.clearTimeout( el.__countTimer );
		if ( ! /[0-9]/.test( original ) || ! ( divisions >= 1 ) || reduced ) {
			return;
		}
		parts = original.split( /(<[^>]+>|[0-9.][,.0-9]*[0-9]*)/ );
		nums = [];
		for ( k = 0; k < divisions; k++ ) {
			nums.push( '' );
		}
		for ( i = 0; i < parts.length; i++ ) {
			if ( /([0-9.][,.0-9]*[0-9]*)/.test( parts[ i ] ) && ! /<[^>]+>/.test( parts[ i ] ) ) {
				num = parts[ i ];
				isComma = /[0-9]+,[0-9]+/.test( num );
				num = num.replace( /,/g, '' );
				isFloat = /^[0-9]+\.[0-9]+$/.test( num );
				places = isFloat ? ( num.split( '.' )[ 1 ] || '' ).length : 0;
				k = nums.length - 1;
				for ( val = divisions; val >= 1; val-- ) {
					next = parseInt( ( num / divisions ) * val, 10 );
					if ( isFloat ) {
						next = parseFloat( parseFloat( ( num / divisions ) * val ).toFixed( places ) ).toLocaleString( lang );
					}
					if ( isComma ) {
						next = next.toLocaleString( lang );
					}
					nums[ k-- ] += next;
				}
			} else {
				for ( k = 0; k < divisions; k++ ) {
					nums[ k ] += parts[ i ];
				}
			}
		}
		nums[ nums.length - 1 ] = original;
		el.innerHTML = nums[ 0 ];
		step = function () {
			el.innerHTML = nums.shift();
			if ( nums.length ) {
				el.__countTimer = win.setTimeout( step, delay );
			}
		};
		el.__countTimer = win.setTimeout( step, delay );
	}

	/* ── tabs ────────────────────────────────────────────────────────────────────────────── */
	// Tabs switch with CSS (hidden radios); the script marks the label of the checked tab.
	// 5.x marked the FIRST label on load whichever radio was checked; this marks the checked one.

	function refreshTabs( tabs ) {
		var radio = tabs.querySelector( '.' + P + 'tab-state:checked' ) || tabs.querySelector( '.' + P + 'tab-state' );
		var bar = tabs.querySelector( '.' + P + 'tab-tabs' );
		var active;
		if ( ! radio || ! bar ) {
			return;
		}
		active = bar.querySelector( '.' + P + 'tab-active' );
		if ( active ) {
			active.classList.remove( P + 'tab-active' );
		}
		all( 'label', bar ).forEach( function ( label ) {
			if ( label.getAttribute( 'for' ) === radio.id ) {
				label.classList.add( P + 'tab-active' );
			}
		} );
	}

	/* ── toggles ─────────────────────────────────────────────────────────────────────────── */
	// CSS opens and closes a toggle; the script animates its height.

	function animateToggle( box ) {
		var row = box.parentNode.querySelector( '.' + P + 'row' );
		var from, to;
		if ( ! row || reduced ) {
			return;
		}
		row.style.transition = '';
		row.style.height = 'auto';
		if ( box.checked ) {
			to = win.getComputedStyle( row ).height;
			row.style.height = '0px';
		} else {
			from = win.getComputedStyle( row ).height;
			row.style.height = from;
		}
		void row.offsetHeight;
		row.style.transition = 'all .3s ease-in-out';
		row.style.height = box.checked ? to : '0px';
		row.addEventListener( 'transitionend', function done( ev ) {
			if ( 'height' === ev.propertyName ) {
				row.style.transition = '';
				row.style.height = '';
				row.removeEventListener( 'transitionend', done );
			}
		} );
	}

	/* ── maps ────────────────────────────────────────────────────────────────────────────── */
	// 5.x loaded the Maps JavaScript API with a site key (and showed an empty box without one).
	// The keyless embed shows the same place at the same zoom, loaded lazily. Custom map colours
	// and marker images have no embed equivalent.

	function initMap( el ) {
		var center, m, zoom, frame, lang;
		if ( el.querySelector( 'iframe' ) ) {
			return;
		}
		center = ( el.getAttribute( 'data-center' ) || '37.09024, -95.712891' ).trim();
		m = center.match( /^([-+]?\d{1,2}([.]\d+)?)\s*,?\s*([-+]?\d{1,3}([.]\d+)?)$/ );
		zoom = parseInt( el.getAttribute( 'data-zoom' ), 10 ) || 3;
		lang = ( root.getAttribute( 'lang' ) || '' ).split( '-' )[ 0 ];
		frame = doc.createElement( 'iframe' );
		frame.setAttribute(
			'src',
			'https://maps.google.com/maps?q=' +
				encodeURIComponent( m ? m[ 1 ] + ',' + m[ 3 ] : center ) +
				'&z=' +
				zoom +
				( lang ? '&hl=' + encodeURIComponent( lang ) : '' ) +
				'&output=embed'
		);
		frame.setAttribute( 'title', CFG.mapTitle || '' );
		frame.setAttribute( 'loading', 'lazy' );
		frame.setAttribute( 'referrerpolicy', 'no-referrer-when-downgrade' );
		frame.style.border = '0';
		frame.style.width = '100%';
		frame.style.height = '100%';
		frame.style.display = 'block';
		el.appendChild( frame );
	}

	/* ── responsive embedded videos ──────────────────────────────────────────────────────── */

	var PLAYERS = /^(https?:)?\/\/(?:www\.youtube\.com|www\.youtube-nocookie\.com|player\.vimeo\.com|fast\.wistia\.net)/i;

	function fluidVideos() {
		all( '.' + P + 'main-wrapper iframe, .' + P + 'main-wrapper object' ).forEach( function ( el ) {
			var src = el.getAttribute( 'src' ) || el.getAttribute( 'data' ) || '';
			var ratio = parseInt( el.getAttribute( 'height' ), 10 ) / parseInt( el.getAttribute( 'width' ), 10 );
			var wrap;
			if (
				! PLAYERS.test( src ) ||
				el.getAttribute( 'data-fluidvids' ) ||
				/video-bg/.test( el.id ) ||
				el.closest( '.' + P + 'video-bg, .jetpack-video-wrapper' )
			) {
				return;
			}
			wrap = doc.createElement( 'div' );
			wrap.className = 'fluidvids';
			wrap.style.width = '100%';
			wrap.style.maxWidth = '100%';
			wrap.style.position = 'relative';
			wrap.style.paddingTop = ( isFinite( ratio ) && ratio > 0 ? ratio * 100 : 56.25 ) + '%';
			el.parentNode.insertBefore( wrap, el );
			el.classList.add( 'fluidvids-item' );
			el.setAttribute( 'data-fluidvids', 'loaded' );
			el.style.position = 'absolute';
			el.style.top = el.style.right = el.style.bottom = el.style.left = '0';
			el.style.width = el.style.height = '100%';
			wrap.appendChild( el );
		} );
	}

	/* ── in-page links ───────────────────────────────────────────────────────────────────── */
	// `#name` scrolls to `<a name="name">` or `#id`; `.class` scrolls to the first match. 5.x did
	// this for every link on the page; here only for links inside legacy content.

	function onClick( ev ) {
		var a = ev.target && ev.target.closest ? ev.target.closest( 'a[href]' ) : null;
		var href, m, to;
		if ( ! a || ! a.closest( '.' + P + 'main-wrapper' ) ) {
			return;
		}
		href = a.getAttribute( 'href' );
		m = href.match( /^#(\w+)/ );
		to = m ? doc.querySelector( 'a[name="' + m[ 1 ] + '"]' ) : null;
		if ( ! to && /^[.#]\w+/.test( href ) ) {
			try {
				to = doc.querySelector( href );
			} catch {
				to = null;
			}
		}
		if ( to ) {
			ev.preventDefault();
			win.scrollTo( {
				top: ( win.pageYOffset || 0 ) + to.getBoundingClientRect().top,
				behavior: reduced ? 'auto' : 'smooth',
			} );
		}
	}

	/* ── start ───────────────────────────────────────────────────────────────────────────── */

	var size = screenSize();

	function onResize() {
		var next = screenSize();
		switchMargins( next, size );
		size = next;
		fixRows();
		videoRows().forEach( resizeEmbed );
		if ( ! reduced ) {
			updateParallax();
		}
		aosRefresh();
	}

	function ready() {
		var resizeTimer;

		if ( CFG.theme ) {
			root.classList.add( 'theme-' + CFG.theme );
		}
		if ( 'desktop' !== size ) {
			switchMargins( size, 'desktop' );
		}
		fixRows();
		all( '[data-ce-tag="tabs"]' ).forEach( refreshTabs );
		all( '[data-ce-tag="map"]' ).forEach( initMap );
		all( '[' + D + 'kenburns-1]' ).forEach( initKenBurns );
		initAllParallax();
		videoRows().forEach( initVideo );
		fluidVideos();
		initAos();

		doc.addEventListener( 'change', function ( ev ) {
			var t = ev.target;
			if ( ! t || ! t.classList ) {
				return;
			}
			if ( t.classList.contains( P + 'tab-state' ) ) {
				refreshTabs( t.closest( '[data-ce-tag="tabs"]' ) || t.parentNode );
			}
			if ( 'toggleradio' === t.getAttribute( 'data-ce-tag' ) ) {
				animateToggle( t );
			}
		} );
		doc.addEventListener( 'click', onClick );
		win.addEventListener( 'resize', function () {
			win.clearTimeout( resizeTimer );
			resizeTimer = win.setTimeout( onResize, 50 );
		} );
	}

	if ( 'loading' === doc.readyState ) {
		doc.addEventListener( 'DOMContentLoaded', ready );
	} else {
		ready();
	}
} )( window, document );
