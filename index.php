<?php
// This is a modified version of the standard WordPress index.php. You

// Include the memcache server configuration
if ( file_exists( 'dirtymc-config.php' ) ) require_once( 'dirtymc-config.php' );

// Load the dirty memcache engine
require_once( 'dirtymc.php' );

// Check for the cookie hash ID. The changes in WP 3.6 require this.
if ( !defined( 'DMC_COOKIEHASH' ) ) { 
    echo( 'You must define DMC_COOKIEHASH for DirtyMC to work.' ); 
    die( 'You must define DMC_COOKIEHASH for DirtyMC to work.' ); 
}

// Only for anonymous requests on landing.
if ( !isset( $_COOKIE[ 'wordpress_logged_in_' . DMC_COOKIEHASH ] ) and ( isset( $NonCached[ $_SERVER['REQUEST_URI'] ] ) ) ) {
	$dirtyMC = dmc_doMemcacheConnect();
	$dirtyMCVersion = $dirtyMC->get( DMC_VERSION_KEY_LABEL );
	dmc_doStampedeProtection( dmc_getMemcacheKey() );
}

//***************************************
// START STANDARD INDEX.PHP
//***************************************
/**
 * Front to the WordPress application. This file doesn't do anything, but loads
 * wp-blog-header.php which does and tells WordPress to load the theme.
 *
 * @package WordPress
 */

/**
 * Tells WordPress to load the WordPress theme and output it.
 *
 * @var bool
 */
define( 'WP_USE_THEMES', true );

/** Loads the WordPress Environment and Template */
require( './wp-blog-header.php' );

//***************************************
// END STANDARD INDEX.PHP
//***************************************
// Update the cache with the page that was generated.
dmc_updateCache();