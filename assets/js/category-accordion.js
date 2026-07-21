/**
 * Product Categories: collapse to top-level, expand/collapse on click.
 *
 * Works with both the classic "Product Categories" widget
 * (.widget_product_categories) and the block (.wc-block-product-categories).
 * Any list item that has a nested <ul> of sub-categories gets a caret toggle;
 * only top-level items show until a branch is expanded. The category links
 * themselves keep working — only the caret toggles the branch.
 */
( function () {
	'use strict';

	// Find the immediate child <ul> of a list item, if any.
	function directChildList( li ) {
		for ( var i = 0; i < li.children.length; i++ ) {
			if ( 'UL' === li.children[ i ].tagName ) {
				return li.children[ i ];
			}
		}
		return null;
	}

	function initContainer( container ) {
		var items = container.querySelectorAll( 'li' );

		Array.prototype.forEach.call( items, function ( li ) {
			if ( ! directChildList( li ) ) {
				return; // No sub-categories — nothing to toggle.
			}

			li.classList.add( 'has-children' );

			// Keep the branch leading to the current category open.
			var startOpen = /(^|\s)(current-cat|wc-block-product-categories-list-item--current)(\s|$)/.test( li.className ) ||
				!! li.querySelector( '.current-cat, .wc-block-product-categories-list-item--current' );

			if ( startOpen ) {
				li.classList.add( 'is-open' );
			}

			var toggle = document.createElement( 'button' );
			toggle.type = 'button';
			toggle.className = 'cat-toggle';
			toggle.setAttribute( 'aria-label', 'Toggle subcategories' );
			toggle.setAttribute( 'aria-expanded', startOpen ? 'true' : 'false' );

			li.insertBefore( toggle, li.firstChild );

			toggle.addEventListener( 'click', function ( event ) {
				event.preventDefault();
				var open = li.classList.toggle( 'is-open' );
				toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
			} );
		} );
	}

	function init() {
		var containers = document.querySelectorAll(
			'.widget_product_categories, .wc-block-product-categories'
		);
		Array.prototype.forEach.call( containers, initContainer );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
