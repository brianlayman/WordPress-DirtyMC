<?php
define( 'MEMCACHE_HOST_01', 'localhost' ); // An example override
define( 'VERSION_DURATION', 60 * 60 * 12); // Invalidate the all cached values in 12 hours

// You must define this value to match the content in the site url field in the WordPress General Settings.
// define( 'DMC_COOKIEHASH', md5( 'www.example.com' ) ); // This value must match the WP site url

// Non-Cached Pages
// Include the full path for any pages that should never be cached.
$NonCached = array();
$NonCached[] = "/signup/";
$NonCached[] = "/login";
