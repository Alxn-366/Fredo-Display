<?php
session_start();
require_once __DIR__ . '/../src/db.php';
if (!isset($_SESSION['user_id'])) header('Location: login.php');
$uid = (int)$_SESSION['user_id'];

if (isset($_GET['food_id'])) {
    $food_id = (int)$_GET['food_id'];
    // get food name
    $stmt = $pdo->prepare("SELECT name FROM foods WHERE food_id=? AND user_id=?");
    $stmt->execute([$food_id, $uid]);
    $f = $stmt->fetch();
    $search = $f ? $f['name'] : '';
    // find recipes containing that food_name (simple LIKE)
    $rstmt = $pdo->prepare("SELECT r.* FROM recipes r JOIN recipe_ingredients ri ON r.recipe_id = ri.recipe_id WHERE ri.food_name LIKE ? GROUP BY r.recipe_id");
    $rstmt->execute(['%' . $search . '%']);
    $hits = $rstmt->fetchAll();
} elseif (isset($_GET['recipe_id'])) {
    $rid = (int)$_GET['recipe_id'];
    $stmt = $pdo->prepare("SELECT * FROM recipes WHERE recipe_id=?");
    $stmt->execute([$rid]);
    $recipe = $stmt->fetch();
    $iring = $pdo->prepare("SELECT * FROM recipe_ingredients WHERE recipe_id=?");
    $iring->execute([$rid]);
    $ings = $iring->fetchAll();

    // fetch user foods
    $foods = $pdo->prepare("SELECT LOWER(name) as name FROM foods WHERE user_id=? AND status='available'");
    $foods->execute([$uid]);
    $userFoods = array_column($foods->fetchAll(), 'name');

    // determine missing ingredients
    $missing = [];
    foreach ($ings as $ing) {
        if (!in_array(mb_strtolower($ing['food_name']), $userFoods)) $missing[] = $ing['food_name'];
    }
}
?>
<!doctype html>
<html lang="de">

<head>
    <meta charset="utf-8">
    <title>Rezepte Match</title>
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
                <a class="btn btn-outline btn-sm" href="profile.php" data-translate="profile">Profil</a>
                <a class="btn btn-outline btn-sm" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <?php if (!empty($hits)): ?>
            <h4 class="mb-4">Rezepte mit dieser Zutat</h4>
            <div class="row">
                <?php foreach ($hits as $h): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card recipe-card">
                            <div class="card-body">
                                <h5><?= htmlspecialchars($h['title']) ?></h5>
                                <p><?= nl2br(htmlspecialchars($h['description'])) ?></p>
                                <a href="recipe_match.php?recipe_id=<?= $h['recipe_id'] ?>" class="btn btn-outline btn-sm">Zutaten prüfen</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif (!empty($recipe)): ?>
            <div class="card mb-4">
                <div class="card-body">
                    <h4><?= htmlspecialchars($recipe['title']) ?></h4>
                    <p><?= nl2br(htmlspecialchars($recipe['description'])) ?></p>
                    <h6>Zutaten</h6>
                    <ul class="list-group mb-3">
                        <?php foreach ($ings as $ing): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?= htmlspecialchars($ing['food_name']) ?>
                                <span class="badge bg-primary rounded-pill"><?= htmlspecialchars($ing['amount'] . ' ' . $ing['unit']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if (empty($missing)): ?>
                        <div class="alert alert-success">Du hast alle Zutaten!</div>
                    <?php else: ?>
                        <div class="alert alert-warning">Fehlende Zutaten: <?= htmlspecialchars(implode(', ', $missing)) ?></div>
                    <?php endif; ?>
                    <h6>Anleitung</h6>
                    <div class="bg-light p-3 rounded">
                        <pre class="mb-0"><?= htmlspecialchars($recipe['instructions']) ?></pre>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-info text-center">Keine passenden Rezepte gefunden.</div>
        <?php endif; ?>
        <div class="text-center">
            <a class="btn btn-secondary mt-3" href="index.php">Zurück zum Dashboard</a>
        </div>
    </div>
</body>

</html>