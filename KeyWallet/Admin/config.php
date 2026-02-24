<?php
$host = "localhost";
$user = "root"; // your MySQL username
$pass = "";     // your MySQL password
$db   = "keyvault"; // your DB name

$conn = mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
    die("Database Connection Failed: " . mysqli_connect_error());
}
