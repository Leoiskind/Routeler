<?php
declare(strict_types=1);
/**
 * Draw a route, snap it to the road network, save the snapped line.
 *
 * The important change from the old create_route.php: what gets saved is
 * the MATCHED geometry from Mapbox — the dense line that follows streets —
 * not the handful of points the user clicked. That is why routes will
 * render along roads without any further API calls when viewed.
 */

$token = mapbox_token();
?>

<h1>Draw a route</h1>

<?php if ($token === ''): ?>

    <div class="empty">
        <p>Drawing needs a Mapbox token. Set <code>MAPBOX_TOKEN</code> in <code>.env</code>
           and recreate the container.</p>
    </div>

<?php else: ?>

    <link href="https://api.mapbox.com/mapbox-gl-js/v3.18.1/mapbox-gl.css" rel="stylesheet">
    <script src="https://api.mapbox.com/mapbox-gl-js/v3.18.1/mapbox-gl.js"></script>
    <link href="https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-draw/v1.4.3/mapbox-gl-draw.css" rel="stylesheet">
    <script src="https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-draw/v1.4.3/mapbox-gl-draw.js"></script>

    <p class="hint">Click along a road to place points, double-click to finish.
       The line snaps to the street network as you go.</p>

    <div id="map" class="map"></div>

    <div id="summary" class="summary" hidden></div>

    <form id="route-form" class="route-form">
        <label class="field">
            <span>Title</span>
            <input type="text" name="name" id="route-name" maxlength="160" required>
        </label>

        <label class="field">
            <span>Description</span>
            <textarea name="description" id="route-description" rows="3"></textarea>
        </label>

        <button type="submit" class="button" id="save-button" disabled>Save route</button>
        <p id="form-error" class="form-error" hidden></p>
    </form>

    <script>
    (() => {
        mapboxgl.accessToken = <?= json_encode($token, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        const MATCH_LIMIT = 100;   // Mapbox Map Matching accepts at most 100 coordinates

        const map = new mapboxgl.Map({
            container: 'map',
            style: 'mapbox://styles/mapbox/streets-v12',
            center: [-2.2339, 53.4668],
            zoom: 14,
        });
        map.addControl(new mapboxgl.NavigationControl());

        const draw = new MapboxDraw({
            displayControlsDefault: false,
            controls: { line_string: true, trash: true },
            styles: [
                { id: 'draft', type: 'line',
                  filter: ['all', ['==', '$type', 'LineString']],
                  layout: { 'line-cap': 'round', 'line-join': 'round' },
                  paint: { 'line-color': '#888', 'line-dasharray': [0.4, 2], 'line-width': 3 } },
            ],
        });
        map.addControl(draw);

        // What we will actually save: the snapped line and its length.
        let matched = null;      // [[lng, lat], ...]
        let distanceM = null;    // integer metres

        const saveButton = document.getElementById('save-button');
        const summary    = document.getElementById('summary');
        const errorBox   = document.getElementById('form-error');

        const showError = (message) => {
            errorBox.textContent = message;
            errorBox.hidden = false;
        };
        const clearError = () => { errorBox.hidden = true; };

        map.on('draw.create', matchDrawing);
        map.on('draw.update', matchDrawing);
        map.on('draw.delete', () => {
            matched = null;
            distanceM = null;
            saveButton.disabled = true;
            summary.hidden = true;
            if (map.getLayer('matched')) { map.removeLayer('matched'); map.removeSource('matched'); }
        });

        async function matchDrawing() {
            clearError();

            const features = draw.getAll().features;
            if (features.length === 0) return;

            const drawn = features[features.length - 1].geometry.coordinates;
            if (drawn.length < 2) return;

            if (drawn.length > MATCH_LIMIT) {
                showError(`Too many points (${drawn.length}). Map Matching allows ${MATCH_LIMIT}; draw a simpler line.`);
                return;
            }

            // Mapbox wants lng,lat pairs separated by semicolons, plus a
            // search radius per coordinate saying how far it may snap.
            const coords   = drawn.map(c => `${c[0]},${c[1]}`).join(';');
            const radiuses = drawn.map(() => 25).join(';');

            const url = `https://api.mapbox.com/matching/v5/mapbox/walking/${coords}`
                      + `?geometries=geojson&radiuses=${radiuses}&steps=false`
                      + `&access_token=${mapboxgl.accessToken}`;

            let data;
            try {
                const response = await fetch(url);
                data = await response.json();
            } catch {
                showError('Could not reach Mapbox. Check your connection.');
                return;
            }

            if (data.code !== 'Ok' || !data.matchings?.length) {
                showError(`Could not snap that to roads (${data.code ?? 'error'}). Try drawing closer to a street.`);
                return;
            }

            const best = data.matchings[0];
            matched   = best.geometry.coordinates;      // the dense, road-following line
            distanceM = Math.round(best.distance);      // metres

            drawMatched(best.geometry);

            summary.hidden = false;
            summary.innerHTML =
                `<strong>${(distanceM / 1000).toFixed(2)} km</strong> · ${matched.length} points`;

            saveButton.disabled = false;
        }

        function drawMatched(geometry) {
            if (map.getSource('matched')) {
                map.getSource('matched').setData({ type: 'Feature', properties: {}, geometry });
                return;
            }
            map.addSource('matched', { type: 'geojson', data: { type: 'Feature', properties: {}, geometry } });
            map.addLayer({
                id: 'matched',
                type: 'line',
                source: 'matched',
                layout: { 'line-join': 'round', 'line-cap': 'round' },
                paint: { 'line-color': '#10695c', 'line-width': 5, 'line-opacity': 0.85 },
            });
        }

        document.getElementById('route-form').addEventListener('submit', async (event) => {
            event.preventDefault();
            clearError();

            if (matched === null) {
                showError('Draw a route on the map first.');
                return;
            }

            saveButton.disabled = true;
            saveButton.textContent = 'Saving…';

            let result;
            try {
                const response = await fetch('/api/routes.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        name:        document.getElementById('route-name').value,
                        description: document.getElementById('route-description').value,
                        distance_m:  distanceM,
                        points:      matched,          // the snapped line, not the clicks
                    }),
                });
                result = await response.json();

                if (!response.ok) {
                    showError(result.error ?? 'Could not save the route.');
                    saveButton.disabled = false;
                    saveButton.textContent = 'Save route';
                    return;
                }
            } catch {
                showError('Could not reach the server.');
                saveButton.disabled = false;
                saveButton.textContent = 'Save route';
                return;
            }

            window.location.href = '/route.php?id=' + encodeURIComponent(result.id);
        });
    })();
    </script>

<?php endif; ?>
