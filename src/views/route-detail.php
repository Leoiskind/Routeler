<?php
declare(strict_types=1);
/**
 * One route.
 *
 * @var array $route  as returned by RouteRepository::find()
 */

$token    = mapbox_token();
$points   = $route['points'] ?? [];
$distance = $route['distance_m'] ?? null;

$pointsJson = json_encode(
    $points,
    JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);

// created_at comes back as a MySQL DATETIME string; format it for humans.
$created = null;
if (!empty($route['created_at'])) {
    $dt = date_create($route['created_at']);
    $created = $dt !== false ? $dt->format('j M Y') : null;
}
?>

<p class="breadcrumb"><a href="/">&larr; All routes</a></p>

<h1><?= e($route['name']) ?></h1>

<p class="route-meta">
    by <a href="/profile.php?u=<?= urlencode((string) $route['author']) ?>">@<?= e($route['author']) ?></a>
    <?php if ($distance !== null): ?>
        &middot; <?= number_format((int) $distance / 1000, 2) ?> km
    <?php endif; ?>
    &middot; <?= count($points) ?> point<?= count($points) === 1 ? '' : 's' ?>
    <?php if ($created !== null): ?>
        &middot; <?= e($created) ?>
    <?php endif; ?>
</p>

<?php if (!empty($route['description'])): ?>
    <p class="route-description"><?= e($route['description']) ?></p>
<?php endif; ?>

<?php if (count($points) < 2): ?>

    <div class="empty">
        <p>This route has no line to draw yet.</p>
    </div>

<?php elseif ($token === ''): ?>

    <div class="empty">
        <p>Map hidden — <code>MAPBOX_TOKEN</code> is not set in <code>.env</code>.</p>
    </div>

<?php else: ?>

    <div id="map" class="map map--tall"></div>

    <link href="https://api.mapbox.com/mapbox-gl-js/v3.18.1/mapbox-gl.css" rel="stylesheet">
    <script src="https://api.mapbox.com/mapbox-gl-js/v3.18.1/mapbox-gl.js"></script>
    <script>
    (() => {
        const points = <?= $pointsJson ?>;

        mapboxgl.accessToken = <?= json_encode($token, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        const map = new mapboxgl.Map({
            container: 'map',
            style: 'mapbox://styles/mapbox/streets-v12',
            center: points[0],
            zoom: 14,
        });
        map.addControl(new mapboxgl.NavigationControl());

        map.on('load', () => {
            map.addSource('route', {
                type: 'geojson',
                data: { type: 'Feature', properties: {},
                        geometry: { type: 'LineString', coordinates: points } },
            });

            map.addLayer({
                id: 'route',
                type: 'line',
                source: 'route',
                layout: { 'line-join': 'round', 'line-cap': 'round' },
                paint: { 'line-color': '#10695c', 'line-width': 5, 'line-opacity': 0.9 },
            });

            // Start and end markers, so the direction of travel is readable.
            new mapboxgl.Marker({ color: '#2f8f4e' }).setLngLat(points[0]).addTo(map);
            new mapboxgl.Marker({ color: '#9d2f26' }).setLngLat(points[points.length - 1]).addTo(map);

            const bounds = points.reduce(
                (b, p) => b.extend(p),
                new mapboxgl.LngLatBounds(points[0], points[0])
            );
            map.fitBounds(bounds, { padding: 56, maxZoom: 16 });
        });
    })();
    </script>

<?php endif; ?>
