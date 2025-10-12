<?php /** @var string $content */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars(config('app')['name']) ?></title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/app.css" />
</head>
<body>
    <nav class="navbar">
        <div class="container">
            <a class="brand" href="<?= BASE_URL ?>">GarageMS</a>
            <div class="nav-right">
                <?php if (!empty($_SESSION['user'])): ?>
                    <span>Welcome, <?= htmlspecialchars($_SESSION['user']['name']) ?> (<?= htmlspecialchars($_SESSION['user']['role']) ?>)</span>
                    <a class="btn" href="<?= BASE_URL ?>/logout">Logout</a>
                <?php else: ?>
                    <a class="btn" href="<?= BASE_URL ?>/login">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <main class="container">
        <?= $content ?>
    </main>

    <script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>
