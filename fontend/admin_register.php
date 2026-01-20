<?php
// 1. เรียกใช้ไฟล์เชื่อมต่อหลักไฟล์เดิม (db_config.php)
require_once '../db_config.php'; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];

    try {
        // 2. ใช้ตาราง admins (ตรวจสอบว่าสร้างตารางนี้ในฐานข้อมูลหลักแล้ว)
        $sql = "INSERT INTO admins (username, password, full_name, email) VALUES (?, ?, ?, ?)";
        
        // 3. เปลี่ยนจาก $conn_admin เป็น $conn
        $stmt = $conn->prepare($sql);
        $stmt->execute([$username, $password, $full_name, $email]);
        
        echo "<script>alert('สร้างบัญชี Admin สำเร็จ!'); window.location='admin_login.php';</script>";
    } catch(PDOException $e) {
        echo "<script>alert('Error: " . $e->getMessage() . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Admin Register</title>
    <style>
        /* CSS เดิม */
        body { font-family: sans-serif; background-color: #2c3e50; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .container { background: #fff; padding: 40px; border-radius: 8px; width: 350px; }
        h2 { text-align: center; color: #c0392b; }
        input { width: 100%; padding: 12px; margin: 8px 0; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background-color: #c0392b; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; margin-top: 10px; }
        .link { text-align: center; margin-top: 15px; } .link a { color: #555; }
    </style>
</head>
<body>
    <div class="container">
        <h2>สร้างบัญชี Admin</h2>
        <form method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <input type="text" name="full_name" placeholder="ชื่อ-นามสกุล" required>
            <input type="email" name="email" placeholder="Email" required>
            <button type="submit">ยืนยันการสมัคร</button>
        </form>
        <div class="link"><a href="admin_login.php">กลับไปหน้า Login</a></div>
    </div>
</body>
</html>