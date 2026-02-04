<?php
session_start();
require_once '../db_config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user = $_POST['username'];
    $pass = $_POST['password'];

    // -------------------------------------------------------------
    // STEP 1: ลองค้นหาในตาราง `users` ก่อน
    // -------------------------------------------------------------
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
    $stmt->execute([$user]);
    $row = $stmt->fetch();

    if ($row && password_verify($pass, $row['password'])) {
        // เจอยูสเซอร์ในตาราง users
        $update_login = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
        $update_login->execute([$row['user_id']]);

        $_SESSION['user_id'] = $row['user_id'];
        $_SESSION['username'] = $row['username'];
        $_SESSION['full_name'] = $row['full_name'];
        $_SESSION['role_id'] = $row['role_id'];

        // ถ้าใน users table มีคน role_id 2 ก็ให้เป็น admin ได้เหมือนกัน
        if ($row['role_id'] == 2) {
            $_SESSION['is_admin'] = true;
            $_SESSION['admin_fullname'] = $row['full_name'];
            header("Location: admin_dashboard.php");
        } else {
            header("Location: home.php");
        }
        exit();

    } else {
        // -------------------------------------------------------------
        // STEP 2: ถ้าไม่เจอใน users ให้ลองค้นหาในตาราง `admins`
        // -------------------------------------------------------------
        // (เผื่อกรณีคุณสมัคร Admin ไว้ในตารางเก่า)
        try {
            $stmt_admin = $conn->prepare("SELECT * FROM admins WHERE username = ?");
            $stmt_admin->execute([$user]);
            $row_admin = $stmt_admin->fetch();

            if ($row_admin && password_verify($pass, $row_admin['password'])) {
                // เจอในตาราง admins -> บังคับให้เป็น Admin เลย
                $_SESSION['user_id'] = $row_admin['admin_id']; // ใช้ admin_id แทน user_id
                $_SESSION['username'] = $row_admin['username'];
                $_SESSION['full_name'] = $row_admin['full_name'];
                $_SESSION['role_id'] = 2; // Admin Role
                
                // ตัวแปรสำคัญสำหรับหน้า Dashboard
                $_SESSION['is_admin'] = true;
                $_SESSION['admin_fullname'] = $row_admin['full_name'];

                header("Location: admin_dashboard.php");
                exit();
            } else {
                // ไม่เจอทั้ง 2 ตาราง หรือรหัสผิด
                echo "<script>alert('ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง');</script>";
            }
        } catch (PDOException $e) {
            // กรณีไม่มีตาราง admins อยู่จริง
            echo "<script>alert('ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง');</script>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carbon Login - Street Style</title>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #a8c0ff 0%, #c2e9fb 50%, #a8c0ff 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        body::before {
            content: ''; 
            position: absolute; 
            top: 0; 
            left: 0; 
            right: 0; 
            bottom: 0;
            background-image: 
                repeating-linear-gradient(45deg, transparent, transparent 35px, rgba(102, 126, 234, 0.05) 35px, rgba(102, 126, 234, 0.05) 70px),
                repeating-linear-gradient(-45deg, transparent, transparent 35px, rgba(168, 192, 255, 0.05) 35px, rgba(168, 192, 255, 0.05) 70px);
            pointer-events: none;
        }
        .login-container {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            padding: 50px 40px;
            border-radius: 20px;
            box-shadow: 
                0 20px 60px rgba(0, 0, 0, 0.3), 
                inset 0 0 0 2px rgba(255, 255, 255, 0.2), 
                0 0 40px rgba(102, 126, 234, 0.2);
            max-width: 450px;
            width: 100%;
            position: relative;
            border: 3px solid transparent;
            background-clip: padding-box;
        }
        .login-container::before {
            content: ''; 
            position: absolute; 
            top: -3px; 
            left: -3px; 
            right: -3px; 
            bottom: -3px;
            background: linear-gradient(45deg, #667eea, #a8c0ff, #5fa3d0, #667eea);
            background-size: 300% 300%;
            border-radius: 20px; 
            z-index: -1; 
            opacity: 0.6;
            animation: gradientShift 6s ease infinite;
        }
        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        h2 {
            font-family: 'Bebas Neue', cursive; 
            font-size: 48px; 
            text-align: center; 
            margin-bottom: 10px;
            background: linear-gradient(45deg, #4c51bf, #2c5282, #2b6cb0);
            -webkit-background-clip: text; 
            -webkit-text-fill-color: transparent; 
            background-clip: text;
            letter-spacing: 3px; 
            text-shadow: 0 0 30px rgba(76, 81, 191, 0.3);
            filter: drop-shadow(0 2px 4px rgba(255, 255, 255, 0.3));
        }
        .subtitle { 
            text-align: center; 
            color: #2d3748; 
            font-size: 14px; 
            margin-bottom: 30px; 
            font-weight: 500; 
            letter-spacing: 2px; 
            text-transform: uppercase;
            text-shadow: 0 2px 4px rgba(255, 255, 255, 0.5);
        }
        .co2-icon { 
            text-align: center; 
            margin-bottom: 20px; 
            font-size: 60px; 
            filter: drop-shadow(0 0 20px rgba(102, 126, 234, 0.5)); 
            animation: float 3s ease-in-out infinite; 
        }
        form { margin-bottom: 30px; }
        .input-group { position: relative; margin-bottom: 25px; }
        input[type="text"], input[type="password"] {
            width: 100%; 
            padding: 15px 20px; 
            background: rgba(255, 255, 255, 0.9);
            border: 2px solid rgba(102, 126, 234, 0.4); 
            border-radius: 12px; 
            color: #2d3748; 
            font-size: 16px; 
            transition: all 0.3s ease; 
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
        }
        input:focus { 
            outline: none; 
            border-color: #5fa3d0; 
            background: rgba(255, 255, 255, 1); 
            box-shadow: 0 0 20px rgba(95, 163, 208, 0.4); 
            transform: translateY(-2px); 
        }
        input::placeholder { 
            color: #4a5568; 
            text-transform: uppercase; 
            font-size: 12px; 
            letter-spacing: 1px;
            font-weight: 500;
        }
        button[type="submit"] {
            width: 100%; 
            padding: 16px; 
            background: linear-gradient(135deg, #667eea 0%, #5fa3d0 100%);
            color: white; 
            border: none; 
            border-radius: 12px; 
            font-size: 18px; 
            font-weight: 700; 
            text-transform: uppercase; 
            letter-spacing: 2px;
            cursor: pointer; 
            transition: all 0.3s ease; 
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4); 
            position: relative; 
            overflow: hidden;
        }
        button[type="submit"]::before { 
            content: ''; 
            position: absolute; 
            top: 0; 
            left: -100%; 
            width: 100%; 
            height: 100%; 
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent); 
            transition: left 0.5s ease; 
        }
        button[type="submit"]:hover::before { left: 100%; }
        button[type="submit"]:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 12px 35px rgba(102, 126, 234, 0.6); 
        }
        button[type="submit"]:active { transform: translateY(-1px); }
        hr { 
            border: none; 
            border-top: 2px solid rgba(102, 126, 234, 0.3); 
            margin: 30px 0; 
        }
        .register-section { text-align: center; }
        .register-section p { 
            color: #2d3748; 
            font-size: 14px; 
            margin-bottom: 15px; 
            text-transform: uppercase; 
            letter-spacing: 1px;
            text-shadow: 0 2px 4px rgba(255, 255, 255, 0.5);
            font-weight: 500;
        }
        .btn-register {
            display: inline-block; 
            padding: 12px 30px; 
            background: transparent; 
            color: #2d3748; 
            text-decoration: none;
            border: 2px solid #4c51bf; 
            border-radius: 12px; 
            font-size: 14px; 
            font-weight: 600; 
            text-transform: uppercase; 
            letter-spacing: 2px;
            transition: all 0.3s ease; 
            position: relative; 
            overflow: hidden;
        }
        .btn-register::before { 
            content: ''; 
            position: absolute; 
            top: 50%; 
            left: 50%; 
            width: 0; 
            height: 0; 
            border-radius: 50%; 
            background: rgba(255, 255, 255, 0.2); 
            transform: translate(-50%, -50%); 
            transition: width 0.6s ease, height 0.6s ease; 
        }
        .btn-register:hover::before { width: 300px; height: 300px; }
        .btn-register:hover { 
            color: #fff; 
            border-color: #5fa3d0; 
            background: linear-gradient(135deg, #667eea 0%, #5fa3d0 100%);
            box-shadow: 0 0 25px rgba(95, 163, 208, 0.5); 
            transform: translateY(-2px); 
        }
        .btn-register span { position: relative; z-index: 1; }
        @keyframes float { 
            0%, 100% { transform: translateY(0px); } 
            50% { transform: translateY(-10px); } 
        }
        .bg-circles { 
            position: absolute; 
            width: 100%; 
            height: 100%; 
            overflow: hidden; 
            z-index: -1; 
        }
        .circle { 
            position: absolute; 
            border-radius: 50%; 
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1), transparent); 
            animation: pulse 4s ease-in-out infinite; 
        }
        .circle:nth-child(1) { 
            width: 400px; 
            height: 400px; 
            top: -200px; 
            left: -200px; 
            animation-delay: 0s; 
            background: radial-gradient(circle, rgba(102, 126, 234, 0.15), transparent);
        }
        .circle:nth-child(2) { 
            width: 300px; 
            height: 300px; 
            bottom: -150px; 
            right: -150px; 
            animation-delay: 2s; 
            background: radial-gradient(circle, rgba(95, 163, 208, 0.15), transparent);
        }
        .circle:nth-child(3) { 
            width: 250px; 
            height: 250px; 
            top: 50%; 
            left: 50%; 
            transform: translate(-50%, -50%);
            animation-delay: 1s; 
            background: radial-gradient(circle, rgba(168, 192, 255, 0.1), transparent);
        }
        @keyframes pulse { 
            0%, 100% { transform: scale(1); opacity: 0.3; } 
            50% { transform: scale(1.2); opacity: 0.5; } 
        }
    </style>
</head>
<body>
    <div class="bg-circles">
        <div class="circle"></div>
        <div class="circle"></div>
        <div class="circle"></div>
    </div>

    <div class="login-container">
        <div class="co2-icon">🌱</div>
        <h2>Carbon Footprint</h2>
        <div class="subtitle">Faculty of Business Administration RMUTP Login</div>

        <form method="POST">
            <div class="input-group">
                <input type="text" name="username" placeholder="Username" required>
            </div>
            <div class="input-group">
                <input type="password" name="password" placeholder="Password" required>
            </div>
            <button type="submit">Enter LOGIN</button>
        </form>

        <hr>

        <div class="register-section">
            <p>Create a new account</p>
            <a href="register.php" class="btn-register"><span>Register</span></a>
        </div>
    </div>
</body>
</html>