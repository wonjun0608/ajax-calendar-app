<?php
$host = 'localhost';
$user = 'wustl_inst';                  
$pass = 'wustl_pass';             
$db   = 'calendar_db';                      



$mysqli = new mysqli($host, $user, $pass, $db);


// Check for connection error
if ($mysqli->connect_errno) {
    printf("Database Connection Failed: %s\n", $mysqli->connect_error);
    exit;
}



?>