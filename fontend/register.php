<?php
// 1. เชื่อมต่อฐานข้อมูล
require_once '../db_config.php';

// ดึงข้อมูลแผนกมารอไว้ก่อน (เพื่อป้องกัน Error ใน HTML)
$departments = [];
try {
    $dept_stmt = $conn->query("SELECT * FROM departments");
    $departments = $dept_stmt->fetchAll();
} catch (PDOException $e) {
    // กรณีดึงข้อมูลไม่ได้ ให้ปล่อยเป็น array ว่าง
}

// 2. Logic การลงทะเบียน
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT); // เข้ารหัสรหัสผ่าน
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    
    // *** กำหนดค่า Role ID = 1 (User ทั่วไป) โดยอัตโนมัติ ***
    $role_id = 1; 
    
    $dept_id = $_POST['dept_id'];
    $status = 'active';

    try {
        // เช็คว่า Username ซ้ำไหม
        $check = $conn->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $check->execute([$username]);
        
        if ($check->fetchColumn() > 0) {
            echo "<script>alert('ชื่อผู้ใช้นี้ถูกใช้งานแล้ว กรุณาเลือกชื่ออื่น');</script>";
        } else {
            // บันทึกข้อมูล
            $sql = "INSERT INTO users (username, password, full_name, email, role_id, dept_id, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$username, $password, $full_name, $email, $role_id, $dept_id, $status]);
            
            echo "<script>alert('ลงทะเบียนสำเร็จ! เข้าสู่โซนของคุณได้เลย'); window.location='login.php';</script>";
        }
    } catch(PDOException $e) {
        echo "<script>alert('เกิดข้อผิดพลาด: " . $e->getMessage() . "');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carbon Register - Street Style</title>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #a8c0ff 0%, #c2e9fb 50%, #a8c0ff 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        /* พื้นหลังแบบ Pattern */
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

        /* กรอบหลัก */
        .register-container {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            padding: 50px 40px;
            border-radius: 20px;
            box-shadow: 
                0 20px 60px rgba(0, 0, 0, 0.3),
                inset 0 0 0 2px rgba(255, 255, 255, 0.2),
                0 0 40px rgba(102, 126, 234, 0.2);
            max-width: 500px;
            width: 100%;
            position: relative;
            border: 3px solid transparent;
            background-clip: padding-box;
        }

        .register-container::before {
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

        /* หัวข้อ */
        h2 {
            font-family: 'Bebas Neue', cursive;
            font-size: 48px;
            text-align: center;
            margin-bottom: 10px;
            background: linear-gradient(45deg, #4c51bf, #2c5282, #2b6cb0);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-transform: uppercase;
            letter-spacing: 3px;
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

        /* ไอคอน CO2 */
        .co2-icon {
            text-align: center;
            margin-bottom: 20px;
            font-size: 60px;
            filter: drop-shadow(0 0 20px rgba(102, 126, 234, 0.5));
            animation: float 3s ease-in-out infinite;
        }

        form {
            margin-bottom: 20px;
        }

        .input-group {
            position: relative;
            margin-bottom: 20px;
        }

        label {
            display: block;
            color: #2d3748;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
            text-shadow: 0 1px 2px rgba(255, 255, 255, 0.5);
        }

        input[type="text"],
        input[type="password"],
        input[type="email"],
        input[type="number"],
        select {
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

        select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%234c51bf' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
            padding-right: 40px;
        }

        select option {
            background: #ffffff;
            color: #2d3748;
            padding: 10px;
        }

        input[type="text"]:focus,
        input[type="password"]:focus,
        input[type="email"]:focus,
        input[type="number"]:focus,
        select:focus {
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

        /* ปุ่ม Register */
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
            margin-top: 10px;
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

        button[type="submit"]:hover::before {
            left: 100%;
        }

        button[type="submit"]:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(102, 126, 234, 0.6);
        }

        button[type="submit"]:active {
            transform: translateY(-1px);
        }

        /* ลิงก์กลับไป Login */
        .back-to-login {
            text-align: center;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 2px solid rgba(102, 126, 234, 0.3);
        }

        .back-to-login p {
            color: #2d3748;
            font-size: 13px;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 500;
            text-shadow: 0 1px 2px rgba(255, 255, 255, 0.5);
        }

        .back-to-login a {
            color: #4c51bf;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .back-to-login a:hover {
            color: #5fa3d0;
            transform: translateX(-5px);
        }

        /* แอนิเมชันลอย */
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        /* Responsive */
        @media (max-width: 480px) {
            .register-container {
                padding: 40px 30px;
            }

            h2 {
                font-size: 36px;
            }

            .co2-icon {
                font-size: 50px;
            }

            input[type="text"],
            input[type="password"],
            input[type="email"],
            input[type="number"],
            select {
                padding: 12px 15px;
                font-size: 14px;
            }
        }

        /* เอฟเฟกต์พื้นหลังเคลื่อนไหว */
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

        /* กำหนดสไตล์ input number ไม่ให้แสดงลูกศร */
        input[type="number"]::-webkit-inner-spin-button,
        input[type="number"]::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        input[type="number"] {
            -moz-appearance: textfield;
        }
    </style>
</head>
<body>
    <div class="bg-circles">
        <div class="circle"></div>
        <div class="circle"></div>
        <div class="circle"></div>
    </div>

    <div class="register-container">
        <div class="co2-icon">🌿</div>
        <h2>Register</h2>
        <div class="subtitle">Create a new account</div>

        <form method="POST">
            <div class="input-group">
                <input type="text" name="username" placeholder="Username" required>
            </div>

            <div class="input-group">
                <input type="password" name="password" placeholder="Password" required>
            </div>

            <div class="input-group">
                <input type="text" name="full_name" placeholder="ชื่อ-นามสกุล" required>
            </div>

            <div class="input-group">
                <input type="email" name="email" placeholder="อีเมล">
            </div>

            <div class="input-group">
                <label>เลือกแผนก:</label>
                <select name="dept_id" required>
                    <option value="" disabled selected>-- เลือกแผนก --</option>
                    <?php
                    // วนลูปแสดงรายชื่อแผนกจาก Database
                    if (count($departments) > 0) {
                        foreach ($departments as $dept) {
                            echo "<option value='{$dept['dept_id']}'>{$dept['dept_name']}</option>";
                        }
                    } else {
                        echo "<option value='' disabled>ไม่พบข้อมูลแผนก</option>";
                    }
                    ?>
                </select>
            </div>

            <button type="submit">Register Now</button>
        </form>

        <div class="back-to-login">
            <p>Have account?</p>
            <a href="login.php">← Return to Login</a>
        </div>
    </div>
</body>
</html>