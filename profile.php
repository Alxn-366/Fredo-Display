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

// Benutzerdaten abrufen
$stmt = $pdo->prepare("SELECT user_id, name, email, profile_picture, phone, address, created_at FROM users WHERE user_id = ?");
$stmt->execute([$uid]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: logout.php');
    exit;
}

// Formularverarbeitung
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);

    // Validierung
    if (!$name || !$email) {
        $error = 'Bitte Name und E-Mail eingeben.';
    } else {
        // Überprüfen, ob E-Mail bereits von anderen Benutzern verwendet wird
        $checkEmail = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $checkEmail->execute([$email, $uid]);

        if ($checkEmail->fetch()) {
            $error = 'E-Mail wird bereits von einem anderen Benutzer verwendet.';
        } else {
            // Profilfoto-Upload verarbeiten
            $profilePicture = $user['profile_picture'];

            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../uploads/profiles/';

                // Upload-Verzeichnis erstellen, falls nicht vorhanden
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $fileExtension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                $fileName = 'user_' . $uid . '_' . time() . '.' . $fileExtension;
                $uploadPath = $uploadDir . $fileName;

                // Dateityp überprüfen
                $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
                if (in_array(strtolower($fileExtension), $allowedTypes)) {
                    // Altes Profilfoto löschen, falls vorhanden
                    if ($profilePicture && file_exists($uploadDir . $profilePicture)) {
                        unlink($uploadDir . $profilePicture);
                    }

                    // Datei verschieben
                    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $uploadPath)) {
                        $profilePicture = $fileName;
                    } else {
                        $error = 'Fehler beim Hochladen der Datei.';
                    }
                } else {
                    $error = 'Nur JPG, JPEG, PNG und GIF Dateien sind erlaubt.';
                }
            }

            if (empty($error)) {
                // Benutzerdaten aktualisieren
                $updateStmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, address = ?, profile_picture = ? WHERE user_id = ?");
                $updateStmt->execute([$name, $email, $phone, $address, $profilePicture, $uid]);

                $_SESSION['name'] = $name;
                $success = 'Profil erfolgreich aktualisiert.';

                // Benutzerdaten neu abrufen
                $stmt->execute([$uid]);
                $user = $stmt->fetch();
            }
        }
    }
}
?>
<!doctype html>
<html lang="de">

<head>
    <meta charset="utf-8">
    <title>Profil bearbeiten - Kühlschrank Manager</title>
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

                <a class="btn btn-outline btn-sm" href="index.php">Dashboard</a>
                <a class="btn btn-outline btn-sm" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow">
                    <div class="card-body p-4">
                        <h3 class="text-center mb-4">Profil bearbeiten</h3>

                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>

                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                        <?php endif; ?>

                        <form method="post" enctype="multipart/form-data">
                            <div class="text-center mb-4">
                                <?php if ($user['profile_picture']): ?>
                                    <img src="../uploads/profiles/<?= htmlspecialchars($user['profile_picture']) ?>"
                                        alt="Profilbild"
                                        class="rounded-circle mb-3"
                                        style="width: 150px; height: 150px; object-fit: cover;">
                                <?php else: ?>
                                    <div class="rounded-circle bg-secondary d-inline-flex align-items-center justify-content-center mb-3"
                                        style="width: 150px; height: 150px;">
                                        <span class="text-white fs-1"><?= strtoupper(substr($user['name'], 0, 1)) ?></span>
                                    </div>
                                <?php endif; ?>

                                <div class="mb-3">
                                    <label for="profile_picture" class="form-label">Profilbild ändern</label>
                                    <input class="form-control" type="file" id="profile_picture" name="profile_picture" accept="image/*">
                                    <div class="form-text">Max. 5MB. Erlaubte Formate: JPG, JPEG, PNG, GIF</div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="name" class="form-label">Name</label>
                                <input type="text" class="form-control" id="name" name="name"
                                    value="<?= htmlspecialchars($user['name']) ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="email" class="form-label">E-Mail</label>
                                <input type="email" class="form-control" id="email" name="email"
                                    value="<?= htmlspecialchars($user['email']) ?>" required>
                            </div>

                            <div class="mb-3">
                                <label for="phone" class="form-label">Telefon</label>
                                <input type="tel" class="form-control" id="phone" name="phone"
                                    value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                            </div>

                            <div class="mb-3">
                                <label for="address" class="form-label">Adresse</label>
                                <textarea class="form-control" id="address" name="address" rows="3"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Mitglied seit</label>
                                <p class="form-control-static"><?= date('d.m.Y', strtotime($user['created_at'])) ?></p>
                            </div>

                            <div class="d-flex gap-2">
                                <a class="btn btn-secondary w-50" href="index.php">Zurück</a>
                                <button type="submit" class="btn btn-primary w-50">Speichern</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card shadow mt-4">
                    <div class="card-body p-4">
                        <h4 class="mb-4">Account-Einstellungen</h4>

                        <div class="d-grid gap-2">
                            <a href="change_password.php" class="btn btn-outline-primary">Passwort ändern</a>
                            <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                                Account löschen
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Account Modal -->
    <div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-labelledby="deleteAccountModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteAccountModalLabel">Account löschen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Bist du sicher, dass du deinen Account löschen möchtest? Diese Aktion kann nicht rückgängig gemacht werden.</p>
                    <p>Alle deine Daten, einschließlich deiner Lebensmittel und Einstellungen, werden permanent gelöscht.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-bismiss="modal">Abbrechen</button>
                    <a href="delete_account.php" class="btn btn-danger">Account löschen</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Vorschau des Profilbilds
        document.getElementById('profile_picture').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.querySelector('.rounded-circle');
                    if (preview.tagName === 'IMG') {
                        preview.src = e.target.result;
                    } else {
                        // Ersetze Platzhalter durch Bild
                        const newImg = document.createElement('img');
                        newImg.src = e.target.result;
                        newImg.alt = 'Profilbild';
                        newImg.className = 'rounded-circle mb-3';
                        newImg.style.width = '150px';
                        newImg.style.height = '150px';
                        newImg.style.objectFit = 'cover';

                        preview.parentNode.replaceChild(newImg, preview);
                    }
                }
                reader.readAsDataURL(file);
            }
        });
    </script>
</body>

</html>