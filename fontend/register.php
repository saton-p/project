<?php
require_once '../db_config.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $role_id = $_POST['role_id'];
    $dept_id = $_POST['dept_id'];
    $status = 'active'; // กำหนดค่าเริ่มต้นเป็น active

    try {
        $sql = "INSERT INTO users (username, password, full_name, email, role_id, dept_id, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$username, $password, $full_name, $email, $role_id, $dept_id, $status]);
        echo "<script>alert('ลงทะเบียนสำเร็จ!'); window.location='login.php';</script>";
    } catch(PDOException $e) {
        echo "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head><meta charset="UTF-8"><title>Register</title></head>
<body>
    <h2>ลงทะเบียนผู้ใช้งานใหม่</h2>
    <form method="POST">
        <input type="text" name="username" placeholder="Username" required><br>
        <input type="password" name="password" placeholder="Password" required><br>
        <input type="text" name="full_name" placeholder="ชื่อ-นามสกุล" required><br>
        <input type="email" name="email" placeholder="อีเมล"><br>
        
        <label>บทบาท (Role ID):</label>
        <input type="number" name="role_id" value="1"><br>
        
        <label>เลือกแผนก:</label>
<select name="dept_id" required>
    <?php
    // ดึงรายชื่อแผนกทั้งหมดมาสร้างตัวเลือก
    $dept_stmt = $conn->query("SELECT * FROM departments");
    while ($dept = $dept_stmt->fetch()) {
        echo "<option value='{$dept['dept_id']}'>{$dept['dept_name']}</option>";
    }
    ?>
</select>
        
        <button type="submit">สมัครสมาชิก</button>
    </form>
</body>
</html>