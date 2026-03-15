<?php

declare(strict_types=1);

use App\Core\Config;

$appName = (string) Config::get('app.name', 'ExamAppV3');
$pageTitle = isset($title) && is_string($title) && $title !== ''
    ? $title . ' - ' . $appName
    : $appName;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="base-url" content="<?= e(base_url()) ?>">
    <title><?= e($pageTitle) ?></title>

    <link rel="icon" type="image/svg+xml" href="<?= e(asset_url('img/logo.svg')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('vendor/bootstrap/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('css/app.css')) ?>">
</head>
<body class="app-body">
    <div class="app-shell">
        <?php require BASE_PATH . '/src/Views/partials/navbar.php'; ?>

        <main class="app-main container py-4 py-lg-5">
            <?= $content ?? '' ?>
        </main>

        <?php require BASE_PATH . '/src/Views/partials/footer.php'; ?>
    </div>

    <script src="<?= e(asset_url('vendor/bootstrap/js/bootstrap.bundle.min.js')) ?>"></script>
    <script src="<?= e(asset_url('js/app.js')) ?>"></script>
</body>
</html>