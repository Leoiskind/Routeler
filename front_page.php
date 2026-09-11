<?php
    session_start();
    $ch = curl_init();
    $_SESSION['username'] = $_GET['username'];
    $params = [
        'username' => $_SESSION['username'], 
    ];
    $url = "https://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
    $tempurl = explode('/', $url);
    $tempurl[count($tempurl)-1] = "user_api.php";
    $url = implode("/", $tempurl);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    $response = curl_exec($ch);

    if(!isset($_SESSION['user_id'])|| $_SESSION['user_id']==null){
        $ch2 = curl_init();
        curl_setopt($ch2, CURLOPT_URL, $url);
        $url = "https://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
        $tempurl = explode('/', $url);
        $tempurl[count($tempurl)-1] = "user_api.php";
        $url = implode("/", $tempurl);
        curl_setopt($ch2, CURLOPT_HTTPHEADER, array('Accept: application/json', 'Content-Type: application/json'));
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_POSTFIELDS, "");
        $response = curl_exec($ch2);
    }

    $url = "https://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
    $tempurl = explode('/', $url);
    $tempurl[count($tempurl)-1] = "route_api.php";
    $url = implode("/", $tempurl);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    $response = curl_exec($ch);
?>


<html>
    <!-- Just made this branch so we have can work on the same space -->
    <head>
        <title> Routler - Home </title>
        <link rel="stylesheet" href="https://api.mapbox.com/mapbox-gl-js/v3.18.1/mapbox-gl.css">
        <link rel="stylesheet" href="front_page.css">      
        <script src="https://api.mapbox.com/mapbox-gl-js/v3.18.1/mapbox-gl.js"></script>
        <style>
        #map { position: relative; top: 0; bottom: 0; width: 40%; height: 40%; }
        </style>
    </head>

    <body>
        <nav class="navbar">
            <a href="front_page.html" class="nav-brand">Routler</a>
            <div class="nav-links">
                <a href="front_page.html" class="nav-item active">Home</a>
                <a href="create_route.html" class="nav-item">Create Route</a>
                <a href="profile.html" class="nav-item">Profile</a>
                <a href="login.html" class="nav-item">Logout</a>
            </div>
            <div class="user">Your username</div>
        </nav>

        <div class = "header">
            <h1> Routler </h1>
            <!-- <h2> <?php echo $_SESSION['username']?> </h2>
            <h2> <?php echo $_SESSION['user_id']?></h2> -->
        </div>
        
        <div class = "header">
            <h2> Recommended Routes placeholder </h2>
            <p> Recommended routes placeholder </p>
        </div>
        <div class = "map">
            <div id="map"></div>
            <script>
                mapboxgl.accessToken = 'pk.eyJ1IjoiZ2VvcmdlZmVpbnNvbiIsImEiOiJjbWxrd2NqcXowMTRrM2ZxeHgwNnJyYzRvIn0.MjrY8KmelJ7DsQpqkoM2RA';
                const map = new mapboxgl.Map({
                    container: 'map',
                    style: 'mapbox://styles/mapbox/streets-v12',
                    zoom: 14,
                    center: [-2.2339857548361524, 53.46761433245296]
                });

                map.addControl(new mapboxgl.NavigationControl());
                map.scrollZoom.disable();
            </script>
        </div>

    </body>
</html>
