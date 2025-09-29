<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../src/db.php';

// Suchbegriff aus GET-Parameter holen
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// SQL-Abfrage mit Suche nach Titel oder Beschreibung
$query = "SELECT recipe_id, title, description FROM recipes WHERE title LIKE :search OR description LIKE :search ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute(['search' => "%$search%"]);
$recipes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// JSON-Antwort senden
echo json_encode(['recipes' => $recipes]);
