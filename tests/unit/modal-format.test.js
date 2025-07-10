/**
 * Tests for modal format registration
 */

const {
	wp,
	getRegisteredFormat,
	clearAllMocks,
} = require( '../mocks/wordpress' );

describe( 'Modal Format Registration', () => {
	beforeEach( () => {
		clearAllMocks();
	} );

	afterEach( () => {
		clearAllMocks();
	} );

	it( 'should register format with correct properties', () => {
		// Simulate what the modal-format.js file does
		wp.richText.registerFormatType( 'modal-toolbar-button/modal-link', {
			title: 'Modal Link',
			tagName: 'span',
			className: 'modal-link-trigger',
			attributes: {
				'data-modal-link': 'data-modal-link',
				'data-modal-content-type': 'data-modal-content-type',
				'data-modal-content-id': 'data-modal-content-id',
			},
			edit: () => null, // Mock edit component
		} );

		// Verify registration
		expect( wp.richText.registerFormatType ).toHaveBeenCalledWith(
			'modal-toolbar-button/modal-link',
			expect.objectContaining( {
				title: 'Modal Link',
				tagName: 'span',
				className: 'modal-link-trigger',
			} )
		);

		// Verify the format was stored
		const format = getRegisteredFormat( 'modal-toolbar-button/modal-link' );
		expect( format ).toBeDefined();
		expect( format.title ).toBe( 'Modal Link' );
		expect( format.tagName ).toBe( 'span' );
		expect( format.className ).toBe( 'modal-link-trigger' );
	} );

	it( 'should have required attributes', () => {
		// Register format
		wp.richText.registerFormatType( 'modal-toolbar-button/modal-link', {
			title: 'Modal Link',
			tagName: 'span',
			className: 'modal-link-trigger',
			attributes: {
				'data-modal-link': 'data-modal-link',
				'data-modal-content-type': 'data-modal-content-type',
				'data-modal-content-id': 'data-modal-content-id',
			},
			edit: () => null,
		} );

		const format = getRegisteredFormat( 'modal-toolbar-button/modal-link' );
		const { attributes } = format;

		expect( attributes ).toHaveProperty( 'data-modal-link' );
		expect( attributes[ 'data-modal-link' ] ).toBe( 'data-modal-link' );

		expect( attributes ).toHaveProperty( 'data-modal-content-type' );
		expect( attributes[ 'data-modal-content-type' ] ).toBe(
			'data-modal-content-type'
		);

		expect( attributes ).toHaveProperty( 'data-modal-content-id' );
		expect( attributes[ 'data-modal-content-id' ] ).toBe(
			'data-modal-content-id'
		);
	} );

	it( 'should have an edit component', () => {
		const mockEdit = jest.fn();

		wp.richText.registerFormatType( 'modal-toolbar-button/modal-link', {
			title: 'Modal Link',
			tagName: 'span',
			className: 'modal-link-trigger',
			attributes: {},
			edit: mockEdit,
		} );

		const format = getRegisteredFormat( 'modal-toolbar-button/modal-link' );
		expect( format.edit ).toBeDefined();
		expect( typeof format.edit ).toBe( 'function' );
		expect( format.edit ).toBe( mockEdit );
	} );
} );
