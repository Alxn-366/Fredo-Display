<?php
session_start();
require_once __DIR__ . '/../src/db.php';
if (!isset($_SESSION['user_id'])) header('Location: login.php');
$uid = (int)$_SESSION['user_id'];

if (!isset($_GET['id'])) header('Location: index.php');
$id = (int)$_GET['id'];

// Sicherheitsabfrage: Prüfen, ob das Lebensmittel dem Benutzer gehört
$check_stmt = $pdo->prepare("SELECT food_id FROM foods WHERE food_id = ? AND user_id = ?");
$check_stmt->execute([$id, $uid]);
$food = $check_stmt->fetch();

if (!$food) {
    header('Location: index.php');
    exit;
}

// mark as discarded and log
$pdo->beginTransaction();
$stmt = $pdo->prepare("UPDATE foods SET status='discarded' WHERE food_id = ? AND user_id = ?");
$stmt->execute([$id, $uid]);
$log = $pdo->prepare("INSERT INTO food_logs (food_id, user_id, action) VALUES (?, ?, 'discarded')");
$log->execute([$id, $uid]);
$pdo->commit();
$_SESSION['discard_success'] = 'Lebensmittel erfolgreich entsorgt.';

header('Location: index.php');
exit;
