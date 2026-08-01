/**
 * Abyss theme scripts: mobile nav, seamless ticker, sortable comparison table.
 */
( function () {
	'use strict';

	/* Mobile navigation ------------------------------------------------- */
	var toggle = document.querySelector( '[data-abyss-nav-toggle]' );
	var nav = document.getElementById( 'site-nav' );

	if ( toggle && nav ) {
		toggle.addEventListener( 'click', function () {
			var open = nav.classList.toggle( 'is-open' );
			toggle.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		} );
	}

	/* Ticker: duplicate the run so the -50% keyframe loops seamlessly --- */
	var run = document.querySelector( '[data-abyss-ticker]' );

	if ( run && ! window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
		run.innerHTML += run.innerHTML;
	}

	/* Sortable comparison table ----------------------------------------- */
	var table = document.querySelector( '[data-abyss-sortable]' );

	if ( ! table ) {
		return;
	}

	var headers = table.querySelectorAll( 'th[data-sort]' );

	Array.prototype.forEach.call( headers, function ( th, columnIndex ) {
		var button = th.querySelector( 'button' );

		if ( ! button ) {
			return;
		}

		button.addEventListener( 'click', function () {
			var body = table.tBodies[ 0 ];
			var rows = Array.prototype.slice.call( body.rows );
			var index = Array.prototype.indexOf.call( th.parentNode.cells, th );
			var descending = th.getAttribute( 'aria-sort' ) !== 'descending';

			rows.sort( function ( a, b ) {
				var av = parseFloat( a.cells[ index ].getAttribute( 'data-value' ) ) || 0;
				var bv = parseFloat( b.cells[ index ].getAttribute( 'data-value' ) ) || 0;

				return descending ? bv - av : av - bv;
			} );

			rows.forEach( function ( row ) {
				body.appendChild( row );
			} );

			Array.prototype.forEach.call( headers, function ( other ) {
				other.removeAttribute( 'aria-sort' );
				var arrow = other.querySelector( '.sortbtn__arrow' );

				if ( arrow ) {
					arrow.textContent = '';
				}
			} );

			th.setAttribute( 'aria-sort', descending ? 'descending' : 'ascending' );
			var ownArrow = th.querySelector( '.sortbtn__arrow' );

			if ( ownArrow ) {
				ownArrow.textContent = descending ? '\u2193' : '\u2191';
			}
		} );
	} );
}() );
