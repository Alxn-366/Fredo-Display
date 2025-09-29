<?php
session_start();
require_once __DIR__ . '/../src/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$uid = (int)$_SESSION['user_id'];

// Optional: Bestätigung per Passwort erfragen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'];

    // Passwort überprüfen
    $stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
    $stmt->execute([$uid]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Alle Benutzerdaten löschen (transaktionssicher)
        $pdo->beginTransaction();

        try {
            // Profilbild löschen, falls vorhanden
            $profileStmt = $pdo->prepare("SELECT profile_picture FROM users WHERE user_id = ?");
            $profileStmt->execute([$uid]);
            $profile = $profileStmt->fetch();

            if ($profile && $profile['profile_picture']) {
                $uploadDir = __DIR__ . '/../uploads/profiles/';
                if (file_exists($uploadDir . $profile['profile_picture'])) {
                    unlink($uploadDir . $profile['profile_picture']);
                }
            }

            // Lebensmittel löschen
            $pdo->prepare("DELETE FROM foods WHERE user_id = ?")->execute([$uid]);

            // Logs löschen
            $pdo->prepare("DELETE FROM food_logs WHERE user_id = ?")->execute([$uid]);

            // Benutzer löschen
            $pdo->prepare("DELETE FROM users WHERE user_id = ?")->execute([$uid]);

            $pdo->commit();

            // Session zerstören und umleiten
            session_destroy();
            header('Location: login.php?account_deleted=1');
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Fehler beim Löschen des Accounts: ' . $e->getMessage();
        }
    } else {
        $error = 'Passwort ist nicht korrekt.';
    }
}

// Falls keine POST-Daten oder Fehler, Formular anzeigen
?>
<!doctype html>
<html lang="de">

<head>
    <meta charset="utf-8">
    <title>Account löschen - Kühlschrank Manager</title>
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
            <div class="d-flex">
                <a class="btn btn-outline btn-sm" href="profile.php">Profil</a>
                <a class="btn btn-outline btn-sm" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow">
                    <div class="card-body p-4">
                        <h3 class="text-center mb-4 text-danger">Account löschen</h3>

                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>

                        <div class="alert alert-warning">
                            <strong>Warnung:</strong> Diese Aktion kann nicht rückgängig gemacht werden.
                            Alle deine Daten werden permanent gelöscht.
                        </div>

                        <form method="post">
                            <div class="mb-3">
                                <label for="password" class="form-label">Passwort zur Bestätigung</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <div class="form-text">Gib dein Passwort ein, um das Löschen zu bestätigen.</div>
                            </div>

                            <div class="d-flex gap-2">
                                <a class="btn btn-secondary w-50" href="profile.php">Abbrechen</a>
                                <button type="submit" class="btn btn-danger w-50">Account endgültig löschen</button>
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