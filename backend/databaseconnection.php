<?php 
$servername = "localhost";
$username = "root";
$dbname = "gantabyaeighth";

//create connection
$conn = new mysqli($servername, $username, "", $dbname);

//checking
if($conn->connect_error){
    die('connection failed ' .$conn->connect_error);
}
?>