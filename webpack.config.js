const webpackConfig = require('@nextcloud/webpack-vue-config')
const path = require('path')

webpackConfig.entry = {
	main: path.join(__dirname, 'src', 'main.js'),
	settings: path.join(__dirname, 'src', 'settings.js'),
}

module.exports = webpackConfig
