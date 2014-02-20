<?php
/*
Script Name: WordPress DirtyMC
Description: This solution is uses output buffer caching to provide the fastest possible caching solution possible for a WordPress website. 
Version: 1.0
Author: BrianLayman
Author URI: http://webdevstudios.com/team/brian-layman/
Script URI: http://webdevstudios.com/wordpress/vip-services-support/

Notes: 
	This tool caches only non-logged-in access of the pages.  
	After a page is first generated, it loads NONE of WordPess.  
	This means it has a lower execution cost than any WordPress plugin can achieve.
	This solution was inspired and originally coded by Henry Rivera.

Use: Place this script in the root of your WordPress install, 
	If needed, include a file named dirtymc-config.php in the root. That file should contain any custom definitions of the constants defined below. It is likely that you would at least define a memcache server.
	Replace the index.php files in the root WordPress directory with the one provided with this file.

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

// New constants. The defaults are added here in case they are not in all versions of the config files.
if ( !defined( 'MEMCACHE_HOST_01' ) ) define( 'MEMCACHE_HOST_01', '127.0.0.1' );
if ( !defined( 'DMC_DMC_VERSION_KEY_LABEL' ) ) define( 'DMC_DMC_VERSION_KEY_LABEL', 'versionkey' ); // Used to maintain different cache sets programmatically 
if ( !defined( 'DMC_FLUSH_ARGUMENT' ) ) define( 'DMC_FLUSH_ARGUMENT', 'flush_main_cache' ); // Sent in the url, this will simulate a full cache flush, though in reality it just changes all key names
if ( !defined( 'DMC_VERSION_DURATION' ) ) define( 'DMC_VERSION_DURATION', 60 * 60 * 24 ); // The absolute max time in seconds before all cached values are considered invalid
if ( !defined( 'DMC_MEMCACHE_ANON_CACHE_TIME' ) ) define('DMC_MEMCACHE_ANON_CACHE_TIME', 60 * 5);  // The time in seconds that a normal value will remain cached.
if ( !defined( 'DMC_COOKIEHASH' ) ) define( 'DMC_COOKIEHASH', md5( ( dmc_is_ssl() ? 'https://': 'http://' ) . dmc_get_host() ) );
if ( !isset( $NonCached ) ) {
	// Non-Cached Pages
	$NonCached = array();
	$NonCached[] = "/signup/";
	$NonCached[] = "/login";
} 


function dmc_is_ssl() {
	if ( isset($_SERVER['HTTPS']) ) {
		if ( 'on' == strtolower($_SERVER['HTTPS']) )
			return true;
		if ( '1' == $_SERVER['HTTPS'] )
			return true;
	} elseif ( isset($_SERVER['SERVER_PORT']) && ( '443' == $_SERVER['SERVER_PORT'] ) ) {
		return true;
	}
	return false;
}

function dmc_get_host() {
    if ($host = $_SERVER['HTTP_X_FORWARDED_HOST'])
    {
        $elements = explode(',', $host);

        $host = trim(end($elements));
    }
    else
    {
        if (!$host = $_SERVER['HTTP_HOST'])
        {
            if (!$host = $_SERVER['SERVER_NAME'])
            {
                $host = !empty($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '';
            }
        }
    }

    // Remove port number from host
    $host = preg_replace('/:\d+$/', '', $host);

    return trim($host);
}

function dmc_doMemcacheConnect() {
	global $dirtyMC;

	$dirtyMC = new Memcache;
	// Allow up to five constants like MEMCACHE_HOST_##, each containing an ip addresse or machine name
	// Possible Constants: MEMCACHE_HOST_01 MEMCACHE_HOST_02 MEMCACHE_HOST_03 MEMCACHE_HOST_04 MEMCACHE_HOST_05
	$hosts = array( '01', '02', '03', '04', '05' );
	foreach ( $hosts as $number ) {
		if ( defined( "MEMCACHE_HOST_$number" ) ) {
			$dirtyMC->addServer( constant( "MEMCACHE_HOST_$number" ), 11211, 11211, true );
		}
	}
	return $dirtyMC;
}

function dmc_doStampedeProtection( $key ) {
	global $dirtyMC, $dmcExec_Times, $dirtyMCVersion;

	// If no memcache, exit
	if ( !$dirtyMC ) return;

	// If we've decided we aren't caching, then exit;
	if ( isset( $_SESSION['disable_cache'] ) && $_SESSION['disable_cache'] == true ) return;

	$content = $dirtyMC->get( $key );
	if ( $content ) {
		if ( is_array( $content['headers'] ) ) {
			foreach ( $content['headers'] as $header ) {
				header( $header );
			}
		}
		echo $content['body'];
		$dmcExec_Times[] = microtime( true );
		exit;
	} 

	$lockTimeout = 24;
	$lockRetries = 100;
	$result = $dirtyMC->add( "{$key}_lock", 'lock', null, $lockTimeout );

	if ( !$result ) {
		for ( $i = 0; $i < $lockRetries; $i++ ) {
			$cacheEntry = $dirtyMC->get( $key );

			// The locking process had added it's cache entry, use it.
			if ( $cacheEntry !== false ) {
				if ( is_array( $cacheEntry['headers'] ) ) {
					foreach ( $cacheEntry['headers'] as $header ) {
						header( $header );
					}
				}
				echo $cacheEntry['body'];

				// Got the cache entry, don't bother generating page and updating cache.
				exit;
			} else {
				$million = 1000000;
				$microseconds = ( $lockTimeout / $lockRetries ) * $million;
				usleep( $microseconds );
			}
		}
	}
	ob_end_clean();
	ob_start();

	return;
}


/**
 * Generates the key used for cache entry in memcached.
 *
 * @return key
 */
