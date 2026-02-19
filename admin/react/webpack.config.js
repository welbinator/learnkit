/**
 * Custom webpack configuration for LearnKit
 * 
 * Extends @wordpress/scripts default config to output
 * bundle to plugin assets directory.
 * 
 * @package LearnKit
 * @since 0.1.0
 */

const defaultConfig = require('@wordpress/scripts/config/webpack.config');
const path = require('path');

module.exports = {
	...defaultConfig,
	output: {
		path: path.resolve(__dirname, '../../assets/js'),
		filename: 'learnkit-admin.js',
	},
};
