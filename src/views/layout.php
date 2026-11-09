<?php /** @var string $title @var string $content */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= h($title) ?> · RWLL Evals</title>
    <meta name="theme-color" content="#1c5634">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="RWLL Evals">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/favicon.ico">
    <link rel="apple-touch-icon" href="<?= asset('/assets/icon-128.png') ?>">
    <link rel="stylesheet" href="<?= asset('/assets/app.css') ?>">
    <script src="<?= asset('/assets/app.js') ?>" defer></script>
</head>
<body>
<header class="bar">
    <a class="bar-home" href="/" aria-label="All stations"><img src="<?= asset('/assets/logo.png') ?>" alt="" width="40" height="22"></a>
    <h1><?= h($title) ?></h1>
    <button type="button" class="outbox" id="outbox" hidden></button>
</header>
<main>
<?= $content ?>
</main>
<div class="toast" id="toast" role="status" aria-live="polite"></div>
</body>
</html>
