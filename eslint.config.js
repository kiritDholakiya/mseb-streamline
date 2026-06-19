const wordpress = require( '@wordpress/eslint-plugin' );

module.exports = [
	{
		ignores: [ 'vendor/**', 'node_modules/**', 'eslint.config.js' ],
	},
	...wordpress.configs.recommended.map( ( config ) => ( {
		...config,
		files: [ 'assets/js/**/*.js' ],
	} ) ),
	{
		files: [ 'assets/js/**/*.js' ],
		languageOptions: {
			globals: {
				jQuery: 'readonly',
				ajax_streamline_params: 'readonly',
				vrb_checkout_page_global_vars: 'readonly',
			},
		},
		rules: {
			// Formatting only, not a correctness issue; don't fail CI on style.
			'prettier/prettier': 'warn',
		},
	},
];
