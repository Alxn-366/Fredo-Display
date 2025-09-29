<?php

/**
 * Open Food Facts API Integration
 */

function getProductByBarcode($barcode)
{
    $url = "https://world.openfoodfacts.org/api/v0/product/$barcode.json";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Kuehlschrank-Manager - Android - Version 1.0');

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);

        if ($data && $data['status'] === 1) {
            $product = $data['product'];

            return [
                'success' => true,
                'product' => [
                    'name' => $product['product_name'] ?? $product['product_name_de'] ?? $product['product_name_en'] ?? 'Unbekannt',
                    'quantity' => $product['quantity'] ?? '',
                    'categories' => $product['categories'] ?? '',
                    'image_url' => $product['image_url'] ?? $product['image_front_url'] ?? '',
                    'brand' => $product['brands'] ?? ''
                ]
            ];
        }
    }

    return [
        'success' => false,
        'error' => 'Produkt nicht gefunden oder API-Fehler'
    ];
}

// API-Endpoint für AJAX-Anfragen
if (isset($_GET['barcode']) && !empty($_GET['barcode'])) {
    header('Content-Type: application/json');
    $result = getProductByBarcode($_GET['barcode']);
    echo json_encode($result);
    exit;
}
