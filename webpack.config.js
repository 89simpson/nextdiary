const path = require('path')
const webpack = require('webpack')
const webpackConfig = require('@nextcloud/webpack-vue-config')

webpackConfig.entry = {
	main: path.join(__dirname, 'src', 'main.js'),
	settings: path.join(__dirname, 'src', 'settings.js'),
}

// The server loads exactly js/nextdiary-main.js and js/nextdiary-settings.js
// (Util::addScript): keep each entry in a single file without lazy-loaded chunks
// (e.g. the date-fns locales of NcDateTimePicker are bundled in).
webpackConfig.plugins.push(new webpack.optimize.LimitChunkCountPlugin({ maxChunks: 1 }))

module.exports = webpackConfig
