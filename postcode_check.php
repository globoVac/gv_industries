<?php
require('includes/config.php');
require_once('includes/database.php');
require_once('includes/delivery.php');

header('Content-Type: application/json');

$postcode = trim($_GET['postcode'] ?? '');
$pais     = trim($_GET['pais']     ?? 'United Kingdom');
$subtotal = (float)($_GET['subtotal'] ?? 0);

if (empty($postcode)) {
    echo json_encode(['error' => 'No postcode provided']);
    exit;
}

$result = calculateDelivery($postcode, $subtotal, $connect_gvi, $pais);
echo json_encode($result);
