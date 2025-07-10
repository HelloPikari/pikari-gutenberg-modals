module.exports = {
	"*.php": () => "composer run lint",
	"*.js": ["wp-scripts lint-js --fix", "prettier --write"],
	"*.{scss,css}": ["wp-scripts lint-style --fix", "prettier --write"],
	"*.{json,md}": "prettier --write",
	".husky/**/*": () => 'echo "Skipping husky files"',
};
