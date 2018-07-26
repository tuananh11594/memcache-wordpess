<?php

    $memcache_config_path = get_template_directory().'/memcache/';
    if ( file_exists($memcache_config_path . 'memcache-config-local.php') )
        require $memcache_config_path . 'memcache-config-local.php';
    elseif ( file_exists($memcache_config_path . 'memcache-config-stage.php') )
        require $memcache_config_path . 'memcache-config-stage.php';
    elseif ( file_exists($memcache_config_path . 'memcache-config-prod.php') )
        require $memcache_config_path . 'memcache-config-prod.php';
    else
    {
        define('MEMCACHE_HOST', 'localhost');
        define('MEMCACHE_PORT', 11211);
        //If not enabled, memcache will not be used, take data from database only
        define('MEMCACHE_ENABLE', 0);
        //Show debug message if set this to true
        define('SHOW_DEBUG_MEMCACHE', 0);
        //How long data is cached in memcache
        define('MEMCACHE_TIME_CACHE_CONVERSATION', 60);
    }
       
    define('POSTS_VIEWMORE_PAGES_TO_CACHE', 0);
?>