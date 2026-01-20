<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once '../db_config.php';

$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>หน้าหลัก - Carbon Footprint System</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background-color: #f8f9fa; }
        
        /* สไตล์ Navbar */
        .navbar {
            background-color: #2c3e50;
            overflow: hidden;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 20px;
            color: white;
            height: 60px;
        }
        .navbar a {
            color: white;
            text-decoration: none;
            padding: 14px 20px;
            display: inline-block;
        }
        .navbar a:hover {
            background-color: #34495e;
        }
        .nav-links { display: flex; }
        .logout-btn { background-color: #e74c3c; border-radius: 4px; margin-left: 10px; }
        
        .container { padding: 30px; max-width: 1000px; margin: auto; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="logo">
        <strong>Carbon System</strong>
    </div>
    <div class="nav-links">
        <a href="home.php">หน้าหลัก</a>
        <a href="profile.php">โปรไฟล์ส่วนตัว</a> <a href="logout.php" class="logout-btn" onclick="return confirm('ยืนยันการออกจากระบบ?')">ออกจากระบบ</a>
    </div>
</nav>

<div class="container">
    <div class="card">
        <h1>ยินดีต้อนรับคุณ <?php echo htmlspecialchars($user['full_name']); ?></h1>
        <p>ขณะนี้คุณอยู่ในหน้าหลักของระบบคำนวณคาร์บอนฟุตพริ้นท์</p>
        <hr>
        <h4>ข้อมูลเบื้องต้น</h4>
        <p>แผนกของคุณ: <?php echo htmlspecialchars($user['dept_id']); ?></p>
        <p>สิทธิ์การใช้งาน: <?php echo htmlspecialchars($user['role_id']); ?></p>
    </div>
</div>

</body>
</html>