/**
 * Tests for the trigger click deferral predicate.
 *
 * @see src/frontend/trigger-click.js
 */

import { shouldDeferToElement } from '../../../src/frontend/trigger-click';

function build( html ) {
	document.body.innerHTML = `<div id="trigger">${ html }</div>`;
	return document.getElementById( 'trigger' );
}

describe( 'shouldDeferToElement', () => {
	it( 'defers to a real link so nested links keep working', () => {
		const trigger = build( '<a href="/somewhere">Read more</a>' );
		const link = trigger.querySelector( 'a' );

		expect( shouldDeferToElement( link, trigger ) ).toBe( true );
	} );

	it( 'does NOT defer to an anchor with no href', () => {
		// A core Button with no link set renders exactly this. It is not a
		// link: not focusable, nothing to navigate to. Deferring made it inert.
		const trigger = build(
			'<div class="wp-block-button"><a class="wp-block-button__link">Open</a></div>'
		);
		const anchor = trigger.querySelector( 'a' );

		expect( shouldDeferToElement( anchor, trigger ) ).toBe( false );
	} );

	it( 'still defers to an anchor with an empty href', () => {
		// An empty href resolves to the current page and navigates, so it is a
		// real link. The bug being fixed is the *absent* href a core Button
		// renders when no link is set; this case is deliberately left alone.
		const trigger = build( '<a href="">Open</a>' );

		expect(
			shouldDeferToElement( trigger.querySelector( 'a' ), trigger )
		).toBe( true );
	} );

	it( 'does NOT defer to the detected primary link', () => {
		const trigger = build(
			'<a href="/post" class="is-primary-link">Title</a>'
		);

		expect(
			shouldDeferToElement( trigger.querySelector( 'a' ), trigger )
		).toBe( false );
	} );

	it( 'defers to a button', () => {
		const trigger = build( '<button type="button">Do a thing</button>' );

		expect(
			shouldDeferToElement( trigger.querySelector( 'button' ), trigger )
		).toBe( true );
	} );

	it( 'defers to form controls', () => {
		const trigger = build(
			'<input type="text" /><select></select><textarea></textarea>'
		);

		[ 'input', 'select', 'textarea' ].forEach( ( tag ) => {
			expect(
				shouldDeferToElement( trigger.querySelector( tag ), trigger )
			).toBe( true );
		} );
	} );

	it( 'defers when the click lands inside a nested link', () => {
		const trigger = build( '<a href="/x"><span>inner</span></a>' );

		expect(
			shouldDeferToElement( trigger.querySelector( 'span' ), trigger )
		).toBe( true );
	} );

	it( 'does not defer to the trigger wrapper itself', () => {
		const trigger = build( '<span>plain content</span>' );
		trigger.setAttribute( 'role', 'button' );

		expect( shouldDeferToElement( trigger, trigger ) ).toBe( false );
	} );

	it( 'does not defer for ordinary content', () => {
		const trigger = build( '<h3>Card title</h3><p>Body</p>' );

		expect(
			shouldDeferToElement( trigger.querySelector( 'h3' ), trigger )
		).toBe( false );
	} );

	it( 'tolerates a null target', () => {
		const trigger = build( '<p>x</p>' );

		expect( shouldDeferToElement( null, trigger ) ).toBe( false );
	} );
} );
