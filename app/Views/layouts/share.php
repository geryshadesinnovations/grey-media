<?php
/** @var string $__yield */
/** @var array<string,string> $__sections */
$theme = $_COOKIE['theme'] ?? 'dark';
?>
<!doctype html>
<html lang="en" data-theme="<?= e($theme) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
<title><?= e($title ?? 'Shared media') ?> · Greyshades</title>
<link rel="stylesheet" href="<?= asset('css/app.css') ?>">
<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link rel="icon" href="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='20' fill='%23111827'/><text x='50' y='62' font-size='52' text-anchor='middle' fill='%23a78bfa' font-family='sans-serif' font-weight='700'>G</text></svg>">
</head>
<body class="share-body">
<header class="topbar share-topbar">
    <span class="brand">
        <span class="brand-mark">G</span>
        <span class="brand-name">Greyshades<small>Shared media</small></span>
    </span>
</header>

<main class="app-main">
    <?= $__yield ?>
</main>

<?= $__sections['scripts'] ?? '' ?>
</body>
</html>
