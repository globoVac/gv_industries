<?php
require_once dirname(__DIR__) . '/config/bootstrap.php';

$gviSite = gvi_config('site');
define('AMBIENTE', $gviSite['environment']);
define('BASE_URL', $gviSite['base_url']);
define('BASE_PATH', $gviSite['base_path']);
unset($gviSite);

// Site

define('SITE_NAME', 'GV Industries');

define('SITE_EMAIL', 'info@gv-industries.co.uk');



// Paths

define('URL_ASSETS', BASE_URL . 'assets/');

define('URL_UPLOADS', BASE_URL . 'uploads/');

define('PATH_UPLOADS', BASE_PATH . 'uploads/');



// VAT (UK - 20%)

define('VAT_RATE', 0.20);



// Currency

define('CURRENCY', '£');

define('CURRENCY_CODE', 'GBP');



// Stripe values belong to this installation's private profile.
$gviStripe = gvi_config('stripe');
define('STRIPE_PUBLIC_KEY', $gviStripe['public_key']);
define('STRIPE_SECRET_KEY', $gviStripe['secret_key']);
define('STRIPE_MODE', $gviStripe['mode']);
unset($gviStripe);
