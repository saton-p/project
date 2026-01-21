<?php
session_start();
// 1. ตรวจสอบ Session
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once '../db_config.php';

$user_id = $_SESSION['user_id'];
$message = "";

// 2. ส่วนประมวลผล PHP (Logic เดิม)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 2.1 แก้ไขข้อมูลส่วนตัว
    if (isset($_POST['update_profile'])) {
        $full_name = $_POST['full_name'];
        $email = $_POST['email'];
        try {
            $sql = "UPDATE users SET full_name = ?, email = ? WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$full_name, $email, $user_id]);
            
            // อัปเดต Session ให้แสดงชื่อใหม่ทันที
            $_SESSION['full_name'] = $full_name;
            $message = "<div class='alert success'>✅ อัปเดตข้อมูลสำเร็จ</div>";
        } catch(PDOException $e) {
            $message = "<div class='alert error'>❌ Error: " . $e->getMessage() . "</div>";
        }
    }
    
    // 2.2 เปลี่ยนรหัสผ่าน
    if (isset($_POST['change_password'])) {
        $old_pass = $_POST['old_password'];
        $new_pass = $_POST['new_password'];
        $confirm_pass = $_POST['confirm_password'];

        $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $row = $stmt->fetch();

        if (password_verify($old_pass, $row['password'])) {
            if ($new_pass === $confirm_pass) {
                $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
                $update_pw = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
                $update_pw->execute([$hashed_pass, $user_id]);
                $message = "<div class='alert success'>✅ เปลี่ยนรหัสผ่านสำเร็จ</div>";
            } else {
                $message = "<div class='alert error'>⚠️ รหัสผ่านใหม่ไม่ตรงกัน</div>";
            }
        } else {
            $message = "<div class='alert error'>⚠️ รหัสผ่านเดิมไม่ถูกต้อง</div>";
        }
    }
}

