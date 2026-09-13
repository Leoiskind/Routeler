<?php
declare(strict_types=1);
/**
 * Site layout. Receives $title and $content from render().
 * $content is already-rendered HTML, so it is deliberately not escaped.
 *
 * @var string $title
 * @var string $content
 */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<?= view('nav') ?>

<main class="container">
<?= $content ?>
</main>

<footer class="site-footer">
    <p>Routeler &middot; routes around Manchester</p>
</footer>
</body>
</html>
