<?php
require_once __DIR__ . '/bootstrap.php';

/** Connect only to the database explicitly configured for this installation. */
function gvi_connect_database($name)
{
    $databases = gvi_config('database');
    if (!array_key_exists($name, $databases)) {
        throw new RuntimeException('A required database profile is missing.');
    }
    $database = $databases[$name];
    if ($database === null) {
        return null;
    }
    if (!is_array($database)) {
        throw new RuntimeException('Invalid database profile.');
    }
    foreach (['host', 'user', 'password', 'name'] as $key) {
        if (!isset($database[$key]) || !is_string($database[$key])) {
            throw new RuntimeException('Incomplete database profile.');
        }
    }
    return mysqli_connect($database['host'], $database['user'], $database['password'], $database['name']);
}
