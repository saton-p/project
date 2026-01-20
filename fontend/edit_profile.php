<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once '../db_config.php';

$user_id = $_SESSION['user_id'];
$message = "";

// --- 1. ส่วนของการแก้ไขข้อมูลโปรไฟล์ ---
if (isset($_POST['update_profile'])) {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];

    try {
        $sql = "UPDATE users SET full_name = ?, email = ? WHERE user_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$full_name, $email, $user_id]);
        $_SESSION['full_name'] = $full_name; // อัปเดตชื่อใน Session ด้วย
        $message = "<div class='alert success'>อัปเดตข้อมูลโปรไฟล์เรียบร้อยแล้ว</div>";
    } catch(PDOException $e) {
        $message = "<div class='alert error'>เกิดข้อผิดพลาด: " . $e->getMessage() . "</div>";
    }
}

// --- 2. ส่วนของการเปลี่ยนรหัสผ่าน ---
if (isset($_POST['change_password'])) {
    $old_pass = $_POST['old_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    // ดึงรหัสผ่านเดิมจาก DB มาเช็ก
    $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();

    if (password_verify($old_pass, $user['password'])) {
        if ($new_pass === $confirm_pass) {
            $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
            $update_pw = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
            $update_pw->execute([$hashed_pass, $user_id]);
            $message = "<div class='alert success'>เปลี่ยนรหัสผ่านสำเร็จ!</div>";
        } else {
            $message = "<div class='alert error'>รหัสผ่านใหม่และยืนยันรหัสผ่านไม่ตรงกัน</div>";
        }
    } else {
        $message = "<div class='alert error'>รหัสผ่านปัจจุบันไม่ถูกต้อง</div>";
    }
}

// ดึงข้อมูลล่าสุดมาแสดงในฟอร์ม
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user_data = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แก้ไขโปรไฟล์ - Carbon System</title>
    <style>
        body { font-family: sans-serif; margin: 0; background-color: #f4f7f6; }
        .navbar { background-color: #2c3e50; display: flex; justify-content: space-between; padding: 0 20px; color: white; height: 60px; align-items: center; }
        .navbar a { color: white; text-decoration: none; padding: 14px 20px; }
        .container { max-width: 600px; margin: 30px auto; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .form-section { margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #eee; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input { width: 100%; padding: 10px; margin-bottom: 15px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { background-color: #27ae60; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; }
        button.pw-btn { background-color: #2980b9; }
        .alert { padding: 10px; margin-bottom: 20px; border-radius: 4px; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>

<nav class="navbar">
    <div class="logo"><strong>Carbon System</strong></div>
    <div class="nav-links">
        <a href="home.php">หน้าหลัก</a>
        <a href="profile.php">โปรไฟล์</a>
        <a href="logout.php">ออกจากระบบ</a>
    </div>
</nav>

<div class="container">
    <h2>ตั้งค่าบัญชีผู้ใช้</h2>
    
    <?php echo $message; ?>

    <div class="form-section">
        <h3>แก้ไขข้อมูลส่วนตัว</h3>
        <form method="POST">
            <label>ชื่อ-นามสกุล:</label>
            <input type="text" name="full_name" value="<?php echo htmlspecialchars($user_data['full_name']); ?>" required>
            
            <label>อีเมล:</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($user_data['email']); ?>" required>
            
            <button type="submit" name="update_profile">บันทึกการเปลี่ยนแปลง</button>
        </form>
    </div>

    <div class="form-section">
        <h3>เปลี่ยนรหัสผ่าน</h3>
        <form method="POST">
            <label>รหัสผ่านปัจจุบัน:</label>
            <input type="password" name="old_password" required>
            
            <label>รหัสผ่านใหม่:</label>
            <input type="password" name="new_password" required>
            
            <label>ยืนยันรหัสผ่านใหม่:</label>
            <input type="password" name="confirm_password" required>
            
            <button type="submit" name="change_password" class="pw-btn">อัปเดตรหัสผ่าน</button>
        </form>
    </div>
</div>

</body>
</html>