<?php
require('includes/config.php');
require_once('includes/database.php');

header('Content-Type: application/json');

$termo = strip_tags(trim($_GET['q'] ?? ''));

if (strlen($termo) < 2) {
    echo json_encode([]);
    exit;
}

$resultados = gvSearch($connect_globovac, $termo);
echo json_encode($resultados);
