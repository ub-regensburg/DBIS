const { merge } = require('webpack-merge')
const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin')
const common = require('./webpack.common.js')

module.exports = merge(common, {
  mode: 'production'
})
