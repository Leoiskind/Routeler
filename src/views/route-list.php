<?php
declare(strict_types=1);
/**
 * The front page listing.
 *
 * @var array $routes  as returned by RouteRepository::listAll()
 */

$token = mapbox_token();

/*
 * Embedding PHP data in a <script> block safely.
 *
 * The danger is a route named  </script><script>alert(1)</script>  — the
 * browser's HTML parser looks for the literal characters "</script>" before
 * JavaScript ever runs, so plain json_encode() would let that break out of
 * the block. The JSON_HEX_* flags escape <, >, &, ' and " as < and
 * friends, which is still valid JSON but contains no character the HTML
 * parser reacts to.
 *
 * e() is for HTML context; this is for JavaScript context. Different escape,
 * same reason.
 */
$routesJson = json_encode(
    $routes,
    JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
);
?>

<h1>Routes</h1>

<?php if ($routes === []): ?>

    <div class="empty">
        <p>No routes yet.</p>
        <p><a href="/create.php">Draw the first one</a></p>
    </div>

<?php else: ?>

    <?php if ($token === ''): ?>
        <div class="empty">
            <p>Map hidden — <code>MAPBOX_TOKEN</code> is not set in <code>.env</code>.</p>
        </div>
    <?php else: ?>
        <div id="map" class="map"></div>
    <?php endif; ?>

    <?php foreach ($routes as $route): ?>
        <?= view('route-card', ['route' => $route]) ?>
    <?php endforeach; ?>

    <?php if ($token !== ''): ?>
        <link href="https://api.mapbox.com/mapbox-gl-js/v3.18.1/mapbox-gl.css" rel="stylesheet">
        <script src="https://api.mapbox.com/mapbox-gl-js/v3.18.1/mapbox-gl.js"></script>
        <script>
        (() => {
            const routes = <?= $routesJson ?>;
            const drawable = routes.filter(r => r.points.length > 1);

            mapboxgl.accessToken = <?= json_encode($token, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

            const map = new mapboxgl.Map({
                container: 'map',
                style: 'mapbox://styles/mapbox/streets-v12',
                center: [-2.2339, 53.4668],   // Oxford Road; overridden by fitBounds below
                zoom: 12,
            });
            map.addControl(new mapboxgl.NavigationControl());

            map.on('load', () => {
                const bounds = new mapboxgl.LngLatBounds();

                drawable.forEach((route, i) => {
                    const id = 'route-' + route.id;

                    map.addSource(id, {
                        type: 'geojson',
                        data: {
                            type: 'Feature',
                            properties: { name: route.name },
                            geometry: { type: 'LineString', coordinates: route.points },
                        },
                    });

                    map.addLayer({
                        id: id,
                        type: 'line',
                        source: id,
                        layout: { 'line-join': 'round', 'line-cap': 'round' },
                        paint: {
                            'line-color': ['#10695c', '#b3541e', '#3b5ea8', '#8a2f5f'][i % 4],
                            'line-width': 4,
                            'line-opacity': 0.85,
                        },
                    });

                    route.points.forEach(p => bounds.extend(p));
                });

                // Frame every route, unless there was nothing to frame.
                if (!bounds.isEmpty()) {
                    map.fitBounds(bounds, { padding: 48, maxZoom: 15 });
                }
            });
        })();
        </script>
    <?php endif; ?>

<?php endif; ?>
