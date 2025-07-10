/**
 * Block fixtures for testing
 */

export const blockFixtures = {
	paragraph: {
		name: "core/paragraph",
		attributes: {
			content:
				'This is a test paragraph with a <span class="modal-link-trigger" data-modal-content-type="post" data-modal-content-id="123">modal link</span>.',
		},
	},

	heading: {
		name: "core/heading",
		attributes: {
			content:
				'Heading with <span class="modal-link-trigger" data-modal-content-type="page" data-modal-content-id="456">modal</span>',
			level: 2,
		},
	},

	list: {
		name: "core/list",
		attributes: {
			values: '<li>Item with <span class="modal-link-trigger" data-modal-content-type="url" data-modal-content-id="https://example.com">external modal</span></li>',
			ordered: false,
		},
	},

	button: {
		name: "core/button",
		attributes: {
			text: "Click me",
			url: "#modal-123",
		},
	},

	unsupported: {
		name: "core/image",
		attributes: {
			url: "https://example.com/image.jpg",
			alt: "Test image",
		},
	},
};

export const modalContentFixtures = {
	post: {
		id: 123,
		title: {
			rendered: "Test Post Title",
		},
		content: {
			rendered: "<p>This is the test post content.</p>",
		},
		excerpt: {
			rendered: "<p>This is the test post excerpt.</p>",
		},
		link: "https://example.com/test-post/",
		type: "post",
	},

	page: {
		id: 456,
		title: {
			rendered: "Test Page Title",
		},
		content: {
			rendered:
				'<div class="wp-block-group"><p>Test page content with blocks.</p></div>',
		},
		link: "https://example.com/test-page/",
		type: "page",
	},

	withStyles: {
		id: 789,
		title: {
			rendered: "Styled Content",
		},
		content: {
			rendered:
				'<div class="has-background has-primary-background-color">Styled content</div>',
		},
		_embedded: {
			styles: [
				".has-primary-background-color { background-color: #007cba; }",
			],
		},
	},
};

export const searchResultsFixture = {
	results: [
		{
			id: 1,
			title: "First Result",
			url: "/first-result/",
			type: "post",
		},
		{
			id: 2,
			title: "Second Result",
			url: "/second-result/",
			type: "page",
		},
	],
	headers: {
		"X-WP-Total": "2",
		"X-WP-TotalPages": "1",
	},
};
