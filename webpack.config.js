const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

// Connectors bundle - ES module for WP 7 Script Modules.
// @wordpress/connectors is a script module, so we must output ESM.
module.exports = {
	mode: defaultConfig.mode || 'production',
	devtool: defaultConfig.devtool,
	entry: {
		connectors: './src/js/connectors.js',
	},
	experiments: {
		outputModule: true,
	},
	output: {
		path: path.resolve( __dirname, 'build' ),
		filename: '[name].js',
		module: true,
		library: {
			type: 'module',
		},
	},
	externalsType: 'module',
	externals: {
		// @wordpress/connectors is a script module - import from WP.
		'@wordpress/connectors': '@wordpress/connectors',
	},
	plugins: [
		// Generate asset file.
		{
			apply: ( compiler ) => {
				compiler.hooks.emit.tap( 'AssetPlugin', ( compilation ) => {
					const assetContent = `<?php return array('dependencies' => array(), 'version' => '${ Date.now() }');`;
					compilation.emitAsset( 'connectors.asset.php', {
						source: () => assetContent,
						size: () => assetContent.length,
					} );
				} );
			},
		},
	],
};
