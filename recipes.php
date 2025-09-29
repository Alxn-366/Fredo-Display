<?php
session_start();
require_once __DIR__ . '/../src/db.php';
if (!isset($_SESSION['user_id'])) header('Location: login.php');

$stmt = $pdo->query("SELECT * FROM recipes ORDER BY created_at DESC");
$recipes = $stmt->fetchAll();
?>
<!doctype html>
<html lang="de">

<head>
    <meta charset="utf-8">
    <title>Rezepte</title>
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
                <a class="btn btn-outline btn-sm" href="index.php">Dashboard</a>
                <a class="btn btn-outline btn-sm" href="profile.php" data-translate="profile">Profil</a>
                <a class="btn btn-outline btn-sm" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <h3 class="text-center mb-4">Rezepte</h3>
        <!-- Search Input -->
        <div class="row mb-4">
            <div class="col-md-6 offset-md-3">
                <input type="text" id="searchInput" class="form-control" placeholder="Rezepte suchen..." aria-label="Rezepte suchen">
            </div>
        </div>
        <div class="row" id="recipeContainer">
            <?php foreach ($recipes as $r): ?>
                <div class="col-md-6 mb-4 recipe-card" data-title="<?= htmlspecialchars(strtolower($r['title'])) ?>" data-description="<?= htmlspecialchars(strtolower($r['description'])) ?>">
                    <div class="card">
                        <div class="card-body">
                            <h5><?= htmlspecialchars($r['title']) ?></h5>
                            <p><?= nl2br(htmlspecialchars($r['description'])) ?></p>
                            <a href="recipe_match.php?recipe_id=<?= $r['recipe_id'] ?>" class="btn btn-outline btn-sm">Rezept anzeigen</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($recipes)): ?>
                <div class="col-12">
                    <div class="alert alert-info text-center">Keine Rezepte vorhanden.</div>
                </div>
            <?php endif; ?>
        </div>
        <div class="text-center mt-4">
            <a href="index.php" class="btn btn-secondary">Zurück zum Dashboard</a>
        </div>
    </div>

    <script>
        // Live search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const recipeCards = document.querySelectorAll('.recipe-card');

            recipeCards.forEach(card => {
                const title = card.getAttribute('data-title');
                const description = card.getAttribute('data-description');
                
                // Show card if title or description contains the search term
                if (title.includes(searchTerm) || description.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });

            // Show/hide "no recipes" message based on visible cards
            const visibleCards = Array.from(recipeCards).filter(card => card.style.display !== 'none');
            const noRecipesMessage = document.querySelector('.alert-info');
            if (noRecipesMessage) {
                noRecipesMessage.style.display = visibleCards.length === 0 && searchTerm ? 'block' : 'none';
            }
        });
    </script>
</body>

</html>