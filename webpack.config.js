const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const TerserPlugin = require('terser-webpack-plugin');
const DependencyExtractionWebpackPlugin = require('@wordpress/dependency-extraction-webpack-plugin');

module.exports = (env, argv) => {
	const isProduction = argv.mode === 'production';

	return {
		entry: {
			// Entry points for each admin page
			dashboard: './assets/js/entries/dashboard.js',
			'database-health': './assets/js/entries/database-health.js',
			'media-audit': './assets/js/entries/media-audit.js',
			performance: './assets/js/entries/performance.js',
			settings: './assets/js/entries/settings.js',
		},
		output: {
			path: path.resolve(__dirname, 'assets/js/dist'),
			filename: '[name].bundle.js',
			clean: true, // Clean the output directory before emit
		},
		module: {
			rules: [
				{
					test: /\.(js|jsx)$/,
					exclude: /node_modules/,
					use: {
						loader: 'babel-loader',
						options: {
							presets: [
								'@babel/preset-env',
								['@babel/preset-react', { runtime: 'automatic' }],
							],
						},
					},
				},
				{
					test: /\.css$/,
					use: [
						isProduction ? MiniCssExtractPlugin.loader : 'style-loader',
						'css-loader',
					],
				},
			],
		},
		resolve: {
			extensions: ['.js', '.jsx'],
		},
		plugins: [
			// Extract WordPress dependencies
			new DependencyExtractionWebpackPlugin({
				injectPolyfill: true,
				combineAssets: true,
			}),
			// Extract CSS in production
			...(isProduction
				? [
						new MiniCssExtractPlugin({
							filename: '[name].bundle.css',
						}),
				  ]
				: []),
		],
		optimization: {
			minimize: isProduction,
			minimizer: [
				new TerserPlugin({
					terserOptions: {
						compress: {
							drop_console: isProduction,
						},
						output: {
							comments: false,
						},
					},
					extractComments: false,
				}),
			],
			// Split vendor code for better caching
			splitChunks: {
				cacheGroups: {
					vendor: {
						test: /[\\/]node_modules[\\/]/,
						name: 'vendor',
						chunks: 'all',
					},
				},
			},
		},
		devtool: isProduction ? 'source-map' : 'inline-source-map',
		devServer: {
			static: {
				directory: path.join(__dirname, 'assets'),
			},
			compress: true,
			port: 9000,
			hot: true,
			proxy: [
				{
					context: ['/wp-admin', '/wp-json'],
					target: 'http://localhost:8080', // WordPress site URL
					changeOrigin: true,
				},
			],
		},
		externals: {
			// Externalize WordPress globals
			react: 'React',
			'react-dom': 'ReactDOM',
			jquery: 'jQuery',
		},
		performance: {
			hints: isProduction ? 'warning' : false,
			maxEntrypointSize: 512000,
			maxAssetSize: 512000,
		},
	};
};
