const defaultConfig = require( '@wordpress/scripts/config/eslint.config.cjs' );

module.exports = [
	...defaultConfig,
	{
		// WordPress loads these packages at runtime; webpack treats them as externals.
		settings: {
			'import/core-modules': [
				'@wordpress/api-fetch',
				'@wordpress/block-editor',
				'@wordpress/blocks',
				'@wordpress/components',
				'@wordpress/element',
				'@wordpress/i18n',
			],
		},
	},
	{
		// Developer scripts: run from the shell, may log and use tools not in package.json.
		files: [ 'dev/**' ],
		rules: {
			'no-console': 'off',
			'import/no-extraneous-dependencies': 'off',
		},
	},
];
