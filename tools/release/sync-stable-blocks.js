/* eslint-disable no-console */
/**
 * External dependencies
 */
const fs = require( 'fs' );
const path = require( 'path' );

/**
 * Constants
 */
const BLOCK_LIBRARY_SRC = 'node_modules/@wordpress/block-library/src/';

/**
 * The main function of this task.
 *
 * Refreshes the PHP files referring to stable @wordpress/block-library blocks.
 */
function main() {
	const blocks = getStableBlocksMetadata();

	// wp-includes/blocks/require-blocks.php
	console.log( 'Refreshing wp-includes/blocks/require-blocks.php...' );
	const PHPRequires = blocks
		.filter( isDynamic )
		.map( toDirectoryName )
		// To PHP require statement:
		.map( dirname => `require ABSPATH . WPINC . '/blocks/${ dirname }.php';` )
		.join( "\n" );

	fs.writeFileSync(
		`src/wp-includes/blocks/require-blocks.php`,
		`<?php \n\n// Include files required for core blocks registration.\n${ PHPRequires }\n`,
	);

	// tests/phpunit/includes/unregister-blocks-hooks.php
	console.log( 'Refreshing tests/phpunit/includes/unregister-blocks-hooks.php...' );
	const unregisterHooks = blocks.filter( isDynamic )
		.map( function toHookName( metadata ) {
			const php = fs.readFileSync( path.join( metadata.path, '..', 'index.php' ) ).toString();
			let hookName = php.substr( php.indexOf( "add_action( 'init', 'register_block_core_" ) );
			return hookName.split( "'" )[ 3 ];
		} )
		.map( function toUnregisterCall( hookName ) {
			return `remove_action( 'init', '${ hookName }' );`;
		} )
		.join( "\n" );

	fs.writeFileSync(
		`tests/phpunit/includes/unregister-blocks-hooks.php`,
		`<?php \n${ unregisterHooks }\n`,
	);
	console.log( 'Done!' );
}

/**
 * Returns a list of unserialized block.json metadata of the
 * stable blocks shipped with the currently installed version
 * of the @wordpress/block-library package/
 *
 * @return {Array} List of stable blocks metadata.
 */
function getStableBlocksMetadata() {
	return (
		fs.readdirSync( BLOCK_LIBRARY_SRC )
			.map( dirMaybe => path.join( BLOCK_LIBRARY_SRC, dirMaybe, 'block.json' ) )
			.filter( fs.existsSync )
			.map( blockJsonPath => ( {
				...JSON.parse( fs.readFileSync( blockJsonPath ) ),
				path: blockJsonPath,
			} ) )
			.filter( metadata => (
				!( '__experimental' in metadata ) || metadata.__experimental === false
			) )
	);
}

/**
 * Returns true if the specified metadata refers to a dynamic block.
 *
 * @param {Object} metadata Block metadata in question.
 * @return {boolean} Is it a dynamic block?
 */
function isDynamic( metadata ) {
	return (
		fs.existsSync( path.join( metadata.path, '..', 'index.php' ) )
	);
}

/**
 * Returns a name of the directory where a given block resides.
 *
 * @param {Object} metadata Block metadata in question.
 * @return {string} Parent directory name.
 */
function toDirectoryName( metadata ) {
	const pathSegments = metadata.path.split( "/" );
	return (
		pathSegments[ pathSegments.length - 2 ]
	);
}

module.exports = {
	isDynamic,
	toDirectoryName,
	getStableBlocksMetadata,
};

// Only run the main() function when this file is executed directly and note
// required by another file.
if ( require.main === module ) {
	main();
}

/* eslint-enable no-console */
