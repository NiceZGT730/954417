<?php
$servername = "localhost";
$username = "root";
$password = "!TP9G78ll210";
$dbname = "417db";


$conn = new mysqli($servername, $username, $password, $dbname);


if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
