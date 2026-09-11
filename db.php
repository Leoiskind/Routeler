<?php
    $database_host = "dbhost.cs.man.ac.uk";
    $database_user = "b44939bn";
    $database_pass = "h8xgeE33zWmnkaIEEv+2lXkCQjbbllk3jFAgMRUQfsE";
    $database_name = "2025_comp10120_cm14";

    $conn = new PDO("mysql:host=$database_host;dbname=$database_name", $database_user, $database_pass);
  
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
?>