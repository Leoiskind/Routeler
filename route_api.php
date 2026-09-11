<?php
    session_start();
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
    include 'db.php';
    
    header("Content-Type: application/json ");
    $method = $_SERVER['REQUEST_METHOD'];
    $result = "";

    echo 'accessed page <br>';


    
    switch ($method){
        case 'GET':
            echo 'got <br>';
            if(isset($_GET['route_id'])){
                $route_id = $_GET['route_id'];
                $query = 'select * from route where route_id = :route_id';
                $stmt = $conn->prepare($query);
                $route_info = $stmt->execute();

                $query='select longitude,latitude from place where route_id=:route_id order by place_index asc';
                $stmt = $conn->prepare($query);
                $stmt->bindParam('route_id',$route_id);
                $stmt->execute();
                $_SESSION['route_info'] = $route_info;
                $route_latlong = array();
                while($latlong = $stmt->fetch()){
                    $route_latlong[] = $latlong;
                }
                $_SESSION["route_latlong"] = $route_latlong;
                $url = "Location: https://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
                $url = substr($url,0,strlen($url)-13);
                $url .= "view_route.php";
                header($url);
            }
            else{
                $query = 'select * from route';
                $stmt = $conn->prepare($query);
                $stmt->execute();
                $index = 0;
                $allroutes = array();
                while($route_data=$stmt->fetch()){
                    $query='select longitude,latitude from place where route_id=:route_id order by place_index asc';
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam('route_id',$route_data['route_id']);
                    $stmt->execute();
                    $full_route = array();
                    while($data = $stmt->fetch()){
                        $full_route[] = $data;
                    }
                    $allroutes[] = array($route_data,$full_route);
                    $index++;
                }
                $_SESSION['all_routes'] = $allroutes;
            }
            break;
        case 'POST':
            echo 'posted <br>';
            echo "\n";
            if(isset($_POST['name']) && isset($_POST['description']) && isset($_POST['coordinates'])){
                echo $_POST["coordinates"];
                echo "\n";
                echo gettype($_POST["coordinates"]);
                echo "\n";
                $name = $_POST['name'];
                $description = $_POST['description'];
                $route_coordinates = json_decode($_POST['coordinates']);
                echo gettype($route_coordinates);
                $user_id = $_SESSION['user_id'];
                $query = 'insert into route (user_id,name,description) values(:user_id,:name,:description)';
                $stmt = $conn->prepare($query);
                $stmt -> bindParam('user_id',$user_id);
                $stmt -> bindParam('name',$name);
                $stmt -> bindParam('description',$description);
                $result =  $stmt -> execute();
                if($result){
                    $last_id = $conn->lastInsertId();
                    $index = 0;
                    foreach($route_coordinates as $value){
                        $query = 'insert into place (route_id,latitude,longitude,place_index) values(:route_id,:latitude,:longitude,:index)';
                        $stmt = $conn->prepare($query);
                        $stmt -> bindParam('route_id',$last_id);
                        $stmt -> bindParam('latitude',$value[0]);
                        $stmt -> bindParam('longitude',$value[1]);
                        $stmt -> bindParam('index',$index);
                        $stmt-> execute();
                        $index++;
                    }
                }
                echo 'ran <br>';
            }
            break;
    } 
    $url = "Location: https://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
    $url = substr($url,0,strlen($url)-13);
    $url .= "front_page.php";
    header($url);
?>