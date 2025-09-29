<?php
session_start();
require_once __DIR__ . '/../src/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'];

    if (!$name || !$email || !$password) {
        $error = 'Bitte alle Felder ausfüllen.';
    } else {
        // check exists
        $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'E-Mail bereits registriert.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
            $stmt->execute([$name, $email, $hash]);
            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['name'] = $name;
            header('Location: index.php');
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="de">

<head>
    <meta charset="utf-8">
    <title>Registrieren</title>
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

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="card shadow">
                    <div class="card-body p-5">
                        <h3 class="text-center mb-4">Registrieren</h3>
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        <form method="post">
                            <div class="mb-3">
                                <input name="name" class="form-control" placeholder="Name" required>
                            </div>
                            <div class="mb-3">
                                <input name="email" class="form-control" placeholder="E-Mail" type="email" required>
                            </div>
                            <div class="mb-3">
                                <input name="password" class="form-control" placeholder="Passwort" type="password" required>
                            </div>
                            <button class="btn btn-primary w-100">Registrieren</button>
                        </form>
                        <hr>
                        <div class="text-center">
                            <a href="login.php">Schon registriert? Login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>