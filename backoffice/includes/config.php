<?php
require_once dirname(__DIR__, 2) . '/config/bootstrap.php';

$gviSite = gvi_config('site');
define('AMBIENTE', $gviSite['environment']);
define('BASE_URL', $gviSite['backoffice_base_url']);
define('BASE_PATH', $gviSite['backoffice_base_path']);
unset($gviSite);

define('SITE_NAME', 'GV Industries — Backoffice');
