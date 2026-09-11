<?php
    session_start();

    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
    
    include 'db.php';

    

    header("Content-Type: application/json ");
    $method = $_SERVER['REQUEST_METHOD'];
    $result = "";

   
    switch ($method){
        case 'GET':
            if(isset($_SESSION['user_id'])){
                $user_id = $_SESSION['user_id'];
                $query = 'select external_username from user where user_id = :user_id';
                $stmt = $conn -> prepare($query);
                $stmt -> bindParam('user_id',$user_id);
                $stmt -> execute();
                $_SESSION['external_username'] = $stmt->fetch();
            }
            else{
                $username = $_SESSION['username'];
                $query = 'select user_id from user where internal_username = :username';
                $stmt = $conn -> prepare($query);
                $stmt -> bindParam('username',$username);
                $stmt -> execute();
                $_SESSION['user_id'] = $stmt->fetch();
            }
        case 'POST':
            if(isset($_SESSION['username']) && isset($_SESSION['fullname'])){
                $username = $_SESSION['username'];
                $fullname = explode(" ",$_SESSION['fullname']);
                $query = 'insert into user (internal_username,external_username,first_name,last_name) values(:username,:username,:first_name,:last_name)';
                $stmt = $conn -> prepare($query);
                $stmt -> bindParam('username',$username);
                $stmt -> bindParam('first_name',$fullname[0]);
                $stmt -> bindParam('last_name',$fullname[1]);
                $result -> $stmt->execute();
                $_SESSION['user_id'] = $conn->lastInsertId();
            }
    }
    


?>