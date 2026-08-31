/**
 * Homepage slider: autoplay and the arrow/dot controls.
 *
 * The markup (template-parts/homepage-slider.php) is a CSS scroll-snap track,
 * which already gives touch swipe, trackpad scrolling and keyboard scrolling
 * for free — and keeps working if this file never loads. So everything here is
 * an enhancement layered on top of native scrolling rather than a carousel
 * engine: navigation is done by scrolling the track, and the active dot is
 * read back off the scroll position. That means a finger swipe and an arrow
 * click can never disagree about which slide is showing.
 *
 * Autoplay stops for good the first time the visitor navigates by any means.
 * A banner that keeps yanking itself along after someone has deliberately
 * chosen a slide is hostile, and there is no way to tell "they are reading
 * this one" from "they are idle" other than that first interaction.
 */
( function () {
	'use strict';

	var AUTOPLAY_INTERVAL = 6000;

	// Honour the OS "reduce motion" setting by never autoplaying. The other
	// half of that promise — manual jumps landing instantly rather than
	// animating across the viewport — is handled by the stylesheet's own
	// prefers-reduced-motion rule on the track.
	function prefersReducedMotion() {
		return !! window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	}

	function initSlider( root ) {
		var track = root.querySelector( '[data-homepage-slider-track]' );

		if ( ! track ) {
			return;
		}

		var slides = track.querySelectorAll( '.homepage-slider__slide' );

		if ( slides.length < 2 ) {
			return; // A single banner is just an image.
		}

		var prevButton = root.querySelector( '[data-homepage-slider-prev]' );
		var nextButton = root.querySelector( '[data-homepage-slider-next]' );
		var dotList    = root.querySelector( '[data-homepage-slider-dots]' );
		var dots       = dotList ? dotList.querySelectorAll( '[data-homepage-slider-dot]' ) : [];

		var reduceMotion = prefersReducedMotion();
		var current      = 0;
		var timer        = null;
		var autoplay     = ! reduceMotion;
		var scrollFrame  = null;
		var smoothVerified = false; // See goTo().

		/**
		 * Scroll the track to a slide. Wraps around at both ends.
		 *
		 * Each slide is exactly one track-width wide (see the stylesheet), so
		 * the scroll offset is the index times the visible width — no need to
		 * measure individual elements, which would go stale on resize.
		 *
		 * The animation itself belongs to `scroll-behavior: smooth` in the
		 * stylesheet, which also carries the prefers-reduced-motion override,
		 * so there is no motion branch here.
		 *
		 * The fallback below is not theoretical. Smooth scrolling is a hint,
		 * not a guarantee: there are environments that report full support —
		 * `scrollBehavior` in the style object, no reduced-motion preference —
		 * and then simply never run the animation, leaving scrollLeft exactly
		 * where it was. Every arrow and every dot is then silently inert, which
		 * is a far worse failure than an unanimated jump. So the first move is
		 * verified: if the position has not budged shortly afterwards, smooth
		 * scrolling is written off for good and the track is switched to
		 * instant. The check runs once, not on every navigation.
		 */
		function goTo( index ) {
			var target = ( index + slides.length ) % slides.length;
			var left   = target * track.clientWidth;
			var before = track.scrollLeft;

			track.scrollLeft = left;

			if ( smoothVerified || track.scrollLeft === left ) {
				smoothVerified = true; // Landed instantly — nothing to verify.
				return;
			}

			smoothVerified = true;

			// A running animation will have moved by now; a dropped one won't.
			window.setTimeout( function () {
				if ( track.scrollLeft === before ) {
					track.style.scrollBehavior = 'auto';
					track.scrollLeft = left;
				}
			}, 150 );
		}

		/**
		 * Reflect the current scroll position in the dots.
		 *
		 * Driven by the scroll event rather than by goTo(), so a manual swipe
		 * updates the dots exactly the same way a click does.
		 */
		function syncFromScroll() {
			var width = track.clientWidth;

			if ( ! width ) {
				return;
			}

			var index = Math.round( track.scrollLeft / width );

			if ( index === current ) {
				return;
			}

			current = index;

			Array.prototype.forEach.call( dots, function ( dot, i ) {
				if ( i === current ) {
					dot.setAttribute( 'aria-current', 'true' );
				} else {
					dot.removeAttribute( 'aria-current' );
				}
			} );
		}

		function startAutoplay() {
			// document.hidden is checked here, not only in the visibilitychange
			// handler below: that handler fires on a *change*, so a page opened
			// in a background tab would otherwise sit there advancing banners
			// nobody is looking at until the tab is first brought forward.
			if ( ! autoplay || timer || document.hidden ) {
				return;
			}

			timer = window.setInterval( function () {
				goTo( current + 1 );
			}, AUTOPLAY_INTERVAL );

			// While the slider is advancing on its own, announcing every slide
			// would interrupt a screen reader mid-sentence, repeatedly.
			track.setAttribute( 'aria-live', 'off' );
		}

		function pauseAutoplay() {
			if ( timer ) {
				window.clearInterval( timer );
				timer = null;
			}

			// Paused — changes are now the visitor's own doing, so they are
			// worth announcing.
			track.setAttribute( 'aria-live', 'polite' );
		}

		/**
		 * Permanently stop autoplay. Called from every navigation path.
		 */
		function stopAutoplay() {
			autoplay = false;
			pauseAutoplay();
		}

		Array.prototype.forEach.call( dots, function ( dot, i ) {
			dot.addEventListener( 'click', function () {
				stopAutoplay();
				goTo( i );
			} );
		} );

		if ( prevButton ) {
			prevButton.addEventListener( 'click', function () {
				stopAutoplay();
				goTo( current - 1 );
			} );
		}

		if ( nextButton ) {
			nextButton.addEventListener( 'click', function () {
				stopAutoplay();
				goTo( current + 1 );
			} );
		}

		// A swipe or trackpad scroll counts as navigating, too.
		track.addEventListener( 'pointerdown', stopAutoplay );
		track.addEventListener( 'wheel', stopAutoplay, { passive: true } );

		root.addEventListener( 'keydown', function ( event ) {
			if ( 'ArrowLeft' === event.key ) {
				event.preventDefault();
				stopAutoplay();
				goTo( current - 1 );
			} else if ( 'ArrowRight' === event.key ) {
				event.preventDefault();
				stopAutoplay();
				goTo( current + 1 );
			}
		} );

		// Reading the scroll position on every scroll event is wasteful during
		// a smooth-scroll animation; once per frame is plenty.
		track.addEventListener( 'scroll', function () {
			if ( scrollFrame ) {
				return;
			}

			scrollFrame = window.requestAnimationFrame( function () {
				scrollFrame = null;
				syncFromScroll();
			} );
		}, { passive: true } );

		// Hovering or tabbing into the slider means someone is looking at this
		// particular banner. Unlike the interactions above this is only a
		// pause: move away and it resumes.
		root.addEventListener( 'mouseenter', pauseAutoplay );
		root.addEventListener( 'mouseleave', startAutoplay );
		root.addEventListener( 'focusin', pauseAutoplay );
		root.addEventListener( 'focusout', function ( event ) {
			if ( ! root.contains( event.relatedTarget ) ) {
				startAutoplay();
			}
		} );

		// No point advancing banners nobody can see, and a background tab that
		// keeps scrolling wakes the machine for nothing.
		document.addEventListener( 'visibilitychange', function () {
			if ( document.hidden ) {
				pauseAutoplay();
				return;
			}

			// Browsers stop servicing requestAnimationFrame in a hidden tab, so
			// a frame requested in the instant before the tab was hidden may
			// never have run. Its callback is what clears the guard below, and
			// without this the guard would stay latched for the rest of the
			// page's life — every later scroll would be ignored and the dots
			// would freeze on whichever slide was showing at the time.
			if ( scrollFrame ) {
				window.cancelAnimationFrame( scrollFrame );
				scrollFrame = null;
			}

			syncFromScroll();
			startAutoplay();
		} );

		// A resize changes the width every offset is derived from, so re-anchor
		// on the slide that was showing rather than leaving the track parked
		// between two of them.
		window.addEventListener( 'resize', function () {
			track.scrollLeft = current * track.clientWidth;
		} );

		// The controls are printed hidden so they are inert without this
		// script. They work now, so show them.
		Array.prototype.forEach.call(
			root.querySelectorAll( '[hidden]' ),
			function ( control ) {
				control.removeAttribute( 'hidden' );
			}
		);

		root.classList.add( 'homepage-slider--enhanced' );

		startAutoplay();
	}

	function init() {
		Array.prototype.forEach.call(
			document.querySelectorAll( '[data-homepage-slider]' ),
			initSlider
		);
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
