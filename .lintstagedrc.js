module.exports = {
	'*.php': () => 'composer run lint',
	'src/**/*.js': [ 'wp-scripts lint-js --fix', 'prettier --write' ],
	'src/**/*.{scss,css}': [ 'wp-scripts lint-style --fix', 'prettier --write' ],
	'*.{json,md}': 'prettier --write',
	'.husky/**/*': () => 'echo "Skipping husky files"',
};