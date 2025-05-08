<?php

$hostName = "localhost";
$dbUser = "root";
$dbPassword = "";
$dbName = "final_sysarch_project";

try {
    $conn = new PDO("mysql:host=$hostName;dbname=$dbName", $dbUser, $dbPassword);
    // Set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>