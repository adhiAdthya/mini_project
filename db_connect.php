<?php
$servername = "127.0.0.1";
$username   = "root";
$password   = "";
$dbname     = "garage_management_system";

// Create connection (default port 3306)
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>
