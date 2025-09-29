<?php
session_start();
require_once __DIR__ . '/../src/db.php';
if (!isset($_SESSION['user_id'])) header('Location: login.php');
$uid = (int)$_SESSION['user_id'];
if (!isset($_GET['id'])) header('Location: index.php');
$id = (int)$_GET['id'];

$stmt = $pdo->prepare("SELECT * FROM foods WHERE food_id = ? AND user_id = ?");
$stmt->execute([$id, $uid]);
$f = $stmt->fetch();
if (!$f) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $category = trim($_POST['category']);
    $quantity = (float)$_POST['quantity'];
    $unit = trim($_POST['unit']);
    $expiry_date = $_POST['expiry_date'] ?: null;
    $barcode = trim($_POST['barcode']) ?: null;
    $stmt = $pdo->prepare("UPDATE foods SET name=?, category=?, quantity=?, unit=?, expiry_date=?, barcode=? WHERE food_id=? AND user_id=?");
    $stmt->execute([$name, $category, $quantity, $unit, $expiry_date, $barcode, $id, $uid]);
    header('Location: index.php');
    exit;
}
?>
<!doctype html>
<html lang="de">

<head>
    <meta charset="utf-8">
    <title>Bearbeiten</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>

<body>
    <!-- Floating Background -->
    <div class="floating-bg">
        <div></div>
        <div></div>
        <div></div>
        <div></div>
    </div>

    <nav class="navbar">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">Fredo<span>.</span></a>
            <a class="navbar-brand" href="#">Hallo, <?= htmlspecialchars($_SESSION['name']) ?></a>
            <div class="d-flex">

                <a class="btn btn-outline btn-sm" href="scanner.php">Scanner</a>
                <a class="btn btn-outline btn-sm" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow">
                    <div class="card-body p-4">
                        <h4 class="text-center mb-4">Lebensmittel Bearbeiten</h4>
                        <form method="post">
                            <div class="mb-3">
                                <input name="name" class="form-control" value="<?= htmlspecialchars($f['name']) ?>" required>
                            </div>
                            <div class="row mb-3">
                                <div class="col-6">
                                    <input name="quantity" class="form-control" type="number" step="0.01" value="<?= htmlspecialchars($f['quantity']) ?>" required>
                                </div>
                                <div class="col-6">
                                    <input name="unit" class="form-control" value="<?= htmlspecialchars($f['unit']) ?>" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <input name="category" class="form-control" value="<?= htmlspecialchars($f['category']) ?>">
                            </div>
                            <div class="mb-3">
                                <input name="expiry_date" class="form-control" type="date" value="<?= htmlspecialchars($f['expiry_date']) ?>">
                            </div>
                            <div class="mb-3">
                                <input name="barcode" class="form-control" value="<?= htmlspecialchars($f['barcode']) ?>">
                            </div>
                            <div class="d-flex gap-2">
                                <a class="btn btn-secondary w-50" href="index.php">Zurück</a>
                                <button class="btn btn-primary w-50">Speichern</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>