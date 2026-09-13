<?php
declare(strict_types=1);
/**
 * One route in a listing.
 *
 * Expects $route in the shape RouteRepository::listAll() returns:
 *   id, name, description, distance_m, author, points[]
 *
 * @var array $route
 */

$points   = $route['points'] ?? [];
$distance = $route['distance_m'] ?? null;
?>
<article class="route-card">
    <h2 class="route-card__title">
        <a href="/route.php?id=<?= (int) $route['id'] ?>"><?= e($route['name']) ?></a>
    </h2>

    <p class="route-card__meta">
        by <a href="/profile.php?u=<?= urlencode((string) $route['author']) ?>">@<?= e($route['author']) ?></a>
        <?php if ($distance !== null): ?>
            &middot; <?= number_format((int) $distance / 1000, 1) ?> km
        <?php endif; ?>
        &middot; <?= count($points) ?> point<?= count($points) === 1 ? '' : 's' ?>
    </p>

    <?php if (!empty($route['description'])): ?>
        <p class="route-card__description"><?= e($route['description']) ?></p>
    <?php endif; ?>
</article>
