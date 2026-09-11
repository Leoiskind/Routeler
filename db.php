<?php
    $database_host = "***REMOVED***";
    $database_user = "***REMOVED***";
    $database_pass = "***REMOVED***";
    $database_name = "***REMOVED***";

    $conn = new PDO("mysql:host=$database_host;dbname=$database_name", $database_user, $database_pass);
  
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
?>