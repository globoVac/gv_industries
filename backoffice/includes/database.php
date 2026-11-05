<?php
require_once dirname(__DIR__, 2) . '/config/bootstrap.php';
require_once dirname(__DIR__, 2) . '/config/database.php';

$gviSite = gvi_config('site');
$connect_globovac = $gviSite['environment'] === 'local'
    ? gvi_connect_database('globovac')
    : null;
$connect_gvi = gvi_connect_database('gvi');
unset($gviSite);
