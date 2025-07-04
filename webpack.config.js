const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');

module.exports = {
    ...defaultConfig,
    entry: {
        'editor/index': path.resolve(__dirname, 'src/editor/index.js'),
        'frontend/index': path.resolve(__dirname, 'src/frontend/index.js'),
    },
    output: {
        ...defaultConfig.output,
        path: path.resolve(__dirname, 'build'),
    },
};