<?php
// config/db_config.php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "carbon_footprint_db";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    // ตั้งค่า Error Mode
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("การเชื่อมต่อฐานข้อมูลผิดพลาด: " . $e->getMessage());
}
?>