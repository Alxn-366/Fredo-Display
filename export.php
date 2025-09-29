<?php
session_start();
require_once __DIR__ . '/../src/db.php';
if (!isset($_SESSION['user_id'])) header('Location: login.php');
$uid = (int)$_SESSION['user_id'];

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=foods_export_' . date('Ymd') . '.csv');

$out = fopen('php://output', 'w');
fputcsv($out, ['name', 'category', 'quantity', 'unit', 'expiry_date', 'barcode', 'added_at']);

$stmt = $pdo->prepare("SELECT name,category,quantity,unit,expiry_date,barcode,added_at FROM foods WHERE user_id = ?");
$stmt->execute([$uid]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($out, $row);
}
fclose($out);
exit;
