<!-- mapbox access token 'pk.eyJ1IjoiZ2VvcmdlZmVpbnNvbiIsImEiOiJjbWxrd2NqcXowMTRrM2ZxeHgwNnJyYzRvIn0.MjrY8KmelJ7DsQpqkoM2RA' -->

<!doctype html>
<?php $session_value=(isset($_SESSION['route_latlong']))?$_SESSION['route_latlong']:''; ?>

<html lang="en">

  <head>
    <meta charset="utf-8" />
    <title>Directions API implementation with hardcoded coords</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    
    <script src="https://api.mapbox.com/mapbox-gl-js/v3.14.0/mapbox-gl.js"></script>
    <link href="https://api.mapbox.com/mapbox-gl-js/v3.14.0/mapbox-gl.css" rel="stylesheet" />

    <style>
        body {margin: 0; padding: 0;}
        #map {position: absolute; top: 0; bottom: 0; width: 100%;}
    </style>
  </head>

  <body>
    <div id="map"></div>

    <script>
        mapboxgl.accessToken = 'pk.eyJ1IjoiZ2VvcmdlZmVpbnNvbiIsImEiOiJjbWxrd2NqcXowMTRrM2ZxeHgwNnJyYzRvIn0.MjrY8KmelJ7DsQpqkoM2RA';
        
        
        const waypoints = '<?php echo $session_value;?>';
        const startCoords = waypoints[0];

        console.log(waypoints);

        const map = new mapboxgl.Map({
            container: 'map',
            style: 'mapbox://styles/mapbox/streets-v12',
            center: startCoords,
            zoom: 14
        });

        async function getRoute(coordsArray){
            const coordsString = coordsArray.map(coord => `${coord[0]},${coord[1]}`).join(';');
            const query = await fetch(
                `https://api.mapbox.com/directions/v5/mapbox/driving/${coordsString}?steps=true&geometries=geojson&access_token=${mapboxgl.accessToken}`,
                {method: 'GET'}
            );

            const json= await query.json();
            if (json.code !== 'Ok'){
                console.error("Mapbox API Error:", json.message);
                return;
            }

            const data= json.routes[0];
            const route = data.geometry;

            map.addLayer({
                id: 'route',
                type: 'line',
                source: {
                    type: 'geojson',
                    data: {
                        type: 'Feature',
                        properties: {},
                        geometry: route
                    }
                },
                layout: {
                    'line-join': 'round',
                    'line-cap': 'round'
                },
                paint:{
                    'line-color': '#3887be',
                    'line-width': 5,
                    'line-opacity': 0.75
                }
            });
        }

        map.on('load', () => {
            waypoints.forEach((coord, index) => {
                let dotColor = '#f1c40f';
                if (index === 0) dotColor = '#4ce05b';
                if (index === waypoints.length-1) dotColor = '#f30';

                map.addLayer({
                    id: `waypoint-${index}`,
                    type: 'circle',
                    source: {
                        type: 'geojson',
                        data: {
                            type: 'Feature',
                            properties: {},
                            geometry: {type: 'Point', coordinates: coord}
                        }
                    },
                    paint: {'circle-radius': 8, 'circle-color': dotColor}
                });
            });
            getRoute(waypoints)
        });
    </script>
  </body>