function dmc_getMemcacheKey( $uri = null ) {
	global $dirtyMCVersion, $dirtyMC;

	if ( !( $dirtyMCVersion = $dirtyMC->get( DMC_DMC_VERSION_KEY_LABEL ) ) || isset( $_GET[DMC_FLUSH_ARGUMENT] ) ) {
		dmc_flush();
	}

	$uri = ( $uri != null ) ? $uri : $_SERVER['REQUEST_URI'];

	$key = sprintf( '%s_%s_%s',
		$_SERVER['SERVER_NAME'],
		$uri,
		$dirtyMCVersion );
	return $key;
}


function dmc_increment_version() {
	global $dirtyMC, $dirtyMCVersion;
	if ( !$dirtyMC ) {
		error_log("dirtyMC: Cannot increment version as dirtyMC has not yet been initialized");
		return;
	}
	$time = time();
	$dirtyMCVersion = $dirtyMC->get( DMC_DMC_VERSION_KEY_LABEL );
	$dirtyMC->delete( DMC_DMC_VERSION_KEY_LABEL );
	$dirtyMC->set( DMC_DMC_VERSION_KEY_LABEL, $time, false, DMC_VERSION_DURATION );
	$dirtyMCVersion = $time;
	return $dirtyMCVersion;
}

function dmc_flush() {
	dmc_increment_version();
}

/**
 * Updates a cache entry for a URI.  The URI may be optionally be passed in due to URL hijacking in custom
 * pages ( such as users.php ).
 *
 * @return void
 */
function dmc_updateCache( $uri = null ) {
	global $dirtyMC, $dmcExec_Times;

	if ( !$dirtyMC ) return;

	$uri = ( $uri != null ) ? $uri : $_SERVER['REQUEST_URI'];

	// Don't cache these URLs.
	if ( preg_match( '#^/log( in|out )|account/#', $uri )
			|| preg_match( '#/subscribe/#', $uri )
			|| isset( $_SESSION['disable_cache'] ) && $_SESSION['disable_cache'] == true ) {
		return;
	}

	// Create the cache key.
	$key = dmc_getMemcacheKey( $uri );

	$cache = array( 
		'headers' => headers_list(),
		'body' => ob_get_contents(),
	);

	$seconds = DMC_MEMCACHE_ANON_CACHE_TIME;

	if ( !$dirtyMC->set( $key, $cache, MEMCACHE_COMPRESSED, $seconds ) ) {
		$error = error_get_last();
		error_log( sprintf( 'dirtyMC updateCache: memcached problem: key = %s,  error  %s', $key, $error['message'] ) );
		error_log("dirtyMC memcache set for $seconds $key");
	}
	$dirtyMC->delete( "{$key}_lock" );

	$dmcExec_Times[] = microtime( true );

	return;
}