<?php
session_start();
require_once __DIR__ . '/../src/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Nicht eingeloggt']);
    exit;
}

if (!isset($_GET['barcode'])) {
    echo json_encode(['error' => 'Kein Barcode angegeben']);
    exit;
}

$barcode = trim($_GET['barcode']);

// In known_products suchen
$stmt = $pdo->prepare("SELECT * FROM known_products WHERE barcode = ?");
$stmt->execute([$barcode]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if ($product) {
    echo json_encode([
        'found' => true,
        'name' => $product['name'],
        'category' => $product['category'],
        'typical_quantity' => $product['typical_quantity'],
        'typical_unit' => $product['typical_unit']
    ]);
} else {
    echo json_encode(['found' => false]);
}
