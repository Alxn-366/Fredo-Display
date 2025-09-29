<?php
session_start();
require_once __DIR__ . '/../src/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$uid = (int)$_SESSION['user_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validierung
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = 'Bitte alle Felder ausfüllen.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Die neuen Passwörter stimmen nicht überein.';
    } elseif (strlen($new_password) < 6) {
        $error = 'Das neue Passwort muss mindestens 6 Zeichen lang sein.';
    } else {
        // Aktuelles Passwort überprüfen
        $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt->execute([$uid]);
        $user = $stmt->fetch();

        if ($user && password_verify($current_password, $user['password'])) {
            // Neues Passwort hashen und speichern
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $updateStmt->execute([$new_hash, $uid]);

            $success = 'Passwort erfolgreich geändert.';
        } else {
            $error = 'Aktuelles Passwort ist nicht korrekt.';
        }
    }
}
?>
<!doctype html>
<html lang="de">

<head>
    <meta charset="utf-8">
    <title>Passwort ändern - Kühlschrank Manager</title>
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
            <a class="navbar-brand" href="#">Fredo<span>.</span></a>
            <a class="navbar-brand" href="#">Hallo, <?= htmlspecialchars($_SESSION['name']) ?></a>
            <div class="d-flex">
                
                <a class="btn btn-outline btn-sm" href="profile.php">Profil</a>
                <a class="btn btn-primary btn-sm ms-2" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow">
                    <div class="card-body p-4">
                        <h3 class="text-center mb-4">Passwort ändern</h3>

                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>

                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                        <?php endif; ?>

                        <form method="post">
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Aktuelles Passwort</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" required>
                            </div>

                            <div class="mb-3">
                                <label for="new_password" class="form-label">Neues Passwort</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" required>
                                <div class="form-text">Mindestens 6 Zeichen</div>
                            </div>

                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Neues Passwort bestätigen</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>

                            <div class="d-flex gap-2">
                                <a class="btn btn-secondary w-50" href="profile.php">Zurück</a>
                                <button type="submit" class="btn btn-primary w-50">Passwort ändern</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>