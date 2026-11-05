<?php
if (!defined('AMBIENTE')) {
    require_once __DIR__ . '/config.php';
}

require_once dirname(__DIR__) . '/config/database.php';

// GloboVac BD - products
if (AMBIENTE === 'local') {
    $connect_globovac = gvi_connect_database('globovac');
} else {
    // Production - via API
    $connect_globovac = null;
}

if ($connect_globovac) {
    mysqli_set_charset($connect_globovac, "utf8mb4");
    if (mysqli_connect_errno()) {
        if (AMBIENTE === 'local') {
            exit('DB Connection Error: ' . mysqli_connect_error());
        } else {
            exit('Connection error. Please contact the administrator.');
        }
    }
}

// GV Industries BD - orders, customers
$connect_gvi = gvi_connect_database('gvi');

if ($connect_gvi) {
    mysqli_set_charset($connect_gvi, "utf8mb4");
    if (mysqli_connect_errno()) {
        if (AMBIENTE === 'local') {
            exit('GVI DB Connection Error: ' . mysqli_connect_error());
        } else {
            exit('Connection error. Please contact the administrator.');
        }
    }
}

// Carregar API e abstracção GloboVac
if (AMBIENTE !== 'local') {
    require_once __DIR__ . '/api.php';
}
require_once __DIR__ . '/globovac.php';
