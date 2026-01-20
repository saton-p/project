<?php
session_start();

// 1. ตรวจสอบสิทธิ์
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header("Location: admin_login.php");
    exit();
}

// 2. เชื่อมต่อฐานข้อมูล
require_once '../db_config.php'; 

// 3. กำหนดหน้าที่จะแสดง (Default คือ dashboard)
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

// --- LOGIC: ส่วนการทำงานของแต่ละหน้า ---

// A. Logic สำหรับหน้า Dashboard (ดึงสถิติ)
if ($page == 'dashboard') {
    $stmt_users = $conn->query("SELECT COUNT(*) FROM users");
    $total_users = $stmt_users->fetchColumn();

    $stmt_depts = $conn->query("SELECT COUNT(*) FROM departments");
    $total_depts = $stmt_depts->fetchColumn();
}

// B. Logic สำหรับหน้า Users (จัดการผู้ใช้)
if ($page == 'users') {
    // ฟังก์ชันลบผู้ใช้
    if (isset($_GET['delete_id'])) {
        $del_id = $_GET['delete_id'];
        if ($del_id != $_SESSION['user_id']) { // ป้องกันลบตัวเอง (ถ้ามี id ใน session)
            $del_stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
            $del_stmt->execute([$del_id]);
            echo "<script>alert('ลบข้อมูลเรียบร้อย'); window.location='admin_dashboard.php?page=users';</script>";
        }
    }

    // ดึงข้อมูล User มาแสดง
    $sql = "SELECT users.*, departments.dept_name 
            FROM users 
            LEFT JOIN departments ON users.dept_id = departments.dept_id
            WHERE users.role_id = 1 
            ORDER BY users.user_id DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $user_list = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <style>
        /* --- CSS เดิม (Layout) --- */
        body { font-family: 'Segoe UI', sans-serif; margin: 0; background-color: #f4f6f9; display: flex; height: 100vh; overflow: hidden; }
        .sidebar { width: 250px; background-color: #2c3e50; color: white; display: flex; flex-direction: column; flex-shrink: 0; box-shadow: 2px 0 5px rgba(0,0,0,0.1); }
        .sidebar-header { padding: 20px; background-color: #1a252f; text-align: center; }
        .brand { font-size: 1.4em; font-weight: bold; display: block; }
        .admin-badge { background-color: #e74c3c; font-size: 0.7em; padding: 2px 8px; border-radius: 10px; vertical-align: middle; }
        .user-profile { padding: 15px; border-bottom: 1px solid #34495e; font-size: 0.9em; color: #bdc3c7; text-align: center; }
        .sidebar-menu { list-style: none; padding: 0; margin: 0; flex-grow: 1; }
        .sidebar-menu li a { display: block; padding: 15px 20px; color: #ecf0f1; text-decoration: none; border-left: 4px solid transparent; transition: all 0.3s; }
        .sidebar-menu li a:hover { background-color: #34495e; border-left-color: #3498db; }
        
        /* Active State */
        .sidebar-menu li a.active { background-color: #34495e; border-left-color: #e74c3c; }

        .logout-container { padding: 15px; }
        .btn-logout { display: block; width: 100%; padding: 10px 0; background-color: #e74c3c; color: white; text-align: center; text-decoration: none; border-radius: 4px; }
        .btn-logout:hover { background-color: #c0392b; }

        .main-content { flex-grow: 1; padding: 30px; overflow-y: auto; }
        .page-header { margin-bottom: 30px; border-bottom: 1px solid #ddd; padding-bottom: 10px; }

        /* --- CSS สำหรับ Dashboard Cards --- */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); text-align: center; border-top: 4px solid #3498db; }
        .card h3 { margin: 0 0 10px; color: #666; font-size: 0.9em; text-transform: uppercase; }
        .card .number { font-size: 2.5em; font-weight: bold; color: #333; }

        /* --- CSS สำหรับ Table Users --- */
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        th, td { padding: 12px 15px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background-color: #34495e; color: white; text-transform: uppercase; font-size: 0.85em; letter-spacing: 0.5px; }
        tr:hover { background-color: #f1f1f1; }
        .status-active { color: #27ae60; font-weight: bold; background: #eafaf1; padding: 2px 8px; border-radius: 12px; font-size: 0.8em; }
        .status-inactive { color: #c0392b; font-weight: bold; background: #fdedec; padding: 2px 8px; border-radius: 12px; font-size: 0.8em; }
        .btn-action { padding: 5px 10px; border-radius: 4px; text-decoration: none; color: white; font-size: 0.8em; margin-right: 5px; }
        .btn-edit { background-color: #f39c12; }
        .btn-del { background-color: #e74c3c; }
        .btn-results { background-color: #3498db; } /* สีฟ้า */
.btn-results:hover { background-color: #2980b9; }
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
            <li>
                <a href="admin_dashboard.php?page=dashboard" class="<?php echo ($page == 'dashboard') ? 'active' : ''; ?>">
                    📊 ภาพรวมระบบ
                </a>
            </li>
            <li>
                <a href="admin_dashboard.php?page=users" class="<?php echo ($page == 'users') ? 'active' : ''; ?>">
                    👥 จัดการผู้ใช้งาน
                </a>
            </li>
            <li>
                <a href="admin_dashboard.php?page=depts" class="<?php echo ($page == 'depts') ? 'active' : ''; ?>">
                    🏢 จัดการแผนก
                </a>
            </li>
        </ul>

        <div class="logout-container">
            <a href="admin_logout.php" class="btn-logout" onclick="return confirm('ยืนยันการออกจากระบบ?')">ออกจากระบบ</a>
        </div>
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

                <div class="card" style="border-top-color: #27ae60;">
                    <h3>สถานะระบบ</h3>
                    <div class="number" style="font-size: 1.5em; color: #27ae60; line-height: 1.6;">ONLINE</div>
                    <div style="font-size: 0.8em; color: #888;">Server OK</div>
                </div>
            </div>
            
            <div style="background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                <h3>ยินดีต้อนรับ</h3>
                <p>เลือกเมนูทางซ้ายเพื่อจัดการข้อมูล</p>
            </div>
        <?php endif; ?>


        <?php if ($page == 'users'): ?>
            <div class="page-header" style="display:flex; justify-content:space-between; align-items:center;">
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
                            <th>จัดการ</th>
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
                <?php if($user['status'] == 'active'): ?>
                    <span class="status-active">Active</span>
                <?php else: ?>
                    <span class="status-inactive">Inactive</span>
                <?php endif; ?>
            </td>
            <td>
                <a href="admin_user_results.php?id=<?php echo $user['user_id']; ?>" 
                   class="btn-action btn-results"
                   title="ดูประวัติการคำนวณ">
                   📊 ผลคำนวณ
                </a>

                <a href="admin_edit_user.php?id=<?php echo $user['user_id']; ?>" class="btn-action btn-edit">แก้ไข</a>
                
                <a href="admin_dashboard.php?page=users&delete_id=<?php echo $user['user_id']; ?>" 
                   class="btn-action btn-del"
                   onclick="return confirm('ยืนยันการลบข้อมูล?')">ลบ</a>
            </td>
        </tr>
    <?php endforeach; ?>
</tbody>
                </table>
            <?php else: ?>
                <div style="text-align:center; padding:20px; color:#777;">ไม่พบข้อมูลผู้ใช้งาน</div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($page == 'depts'): ?>
            <div class="page-header">
                <h2>จัดการแผนก</h2>
            </div>
            <p>เนื้อหาสำหรับจัดการแผนกจะแสดงตรงนี้...</p>
        <?php endif; ?>

    </main>

</body>
</html>