// 3. ดึงข้อมูลล่าสุดมาแสดง
$stmt = $conn->prepare("SELECT users.*, departments.dept_name 
                        FROM users 
                        LEFT JOIN departments ON users.dept_id = departments.dept_id 
                        WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>โปรไฟล์ส่วนตัว - Carbon System</title>
    <style>
        /* CSS หลัก (Theme เดียวกับ Home/History) */
        body { font-family: 'Segoe UI', sans-serif; margin: 0; background-color: #f4f6f9; display: flex; height: 100vh; overflow: hidden; }
        
        /* Sidebar */
        .sidebar { width: 250px; background-color: #117a65; color: white; display: flex; flex-direction: column; flex-shrink: 0; box-shadow: 2px 0 5px rgba(0,0,0,0.1); }
        .sidebar-header { padding: 20px; background-color: #0e6251; text-align: center; font-weight: bold; font-size: 1.2em; }
        .user-profile { padding: 15px; border-bottom: 1px solid #148f77; font-size: 0.9em; color: #a2d9ce; text-align: center; }
        .sidebar-menu { list-style: none; padding: 0; margin: 0; flex-grow: 1; }
        .sidebar-menu li a { display: block; padding: 15px 20px; color: white; text-decoration: none; border-left: 4px solid transparent; transition: 0.3s; }
        .sidebar-menu li a:hover { background-color: #0e6251; border-left-color: #f1c40f; }
        .sidebar-menu li a.active { background-color: #0e6251; border-left-color: #f1c40f; font-weight: bold; }
        .logout-container { padding: 15px; }
        .btn-logout { display: block; width: 100%; padding: 10px 0; background-color: #e74c3c; color: white; text-align: center; text-decoration: none; border-radius: 4px; }
        
        /* Main Content */
        .main-content { flex-grow: 1; padding: 30px; overflow-y: auto; }
        .container { max-width: 1000px; margin: 0 auto; }
        
        /* Grid Layout สำหรับหน้า Profile */
        .profile-grid { display: grid; grid-template-columns: 1fr 2fr; gap: 20px; }
        
        /* Card Styles */
        .card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .card h3 { margin-top: 0; border-bottom: 1px solid #eee; padding-bottom: 15px; color: #2c3e50; font-size: 1.1em; }
        
        /* Profile Info (Left Side) */
        .profile-info-row { margin-bottom: 15px; }
        .profile-info-row label { display: block; font-weight: bold; color: #888; font-size: 0.85em; margin-bottom: 3px; }
        .profile-info-row span { font-size: 1.1em; color: #333; font-weight: 500; }
        .avatar-placeholder { width: 80px; height: 80px; background: #e8f6f3; color: #117a65; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.5em; margin: 0 auto 20px auto; }
        
        /* Forms */
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; font-size: 0.9em; }
        .form-group input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .form-group input:disabled { background-color: #f9f9f9; color: #999; cursor: not-allowed; }
        
        .btn-submit { background-color: #117a65; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; width: 100%; font-weight: bold; }
        .btn-submit:hover { background-color: #0e6251; }
        .btn-warning { background-color: #e67e22; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; width: 100%; font-weight: bold; }
        .btn-warning:hover { background-color: #d35400; }

        /* Alert */
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; text-align: center; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        /* Responsive: มือถือให้เรียงแนวตั้ง */
        @media (max-width: 768px) {
            .profile-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <nav class="sidebar">
        <div class="sidebar-header">Carbon System</div>
        <div class="user-profile">
            <div style="font-size: 2em; margin-bottom: 5px;">👤</div>
            <?php echo htmlspecialchars($user['full_name']); ?>
        </div>
        <ul class="sidebar-menu">
            <li><a href="home.php">📝 บันทึกข้อมูล</a></li>
            <li><a href="history.php">📊 ประวัติ</a></li>
            <li><a href="profile.php" class="active">⚙️ โปรไฟล์</a></li>
        </ul>
        <div class="logout-container">
            <a href="logout.php" class="btn-logout" onclick="return confirm('ยืนยันการออกจากระบบ?')">ออกจากระบบ</a>
        </div>
    </nav>

    <main class="main-content">
        <div class="container">
            
            <h2 style="color: #333;">⚙️ จัดการข้อมูลส่วนตัว</h2>
            
            <?php echo $message; ?>

            <div class="profile-grid">
                
                <div class="card" style="text-align: center; height: fit-content;">
                    <div class="avatar-placeholder">👤</div>
                    <h3 style="border-bottom:none; margin-bottom:5px;">
                        <?php echo htmlspecialchars($user['full_name']); ?>
                    </h3>
                    <p style="color:#666; margin-top:0;">@<?php echo htmlspecialchars($user['username']); ?></p>
                    
                    <hr style="border:0; border-top:1px solid #eee; margin: 20px 0;">

                    <div style="text-align: left;">
                        <div class="profile-info-row">
                            <label>สถานะบัญชี:</label>
                            <span style="color: #27ae60; font-weight:bold;"><?php echo htmlspecialchars($user['status']); ?></span>
                        </div>
                        <div class="profile-info-row">
                            <label>แผนก:</label>
                            <span><?php echo htmlspecialchars($user['dept_name'] ?? '-'); ?></span>
                        </div>
                        <div class="profile-info-row">
                            <label>อีเมล:</label>
                            <span><?php echo htmlspecialchars($user['email']); ?></span>
                        </div>
                        
                    </div>
                </div>

                <div class="card">
                    <h3>✏️ แก้ไขข้อมูลทั่วไป</h3>
                    <form method="POST">
                        <div class="form-group">
                            <label>ชื่อผู้ใช้งาน (Username):</label>
                            <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                            <small style="color:#999;">*Username ไม่สามารถเปลี่ยนได้</small>
                        </div>
                        <div class="form-group">
                            <label>ชื่อ-นามสกุล:</label>
                            <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>อีเมล:</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <button type="submit" name="update_profile" class="btn-submit">💾 บันทึกการเปลี่ยนแปลง</button>
                    </form>

                    <br><br>

                    <h3 style="color:#e67e22; border-bottom: 1px solid #fad7a0;">🔒 เปลี่ยนรหัสผ่าน</h3>
                    <form method="POST">
                        <div class="form-group">
                            <label>รหัสผ่านเดิม:</label>
                            <input type="password" name="old_password" placeholder="กรอกรหัสผ่านปัจจุบัน" required>
                        </div>
                        <div class="form-group">
                            <label>รหัสผ่านใหม่:</label>
                            <input type="password" name="new_password" placeholder="ตั้งรหัสผ่านใหม่" required>
                        </div>
                        <div class="form-group">
                            <label>ยืนยันรหัสผ่านใหม่:</label>
                            <input type="password" name="confirm_password" placeholder="กรอกรหัสผ่านใหม่ซ้ำอีกครั้ง" required>
                        </div>
                        <button type="submit" name="change_password" class="btn-warning">🔑 ยืนยันเปลี่ยนรหัสผ่าน</button>
                    </form>
                </div>

            </div>
        </div>
    </main>

</body>
</html>