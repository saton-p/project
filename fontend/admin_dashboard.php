<?php
session_start();

// --- Logic สำหรับออกจากระบบ ---
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_unset();    // ล้างค่าตัวแปร Session ทั้งหมด
    session_destroy();  // ทำลาย Session
    header("Location: login.php"); // เด้งกลับไปหน้า Login
    exit();
}
// 1. ตรวจสอบสิทธิ์
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: login.php"); // เปลี่ยนตรงนี้
    exit();
}

// 2. เชื่อมต่อฐานข้อมูล
require_once '../db_config.php'; 

// 3. กำหนดหน้าที่จะแสดง (Default คือ dashboard)
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// ==========================================
// LOGIC: ส่วนการทำงานของแต่ละหน้า (PHP)
// ==========================================

// A. Logic: Dashboard (ภาพรวมระบบ)
if ($page == 'dashboard') {
    $stmt_users = $conn->query("SELECT COUNT(*) FROM users");
    $total_users = $stmt_users->fetchColumn();

    $stmt_depts = $conn->query("SELECT COUNT(*) FROM departments");
    $total_depts = $stmt_depts->fetchColumn();

    // นับจำนวน Factor ทั้งหมดที่มีในระบบ
    $stmt_src = $conn->query("SELECT COUNT(*) FROM emission_factors");
    $total_factors = $stmt_src->fetchColumn();
}

// B. Logic: Users (จัดการผู้ใช้)
if ($page == 'users') {
    
    // 1. [UPDATE] บันทึกข้อมูลแก้ไขจาก Modal
    if (isset($_POST['update_user'])) {
        $edit_id = $_POST['edit_user_id'];
        $full_name = $_POST['edit_full_name'];
        $email = $_POST['edit_email'];
        $dept_id = $_POST['edit_dept_id'];
        $status = $_POST['edit_status'];
        $new_password = $_POST['edit_new_password'];

        try {
            if (!empty($new_password)) {
                // กรณีเปลี่ยนรหัสผ่านด้วย
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET full_name=?, email=?, dept_id=?, status=?, password=? WHERE user_id=?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$full_name, $email, $dept_id, $status, $hashed_password, $edit_id]);
            } else {
                // กรณีไม่เปลี่ยนรหัสผ่าน
                $sql = "UPDATE users SET full_name=?, email=?, dept_id=?, status=? WHERE user_id=?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$full_name, $email, $dept_id, $status, $edit_id]);
            }
            echo "<script>alert('แก้ไขข้อมูลผู้ใช้เรียบร้อย'); window.location='admin_dashboard.php?page=users';</script>";
        } catch (PDOException $e) {
            echo "<script>alert('Error: " . $e->getMessage() . "');</script>";
        }
    }

    // 2. [DELETE] ลบผู้ใช้ (เหมือนเดิม)
    if (isset($_GET['delete_id'])) {
        $del_id = $_GET['delete_id'];
        if ($del_id != $_SESSION['user_id']) { 
            $del_stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $del_stmt->execute([$del_id]);
            echo "<script>alert('ลบข้อมูลเรียบร้อย'); window.location='admin_dashboard.php?page=users';</script>";
        }
    }

    // 3. ดึงรายชื่อผู้ใช้ทั้งหมด
    $sql = "SELECT users.*, departments.dept_name 
            FROM users 
            LEFT JOIN departments ON users.dept_id = departments.dept_id 
            WHERE users.role_id = 1 
            ORDER BY users.user_id DESC";
    $user_list = $conn->query($sql)->fetchAll();

    // 4. ดึงรายชื่อแผนกทั้งหมด (สำหรับใส่ Dropdown ใน Modal)
    $all_depts = $conn->query("SELECT * FROM departments")->fetchAll();
}

