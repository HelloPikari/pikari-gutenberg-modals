module.exports = {
	"*.php": () => "composer run lint",
	"*.js": "wp-scripts lint-js --fix",
	"*.{scss,css}": "wp-scripts lint-style --fix",
	"*.{json,md}": "prettier --write",
	".husky/**/*": () => 'echo "Skipping husky files"',
};
