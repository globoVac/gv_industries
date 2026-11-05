<?php
/**
 * Delivery functions for GV Industries
 */

define('DELIVERY_STANDARD', 10.20);
define('DELIVERY_FREE_ABOVE', 100.00);

function checkPostcode($postcode, $connect_gvi, $pais = 'United Kingdom') {
    // Se não é UK, é sempre zona especial
    if ($pais !== 'United Kingdom') {
        return [
            'standard'    => false,
            'zone'        => 'E',
            'description' => $pais,
            'postcode'    => $postcode
        ];
    }

    // Limpar e converter para maiúsculas
    $postcode       = strtoupper(trim($postcode));
    $postcode_clean = str_replace(' ', '', $postcode);

    // Buscar prefixos excluídos — ordenar por comprimento DESC para match mais específico primeiro
    $result = mysqli_query($connect_gvi, "SELECT zone_name, postcode_prefix, description FROM delivery_zones ORDER BY LENGTH(postcode_prefix) DESC");

    while ($zone = mysqli_fetch_assoc($result)) {
        $prefix = strtoupper(trim($zone['postcode_prefix']));
        if (strpos($postcode_clean, $prefix) === 0) {
            return [
                'standard'    => false,
                'zone'        => $zone['zone_name'],
                'description' => $zone['description'],
                'postcode'    => $postcode
            ];
        }
    }

    // Zona standard UK
    return [
        'standard'    => true,
        'zone'        => 'UK',
        'description' => 'Standard UK delivery',
        'postcode'    => $postcode
    ];
}

function calculateDelivery($postcode, $subtotal, $connect_gvi, $pais = 'United Kingdom') {
    $zone = checkPostcode($postcode, $connect_gvi, $pais);

    if (!$zone['standard']) {
        return [
            'standard'     => false,
            'zone'         => $zone['zone'],
            'description'  => $zone['description'],
            'delivery_fee' => 0,
            'message'      => 'Your delivery area requires a custom quote. Our team will contact you with delivery costs and arrange payment separately.'
        ];
    }

    // Zona standard — calcula portes
    $delivery_fee = ($subtotal >= DELIVERY_FREE_ABOVE) ? 0 : DELIVERY_STANDARD;
    $message      = ($delivery_fee == 0)
        ? 'Free delivery on orders over £' . number_format(DELIVERY_FREE_ABOVE, 2) . '!'
        : 'Standard delivery: £' . number_format(DELIVERY_STANDARD, 2);

    return [
        'standard'     => true,
        'zone'         => 'UK',
        'description'  => 'Standard UK delivery',
        'delivery_fee' => $delivery_fee,
        'message'      => $message
    ];
}
