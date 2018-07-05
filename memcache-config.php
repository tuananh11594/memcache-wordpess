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
        define('MEMCACHE_ENABLE', 0);
        define('MEMCACHE_TIME_CACHE_CONVERSATION', 60);
        define('SET_DATA_TO_MEMCACHE', 1);
    }

?>