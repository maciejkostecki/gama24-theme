/**
 * Phone-order button: client-side re-check of business hours.
 *
 * PHP already rendered the correct state, so this script is a correction pass,
 * not the primary decision. It exists for two cases PHP cannot cover:
 *
 *   1. A full-page cache or CDN served markup generated at some earlier hour,
 *      freezing every visitor into whichever state got cached.
 *   2. A tab left open across an opening or closing time.
 *
 * The clock is read in the shop's timezone via Intl, never in the visitor's —
 * a customer in Berlin or with a skewed system clock must still see the button
 * that matches Poland. If Intl is unavailable or throws, the server-rendered
 * state is left untouched.
 */
( function () {
	'use strict';

	var config = window.storefrontChildPhoneOrder || {};
	var DAYS   = { Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6, Sun: 7 };

	/**
	 * Current day and time in the shop's timezone.
	 *
	 * @return {{day: number, minutes: number}|null}
	 */
	function shopNow() {
		var parts = new Intl.DateTimeFormat( 'en-GB', {
			timeZone: config.timezone,
			hourCycle: 'h23',
			hour: '2-digit',
			minute: '2-digit',
			weekday: 'short'
		} ).formatToParts( new Date() );

		var value = {};
		parts.forEach( function ( part ) {
			value[ part.type ] = part.value;
		} );

		var day = DAYS[ value.weekday ];
		if ( ! day ) {
			return null;
		}

		return {
			day: day,
			// hourCycle h23 should give 00-23, but some engines still emit "24"
			// at midnight; normalise so 24:10 does not read as past closing.
			minutes: ( parseInt( value.hour, 10 ) % 24 ) * 60 + parseInt( value.minute, 10 )
		};
	}

	/**
	 * "10:00" -> 600. Minutes since midnight.
	 *
	 * @param {string} hhmm 24h time.
	 * @return {number}
	 */
	function toMinutes( hhmm ) {
		var bits = String( hhmm ).split( ':' );
		return parseInt( bits[ 0 ], 10 ) * 60 + parseInt( bits[ 1 ] || 0, 10 );
	}

	/**
	 * Are phone orders being taken right now? Mirrors
	 * storefront_child_phone_order_is_open() in functions.php.
	 *
	 * @return {boolean|null} null when it cannot be determined.
	 */
	function isOpen() {
		var now;

		try {
			now = shopNow();
		} catch ( e ) {
			return null;
		}

		if ( ! now || ! config.hours ) {
			return null;
		}

		var today = config.hours[ now.day ];
		if ( ! today ) {
			return false;
		}

		return now.minutes >= toMinutes( today[ 0 ] ) && now.minutes < toMinutes( today[ 1 ] );
	}

	/**
	 * Apply the current state to every button on the page.
	 */
	function sync() {
		var open = isOpen();
		if ( null === open ) {
			return; // Keep whatever PHP rendered.
		}

		var state = open ? 'open' : 'closed';
		document.querySelectorAll( '[data-phone-order-state]' ).forEach( function ( el ) {
			el.setAttribute( 'data-phone-order-state', state );
		} );
	}

	/**
	 * Wire the dialog open/close controls.
	 */
	function bindDialog() {
		var dialog = document.querySelector( '[data-phone-order-dialog]' );
		if ( ! dialog || 'function' !== typeof dialog.showModal ) {
			return;
		}

		document.querySelectorAll( '[data-phone-order-open]' ).forEach( function ( trigger ) {
			trigger.addEventListener( 'click', function () {
				dialog.showModal();

				// <dialog> focuses the first focusable child, which is the
				// close button — put the caret in the phone field instead,
				// since that is the one thing the visitor has to fill in.
				var field = dialog.querySelector( 'input[type="tel"], input[type="text"], input[type="email"], textarea' );
				if ( field ) {
					field.focus();
				}
			} );
		} );

		document.querySelectorAll( '[data-phone-order-close]' ).forEach( function ( closer ) {
			closer.addEventListener( 'click', function () {
				dialog.close();
			} );
		} );

		// Click on the backdrop — i.e. on the dialog element itself rather than
		// on its inner panel — closes it, matching normal modal behaviour.
		dialog.addEventListener( 'click', function ( event ) {
			if ( event.target === dialog ) {
				dialog.close();
			}
		} );

		// Contact Form 7 fires this on a successful submission.
		document.addEventListener( 'wpcf7mailsent', function () {
			window.setTimeout( function () {
				dialog.close();
			}, 2500 );
		} );
	}

	function init() {
		sync();
		bindDialog();
		// Re-check periodically so a long-open tab flips itself at 10:00/18:00.
		window.setInterval( sync, 60000 );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
