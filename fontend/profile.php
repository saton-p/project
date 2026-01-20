<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once '../db_config.php';

$user_id = $_SESSION['user_id'];
$message = "";

// --- ส่วนประมวลผล PHP (ย้ายมาจาก edit_profile.php) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. แก้ไขข้อมูลส่วนตัว
    if (isset($_POST['update_profile'])) {
        $full_name = $_POST['full_name'];
        $email = $_POST['email'];
        try {
            $sql = "UPDATE users SET full_name = ?, email = ? WHERE user_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$full_name, $email, $user_id]);
            $_SESSION['full_name'] = $full_name;
            $message = "<script>alert('อัปเดตข้อมูลสำเร็จ');</script>";
        } catch(PDOException $e) {
            $message = "<script>alert('Error: " . $e->getMessage() . "');</script>";
        }
    }
    
    // 2. เปลี่ยนรหัสผ่าน
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
                $message = "<script>alert('เปลี่ยนรหัสผ่านสำเร็จ');</script>";
            } else {
                $message = "<script>alert('รหัสผ่านใหม่ไม่ตรงกัน');</script>";
            }
        } else {
            $message = "<script>alert('รหัสผ่านเดิมไม่ถูกต้อง');</script>";
        }
    }
    
    // รีเฟรชหน้าเพื่อเคลียร์ค่า POST (ป้องกันการกด F5 แล้วส่งซ้ำ)
    // echo "<meta http-equiv='refresh' content='0'>"; 
}

// ดึงข้อมูลล่าสุดมาแสดง
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>โปรไฟล์ส่วนตัว</title>
    <style>
        /* CSS หลัก */
        body { font-family: sans-serif; margin: 0; background-color: #f4f4f4; }
        .navbar { background-color: #2c3e50; overflow: hidden; display: flex; justify-content: space-between; align-items: center; padding: 0 20px; color: white; height: 60px; }
        .navbar a { color: white; text-decoration: none; padding: 14px 20px; display: inline-block; }
        .container { padding: 20px; max-width: 600px; margin: 30px auto; background: white; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .info-group { margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 5px; }
        label { font-weight: bold; color: #555; }

        /* --- CSS สำหรับ Pop-up (Modal) --- */
        .modal {
            display: none; /* ซ่อนไว้ก่อน */
            position: fixed; 
            z-index: 1000; 
            left: 0;
            top: 0;
            width: 100%; 
            height: 100%; 
            overflow: auto; 
            background-color: rgba(0,0,0,0.5); /* พื้นหลังสีดำจางๆ */
        }
        .modal-content {
            background-color: #fefefe;
            margin: 10% auto; /* จัดกึ่งกลางแนวตั้ง */
            padding: 20px;
            border: 1px solid #888;
            width: 80%;
            max-width: 500px; /* ความกว้างสูงสุด */
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            position: relative;
        }
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        .close:hover { color: black; }
        
        /* สไตล์ฟอร์มใน Modal */
        .form-group { margin-bottom: 15px; }
        .form-group input { width: 100%; padding: 8px; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; }
        .btn-submit { background-color: #27ae60; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; width: 100%; }
        .btn-submit:hover { background-color: #219150; }
        .tab-header { border-bottom: 1px solid #ddd; margin-bottom: 20px; padding-bottom: 10px; font-weight: bold; color: #2c3e50; }
    </style>
</head>
<body>
    <?php echo $message; ?>

    <nav class="navbar">
        <div class="logo"><strong>Carbon System</strong></div>
        <div class="nav-links">
            <a href="home.php">หน้าหลัก</a>
            <a href="profile.php">โปรไฟล์ส่วนตัว</a>
            <a href="logout.php">ออกจากระบบ</a>
        </div>
    </nav>

    <div class="container">
        <h2>โปรไฟล์ส่วนตัว</h2>
        <div class="info-group">
            <label>ชื่อ-นามสกุล:</label> <span><?php echo htmlspecialchars($user['full_name']); ?></span>
        </div>
        <div class="info-group">
            <label>Username:</label> <span><?php echo htmlspecialchars($user['username']); ?></span>
        </div>
        <div class="info-group">
            <label>อีเมล:</label> <span><?php echo htmlspecialchars($user['email']); ?></span>
        </div>
        <div class="info-group">
            <label>สถานะบัญชี:</label> <span><?php echo htmlspecialchars($user['status']); ?></span>
        </div>
        
        <br>
        
        <button onclick="openModal()" style="padding:10px 20px; background:#2980b9; color:white; border:none; border-radius:5px; cursor:pointer; font-size:16px;">
            แก้ไขโปรไฟล์และเปลี่ยนรหัสผ่าน
        </button>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal()">&times;</span>
            
            <h3 class="tab-header">แก้ไขข้อมูลส่วนตัว</h3>
            <form method="POST">
                <div class="form-group">
                    <label>ชื่อ-นามสกุล:</label>
                    <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label>อีเมล:</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>
                <button type="submit" name="update_profile" class="btn-submit">บันทึกข้อมูลทั่วไป</button>
            </form>

            <br><hr><br>

            <h3 class="tab-header">เปลี่ยนรหัสผ่าน</h3>
            <form method="POST">
                <div class="form-group">
                    <label>รหัสผ่านปัจจุบัน:</label>
                    <input type="password" name="old_password" required>
                </div>
                <div class="form-group">
                    <label>รหัสผ่านใหม่:</label>
                    <input type="password" name="new_password" required>
                </div>
                <div class="form-group">
                    <label>ยืนยันรหัสผ่านใหม่:</label>
                    <input type="password" name="confirm_password" required>
                </div>
                <button type="submit" name="change_password" class="btn-submit" style="background-color: #e67e22;">เปลี่ยนรหัสผ่าน</button>
            </form>
        </div>
    </div>

    <script>
        // ฟังก์ชันเปิด Modal
        function openModal() {
            document.getElementById("editModal").style.display = "block";
        }

        // ฟังก์ชันปิด Modal
        function closeModal() {
            document.getElementById("editModal").style.display = "none";
        }

        // ถ้าคลิกที่พื้นหลังสีดำ (นอกกล่อง) ให้ปิด Modal
        window.onclick = function(event) {
            var modal = document.getElementById("editModal");
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>
</body>
</html>