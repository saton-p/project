<?php
session_start();
require_once '../db_config.php'; // ใช้การเชื่อมต่อใหม่

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = $_POST['username'];
    $pass = $_POST['password'];

    // เช็กข้อมูลจากตาราง admins
    $stmt = $conn->prepare("SELECT * FROM admins WHERE username = ?");
    $stmt->execute([$user]);
    $row = $stmt->fetch();

    if ($row && password_verify($pass, $row['password'])) {
        // เก็บ Session
        $_SESSION['admin_id'] = $row['admin_id']; // ใช้ admin_id แทน user_id
        $_SESSION['admin_username'] = $row['username'];
        $_SESSION['admin_fullname'] = $row['full_name'];
        $_SESSION['is_admin'] = true; // ตัวบ่งชี้ว่าเป็น Admin

        header("Location: admin_dashboard.php");
        exit();
    } else {
        echo "<script>alert('ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Admin Login</title>
    <style>
        body { font-family: sans-serif; background-color: #2c3e50; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .container { background: #fff; padding: 40px; border-radius: 8px; width: 350px; text-align: center; }
        h2 { color: #333; }
        input { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background-color: #c0392b; color: white; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .footer-link { margin-top: 20px; font-size: 13px; } .footer-link a { color: #555; text-decoration: none; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Admin Portal</h2>
        <form method="POST">
            <input type="text" name="username" placeholder="Admin Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">LOGIN</button>
        </form>
        <div class="footer-link">
            <a href="admin_register.php">สมัคร Admin ใหม่</a>
        </div>
    </div>
</body>
</html>