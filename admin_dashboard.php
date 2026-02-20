<?php
session_start();

// --- 1. LOGIC: AUTHENTICATION & LOGOUT ---
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

// ตรวจสอบสิทธิ์ (ถ้าไม่มี Session ให้เด้งออก)
if (!isset($_SESSION['user_id'])) { 
    header("Location: login.php"); 
    exit(); 
}

require_once 'db_config.php'; 

// ดึงข้อมูล Admin ปัจจุบัน (เพื่อให้ชื่อตรงกับ Database ไม่ Error)
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$current_admin = $stmt->fetch();

// 3. กำหนดหน้าที่จะแสดง
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// ==========================================
// LOGIC: ส่วนการทำงานของแต่ละหน้า (PHP)
// ==========================================

// A. Logic: Dashboard
if ($page == 'dashboard') {
    $total_users = $conn->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $total_depts = $conn->query("SELECT COUNT(*) FROM departments")->fetchColumn();
    $total_factors = $conn->query("SELECT COUNT(*) FROM emission_factors")->fetchColumn();
}

// B. Logic: Users (จัดการผู้ใช้)
if ($page == 'users') {
    // Update User
    if (isset($_POST['update_user'])) {
        $edit_id = $_POST['edit_user_id'];
        $full_name = $_POST['edit_full_name'];
        $email = $_POST['edit_email'];
        $dept_id = $_POST['edit_dept_id'];
        $status = $_POST['edit_status'];
        $new_password = $_POST['edit_new_password'];

        try {
            if (!empty($new_password)) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $sql = "UPDATE users SET full_name=?, email=?, dept_id=?, status=?, password=? WHERE user_id=?";
                $conn->prepare($sql)->execute([$full_name, $email, $dept_id, $status, $hashed_password, $edit_id]);
            } else {
                $sql = "UPDATE users SET full_name=?, email=?, dept_id=?, status=? WHERE user_id=?";
                $conn->prepare($sql)->execute([$full_name, $email, $dept_id, $status, $edit_id]);
            }
            echo "<script>alert('แก้ไขข้อมูลผู้ใช้เรียบร้อย'); window.location='admin_dashboard.php?page=users';</script>";
        } catch (PDOException $e) { echo "<script>alert('Error');</script>"; }
    }

    // Delete User
    if (isset($_GET['delete_id'])) {
        $del_id = $_GET['delete_id'];
        if ($del_id != $_SESSION['user_id']) { 
            $conn->prepare("DELETE FROM users WHERE user_id = ?")->execute([$del_id]);
            echo "<script>alert('ลบข้อมูลเรียบร้อย'); window.location='admin_dashboard.php?page=users';</script>";
        }
    }

    $user_list = $conn->query("SELECT users.*, departments.dept_name FROM users LEFT JOIN departments ON users.dept_id = departments.dept_id ORDER BY users.user_id DESC")->fetchAll();
    $all_depts = $conn->query("SELECT * FROM departments")->fetchAll();
}

