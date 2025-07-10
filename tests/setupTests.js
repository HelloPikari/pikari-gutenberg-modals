/**
 * Jest setup file for Pikari Gutenberg Modals
 *
 * This file runs before each test suite to set up the testing environment.
 */

// Mock WordPress globals
global.wp = {
	i18n: {
		__: (text) => text,
		_x: (text) => text,
		_n: (single, plural, count) => (count === 1 ? single : plural),
		sprintf: (format, ...args) => {
			let i = 0;
			return format.replace(/%s/g, () => args[i++]);
		},
	},
	data: {
		select: jest.fn(),
		dispatch: jest.fn(),
		subscribe: jest.fn(),
	},
	element: {
		createElement: jest.fn(),
		Fragment: jest.fn(),
	},
	richText: {
		registerFormatType: jest.fn(),
		unregisterFormatType: jest.fn(),
		toggleFormat: jest.fn(),
		applyFormat: jest.fn(),
		removeFormat: jest.fn(),
		getActiveFormat: jest.fn(),
	},
	blockEditor: {
		RichTextToolbarButton: jest.fn(),
		BlockControls: jest.fn(),
	},
	components: {
		Modal: jest.fn(),
		Button: jest.fn(),
		TextControl: jest.fn(),
	},
	url: {
		addQueryArgs: jest.fn(),
		getQueryArg: jest.fn(),
	},
};

// Mock fetch for REST API tests
global.fetch = jest.fn();