// C. Logic: Emission Sources & Factors (จัดการค่าสัมประสิทธิ์)
// C. [UPDATED] Logic: Emission Sources & Factors (จัดการค่าสัมประสิทธิ์)
if ($page == 'sources') {
    
    // Helper Function: ระบุ Scope จากชื่อ Source (ใช้เฉพาะหน้านี้)
    function getScopeFromSourceName($name) {
        if (strpos($name, 'ไฟฟ้า') !== false) return 2;
        if (strpos($name, 'น้ำมัน') !== false || strpos($name, 'เชื้อเพลิง') !== false || stripos($name, 'LPG') !== false) return 1;
        return 3;
    }

    // 1. [CREATE] เพิ่มข้อมูลใหม่
    if (isset($_POST['add_factor'])) {
        $source_id = $_POST['source_id'];
        $factor_name = $_POST['factor_name'];
        $factor_value = $_POST['factor_value'];
        $unit = $_POST['unit'];

        if (!empty($factor_name) && !empty($factor_value)) {
            $sql = "INSERT INTO emission_factors (source_id, factor_name, factor_value, unit) VALUES (?, ?, ?, ?)";
            $conn->prepare($sql)->execute([$source_id, $factor_name, $factor_value, $unit]);
            echo "<script>alert('เพิ่มข้อมูลเรียบร้อย'); window.location='admin_dashboard.php?page=sources';</script>";
        }
    }

    // 2. [UPDATE] แก้ไขข้อมูล
    if (isset($_POST['update_factor'])) {
        $edit_id = $_POST['edit_factor_id'];
        $edit_source_id = $_POST['edit_source_id'];
        $edit_name = $_POST['edit_factor_name'];
        $edit_value = $_POST['edit_factor_value'];
        $edit_unit = $_POST['edit_unit'];

        $sql = "UPDATE emission_factors SET source_id=?, factor_name=?, factor_value=?, unit=? WHERE factor_id=?";
        $conn->prepare($sql)->execute([$edit_source_id, $edit_name, $edit_value, $edit_unit, $edit_id]);
        echo "<script>alert('แก้ไขข้อมูลเรียบร้อย'); window.location='admin_dashboard.php?page=sources';</script>";
    }

    // 3. [DELETE] ลบข้อมูล
    if (isset($_GET['delete_factor_id'])) {
        $del_id = $_GET['delete_factor_id'];
        $conn->prepare("DELETE FROM emission_factors WHERE factor_id = ?")->execute([$del_id]);
        echo "<script>alert('ลบข้อมูลเรียบร้อย'); window.location='admin_dashboard.php?page=sources';</script>";
    }

    // 4. [READ] ดึงข้อมูลและจัดกลุ่มตาม Scope
    $factors_raw = $conn->query("SELECT ef.*, es.source_name FROM emission_factors ef LEFT JOIN emission_sources es ON ef.source_id = es.source_id ORDER BY es.source_id, ef.factor_id")->fetchAll();
    
    // Array เก็บข้อมูลแยกตาม Scope (1, 2, 3)
    $scope_grouped_factors = [1 => [], 2 => [], 3 => []];
    foreach ($factors_raw as $row) {
        $s_id = getScopeFromSourceName($row['source_name']);
        $scope_grouped_factors[$s_id][] = $row;
    }

    // ดึงตัวเลือก Source สำหรับ Dropdown (จัดกลุ่มด้วย)
    $sources_raw = $conn->query("SELECT * FROM emission_sources")->fetchAll();
    $scope_grouped_sources = [1 => [], 2 => [], 3 => []];
    foreach ($sources_raw as $src) {
        $s_id = getScopeFromSourceName($src['source_name']);
        $scope_grouped_sources[$s_id][] = $src;
    }
}

