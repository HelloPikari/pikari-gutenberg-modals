/**
 * Enhanced WordPress global mocks for testing
 */

// Store registered format types
const registeredFormats = new Map();

// Store registered hooks
const hooks = {
	filters: new Map(),
	actions: new Map(),
};

// Enhanced WordPress mocks
const wp = {
	i18n: {
		__: jest.fn( ( text ) => text ),
		_x: jest.fn( ( text ) => text ),
		_n: jest.fn( ( single, plural, count ) =>
			count === 1 ? single : plural
		),
		sprintf: jest.fn( ( format, ...args ) => {
			let i = 0;
			return format.replace( /%s/g, () => args[ i++ ] );
		} ),
	},

	data: {
		select: jest.fn( ( store ) => {
			if ( store === 'core/block-editor' ) {
				return {
					getSelectedBlock: jest.fn(),
					getBlock: jest.fn(),
				};
			}
			return {};
		} ),
		dispatch: jest.fn(),
		subscribe: jest.fn(),
		useSelect: jest.fn( ( callback ) => callback( wp.data.select ) ),
	},

	element: {
		createElement: jest.fn(),
		Fragment: jest.fn( ( { children } ) => children ),
		useState: jest.fn( ( initial ) => [ initial, jest.fn() ] ),
		useEffect: jest.fn(),
		useRef: jest.fn( () => ( { current: null } ) ),
	},

	richText: {
		registerFormatType: jest.fn( ( name, settings ) => {
			registeredFormats.set( name, settings );
			return settings;
		} ),
		unregisterFormatType: jest.fn( ( name ) => {
			registeredFormats.delete( name );
		} ),
		toggleFormat: jest.fn(),
		applyFormat: jest.fn( ( value, format ) => ( {
			...value,
			activeFormats: [ format ],
		} ) ),
		removeFormat: jest.fn( ( value ) => ( {
			...value,
			activeFormats: [],
		} ) ),
		getActiveFormat: jest.fn(),
	},

	blockEditor: {
		RichTextToolbarButton: jest.fn( () => null ),
		BlockControls: jest.fn( () => null ),
		LinkControl: jest.fn( () => null ),
	},

	components: {
		Modal: jest.fn( () => null ),
		Button: jest.fn( () => null ),
		TextControl: jest.fn( () => null ),
		Popover: jest.fn( () => null ),
		ToolbarButton: jest.fn( () => null ),
	},

	url: {
		addQueryArgs: jest.fn( ( url, args ) => {
			const params = new URLSearchParams( args );
			return `${ url }?${ params }`;
		} ),
		getQueryArg: jest.fn(),
	},

	icons: {
		external: '<svg></svg>',
		link: '<svg></svg>',
	},

	hooks: {
		addFilter: jest.fn( ( hook, namespace, callback, priority = 10 ) => {
			if ( ! hooks.filters.has( hook ) ) {
				hooks.filters.set( hook, [] );
			}
			hooks.filters.get( hook ).push( { namespace, callback, priority } );
		} ),
		applyFilters: jest.fn( ( hook, value, ...args ) => {
			if ( ! hooks.filters.has( hook ) ) {
				return value;
			}
			return hooks.filters
				.get( hook )
				.sort( ( a, b ) => a.priority - b.priority )
				.reduce(
					( val, filter ) => filter.callback( val, ...args ),
					value
				);
		} ),
	},
};

// Helper to get registered formats
function getRegisteredFormat( name ) {
	return registeredFormats.get( name );
}

// Helper to clear all mocks
function clearAllMocks() {
	registeredFormats.clear();
	hooks.filters.clear();
	hooks.actions.clear();
	Object.values( wp ).forEach( ( module ) => {
		Object.values( module ).forEach( ( fn ) => {
			if ( typeof fn === 'function' && fn.mockClear ) {
				fn.mockClear();
			}
		} );
	} );
}

// Set up global
global.wp = wp;

// Export for use in tests
module.exports = {
	wp,
	getRegisteredFormat,
	clearAllMocks,
};