// C. Logic: Sources (จัดการค่าสัมประสิทธิ์)
if ($page == 'sources') {
    // Add Factor
    if (isset($_POST['add_factor'])) {
        $source_id = $_POST['source_id'];
        // ดึง scope_id จาก source ที่เลือก เพื่อบันทึกลง emission_factors ด้วย (ถ้ามีการอัปเดตตารางแล้ว)
        $src_info = $conn->query("SELECT scope_id FROM emission_sources WHERE source_id = '$source_id'")->fetch();
        $scope_id = $src_info['scope_id'] ?? 1;

        $sql = "INSERT INTO emission_factors (source_id, factor_name, factor_value, unit, scope_id) VALUES (?, ?, ?, ?, ?)";
        $conn->prepare($sql)->execute([$source_id, $_POST['factor_name'], $_POST['factor_value'], $_POST['unit'], $scope_id]);
        echo "<script>alert('เพิ่มข้อมูลเรียบร้อย'); window.location='admin_dashboard.php?page=sources';</script>";
    }

    // Update Factor
    if (isset($_POST['update_factor'])) {
        $src_info = $conn->query("SELECT scope_id FROM emission_sources WHERE source_id = '{$_POST['edit_source_id']}'")->fetch();
        $scope_id = $src_info['scope_id'] ?? 1;

        $sql = "UPDATE emission_factors SET source_id=?, factor_name=?, factor_value=?, unit=?, scope_id=? WHERE factor_id=?";
        $conn->prepare($sql)->execute([$_POST['edit_source_id'], $_POST['edit_factor_name'], $_POST['edit_factor_value'], $_POST['edit_unit'], $scope_id, $_POST['edit_factor_id']]);
        echo "<script>alert('แก้ไขข้อมูลเรียบร้อย'); window.location='admin_dashboard.php?page=sources';</script>";
    }

    // Delete Factor
    if (isset($_GET['delete_factor_id'])) {
        $conn->prepare("DELETE FROM emission_factors WHERE factor_id = ?")->execute([$_GET['delete_factor_id']]);
        echo "<script>alert('ลบข้อมูลเรียบร้อย'); window.location='admin_dashboard.php?page=sources';</script>";
    }

    // ดึงข้อมูล Sources สำหรับ Dropdown
    $sources_raw = $conn->query("SELECT * FROM emission_sources ORDER BY scope_id, source_id")->fetchAll();
    $scope_grouped_sources = [1 => [], 2 => [], 3 => []];
    foreach ($sources_raw as $src) {
        $scope_grouped_sources[$src['scope_id']][] = $src;
    }

    // ดึงข้อมูล Factors มาแสดง (ใช้ scope_id จากตาราง sources)
    $factors_raw = $conn->query("SELECT ef.*, es.source_name, es.scope_id 
                                 FROM emission_factors ef 
                                 LEFT JOIN emission_sources es ON ef.source_id = es.source_id 
                                 ORDER BY es.scope_id, es.source_id, ef.factor_id")->fetchAll();
    
    $scope_grouped_factors = [1 => [], 2 => [], 3 => []];
    foreach ($factors_raw as $row) {
        $scope_grouped_factors[$row['scope_id']][] = $row;
    }
}

// D. Logic: Organization & Depts
if ($page == 'depts') {
    // Org Info
    if (isset($_POST['update_org_info'])) {
        $check = $conn->query("SELECT COUNT(*) FROM organization_info")->fetchColumn();
        if ($check == 0) {
            $sql = "INSERT INTO organization_info (org_name, address, total_employees, fiscal_year_start) VALUES (?, ?, ?, ?)";
            $conn->prepare($sql)->execute([$_POST['org_name'], $_POST['address'], $_POST['total_employees'], $_POST['fiscal_year_start']]);
        } else {
            $sql = "UPDATE organization_info SET org_name=?, address=?, total_employees=?, fiscal_year_start=? WHERE org_id=1";
            $conn->prepare($sql)->execute([$_POST['org_name'], $_POST['address'], $_POST['total_employees'], $_POST['fiscal_year_start']]);
        }
        echo "<script>alert('บันทึกข้อมูลองค์กรเรียบร้อย'); window.location='admin_dashboard.php?page=depts';</script>";
    }

    // Active Activities
    if (isset($_POST['update_activities'])) {
        $conn->query("TRUNCATE TABLE org_active_activities");
        if (!empty($_POST['active_sources'])) {
            $stmt = $conn->prepare("INSERT INTO org_active_activities (source_id) VALUES (?)");
            foreach ($_POST['active_sources'] as $src_id) { $stmt->execute([$src_id]); }
        }
        echo "<script>alert('บันทึกประเภทกิจกรรมเรียบร้อย'); window.location='admin_dashboard.php?page=depts';</script>";
    }

    // Depts CRUD
    if (isset($_POST['add_dept'])) {
        if (!empty($_POST['dept_name'])) {
            $conn->prepare("INSERT INTO departments (dept_name) VALUES (?)")->execute([$_POST['dept_name']]);
            echo "<script>window.location='admin_dashboard.php?page=depts';</script>";
        }
    }
    if (isset($_POST['edit_dept'])) {
        $conn->prepare("UPDATE departments SET dept_name=? WHERE dept_id=?")->execute([$_POST['dept_name'], $_POST['dept_id']]);
        echo "<script>window.location='admin_dashboard.php?page=depts';</script>";
    }
    if (isset($_GET['del_dept_id'])) {
        $chk = $conn->prepare("SELECT COUNT(*) FROM users WHERE dept_id=?");
        $chk->execute([$_GET['del_dept_id']]);
        if ($chk->fetchColumn() > 0) { echo "<script>alert('ลบไม่ได้ มีผู้ใช้ในแผนกนี้'); window.location='admin_dashboard.php?page=depts';</script>"; } 
        else { $conn->prepare("DELETE FROM departments WHERE dept_id=?")->execute([$_GET['del_dept_id']]); echo "<script>window.location='admin_dashboard.php?page=depts';</script>"; }
    }

    $org_info = $conn->query("SELECT * FROM organization_info LIMIT 1")->fetch();
    if (!$org_info) $org_info = ['org_name'=>'', 'address'=>'', 'total_employees'=>0, 'fiscal_year_start'=>''];
    $dept_list = $conn->query("SELECT * FROM departments ORDER BY dept_id ASC")->fetchAll();
    $activity_list = $conn->query("SELECT s.source_id, s.source_name, (SELECT COUNT(*) FROM org_active_activities a WHERE a.source_id = s.source_id) as is_active FROM emission_sources s ORDER BY s.scope_id ASC, s.source_id ASC")->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        /* CSS หลัก */
        :root { --primary: #2c3e50; --accent: #3498db; --bg: #f4f6f9; --white: #fff; --text: #333; }
        body { font-family: 'Prompt', sans-serif; margin: 0; background: var(--bg); color: var(--text); display: flex; height: 100vh; overflow: hidden; }
        * { box-sizing: border-box; outline: none; } a { text-decoration: none; }

        /* Sidebar Styling */
        .sidebar { width: 260px; background: var(--primary); color: white; display: flex; flex-direction: column; z-index: 100; transition: 0.3s; box-shadow: 2px 0 10px rgba(0,0,0,0.1); }
        .sidebar-header { padding: 20px; text-align: center; background: rgba(0,0,0,0.2); font-size: 1.4em; font-weight: bold; display: flex; justify-content: space-between; align-items: center; }
        .user-profile { padding: 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); background: rgba(255,255,255,0.05); }
        .menu { list-style: none; padding: 0; margin: 0; overflow-y: auto; flex: 1; }
        .menu a { display: block; padding: 15px 20px; color: #ecf0f1; border-left: 4px solid transparent; transition: 0.2s; }
        .menu a:hover { background: rgba(255,255,255,0.1); border-left-color: var(--accent); }
        .menu a.active { background: rgba(0,0,0,0.3); border-left-color: #e74c3c; font-weight: 500; }
        
        /* Logout Button */
        .logout-wrap { padding: 15px; border-top: 1px solid rgba(255,255,255,0.1); }
        .btn-logout { display: block; width: 100%; padding: 12px; background: #c0392b; color: white; text-align: center; border-radius: 6px; transition: 0.2s; }
        .btn-logout:hover { background: #e74c3c; }

        /* Main Content */
        .main-content { flex: 1; padding: 25px; overflow-y: auto; }
        .header-title { font-size: 1.8em; margin-bottom: 20px; color: var(--primary); font-weight: 600; border-bottom: 2px solid #ddd; padding-bottom: 10px; }
        
        /* Elements */
        .card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); border-top: 4px solid var(--accent); margin-bottom: 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stats-grid .card h3 { margin: 0 0 10px; font-size: 0.9em; color: #777; text-transform: uppercase; }
        .stats-grid .card .number { font-size: 2.5em; font-weight: bold; color: var(--primary); }

        /* Table */
        .table-responsive { overflow-x: auto; background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        table { width: 100%; border-collapse: collapse; min-width: 600px; }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: var(--primary); color: white; font-weight: 500; }
        tr:hover { background: #f9f9f9; }

        /* Buttons & Badges */
        .btn { padding: 6px 12px; border-radius: 4px; border: none; cursor: pointer; color: white; font-size: 0.9em; display: inline-block; margin-right: 5px; }
        .btn-add { background: #27ae60; padding: 10px 20px; font-size: 1em; margin-bottom: 15px; }
        .btn-edit { background: #f39c12; } .btn-del { background: #c0392b; }
        .scope-label { display: inline-block; padding: 5px 10px; border-radius: 4px; color: white; font-size: 0.85em; font-weight: bold; margin-bottom: 10px; }
        .s1-bg { background: #e74c3c; } .s2-bg { background: #f39c12; } .s3-bg { background: #3498db; }
        
        /* Modal & Form */
        .modal { display: none; position: fixed; z-index: 999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(2px); }
        .modal-content { background: white; margin: 5% auto; padding: 25px; width: 90%; max-width: 500px; border-radius: 10px; animation: slideDown 0.3s; }
        @keyframes slideDown { from{transform:translateY(-20px);opacity:0} to{transform:translateY(0);opacity:1} }
        .close { float: right; font-size: 1.5em; cursor: pointer; color: #999; }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; }
        .form-group input, .form-group select, textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-family: 'Prompt'; }

        /* Mobile Responsive */
        .hamburger { display: none; background: none; border: none; color: white; font-size: 1.8em; cursor: pointer; }
        
        @media (max-width: 768px) {
            .sidebar { position: fixed; top: 0; left: 0; width: 100%; height: auto; max-height: 70px; overflow: hidden; }
            .sidebar.open { max-height: 100vh; overflow-y: auto; }
            .sidebar-header { height: 70px; padding: 0 20px; }
            .user-profile { display: none; }
            .hamburger { display: block; }
            .main-content { padding: 90px 15px 20px 15px; }
            .stats-grid { grid-template-columns: 1fr; }
            .modal-content { margin: 15% auto; width: 95%; }
            .menu { padding-top: 10px; }
            .menu a { border-bottom: 1px solid rgba(255,255,255,0.05); }
        }
    </style>
    <script>
        function toggleMenu() { document.querySelector('.sidebar').classList.toggle('open'); }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        window.onclick = e => { if(e.target.className === 'modal') e.target.style.display='none'; }
        
        function openEditUser(id, uname, fname, email, dept, status) {
            document.getElementById('e_uid').value=id; document.getElementById('e_uname').value=uname; 
            document.getElementById('e_fname').value=fname; document.getElementById('e_email').value=email;
            document.getElementById('e_dept').value=dept; document.getElementById('e_stat').value=status;
            document.getElementById('userModal').style.display='block';
        }
        function openEditFactor(id, sid, name, val, unit) {
            document.getElementById('f_id').value=id; document.getElementById('f_sid').value=sid;
            document.getElementById('f_name').value=name; document.getElementById('f_val').value=val;
            document.getElementById('f_unit').value=unit;
            document.getElementById('factorModal').style.display='block';
        }
        function openEditDept(id, name) {
            document.getElementById('d_id').value=id; document.getElementById('d_name').value=name;
            document.getElementById('deptModal').style.display='block';
        }
    </script>
</head>
<body>

    <nav class="sidebar">
        <div class="sidebar-header">
            <div>AdminPanel</div>
            <button class="hamburger" onclick="toggleMenu()">☰</button>
        </div>
        <div class="user-profile">
    <div style="font-size:2em;">🛡️</div>
    <div><?php echo htmlspecialchars($current_admin['full_name'] ?? 'Admin User'); ?></div>
</div>
        <ul class="menu">
            <li><a href="?page=dashboard" class="<?php echo $page=='dashboard'?'active':''; ?>">📊 ภาพรวมระบบ</a></li>
            <li><a href="?page=users" class="<?php echo $page=='users'?'active':''; ?>">👥 จัดการผู้ใช้</a></li>
            <li><a href="?page=depts" class="<?php echo $page=='depts'?'active':''; ?>">🏢 จัดการแผนก</a></li>
            <li><a href="?page=sources" class="<?php echo $page=='sources'?'active':''; ?>">🌱 แหล่งกำเนิด (Factor)</a></li>
        </ul>
        <div class="logout-wrap">
            <a href="?action=logout" class="btn-logout" onclick="return confirm('ยืนยันออกจากระบบ?')">ออกจากระบบ</a>
        </div>
    </nav>

    <main class="main-content">
        
        <?php if($page=='dashboard'): ?>
            <div class="header-title">ภาพรวมระบบ (Overview)</div>
            <div class="stats-grid">
                <div class="card" style="border-color:#3498db"><h3>ผู้ใช้งาน</h3><div class="number"><?php echo $total_users; ?></div></div>
                <div class="card" style="border-color:#e67e22"><h3>แผนก</h3><div class="number"><?php echo $total_depts; ?></div></div>
                <div class="card" style="border-color:#27ae60"><h3>รายการ Factor</h3><div class="number"><?php echo $total_factors; ?></div></div>
            </div>
        <?php endif; ?>

        <?php if($page=='users'): ?>
            <div class="header-title">จัดการผู้ใช้งาน</div>
            <div class="table-responsive">
                <table>
                    <thead><tr><th>ID</th><th>Username</th><th>ชื่อ-นามสกุล</th><th>แผนก</th><th>สถานะ</th><th style="text-align:center">จัดการ</th></tr></thead>
                    <tbody>
                        <?php foreach($user_list as $u): ?>
                        <tr>
                            <td><?php echo $u['user_id']; ?></td>
                            <td><?php echo htmlspecialchars($u['username']); ?></td>
                            <td><?php echo htmlspecialchars($u['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($u['dept_name']??'-'); ?></td>
                            <td style="text-align:center;">
                                <?php if($u['status'] == 'active'): ?>
                                <span style="background-color: #d1fae5; color: #065f46; padding: 5px 12px; border-radius: 20px; font-size: 0.85em; font-weight: 600; border: 1px solid #a7f3d0;">
                                    🟢 Active
                                </span>
                            <?php else: ?>
                                <span style="background-color: #fee2e2; color: #991b1b; padding: 5px 12px; border-radius: 20px; font-size: 0.85em; font-weight: 600; border: 1px solid #fecaca;">
                                    🔴 Inactive
                                </span>
                            <?php endif; ?>
                        </td>
                            <td style="text-align:center">
                                <button class="btn btn-edit" onclick="openEditUser('<?php echo $u['user_id']; ?>','<?php echo $u['username']; ?>','<?php echo $u['full_name']; ?>','<?php echo $u['email']; ?>','<?php echo $u['dept_id']; ?>','<?php echo $u['status']; ?>')">แก้ไข</button>
                                <a href="?page=users&delete_id=<?php echo $u['user_id']; ?>" class="btn btn-del" onclick="return confirm('ลบ?')">ลบ</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div id="userModal" class="modal"><div class="modal-content"><span class="close" onclick="closeModal('userModal')">&times;</span><h3>แก้ไขผู้ใช้</h3><form method="POST"><input type="hidden" name="edit_user_id" id="e_uid"><div class="form-group"><label>Username</label><input type="text" id="e_uname" disabled style="background:#eee"></div><div class="form-group"><label>ชื่อ-นามสกุล</label><input type="text" name="edit_full_name" id="e_fname" required></div><div class="form-group"><label>Email</label><input type="email" name="edit_email" id="e_email" required></div><div class="form-group"><label>แผนก</label><select name="edit_dept_id" id="e_dept"><?php foreach($all_depts as $d) echo "<option value='{$d['dept_id']}'>{$d['dept_name']}</option>"; ?></select></div><div class="form-group"><label>สถานะ</label><select name="edit_status" id="e_stat"><option value="active">Active</option><option value="inactive">Inactive</option></select></div><div class="form-group"><label style="color:red">เปลี่ยนรหัสผ่าน (ถ้ามี)</label><input type="password" name="edit_new_password" placeholder="ว่างไว้ถ้าไม่เปลี่ยน"></div><button type="submit" name="update_user" class="btn btn-add" style="width:100%">บันทึก</button></form></div></div>
        <?php endif; ?>

        <?php if($page=='depts'): ?>
            <div class="header-title">จัดการข้อมูลองค์กร</div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:20px;">
                <div class="card">
                    <h3>ข้อมูลองค์กร</h3>
                    <form method="POST">
                        <div class="form-group"><label>ชื่อองค์กร</label><input type="text" name="org_name" value="<?php echo htmlspecialchars($org_info['org_name']); ?>" required></div>
                        <div class="form-group"><label>ที่อยู่</label><textarea name="address" rows="2"><?php echo htmlspecialchars($org_info['address']); ?></textarea></div>
                        <div class="form-group"><label>จำนวนพนักงาน</label><input type="number" name="total_employees" value="<?php echo $org_info['total_employees']; ?>"></div>
                        <div class="form-group"><label>ปีงบประมาณ</label><input type="date" name="fiscal_year_start" value="<?php echo $org_info['fiscal_year_start']; ?>"></div>
                        <button type="submit" name="update_org_info" class="btn btn-add" style="width:100%">บันทึก</button>
                    </form>
                </div>
                <div class="card">
                    <h3>ขอบเขตกิจกรรมที่ใช้</h3>
                    <form method="POST">
                        <div style="max-height:250px; overflow-y:auto; border:1px solid #eee; padding:10px;">
                            <?php foreach($activity_list as $a): ?>
                                <div><label style="font-weight:normal"><input type="checkbox" name="active_sources[]" value="<?php echo $a['source_id']; ?>" <?php echo $a['is_active']?'checked':''; ?>> <?php echo htmlspecialchars($a['source_name']); ?></label></div>
                            <?php endforeach; ?>
                        </div>
                        <button type="submit" name="update_activities" class="btn btn-add" style="width:100%; margin-top:10px;">บันทึก</button>
                    </form>
                </div>
            </div>
            
            <div class="card">
                <h3>จัดการแผนก <button onclick="document.getElementById('addDeptModal').style.display='block'" class="btn btn-add" style="font-size:0.8em; float:right;">+ เพิ่มแผนก</button></h3>
                <<div class="table-responsive">
    <table>
        <thead>
            <tr>
                <th style="width:10%;">ID</th>
                <th>ชื่อแผนก</th>
                <th style="width:20%; text-align:center;">จัดการ</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($dept_list as $d): ?>
            <tr>
                <td><?php echo $d['dept_id']; ?></td>
                <td><?php echo htmlspecialchars($d['dept_name']); ?></td>
                <td style="text-align:center;">
                    <button type="button" class="btn btn-edit" 
                        onclick="openEditDept('<?php echo $d['dept_id']; ?>', '<?php echo htmlspecialchars($d['dept_name']); ?>')">
                        แก้ไข
                    </button>
                    <a href="?page=depts&del_dept_id=<?php echo $d['dept_id']; ?>" 
                       class="btn btn-del" 
                       onclick="return confirm('ยืนยันการลบแผนก?')">
                       ลบ
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
            </div>
            <div id="addDeptModal" class="modal"><div class="modal-content" style="width:300px;"><span class="close" onclick="closeModal('addDeptModal')">&times;</span><h3>เพิ่มแผนก</h3><form method="POST"><div class="form-group"><label>ชื่อแผนก</label><input type="text" name="dept_name" required></div><button type="submit" name="add_dept" class="btn btn-add" style="width:100%">บันทึก</button></form></div></div>
            <div id="deptModal" class="modal"><div class="modal-content" style="width:300px;"><span class="close" onclick="closeModal('deptModal')">&times;</span><h3>แก้ไขแผนก</h3><form method="POST"><input type="hidden" name="dept_id" id="d_id"><div class="form-group"><label>ชื่อแผนก</label><input type="text" name="dept_name" id="d_name" required></div><button type="submit" name="edit_dept" class="btn btn-add" style="width:100%">บันทึก</button></form></div></div>
        <?php endif; ?>

        <?php if($page=='sources'): ?>
            <div class="header-title">จัดการค่าสัมประสิทธิ์ (Factor)</div>
            <button onclick="document.getElementById('addFactorModal').style.display='block'" class="btn btn-add">+ เพิ่มรายการใหม่</button>
            <?php foreach([1,2,3] as $s_id): ?>
                <div style="background:white; padding:15px; border-radius:8px; margin-bottom:20px; box-shadow:0 2px 5px rgba(0,0,0,0.05);">
                    <div class="scope-label s<?php echo $s_id; ?>-bg">Scope <?php echo $s_id; ?></div>
                    <div class="table-responsive">
                        <table>
                            <thead><tr><th>หมวดหมู่</th><th>ชื่อรายการ</th><th>ค่า Factor</th><th>หน่วย</th><th>จัดการ</th></tr></thead>
                            <tbody>
                                <?php foreach($scope_grouped_factors[$s_id] as $f): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($f['source_name']); ?></td>
                                    <td><?php echo htmlspecialchars($f['factor_name']); ?></td>
                                    <td><b><?php echo $f['factor_value']; ?></b></td>
                                    <td><?php echo $f['unit']; ?></td>
                                    <td>
                                        <button class="btn btn-edit" onclick="openEditFactor('<?php echo $f['factor_id']; ?>','<?php echo $f['source_id']; ?>','<?php echo $f['factor_name']; ?>','<?php echo $f['factor_value']; ?>','<?php echo $f['unit']; ?>')">แก้ไข</button>
                                        <a href="?page=sources&delete_factor_id=<?php echo $f['factor_id']; ?>" class="btn btn-del" onclick="return confirm('ลบ?')">ลบ</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <div id="addFactorModal" class="modal"><div class="modal-content"><span class="close" onclick="closeModal('addFactorModal')">&times;</span><h3>เพิ่มรายการใหม่</h3><form method="POST"><div class="form-group"><label>หมวดหมู่</label><select name="source_id"><?php foreach($scope_grouped_sources as $sid=>$grp) { echo "<optgroup label='Scope $sid'>"; foreach($grp as $s) echo "<option value='{$s['source_id']}'>{$s['source_name']}</option>"; echo "</optgroup>"; } ?></select></div><div class="form-group"><label>ชื่อรายการ</label><input type="text" name="factor_name" required></div><div class="form-group"><label>ค่า Factor</label><input type="number" step="0.0001" name="factor_value" required></div><div class="form-group"><label>หน่วย</label><input type="text" name="unit" required></div><button type="submit" name="add_factor" class="btn btn-add" style="width:100%">บันทึก</button></form></div></div>
            <div id="factorModal" class="modal"><div class="modal-content"><span class="close" onclick="closeModal('factorModal')">&times;</span><h3>แก้ไขรายการ</h3><form method="POST"><input type="hidden" name="edit_factor_id" id="f_id"><div class="form-group"><label>หมวดหมู่</label><select name="edit_source_id" id="f_sid"><?php foreach($scope_grouped_sources as $sid=>$grp) { echo "<optgroup label='Scope $sid'>"; foreach($grp as $s) echo "<option value='{$s['source_id']}'>{$s['source_name']}</option>"; echo "</optgroup>"; } ?></select></div><div class="form-group"><label>ชื่อรายการ</label><input type="text" name="edit_factor_name" id="f_name" required></div><div class="form-group"><label>ค่า Factor</label><input type="number" step="0.0001" name="edit_factor_value" id="f_val" required></div><div class="form-group"><label>หน่วย</label><input type="text" name="edit_unit" id="f_unit" required></div><button type="submit" name="update_factor" class="btn btn-add" style="width:100%">บันทึก</button></form></div></div>
        <?php endif; ?>

    </main>
</body>
</html>