// D. Logic: Organization & Departments (จัดการข้อมูลองค์กร)
if ($page == 'depts') {
    
    // 1. จัดการข้อมูลพื้นฐานองค์กร (Basic Info)
    if (isset($_POST['update_org_info'])) {
        $org_name = $_POST['org_name'];
        $address = $_POST['address'];
        $employees = $_POST['total_employees'];
        $fiscal_date = $_POST['fiscal_year_start'];

        // ตรวจสอบว่ามีแถวแรกหรือยัง ถ้าไม่มีให้ Insert ถ้ามีให้ Update
        $check = $conn->query("SELECT COUNT(*) FROM organization_info")->fetchColumn();
        if ($check == 0) {
            $sql = "INSERT INTO organization_info (org_name, address, total_employees, fiscal_year_start) VALUES (?, ?, ?, ?)";
            $conn->prepare($sql)->execute([$org_name, $address, $employees, $fiscal_date]);
        } else {
            $sql = "UPDATE organization_info SET org_name=?, address=?, total_employees=?, fiscal_year_start=? WHERE org_id=1";
            $conn->prepare($sql)->execute([$org_name, $address, $employees, $fiscal_date]);
        }
        echo "<script>alert('บันทึกข้อมูลองค์กรเรียบร้อย'); window.location='admin_dashboard.php?page=depts';</script>";
    }

    // 2. จัดการกิจกรรมที่ใช้งาน (Active Activities)
    if (isset($_POST['update_activities'])) {
        // ลบค่าเดิมทั้งหมดก่อน
        $conn->query("TRUNCATE TABLE org_active_activities");
        
        // บันทึกค่าใหม่ที่ติ๊กเลือก
        if (!empty($_POST['active_sources'])) {
            $insert_sql = "INSERT INTO org_active_activities (source_id) VALUES (?)";
            $stmt = $conn->prepare($insert_sql);
            foreach ($_POST['active_sources'] as $src_id) {
                $stmt->execute([$src_id]);
            }
        }
        echo "<script>alert('บันทึกประเภทกิจกรรมเรียบร้อย'); window.location='admin_dashboard.php?page=depts';</script>";
    }

    // 3. จัดการแผนก (Departments CRUD)
    if (isset($_POST['add_dept'])) {
        $d_name = $_POST['dept_name'];
        if (!empty($d_name)) {
            $conn->prepare("INSERT INTO departments (dept_name) VALUES (?)")->execute([$d_name]);
            echo "<script>window.location='admin_dashboard.php?page=depts';</script>";
        }
    }
    if (isset($_POST['edit_dept'])) {
        $d_id = $_POST['dept_id'];
        $d_name = $_POST['dept_name'];
        $conn->prepare("UPDATE departments SET dept_name=? WHERE dept_id=?")->execute([$d_name, $d_id]);
        echo "<script>window.location='admin_dashboard.php?page=depts';</script>";
    }
    if (isset($_GET['del_dept_id'])) {
        $d_id = $_GET['del_dept_id'];
        // เช็คก่อนลบว่ามี user ใช้อยู่ไหม
        $chk = $conn->prepare("SELECT COUNT(*) FROM users WHERE dept_id=?");
        $chk->execute([$d_id]);
        if ($chk->fetchColumn() > 0) {
            echo "<script>alert('ไม่สามารถลบแผนกนี้ได้ เนื่องจากมีผู้ใช้งานอยู่ในแผนก'); window.location='admin_dashboard.php?page=depts';</script>";
        } else {
            $conn->prepare("DELETE FROM departments WHERE dept_id=?")->execute([$d_id]);
            echo "<script>alert('ลบแผนกเรียบร้อย'); window.location='admin_dashboard.php?page=depts';</script>";
        }
    }

    // --- ดึงข้อมูลมาแสดง ---
    // 1. ข้อมูลองค์กร
    $org_info = $conn->query("SELECT * FROM organization_info LIMIT 1")->fetch();
    if (!$org_info) {
        $org_info = ['org_name'=>'', 'address'=>'', 'total_employees'=>0, 'fiscal_year_start'=>''];
    }

    // 2. รายชื่อแผนก
    $dept_list = $conn->query("SELECT * FROM departments ORDER BY dept_id ASC")->fetchAll();

    // 3. กิจกรรมทั้งหมด + สถานะการใช้งาน
    // Logic: ดึง emission_sources ทั้งหมด แล้วเช็คว่า ID นั้นมีอยู่ใน org_active_activities หรือไม่
    $sql_act = "SELECT s.source_id, s.source_name, 
                (SELECT COUNT(*) FROM org_active_activities a WHERE a.source_id = s.source_id) as is_active 
                FROM emission_sources s ORDER BY s.source_id ASC";
    $activity_list = $conn->query($sql_act)->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <style>
        /* CSS หลัก */
        body { font-family: 'Segoe UI', sans-serif; margin: 0; background-color: #f4f6f9; display: flex; height: 100vh; overflow: hidden; }
        
        /* Sidebar */
        .sidebar { width: 250px; background-color: #2c3e50; color: white; display: flex; flex-direction: column; flex-shrink: 0; box-shadow: 2px 0 5px rgba(0,0,0,0.1); }
        .sidebar-header { padding: 20px; background-color: #1a252f; text-align: center; }
        .brand { font-size: 1.4em; font-weight: bold; display: block; }
        .admin-badge { background-color: #e74c3c; font-size: 0.7em; padding: 2px 8px; border-radius: 10px; vertical-align: middle; }
        .user-profile { padding: 15px; border-bottom: 1px solid #34495e; font-size: 0.9em; color: #bdc3c7; text-align: center; }
        
        .sidebar-menu { list-style: none; padding: 0; margin: 0; flex-grow: 1; }
        .sidebar-menu li a { display: block; padding: 15px 20px; color: #ecf0f1; text-decoration: none; border-left: 4px solid transparent; transition: all 0.3s; }
        .sidebar-menu li a:hover { background-color: #34495e; border-left-color: #3498db; }
        .sidebar-menu li a.active { background-color: #34495e; border-left-color: #e74c3c; }
        
        .logout-container { padding: 15px; }
        .btn-logout { display: block; width: 100%; padding: 10px 0; background-color: #e74c3c; color: white; text-align: center; text-decoration: none; border-radius: 4px; }
        
        /* Main Content */
        .main-content { flex-grow: 1; padding: 30px; overflow-y: auto; }
        .page-header { margin-bottom: 30px; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
        
        /* Stats Card (Dashboard) */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); text-align: center; border-top: 4px solid #3498db; }
        .card h3 { margin: 0 0 10px; color: #666; font-size: 0.9em; text-transform: uppercase; }
        .card .number { font-size: 2.5em; font-weight: bold; color: #333; }
        
        /* Table Styles */
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #34495e; color: white; text-transform: uppercase; font-size: 0.85em; letter-spacing: 0.5px; }
        tr:hover { background-color: #f1f1f1; }
        
        /* Buttons & Status */
        .btn-action { padding: 5px 10px; border-radius: 4px; text-decoration: none; color: white; font-size: 0.8em; margin-right: 5px; display: inline-block; cursor: pointer; }
        .btn-edit { background-color: #f39c12; }
        .btn-del { background-color: #e74c3c; }
        .btn-results { background-color: #3498db; }
        .btn-add { background-color: #27ae60; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        
        .status-active { color: #27ae60; background: #eafaf1; padding: 2px 8px; border-radius: 12px; font-size: 0.8em; font-weight: bold; }
        .status-inactive { color: #c0392b; background: #fdedec; padding: 2px 8px; border-radius: 12px; font-size: 0.8em; font-weight: bold; }

        /* Modal (Pop-up) Styles */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fefefe; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 450px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.2); animation: fadeIn 0.3s; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        .close { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { color: black; }
        
        /* Form in Modal */
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
        .form-group input, .form-group select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        .modal-footer { margin-top: 20px; text-align: right; }
    </style>
</head>
<body>

    <nav class="sidebar">
        <div class="sidebar-header">
            <span class="brand">Admin Portal</span>
            <span class="admin-badge">Super Admin</span>
        </div>
        <div class="user-profile">
            <div style="font-size: 2em; margin-bottom: 5px;">👤</div>
            สวัสดี, <?php echo htmlspecialchars($_SESSION['admin_fullname']); ?>
        </div>
        <ul class="sidebar-menu">
            <li><a href="admin_dashboard.php?page=dashboard" class="<?php echo ($page == 'dashboard') ? 'active' : ''; ?>">📊 ภาพรวมระบบ</a></li>
            <li><a href="admin_dashboard.php?page=users" class="<?php echo ($page == 'users') ? 'active' : ''; ?>">👥 จัดการผู้ใช้งาน</a></li>
            <li><a href="admin_dashboard.php?page=depts" class="<?php echo ($page == 'depts') ? 'active' : ''; ?>">🏢 จัดการแผนก</a></li>
            <li><a href="admin_dashboard.php?page=sources" class="<?php echo ($page == 'sources') ? 'active' : ''; ?>">🌱 แหล่งกำเนิดคาร์บอน</a></li>
        </ul>
        <div class="logout-container">
<a href="admin_dashboard.php?action=logout" class="btn-logout" onclick="return confirm('ยืนยันการออกจากระบบ?')">ออกจากระบบ</a>        </div>
    </nav>

    <main class="main-content">

        <?php if ($page == 'dashboard'): ?>
            <div class="page-header">
                <h2>Dashboard Overview</h2>
            </div>
            
            <div class="stats-grid">
                <div class="card">
                    <h3>ผู้ใช้งานทั้งหมด</h3>
                    <div class="number"><?php echo number_format($total_users); ?></div>
                    <div style="font-size: 0.8em; color: #888;">Active Users</div>
                </div>
                
                <div class="card" style="border-top-color: #e67e22;">
                    <h3>แผนกในองค์กร</h3>
                    <div class="number"><?php echo number_format($total_depts); ?></div>
                    <div style="font-size: 0.8em; color: #888;">Departments</div>
                </div>

                <div class="card" style="border-top-color: #2ecc71;">
                    <h3>ค่าสัมประสิทธิ์ (รายการ)</h3>
                    <div class="number"><?php echo number_format($total_factors); ?></div>
                    <div style="font-size: 0.8em; color: #888;">Emission Factors</div>
                </div>
            </div>
            
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                <h3>ยินดีต้อนรับสู่ระบบจัดการ</h3>
                <p>กรุณาเลือกเมนูทางซ้ายมือเพื่อเริ่มจัดการข้อมูลต่างๆ</p>
            </div>
        <?php endif; ?>


       <?php if ($page == 'users'): ?>
    <div class="page-header">
        <h2>จัดการรายชื่อผู้ใช้งาน (User Management)</h2>
    </div>

    <?php if (count($user_list) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>ชื่อ-นามสกุล</th>
                    <th>แผนก</th>
                    <th>สถานะ</th>
                    <th style="text-align:center;">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($user_list as $user): ?>
                    <tr>
                        <td><?php echo $user['user_id']; ?></td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($user['dept_name'] ?? '-'); ?></td>
                        <td>
                            <?php echo ($user['status'] == 'active') 
                                ? '<span class="status-active">Active</span>' 
                                : '<span class="status-inactive">Inactive</span>'; ?>
                        </td>
                        <td style="text-align:center;">
                            
                            
                            <button type="button" class="btn-action btn-edit" style="border:none;"
                                onclick="openEditUserModal(
                                    '<?php echo $user['user_id']; ?>',
                                    '<?php echo htmlspecialchars($user['username']); ?>',
                                    '<?php echo htmlspecialchars($user['full_name']); ?>',
                                    '<?php echo htmlspecialchars($user['email']); ?>',
                                    '<?php echo $user['dept_id']; ?>',
                                    '<?php echo $user['status']; ?>'
                                )">
                                แก้ไข
                            </button>
                            
                            <a href="admin_dashboard.php?page=users&delete_id=<?php echo $user['user_id']; ?>" 
                               class="btn-action btn-del" onclick="return confirm('ยืนยันลบ?')">ลบ</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div style="text-align:center; padding:20px;">ไม่พบข้อมูลผู้ใช้งาน</div>
    <?php endif; ?>

    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editUserModal')">&times;</span>
            <h3>✏️ แก้ไขข้อมูลผู้ใช้</h3>
            <form method="POST">
                <input type="hidden" id="edit_user_id" name="edit_user_id">
                
                <div class="form-group">
                    <label>Username (แก้ไขไม่ได้):</label>
                    <input type="text" id="edit_username" disabled style="background:#eee; color:#555;">
                </div>

                <div class="form-group">
                    <label>ชื่อ-นามสกุล:</label>
                    <input type="text" id="edit_full_name" name="edit_full_name" required>
                </div>

                <div class="form-group">
                    <label>อีเมล:</label>
                    <input type="email" id="edit_email" name="edit_email" required>
                </div>

                <div class="form-group">
                    <label>แผนก:</label>
                    <select id="edit_dept_id" name="edit_dept_id" style="width:100%; padding:10px;">
                        <?php foreach ($all_depts as $dept): ?>
                            <option value="<?php echo $dept['dept_id']; ?>">
                                <?php echo htmlspecialchars($dept['dept_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>สถานะ:</label>
                    <select id="edit_status" name="edit_status" style="width:100%; padding:10px;">
                        <option value="active">Active (ใช้งานปกติ)</option>
                        <option value="inactive">Inactive (ระงับการใช้งาน)</option>
                    </select>
                </div>

                <div class="form-group" style="border-top:1px dashed #ccc; padding-top:10px; margin-top:15px;">
                    <label style="color:#e74c3c;">รีเซ็ตรหัสผ่านใหม่ (Admin Reset):</label>
                    <input type="password" name="edit_new_password" placeholder="ปล่อยว่างไว้ถ้าไม่ต้องการเปลี่ยน">
                    <small style="color:#888;">* กรอกเฉพาะเมื่อต้องการตั้งรหัสผ่านใหม่ให้ผู้ใช้</small>
                </div>

                <div class="modal-footer">
                    <button type="submit" name="update_user" class="btn-action" style="background:#27ae60; border:none; width:100%;">บันทึกการเปลี่ยนแปลง</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditUserModal(id, username, fullname, email, deptId, status) {
            document.getElementById('edit_user_id').value = id;
            document.getElementById('edit_username').value = username;
            document.getElementById('edit_full_name').value = fullname;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_dept_id').value = deptId;
            document.getElementById('edit_status').value = status;
            
            document.getElementById('editUserModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // ปิดเมื่อคลิกพื้นหลัง
        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = "none";
            }
        }
    </script>
<?php endif; ?>


        <?php if ($page == 'depts'): ?>
    <div class="page-header">
        <h2>จัดการข้อมูลพื้นฐานองค์กร</h2>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        
        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">🏢 ข้อมูลขององค์กร</h3>
            <form method="POST">
                <div class="form-group">
                    <label>ชื่อองค์กร :</label>
                    <input type="text" name="org_name" value="<?php echo htmlspecialchars($org_info['org_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label>ที่อยู่ / ข้อมูลอาคาร :</label>
                    <textarea name="address" rows="3" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:4px; box-sizing:border-box;"><?php echo htmlspecialchars($org_info['address']); ?></textarea>
                </div>
                <div class="form-group">
                    <label>จำนวนพนักงาน (คน) :</label>
                    <input type="number" name="total_employees" value="<?php echo $org_info['total_employees']; ?>" required>
                </div>
                <div class="form-group">
                    <label>วันเริ่มปีงบประมาณ :</label>
                    <input type="date" name="fiscal_year_start" value="<?php echo $org_info['fiscal_year_start']; ?>">
                </div>
                <button type="submit" name="update_org_info" class="btn-action" style="background:#2c3e50; border:none; width:100%; padding:10px;">บันทึกข้อมูลองค์กร</button>
            </form>
        </div>

        <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px;">🌱 ประเภทกิจกรรมที่ใช้งานจริง</h3>
            <p style="font-size:0.9em; color:#666;">เลือกติ๊กถูกเฉพาะกิจกรรมที่มีการปล่อยคาร์บอนในองค์กรของท่าน</p>
            
            <form method="POST">
                <div style="max-height: 250px; overflow-y: auto; border: 1px solid #eee; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
                    <?php foreach ($activity_list as $act): ?>
                        <div style="margin-bottom: 8px;">
                            <label style="font-weight: normal; cursor: pointer; display: flex; align-items: center;">
                                <input type="checkbox" name="active_sources[]" value="<?php echo $act['source_id']; ?>" 
                                    <?php echo ($act['is_active'] > 0) ? 'checked' : ''; ?> 
                                    style="width:auto; margin-right: 10px;">
                                <?php echo htmlspecialchars($act['source_name']); ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="submit" name="update_activities" class="btn-action" style="background:#27ae60; border:none; width:100%; padding:10px;">บันทึกขอบเขตกิจกรรม</button>
            </form>
        </div>

    </div>

    <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); margin-top: 20px;">
        <h3 style="margin-top:0; border-bottom:1px solid #eee; padding-bottom:10px; display:flex; justify-content:space-between; align-items:center;">
            👥 โครงสร้างหน่วยงาน (แผนก/ฝ่าย)
            <button onclick="document.getElementById('addDeptModal').style.display='block'" class="btn-add" style="font-size:0.8em;">+ เพิ่มแผนก</button>
        </h3>

        <table style="margin-top:10px;">
            <thead>
                <tr>
                    <th style="width:10%;">ID</th>
                    <th>ชื่อแผนก</th>
                    <th style="width:20%; text-align:center;">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($dept_list) > 0): ?>
                    <?php foreach ($dept_list as $dept): ?>
                        <tr>
                            <td><?php echo $dept['dept_id']; ?></td>
                            <td><?php echo htmlspecialchars($dept['dept_name']); ?></td>
                            <td style="text-align:center;">
                                <button type="button" class="btn-action btn-edit" style="border:none;"
                                    onclick="openEditDeptModal('<?php echo $dept['dept_id']; ?>', '<?php echo htmlspecialchars($dept['dept_name']); ?>')">
                                    แก้ไข
                                </button>
                                <a href="admin_dashboard.php?page=depts&del_dept_id=<?php echo $dept['dept_id']; ?>" 
                                   class="btn-action btn-del" onclick="return confirm('ยืนยันการลบแผนก?')">ลบ</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3" style="text-align:center; padding:20px; color:#aaa;">ยังไม่มีข้อมูลแผนก</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div id="addDeptModal" class="modal">
        <div class="modal-content" style="width:350px;">
            <span class="close" onclick="document.getElementById('addDeptModal').style.display='none'">&times;</span>
            <h3>เพิ่มแผนกใหม่</h3>
            <form method="POST">
                <div class="form-group">
                    <label>ชื่อแผนก:</label>
                    <input type="text" name="dept_name" required>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="add_dept" class="btn-action" style="background:#27ae60; border:none; width:100%;">เพิ่มแผนก</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editDeptModal" class="modal">
        <div class="modal-content" style="width:350px;">
            <span class="close" onclick="document.getElementById('editDeptModal').style.display='none'">&times;</span>
            <h3>แก้ไขชื่อแผนก</h3>
            <form method="POST">
                <input type="hidden" id="edit_dept_id" name="dept_id">
                <div class="form-group">
                    <label>ชื่อแผนก:</label>
                    <input type="text" id="edit_dept_name" name="dept_name" required>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="edit_dept" class="btn-action" style="background:#f39c12; border:none; width:100%;">บันทึก</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openEditDeptModal(id, name) {
            document.getElementById('edit_dept_id').value = id;
            document.getElementById('edit_dept_name').value = name;
            document.getElementById('editDeptModal').style.display = 'block';
        }
    </script>

<?php endif; ?>


        <?php if ($page == 'sources'): ?>
    <div class="page-header">
        <h2>จัดการค่าสัมประสิทธิ์</h2>
    </div>
    
    <div style="margin-bottom: 20px;">
        <button onclick="openAddModal()" class="btn-add">
            + เพิ่มรายการใหม่
        </button>
    </div>

    <?php foreach([1, 2, 3] as $s_id): ?>
        <div class="card" style="padding: 15px; margin-bottom: 25px;">
            <div class="scope-header">
                
                <span class="scope-badge s<?php echo $s_id; ?>-bg" style="color: white;">Scope <?php echo $s_id; ?></span>
                <h3 style="margin:0; font-size:1.1em; color:#444;">
                    <?php echo ($s_id==1) ? 'การปล่อยก๊าซเรือนกระจกทางตรง (Direct Emissions)' : (($s_id==2) ? 'การปล่อยก๊าซเรือนกระจกทางอ้อม (Indirect Emissions)' : 'การปล่อยก๊าซเรือนกระจกทางอ้อมอื่นๆ (Other Indirect Emissions)'); ?>
                </h3>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th style="width:10%;">ID</th>
                        <th style="width:20%;">หมวดหมู่ (Source)</th>
                        <th style="width:30%;">ชื่อรายการ (Factor Name)</th>
                        <th style="width:20%; text-align: right;">ค่าสัมประสิทธิ์</th>
                        <th style="width:10%;">หน่วย</th>
                        <th style="width:10%; text-align: center;">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($scope_grouped_factors[$s_id]) > 0): ?>
                        <?php foreach ($scope_grouped_factors[$s_id] as $row): ?>
                            <tr>
                                <td><?php echo $row['factor_id']; ?></td>
                                <td>
                                    <span style="background:#f0f2f5; padding:2px 8px; border-radius:4px; font-size:0.9em; color:#555;">
                                        <?php echo htmlspecialchars($row['source_name'] ?? 'N/A'); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($row['factor_name']); ?></td>
                                <td style="text-align: right; color: #2980b9; font-weight: bold;">
                                    <?php echo number_format($row['factor_value'], 4); ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['unit']); ?></td>
                                <td style="text-align: center;">
                                    <button type="button" class="btn-action btn-edit" style="border:none;"
                                        onclick="openEditModal(
                                            '<?php echo $row['factor_id']; ?>',
                                            '<?php echo $row['source_id']; ?>',
                                            '<?php echo htmlspecialchars($row['factor_name']); ?>',
                                            '<?php echo $row['factor_value']; ?>',
                                            '<?php echo htmlspecialchars($row['unit']); ?>'
                                        )">
                                        แก้ไข
                                    </button>
                                    <a href="admin_dashboard.php?page=sources&delete_factor_id=<?php echo $row['factor_id']; ?>" 
                                       class="btn-action btn-del"
                                       onclick="return confirm('ยืนยันที่จะลบรายการนี้?')">ลบ</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align:center; padding:20px; color:#999;">-- ไม่มีข้อมูลใน Scope นี้ --</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endforeach; ?>

    <div id="addModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('addModal')">&times;</span>
            <h3>เพิ่มข้อมูลใหม่</h3>
            <form method="POST">
                <div class="form-group">
                    <label>เลือกหมวดหมู่ (Source):</label>
                    <select name="source_id" required>
                        <option value="">-- กรุณาเลือก --</option>
                        <?php foreach([1, 2, 3] as $s_id): ?>
                            <optgroup label="Scope <?php echo $s_id; ?>">
                                <?php foreach ($scope_grouped_sources[$s_id] as $src): ?>
                                    <option value="<?php echo $src['source_id']; ?>">
                                        <?php echo $src['source_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>ชื่อรายการ (Factor Name):</label>
                    <input type="text" name="factor_name" placeholder="เช่น Diesel, LPG" required>
                </div>
                <div class="form-group">
                    <label>ค่าสัมประสิทธิ์ (Factor Value):</label>
                    <input type="number" step="0.0001" name="factor_value" required>
                </div>
                <div class="form-group">
                    <label>หน่วย (Unit):</label>
                    <input type="text" name="unit" placeholder="เช่น kgCO2e/Litre" required>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="add_factor" class="btn-action btn-add" style="width:100%;">บันทึก</button>
                </div>
            </form>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editModal')">&times;</span>
            <h3>แก้ไขข้อมูล</h3>
            <form method="POST">
                <input type="hidden" id="edit_factor_id" name="edit_factor_id">
                
                <div class="form-group">
                    <label>หมวดหมู่ (Source):</label>
                    <select id="edit_source_id" name="edit_source_id" required>
                        <?php foreach([1, 2, 3] as $s_id): ?>
                            <optgroup label="Scope <?php echo $s_id; ?>">
                                <?php foreach ($scope_grouped_sources[$s_id] as $src): ?>
                                    <option value="<?php echo $src['source_id']; ?>">
                                        <?php echo $src['source_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>ชื่อรายการ:</label>
                    <input type="text" id="edit_factor_name" name="edit_factor_name" required>
                </div>
                <div class="form-group">
                    <label>ค่าสัมประสิทธิ์:</label>
                    <input type="number" step="0.0001" id="edit_factor_value" name="edit_factor_value" required>
                </div>
                <div class="form-group">
                    <label>หน่วย:</label>
                    <input type="text" id="edit_unit" name="edit_unit" required>
                </div>
                <div class="modal-footer">
                    <button type="submit" name="update_factor" class="btn-action btn-edit" style="width:100%; border:none;">บันทึกแก้ไข</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addModal').style.display = 'block';
        }

        function openEditModal(id, sourceId, name, value, unit) {
            document.getElementById('edit_factor_id').value = id;
            document.getElementById('edit_source_id').value = sourceId;
            document.getElementById('edit_factor_name').value = name;
            document.getElementById('edit_factor_value').value = value;
            document.getElementById('edit_unit').value = unit;
            document.getElementById('editModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target.className === 'modal') {
                event.target.style.display = "none";
            }
        }
    </script>
<?php endif; ?>

    </main>
</body>
</html>