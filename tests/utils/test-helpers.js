/**
 * Test helper utilities for Pikari Gutenberg Modals
 */

/**
 * Create a mock block object
 *
 * @param {Object} overrides - Properties to override
 * @return {Object} Mock block object
 */
function createMockBlock(overrides = {}) {
	return {
		clientId: "test-block-123",
		name: "core/paragraph",
		attributes: {},
		innerBlocks: [],
		...overrides,
	};
}

/**
 * Create a mock RichText value object
 *
 * @param {string} text    - The text content
 * @param {Array}  formats - Format arrays
 * @return {Object} Mock RichText value
 */
function createMockRichTextValue(text = "", formats = []) {
	return {
		text,
		formats,
		replacements: [],
		activeFormats: [],
		start: 0,
		end: text.length,
	};
}

/**
 * Create a mock format object
 *
 * @param {Object} overrides - Properties to override
 * @return {Object} Mock format object
 */
function createMockFormat(overrides = {}) {
	return {
		type: "modal-toolbar-button/modal-link",
		attributes: {
			"data-modal-link": "{}",
			"data-modal-content-type": "post",
			"data-modal-content-id": "123",
			href: "#",
		},
		...overrides,
	};
}

/**
 * Create a mock modal trigger element
 *
 * @param {Object} dataset - Data attributes
 * @return {HTMLElement} Mock trigger element
 */
function createMockTrigger(dataset = {}) {
	const element = document.createElement("span");
	element.className = "modal-link-trigger";

	Object.entries({
		modalContentType: "post",
		modalContentId: "123",
		...dataset,
	}).forEach(([key, value]) => {
		element.dataset[key] = value;
	});

	return element;
}

/**
 * Wait for async operations to complete
 *
 * @param {number} ms - Milliseconds to wait
 * @return {Promise} Promise that resolves after delay
 */
function wait(ms = 0) {
	return new Promise((resolve) => setTimeout(resolve, ms));
}

/**
 * Mock fetch response
 *
 * @param {Object} data    - Response data
 * @param {Object} options - Response options
 * @return {Object} Mock response object
 */
function mockFetchResponse(data, options = {}) {
	return {
		ok: true,
		status: 200,
		json: async () => data,
		text: async () => JSON.stringify(data),
		...options,
	};
}

// Export all functions
module.exports = {
	createMockBlock,
	createMockRichTextValue,
	createMockFormat,
	createMockTrigger,
	wait,
	mockFetchResponse,
};
