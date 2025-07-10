module.exports = {
	root: true,
	extends: [ 'plugin:@wordpress/eslint-plugin/recommended' ],
	overrides: [
		{
			files: [ 'tests/**/*.js', 'playwright.config.js' ],
			env: {
				jest: true,
				node: true,
			},
			rules: {
				'import/no-extraneous-dependencies': 'off',
			},
		},
		{
			files: [ 'tests/unit/**/*.js' ],
			globals: {
				KeyboardEvent: 'readonly',
			},
			rules: {
				'jest/no-conditional-expect': 'off',
			},
		},
	],
};
