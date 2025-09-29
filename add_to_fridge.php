<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Nicht eingeloggt']);
    exit;
}

require_once __DIR__ . '/../src/db.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['barcode'])) {
    echo json_encode(['status' => 'error', 'message' => 'Kein Barcode übergeben']);
    exit;
}

$barcode = $data['barcode'];
$userId = $_SESSION['user_id'];

try {
    // 1. Prüfen ob Produkt in known_products existiert
    $stmt = $pdo->prepare("SELECT * FROM known_products WHERE barcode = ?");
    $stmt->execute([$barcode]);
    $knownProduct = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$knownProduct) {
        echo json_encode(['status' => 'not_found', 'message' => 'Produkt nicht in Datenbank gefunden']);
        exit;
    }

    // 2. Prüfen ob Lebensmittel bereits im Kühlschrank existiert
    $stmt = $pdo->prepare("SELECT * FROM foods WHERE user_id = ? AND barcode = ? AND status = 'available'");
    $stmt->execute([$userId, $barcode]);
    $existingFood = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingFood) {
        // Menge erhöhen falls bereits vorhanden
        $stmt = $pdo->prepare("UPDATE foods SET quantity = quantity + ? WHERE food_id = ?");
        $stmt->execute([$knownProduct['typical_quantity'], $existingFood['food_id']]);
        $action = 'aktualisiert';
    } else {
        // Neues Lebensmittel hinzufügen
        $stmt = $pdo->prepare("INSERT INTO foods (user_id, name, category, quantity, unit, expiry_date, barcode, added_at) 
                              VALUES (?, ?, ?, ?, ?, DATE_ADD(CURDATE(), INTERVAL 14 DAY), ?, NOW())");
        $stmt->execute([
            $userId,
            $knownProduct['name'],
            $knownProduct['category'],
            $knownProduct['typical_quantity'],
            $knownProduct['typical_unit'],
            $barcode
        ]);
        $action = 'hinzugefügt';
    }

    // Erfolgsmeldung für Session speichern
    $_SESSION['scan_success'] = $knownProduct['name'] . ' wurde erfolgreich ' . $action . '!';

    echo json_encode([
        'status' => 'success',
        'message' => $knownProduct['name'] . ' wurde deinem Kühlschrank ' . $action . '!',
        'product' => $knownProduct,
        'action' => $action
    ]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Datenbankfehler: ' . $e->getMessage()]);
}
