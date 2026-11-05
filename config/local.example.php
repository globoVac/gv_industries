<?php

// Copy to local.php and fill with the values for THAT installation.
// local.php is private, ignored by Git, and must be preserved during deployments.
// Local Stripe uses test keys. The production profile uses its own live keys.
return [
    'site' => [
        'environment' => 'local', // 'producao' on WebTuga
        'base_url' => 'http://gv-industries.local/',
        'base_path' => dirname(__DIR__) . '/',
        'backoffice_base_url' => 'http://gv-industries.local/backoffice/',
        'backoffice_base_path' => dirname(__DIR__) . '/backoffice/',
    ],
    'database' => [
        // null in production: the product catalogue is read through the GloboVac API.
        'globovac' => [
            'host' => 'localhost',
            'user' => 'PREENCHER',
            'password' => 'PREENCHER',
            'name' => 'PREENCHER',
        ],
        'gvi' => [
            'host' => 'localhost',
            'user' => 'PREENCHER',
            'password' => 'PREENCHER',
            'name' => 'PREENCHER',
        ],
    ],
    'stripe' => [
        'public_key' => 'PREENCHER',
        'secret_key' => 'PREENCHER',
        'mode' => 'test', // 'live' on WebTuga
    ],
    'mail' => [
        'host' => 'PREENCHER',
        'username' => 'PREENCHER',
        'password' => 'PREENCHER',
        'secure' => 'ssl',
        'port' => 465,
    ],
];
