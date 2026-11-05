<?php

/** Read the private profile for this installation, independently of HTTP_HOST. */
function gvi_config($section)
{
    static $config = null;

    if ($config === null) {
        $file = __DIR__ . '/local.php';
        if (!is_file($file)) {
            throw new RuntimeException('Prepare config/local.php before starting GV Industries.');
        }
        $loaded = require $file;
        if (!is_array($loaded)) {
            throw new RuntimeException('The private configuration must return an array.');
        }
        $config = $loaded;
    }

    if (!isset($config[$section]) || !is_array($config[$section])) {
        throw new RuntimeException('A required private configuration section is missing.');
    }

    return $config[$section];
}
