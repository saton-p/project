<?php
session_start();
require_once '../db_config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = $_POST['username'];
    $pass = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
    $stmt->execute([$user]);
    $row = $stmt->fetch();


    if ($row && password_verify($pass, $row['password'])) {
        // อัปเดตเวลาและเก็บ Session (เหมือนเดิม)
        $update_login = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
        $update_login->execute([$row['user_id']]);

        $_SESSION['user_id'] = $row['user_id'];
        $_SESSION['username'] = $row['username'];
        $_SESSION['full_name'] = $row['full_name'];
        $_SESSION['role_id'] = $row['role_id'];

        // --- เพิ่มเงื่อนไขเช็ค Role ตรงนี้ ---
        if ($row['role_id'] == 2) {
            header("Location: admin_dashboard.php"); // ถ้าเป็น Admin ไปหน้า Admin
        } else {
            header("Location: home.php"); // ถ้าเป็น User ทั่วไป ไปหน้า Home ปกติ
        }
        exit();

    } else {
// ... (ส่วน Error) ...
        echo "<script>alert('ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง หรือบัญชีถูกระงับ');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <style>
        /* ตกแต่งปุ่มสมัครสมาชิกเล็กน้อยให้ดูเป็นปุ่ม */
        .btn-register {
            display: inline-block;
            margin-top: 10px;
            padding: 5px 10px;
            background-color: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 14px;
        }
        .btn-register:hover {
            background-color: #5a6268;
        }
    </style>
</head>
<body>
    <h2>เข้าสู่ระบบ</h2>
    <form method="POST">
        <input type="text" name="username" placeholder="Username" required><br><br>
        <input type="password" name="password" placeholder="Password" required><br><br>
        <button type="submit">Login</button>
    </form>

    <hr>
    <p>ยังไม่มีบัญชีผู้ใช้งาน?</p>
    <a href="register.php" class="btn-register">สมัครสมาชิกที่นี่</a>
</body>
</html>