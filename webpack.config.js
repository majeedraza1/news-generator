const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const CssMinimizerPlugin = require('css-minimizer-webpack-plugin');
const TerserPlugin = require('terser-webpack-plugin');
const BrowserSyncPlugin = require('browser-sync-webpack-plugin');
const { VueLoaderPlugin } = require('vue-loader');
const svgToMiniDataURI = require('mini-svg-data-uri');
const webpack = require('webpack');

const config = require('./wp.config.json');

module.exports = (env, argv) => {
	const isDev = argv.mode !== 'production';

	const plugins = [];

	plugins.push(
		new webpack.DefinePlugin({
			__VUE_OPTIONS_API__: false,
			__VUE_PROD_DEVTOOLS__: false,
		})
	);

	plugins.push(
		new MiniCssExtractPlugin({
			filename: '../css/[name].css',
		})
	);

	plugins.push(
		new BrowserSyncPlugin({
			proxy: config.proxyURL,
		})
	);

	plugins.push(new VueLoaderPlugin());

	const webpackConfig = {
		entry: config.entryPoints,
		output: {
			path: path.resolve(__dirname, 'assets/js'),
			filename: '[name].js',
			clean: true,
		},
		devtool: isDev ? 'eval-source-map' : false,
		module: {
			rules: [
				{
					test: /\.tsx?$/,
					loader: 'ts-loader',
					options: {
						appendTsSuffixTo: [/\.vue$/],
					},
					exclude: /node_modules/,
				},
				{
					test: /\.(js|jsx)$/i,
					use: {
						loader: 'babel-loader',
					},
				},
				{
					test: /\.vue$/i,
					use: [{ loader: 'vue-loader' }],
				},
				{
					test: /\.(sass|scss|css)$/i,
					use: [
						isDev
							? { loader: 'style-loader' }
							: { loader: MiniCssExtractPlugin.loader },
						{
							loader: 'css-loader',
							options: { sourceMap: isDev, importLoaders: 1 },
						},
						{
							loader: 'postcss-loader',
							options: { sourceMap: isDev },
						},
						{
							loader: 'sass-loader',
							options: { sourceMap: isDev },
						},
					],
				},
				{
					test: /\.(eot|ttf|woff|woff2)$/i,
					type: 'asset/resource',
					generator: {
						filename: '../fonts/[hash][ext]',
					},
				},
				{
					test: /\.(png|je?pg|gif)$/i,
					type: 'asset',
					generator: {
						filename: '../images/[hash][ext]',
					},
				},
				{
					test: /\.svg$/i,
					type: 'asset',
					generator: {
						filename: '../images/[hash][ext]',
						dataUrl: (content) =>
							svgToMiniDataURI(content.toString()),
					},
				},
			],
		},
		optimization: {
			minimizer: [new TerserPlugin(), new CssMinimizerPlugin()],
		},
		resolve: {
			alias: {
				'@': path.resolve('./resources/'),
			},
			modules: [path.resolve('./node_modules')],
			extensions: ['*', '.js', '.jsx', '.vue', '.json', '.tsx', '.ts'],
		},
		plugins,
	};

	webpackConfig.externals = {
		jquery: 'jQuery',
	};

	return webpackConfig;
};
