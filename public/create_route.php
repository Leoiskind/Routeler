<!-- mapbox access token 'pk.eyJ1IjoiZ2VvcmdlZmVpbnNvbiIsImEiOiJjbWxrd2NqcXowMTRrM2ZxeHgwNnJyYzRvIn0.MjrY8KmelJ7DsQpqkoM2RA' -->
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <title>Routler - Create Route</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    
    <script src="https://api.mapbox.com/mapbox-gl-js/v3.14.0/mapbox-gl.js"></script>
    <link href="https://api.mapbox.com/mapbox-gl-js/v3.14.0/mapbox-gl.css" rel="stylesheet" />
    
    <script src="https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-draw/v1.0.9/mapbox-gl-draw.js"></script>
    <link rel="stylesheet" href="https://api.mapbox.com/mapbox-gl-js/plugins/mapbox-gl-draw/v1.0.9/mapbox-gl-draw.css" type="text/css" />

    <link rel="stylesheet" href="../assets/css/create_route.css">
    <!-- <style>
        body { margin: 0; padding: 0; }
        #map { position: relative; top: 0; bottom: 0; width: 200px; height: 200px }
        .info-box {
            position: relative;
            margin: 20px;
            width: 25%;
            top: 0;
            height: 10%;
            bottom: 20px;
            padding: 20px;
            background-color: #fff;
            overflow-y: scroll;
        }
    </style> -->
  </head>

  <body>
    <nav class="navbar">
      <a href="front_page.php" class="nav-brand">Routler</a>
      <div class="nav-links">
        <a href="front_page.php" class="nav-item">Home</a>
        <a href="create_route.php" class="nav-item active">Create Route</a>
        <a href="profile.php" class="nav-item">Profile</a>
        <a href="login.php" class="nav-item">Logout</a>
      </div>
      <div class="user">Your username</div>
    </nav>
    
    <div id="map"></div>
    <script>
        // 3. Initialize the Map
        mapboxgl.accessToken = 'pk.eyJ1IjoiZ2VvcmdlZmVpbnNvbiIsImEiOiJjbWxrd2NqcXowMTRrM2ZxeHgwNnJyYzRvIn0.MjrY8KmelJ7DsQpqkoM2RA';
        const map = new mapboxgl.Map({
            container: 'map',
            style: 'mapbox://styles/mapbox/streets-v12',
            center: [-2.2384, 53.4735],
            zoom: 14.5
        });

      // 4. Initialize the MapboxDraw tool
      const draw = new MapboxDraw({
        displayControlsDefault: false,
        controls: {
            line_string: true,
            trash: true
        },
        defaultMode: 'draw_line_string',
        styles: [
          {
            id: 'gl-draw-line',
            type: 'line',
            filter: ['all', ['==', '$type', 'LineString'], ['!=', 'mode', 'static']],
            layout: { 'line-cap': 'round', 'line-join': 'round' },
            paint: {
              'line-color': '#438EE4',
              'line-dasharray': [0.2, 2],
              'line-width': 4,
              'line-opacity': 0.7
            }
          },
          {
            id: 'gl-draw-polygon-and-line-vertex-halo-active',
            type: 'circle',
            filter: ['all', ['==', 'meta', 'vertex'], ['==', '$type', 'Point'], ['!=', 'mode', 'static']],
            paint: { 'circle-radius': 12, 'circle-color': '#FFF' }
          },
          {
            id: 'gl-draw-polygon-and-line-vertex-active',
            type: 'circle',
            filter: ['all', ['==', 'meta', 'vertex'], ['==', '$type', 'Point'], ['!=', 'mode', 'static']],
            paint: { 'circle-radius': 8, 'circle-color': '#438EE4' }
          }
          
        ]
      });

      // Add the draw tool to the map.
      map.addControl(draw);

      // Use the coordinates you drew to make the Map Matching API request
      function updateRoute() {
        // Set the profile
        const profile = 'driving';
        // Get the coordinates that were drawn on the map
        const data = draw.getAll();
        const lastFeature = data.features.length - 1;
        const coords = data.features[lastFeature].geometry.coordinates;
        // Format the coordinates
        const newCoords = coords.join(';');
        // Set the radius for each coordinate pair to 25 meters
        const radius = coords.map(() => 25);
        console.log("newer coords", newCoords, "new coords")
        getMatch(newCoords, radius, profile);
      }

      // Make a Map Matching request
      // Make a Map Matching request
      async function getMatch(coordinates, radius, profile) {
        // Separate the radiuses with semicolons
        const radiuses = radius.join(';');
        // Create the query
        const query = await fetch(
          `https://api.mapbox.com/matching/v5/mapbox/${profile}/${coordinates}?geometries=geojson&radiuses=${radiuses}&steps=true&access_token=${mapboxgl.accessToken}`,
          { method: 'GET' }
        );
        const response = await query.json();
        // Handle errors
        if (response.code !== 'Ok') {
          alert(
            `${response.code} - ${response.message}.\n\nFor more information: https://docs.mapbox.com/api/navigation/map-matching/#map-matching-api-errors`
          );
          return;
        }
        // Get the coordinates from the response
        const coords = response.matchings[0].geometry;
        // Draw the route on the map
        addRoute(coords);
        getInstructions(response.matchings[0]);
      }
      function getInstructions(data) {
        // Target the sidebar to add the instructions
        const directions = document.getElementById('directions');
        let tripDirections = '';
        // Output the instructions for each step of each leg in the response object
        for (const leg of data.legs) {
          const steps = leg.steps;
          for (const step of steps) {
            tripDirections += `<li>${step.maneuver.instruction}</li>`;
          }
        }
        const summary = document.getElementById('summary');

        // Update summary box
        summary.innerHTML = `
          <p><strong>Duration:</strong> ${Math.floor(data.duration / 60)} min</p>
          <p><strong>Distance:</strong> ${(data.distance / 1000).toFixed(2)} km</p>
        `;

        // Only show directions list (no duplicate duration)
        directions.innerHTML = `<ol>${tripDirections}</ol>`;
        
      }
      // Draw the Map Matching route as a new layer on the map
      function addRoute(coords) {
        // If a route is already loaded, remove it
        if (map.getSource('route')) {
          map.removeLayer('route');
          map.removeSource('route');
        } else {
          // Add a new layer to the map
          map.addLayer({
            id: 'route',
            type: 'line',
            source: {
              type: 'geojson',
              data: {
                type: 'Feature',
                properties: {},
                geometry: coords
              }
            },
            layout: {
              'line-join': 'round',
              'line-cap': 'round'
            },
            paint: {
              'line-color': '#03AA46',
              'line-width': 8,
              'line-opacity': 0.8
            }
          });
        }
      }
      // If the user clicks the delete draw button, remove the layer if it exists
      function removeRoute() {
        if (!map.getSource('route')) return;
        map.removeLayer('route');
        map.removeSource('route');
      }

      // updates text box to show coordinates for troubleshooting
      function UpdateTroubleshootingBox(){
        const coordsBox = document.getElementById('route-coordinates');
        const drawnData = draw.getAll();
        if (drawnData.features.length > 0){
          const lastFeatureIndex = drawnData.features.length-1;
          const routeCoordinates = drawnData.features[lastFeatureIndex].geometry.coordinates;
          coordsBox.value=JSON.stringify(routeCoordinates);
        } else{
          coordsBox.value='';
          coordsBox.removeRoute();
        }
      }

      map.on('draw.delete', removeRoute);
      map.on('draw.create', updateRoute);
      map.on('draw.update', updateRoute);

      document.querySelector('.save-btn').addEventListener('click', () => {
        const name = document.getElementById('route-name').value;
        const description = document.getElementById('route-desc').value;

        console.log("Route Name:", name);
        console.log("Description:", description);
      });
      // here to to input coordinates into the text field
      map.on('draw.delete', UpdateTroubleshootingBox);
      map.on('draw.create', UpdateTroubleshootingBox);
      map.on('draw.update', UpdateTroubleshootingBox);
    </script>
    <div class="info-box">
      <section>
        <h2>Create Route</h2>
        <p class="subtitle">Draw your route using the tools on the map.</p>
      </section>

      <section id="summary"></section>

      <section>
        <h3>Directions</h3>
        <div class="directions-container">
          <div id="directions"></div>
        </div>
      </section>

      <section class="route-form">
        
      <form id="route-form" action="api/route_api.php" method="POST" class="input-container">
        <label for="route-title">Title:</label>
        <input type="text" id="route-title" name="name" placeholder="Please enter a title" required>

        <label for="route-notes">Description:</label>
        <textarea id="route-notes" name="description" placeholder="Describe your route"></textarea>

        <!-- <label for="route-coordinates">Raw Coordinates (Troubleshooting):</label>
        <textarea id="route-coordinates" name="coordinates" rows="4" readonly placeholder="Draw a route to see coordinates here..."></textarea> -->

        <button type="submit" id="save-button">Save and Upload Route</button>
      </form>
    </div>
    <script>
      const routeForm = document.getElementById("route-form");

      routeForm.addEventListener('submit', function(event){
        const drawnData = draw.getAll()

        if (drawnData.features.length===0){
          event.preventDefault();
          alert("Please draw a route on the map before saving!");
          return;
        }

        const lastFeatureIndex = drawnData.features.length -1;
        const routeCoordinates = drawnData.features[lastFeatureIndex].geometry.coordinates;

        // This is where routeCoordinates are put in the r
        document.getElementById('route-coordinates').values = JSON.stringify(routeCoordinates)
      })
    </script>
  </body>
</html>