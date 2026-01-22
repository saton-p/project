<?php
session_start();

// --- 1. Logic: Logout ---
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_unset(); session_destroy(); header("Location: login.php"); exit();
}

// --- 2. Check Session ---
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
require_once '../db_config.php';
$user_id = $_SESSION['user_id'];
$message = "";
$active_tab = 'record'; // Default Tab

// --- 3. POST HANDLERS ---

// 3.1 บันทึกกิจกรรม (Record)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_multiple'])) {
    $log_date = $_POST['log_date'];
    $activities = $_POST['activity'];
    $count = 0;
    try {
        $stmt_f = $conn->prepare("SELECT factor_value FROM emission_factors WHERE factor_id = ?");
        $stmt_ins = $conn->prepare("INSERT INTO carbon_logs (user_id, factor_id, amount, emission_result, log_date) VALUES (?, ?, ?, ?, ?)");
        foreach ($activities as $fid => $amt) {
            if (is_numeric($amt) && $amt > 0) {
                $stmt_f->execute([$fid]);
                $f_row = $stmt_f->fetch();
                if ($f_row) {
                    $res = $amt * $f_row['factor_value'];
                    $stmt_ins->execute([$user_id, $fid, $amt, $res, $log_date]);
                    $count++;
                }
            }
        }
        $message = ($count > 0) ? "<div class='notification success'><i class='icon'>✓</i> บันทึกสำเร็จ $count รายการ</div>" : "<div class='notification error'><i class='icon'>!</i> กรุณากรอกตัวเลข</div>";
        $active_tab = 'record';
    } catch (PDOException $e) { $message = "<div class='notification error'>Error: " . $e->getMessage() . "</div>"; }
}

// 3.2 แก้ไขโปรไฟล์
if (isset($_POST['update_profile'])) {
    try {
        $sql = "UPDATE users SET full_name=?, email=?, dept_id=? WHERE user_id=?";
        $conn->prepare($sql)->execute([$_POST['full_name'], $_POST['email'], $_POST['dept_id'], $user_id]);
        $_SESSION['full_name'] = $_POST['full_name'];
        $message = "<div class='notification success'>✅ อัปเดตข้อมูลสำเร็จ</div>";
        $active_tab = 'profile';
    } catch(Exception $e) { $message = "<div class='notification error'>Error</div>"; }
}

// 3.3 เปลี่ยนรหัสผ่าน
if (isset($_POST['change_password'])) {
    $user_row = $conn->query("SELECT password FROM users WHERE user_id=$user_id")->fetch();
    if (password_verify($_POST['old_password'], $user_row['password'])) {
        if ($_POST['new_password'] === $_POST['confirm_password']) {
            $conn->prepare("UPDATE users SET password=? WHERE user_id=?")->execute([password_hash($_POST['new_password'], PASSWORD_DEFAULT), $user_id]);
            $message = "<div class='notification success'>✅ เปลี่ยนรหัสผ่านสำเร็จ</div>";
        } else { $message = "<div class='notification error'>⚠️ รหัสผ่านใหม่ไม่ตรงกัน</div>"; }
    } else { $message = "<div class='notification error'>⚠️ รหัสผ่านเดิมไม่ถูกต้อง</div>"; }
    $active_tab = 'profile';
}

// --- 4. DATA FETCHING ---

// ข้อมูล User
$user = $conn->query("SELECT users.*, departments.dept_name FROM users LEFT JOIN departments ON users.dept_id = departments.dept_id WHERE user_id=$user_id")->fetch();
$departments = $conn->query("SELECT * FROM departments ORDER BY dept_id ASC")->fetchAll();

// รายการกิจกรรม
$factors_raw = $conn->query("SELECT ef.*, es.source_name, es.source_id FROM emission_factors ef LEFT JOIN emission_sources es ON ef.source_id=es.source_id ORDER BY es.source_id, ef.factor_id")->fetchAll();
$grouped_factors = [];
foreach ($factors_raw as $r) { $grouped_factors[$r['source_name']][] = $r; }

// ประวัติ & ตัวกรอง
$filter_m = $_GET['m'] ?? date('m');
$filter_y = $_GET['y'] ?? date('Y');
if(isset($_GET['m'])) $active_tab = 'history';

$sql_logs = "SELECT log.*, f.factor_name, f.unit, s.source_name FROM carbon_logs log 
             JOIN emission_factors f ON log.factor_id=f.factor_id 
             JOIN emission_sources s ON f.source_id=s.source_id 
             WHERE log.user_id=? AND MONTH(log.log_date)=? AND YEAR(log.log_date)=? 
             ORDER BY log.log_date DESC";
$logs = $conn->prepare($sql_logs);
$logs->execute([$user_id, $filter_m, $filter_y]);
$logs_data = $logs->fetchAll();

// คำนวณ Scope
$daily_reports = []; $sums = [1=>0, 2=>0, 3=>0]; $grand_total = 0;
foreach ($logs_data as $row) {
    $sc = (strpos($row['source_name'],'ไฟฟ้า')!==false)?2:( (strpos($row['source_name'],'น้ำมัน')!==false||strpos($row['source_name'],'เชื้อเพลิง')!==false)?1:3 );
    $sums[$sc] += $row['emission_result'];
    $row['scope_txt'] = "Scope $sc"; 
    $row['scope_cls'] = "badge-scope$sc";
    
    $d = $row['log_date'];
    if(!isset($daily_reports[$d])) $daily_reports[$d] = ['total'=>0, 'count'=>0, 'items'=>[]];
    $daily_reports[$d]['items'][] = $row;
    $daily_reports[$d]['total'] += $row['emission_result'];
    $daily_reports[$d]['count']++;
}
$grand_total = array_sum($sums);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>Carbon Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #00b894; --primary-dark: #00a383; --secondary: #2d3436;
            --bg-color: #f1f3f6; --white: #ffffff; --shadow: 0 4px 15px rgba(0,0,0,0.05);
            --border-radius: 12px; --text-color: #2d3436; --text-muted: #636e72;
        }
        body { font-family: 'Prompt', sans-serif; margin: 0; background: var(--bg-color); display: flex; height: 100vh; overflow: hidden; color: var(--text-color); }
        
        /* SIDEBAR */
        .sidebar { width: 260px; background: linear-gradient(180deg, #16a085 0%, #1abc9c 100%); color: var(--white); display: flex; flex-direction: column; flex-shrink: 0; box-shadow: 4px 0 10px rgba(0,0,0,0.1); z-index: 10; }
        .sidebar-header { padding: 30px 20px; text-align: center; font-weight: 600; font-size: 1.4em; letter-spacing: 1px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .user-profile { padding: 25px 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.05); }
        .user-avatar { width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 50%; margin: 0 auto 10px; display: flex; align-items: center; justify-content: center; font-size: 1.8em; }
        .sidebar-menu { list-style: none; padding: 20px 0; margin: 0; flex-grow: 1; }
        .sidebar-menu li button { width: 100%; text-align: left; padding: 15px 25px; background: none; border: none; color: rgba(255,255,255,0.8); cursor: pointer; font-family: 'Prompt', sans-serif; font-size: 1em; transition: all 0.3s; border-left: 5px solid transparent; display: flex; align-items: center; gap: 10px; }
        .sidebar-menu li button:hover { background: rgba(255,255,255,0.1); color: var(--white); padding-left: 30px; }
        .sidebar-menu li button.active { background: rgba(255,255,255,0.15); color: var(--white); border-left-color: #f1c40f; font-weight: 500; }
        .logout-container { margin-top: auto; padding: 20px; }
        .btn-logout { display: block; width: 100%; padding: 12px; background: rgba(231, 76, 60, 0.9); color: white; text-align: center; text-decoration: none; border-radius: 8px; transition: 0.3s; font-weight: 500; }
        .btn-logout:hover { background: #c0392b; transform: translateY(-2px); }

        /* CONTENT */
        .main-content { flex-grow: 1; padding: 30px 40px; overflow-y: auto; scroll-behavior: smooth; }
        .container { max-width: 950px; margin: 0 auto; padding-bottom: 50px; }
        .tab-content { display: none; animation: slideUp 0.4s ease-out; }
        .tab-content.active { display: block; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }

        .card { background: var(--white); padding: 25px 30px; border-radius: var(--border-radius); box-shadow: var(--shadow); margin-bottom: 25px; border: 1px solid #eee; }
        .header-section { margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
        .header-section h2 { margin: 0; color: var(--secondary); font-weight: 600; }
        .sub-text { color: var(--text-muted); font-size: 0.9em; margin-top: 5px; }

        /* UI ELEMENTS */
        .grid-inputs { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
        .input-wrapper { margin-bottom: 5px; }
        .input-label { font-size: 0.9em; color: var(--text-muted); margin-bottom: 6px; display: block; font-weight: 500; }
        .modern-input-group { display: flex; align-items: center; border: 1px solid #dfe6e9; border-radius: 8px; background: #fff; transition: 0.3s; overflow: hidden; }
        .modern-input-group:focus-within { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(0, 184, 148, 0.1); }
        .modern-input-group input, .modern-input-group select { flex-grow: 1; border: none; padding: 12px 15px; font-size: 1em; outline: none; font-family: 'Prompt', sans-serif; width: 100%; background:transparent; }
        .modern-input-group .unit-badge { background: #f1f2f6; color: #636e72; padding: 0 15px; height: 45px; display: flex; align-items: center; font-size: 0.85em; border-left: 1px solid #dfe6e9; font-weight: 500; }
        .btn-submit { background: var(--primary); color: white; border: none; padding: 14px; border-radius: 50px; cursor: pointer; width: 100%; font-size: 1.1em; font-weight: 600; box-shadow: 0 4px 10px rgba(0, 184, 148, 0.3); transition: all 0.3s; margin-top: 10px; font-family: 'Prompt', sans-serif; }
        .btn-submit:hover { background: var(--primary-dark); transform: translateY(-2px); box-shadow: 0 6px 15px rgba(0, 184, 148, 0.4); }
        .notification { padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-size: 0.95em; }
        .notification.success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .notification.error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        /* DASHBOARD */
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .sum-card { padding: 20px; border-radius: 12px; color: white; text-align: center; position: relative; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .sum-card h4 { margin: 0; font-size: 0.9em; opacity: 0.9; font-weight: 400; }
        .sum-card h2 { margin: 10px 0; font-size: 2em; font-weight: 600; }
        .bg-s1 { background: linear-gradient(135deg, #ff7675, #d63031); }
        .bg-s2 { background: linear-gradient(135deg, #fdcb6e, #e17055); }
        .bg-s3 { background: linear-gradient(135deg, #74b9ff, #0984e3); }
        .bg-all { background: linear-gradient(135deg, #55efc4, #00b894); }

        /* REPORT LIST */
        .report-card { background: white; padding: 18px 25px; border-radius: 10px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; border-left: 5px solid transparent; transition: transform 0.2s; box-shadow: 0 2px 5px rgba(0,0,0,0.02); border: 1px solid #f0f0f0; }
        .report-card:hover { transform: translateX(5px); border-color: #e0e0e0; }
        .report-card.scope-mix { border-left-color: var(--primary); }
        .btn-view { background: #dfe6e9; color: var(--secondary); border: none; padding: 6px 15px; border-radius: 20px; cursor: pointer; font-size: 0.85em; font-weight: 500; transition: 0.2s; font-family: 'Prompt', sans-serif; }
        .btn-view:hover { background: #b2bec3; }

        /* PROFILE */
        .profile-grid { display: grid; grid-template-columns: 300px 1fr; gap: 25px; }
        .profile-pic-large { width: 100px; height: 100px; background: #e0f2f1; color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 3em; margin: 0 auto 15px; }
        .data-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f0f0f0; font-size: 0.95em; }
        .data-row:last-child { border-bottom: none; }
        .data-label { color: var(--text-muted); }
        .data-value { font-weight: 500; color: var(--text-color); }

        /* MODAL */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); backdrop-filter: blur(3px); }
        .modal-content { background: #fff; margin: 3% auto; padding: 0; border-radius: 12px; width: 800px; max-width: 90%; overflow: hidden; animation: slideDown 0.3s; }
        @keyframes slideDown { from { transform: translateY(-50px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
        .modal-header { background: #f8f9fa; padding: 15px 25px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .modal-body { padding: 25px; max-height: 60vh; overflow-y: auto; }
        .modal-footer { padding: 15px 25px; background: #f8f9fa; border-top: 1px solid #eee; display: flex; justify-content: flex-end; gap: 10px; }
        
        .detail-table { width: 100%; border-collapse: collapse; }
        .detail-table th { text-align: left; padding: 12px; color: var(--text-muted); font-weight: 500; border-bottom: 2px solid #eee; font-size: 0.9em; }
        .detail-table td { padding: 12px; border-bottom: 1px solid #eee; font-size: 0.95em; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75em; font-weight: 600; text-transform: uppercase; }
        .badge-scope1 { background: #ffeaa7; color: #d35400; }
        .badge-scope2 { background: #dfe6e9; color: #2d3436; }
        .badge-scope3 { background: #74b9ff; color: #0984e3; }
        
        .btn-print { background: #34495e; color: white; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-family: 'Prompt', sans-serif; display: flex; align-items: center; gap: 5px; }
        .btn-print:hover { background: #2c3e50; }
        .btn-close-modal { background: white; border: 1px solid #ddd; color: #666; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-family: 'Prompt'; }
        .btn-close-modal:hover { background: #eee; }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar-header span, .user-profile span, .sidebar-menu li button span { display: none; }
            .sidebar-menu li button { justify-content: center; padding: 15px; }
            .profile-grid { grid-template-columns: 1fr; }
            .main-content { padding: 20px; }
        }
    </style>
</head>
<body>

    <nav class="sidebar">
        <div class="sidebar-header">Carbon<span>Sys</span></div>
        <div class="user-profile">
            <div class="user-avatar">👤</div>
            <span><?php echo htmlspecialchars($user['full_name']); ?></span>
        </div>
        <ul class="sidebar-menu">
            <li><button onclick="switchTab('record')" class="<?php echo $active_tab=='record'?'active':''; ?>" id="btn-record">📝 <span>บันทึกข้อมูล</span></button></li>
            <li><button onclick="switchTab('history')" class="<?php echo $active_tab=='history'?'active':''; ?>" id="btn-history">📊 <span>ประวัติ & รายงาน</span></button></li>
            <li><button onclick="switchTab('profile')" class="<?php echo $active_tab=='profile'?'active':''; ?>" id="btn-profile">⚙️ <span>โปรไฟล์ส่วนตัว</span></button></li>
        </ul>
        <div class="logout-container">
            <a href="home.php?action=logout" class="btn-logout" onclick="return confirm('ยืนยันออกจากระบบ?')">ออกจากระบบ</a>
        </div>
    </nav>

    <main class="main-content">
        <div class="container">
            
            <?php echo $message; ?>

            <div id="tab-record" class="tab-content <?php echo $active_tab=='record'?'active':''; ?>">
                <div class="header-section">
                    <div>
                        <h2>บันทึกกิจกรรมประจำวัน</h2>
                        <div class="sub-text">กรอกข้อมูลการใช้พลังงานและทรัพยากรเพื่อคำนวณคาร์บอนฟุตพริ้นท์</div>
                    </div>
                </div>

                <form method="POST">
                    <div class="card" style="display:flex; align-items:center; gap:15px; background:#e8f8f5; border-color:#b2dfdb;">
                        <label style="font-weight:600; color:#00695c;">📅 วันที่ทำกิจกรรม:</label>
                        <input type="date" name="log_date" value="<?php echo date('Y-m-d'); ?>" 
                               style="padding:8px 12px; border:1px solid #00b894; border-radius:6px; font-family:'Prompt'; outline:none; cursor:pointer;">
                    </div>

                    <?php foreach ($grouped_factors as $src => $facts): ?>
                    <div class="card">
                        <div style="display:flex; align-items:center; gap:10px; margin-bottom:20px; border-bottom:1px solid #eee; padding-bottom:10px;">
                            <span style="font-size:1.5em;">📌</span>
                            <h3 style="margin:0; color:var(--primary-dark); font-size:1.1em;"><?php echo htmlspecialchars($src); ?></h3>
                        </div>
                        
                        <div class="grid-inputs">
                            <?php foreach ($facts as $f): ?>
                            <div class="input-wrapper">
                                <label class="input-label"><?php echo $f['factor_name']; ?></label>
                                <div class="modern-input-group">
                                    <input type="number" step="0.01" min="0" name="activity[<?php echo $f['factor_id']; ?>]" placeholder="0.00">
                                    <span class="unit-badge"><?php echo $f['unit']; ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <button type="submit" name="save_multiple" class="btn-submit">💾 บันทึกข้อมูลทั้งหมด</button>
                </form>
            </div>

            <div id="tab-history" class="tab-content <?php echo $active_tab=='history'?'active':''; ?>">
                <div class="header-section">
                    <h2>รายงานผลการดำเนินงาน</h2>
                </div>
                
                <div class="summary-grid">
                    <div class="sum-card bg-s1">
                        


                        <h4>Scope 1 (Direct)</h4><h2><?php echo number_format($sums[1],2); ?></h2><small>kgCO2e</small></div>
                    <div class="sum-card bg-s2">
                        



                        <h4>Scope 2 (Energy)</h4><h2><?php echo number_format($sums[2],2); ?></h2><small>kgCO2e</small></div>
                    <div class="sum-card bg-s3">
                        
                        <h4>Scope 3 (Others)</h4><h2><?php echo number_format($sums[3],2); ?></h2><small>kgCO2e</small></div>
                    <div class="sum-card bg-all"><h4>Total Emission</h4><h2><?php echo number_format($grand_total,2); ?></h2><small>kgCO2e</small></div>
                </div>

                <div class="card" style="display:flex; justify-content:space-between; align-items:center; padding:15px 25px;">
                    <span style="font-weight:600; color:var(--text-muted);">🗓 เลือกช่วงเวลาดูรายงาน</span>
                    <form method="GET" style="display:flex; gap:10px;">
                        <select name="m" style="padding:8px 12px; border-radius:6px; border:1px solid #ddd; font-family:'Prompt';">
                            <?php for($i=1;$i<=12;$i++) echo "<option value='$i' ".($i==$filter_m?'selected':'').">เดือน $i</option>"; ?>
                        </select>
                        <select name="y" style="padding:8px 12px; border-radius:6px; border:1px solid #ddd; font-family:'Prompt';">
                            <?php for($y=date('Y');$y>=date('Y')-2;$y--) echo "<option value='$y' ".($y==$filter_y?'selected':'').">".($y+543)."</option>"; ?>
                        </select>
                        <button type="submit" style="padding:8px 20px; background:var(--secondary); color:white; border:none; border-radius:6px; cursor:pointer; font-family:'Prompt';">ค้นหา</button>
                    </form>
                </div>

                <div style="display:flex; flex-direction:column; gap:10px;">
                    <?php foreach ($daily_reports as $date => $rpt): ?>
                    <div class="report-card scope-mix">
                        <div>
                            <div style="font-weight:600; font-size:1.05em; color:var(--text-color);">📅 <?php echo date('d/m/Y', strtotime($date)); ?></div>
                            <small style="color:var(--text-muted);">จำนวน <?php echo $rpt['count']; ?> รายการ</small>
                        </div>
                        <div style="display:flex; align-items:center; gap:20px;">
                            <div style="text-align:right;">
                                <div style="color:var(--primary-dark); font-weight:700; font-size:1.2em;"><?php echo number_format($rpt['total'],4); ?></div>
                                <div style="font-size:0.7em; color:#888;">kgCO2e</div>
                            </div>
                            <button class="btn-view" onclick='openModal("<?php echo date("d/m/Y", strtotime($date)); ?>", <?php echo json_encode($rpt["items"]); ?>)'>
                                👁️ รายละเอียด
                            </button>
                        </div>
                    </div>
                    <?php endforeach; if(empty($daily_reports)) echo "<div style='text-align:center; padding:40px; color:#aaa;'>ไม่พบข้อมูลการบันทึกในเดือนนี้</div>"; ?>
                </div>
            </div>

            <div id="tab-profile" class="tab-content <?php echo $active_tab=='profile'?'active':''; ?>">
                <div class="header-section">
                    <h2>จัดการข้อมูลส่วนตัว</h2>
                </div>

                <div class="profile-grid">
                    <div class="card" style="text-align:center; height:fit-content;">
                        <div class="profile-pic-large">👤</div>
                        <h3 style="margin:5px 0;"><?php echo htmlspecialchars($user['full_name']); ?></h3>
                        <p style="color:#aaa; margin:0 0 20px 0; font-size:0.9em;">@<?php echo htmlspecialchars($user['username']); ?></p>
                        <div style="text-align:left;">
                            <div class="data-row"><span class="data-label">แผนก</span><span class="data-value"><?php echo htmlspecialchars($user['dept_name'] ?? '-'); ?></span></div>
                            <div class="data-row"><span class="data-label">อีเมล</span><span class="data-value"><?php echo htmlspecialchars($user['email']); ?></span></div>
                            <div class="data-row"><span class="data-label">สถานะ</span><span style="color:#00b894; font-weight:600;"><?php echo ucfirst($user['status']); ?></span></div>
                            
                        </div>
                    </div>
                    
                    <div>
                        <div class="card">
                            <h4 style="margin-top:0; color:var(--secondary);">✏️ แก้ไขข้อมูลทั่วไป</h4>
                            <form method="POST">
                                <div class="grid-inputs">
                                    <div class="input-wrapper">
                                        <label class="input-label">ชื่อ - นามสกุล</label>
                                        <div class="modern-input-group"><input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required></div>
                                    </div>
                                    <div class="input-wrapper">
                                        <label class="input-label">อีเมล</label>
                                        <div class="modern-input-group"><input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required></div>
                                    </div>
                                    <div class="input-wrapper" style="grid-column: span 2;">
                                        <label class="input-label">เปลี่ยนแผนก/หน่วยงาน</label>
                                        <div class="modern-input-group">
                                            <select name="dept_id" required>
                                                <?php foreach ($departments as $dept): ?>
                                                    <option value="<?php echo $dept['dept_id']; ?>" <?php echo ($dept['dept_id'] == $user['dept_id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($dept['dept_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" name="update_profile" class="btn-submit" style="padding:10px; font-size:1em;">บันทึกการเปลี่ยนแปลง</button>
                            </form>
                        </div>

                        <div class="card">
                            <h4 style="margin-top:0; color:#e17055;">🔒 เปลี่ยนรหัสผ่าน</h4>
                            <form method="POST">
                                <div class="grid-inputs">
                                    <div class="modern-input-group"><input type="password" name="old_password" placeholder="รหัสผ่านเดิม" required></div>
                                    <div class="modern-input-group"><input type="password" name="new_password" placeholder="รหัสผ่านใหม่" required></div>
                                    <div class="modern-input-group" style="grid-column: span 2;"><input type="password" name="confirm_password" placeholder="ยืนยันรหัสผ่านใหม่" required></div>
                                </div>
                                <button type="submit" name="change_password" class="btn-submit" style="background:#e17055; padding:10px; font-size:1em;">ยืนยันเปลี่ยนรหัสผ่าน</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <div id="reportModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="margin:0;">📄 รายละเอียดกิจกรรม</h3>
                <span class="close" onclick="closeModal()" style="cursor:pointer; font-size:1.5em; color:#aaa;">&times;</span>
            </div>
            <div style="padding:15px 25px; background:#f1f3f6;">
                <span style="color:var(--text-muted);">วันที่บันทึก:</span> 
                <strong style="color:var(--primary-dark); font-size:1.1em;" id="modalDate"></strong>
            </div>
            <div class="modal-body">
                <table class="detail-table">
                    <thead><tr><th>Scope</th><th>รายการกิจกรรม</th><th style="text-align:right;">ปริมาณ</th><th style="text-align:right;">Emission (kgCO2e)</th></tr></thead>
                    <tbody id="modalBody"></tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-print" onclick="printDailyReport()">🖨️ พิมพ์รายงาน</button>
                <button type="button" class="btn-close-modal" onclick="closeModal()">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>

    <script>
        // Store current report data globally
        let currentReportDate = "";
        let currentReportItems = [];
        
        // User info
        const currentUser = {
            name: "<?php echo htmlspecialchars($user['full_name']); ?>",
            dept: "<?php echo htmlspecialchars($user['dept_name']); ?>"
        };

        // Tab Logic
        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.sidebar-menu button').forEach(el => el.classList.remove('active'));
            document.getElementById('tab-' + tabName).classList.add('active');
            document.getElementById('btn-' + tabName).classList.add('active');
        }

        // Modal Logic
        function openModal(date, items) {
            document.getElementById('modalDate').innerText = date;
            currentReportDate = date; // Save for print
            currentReportItems = items; // Save for print
            
            // Sorting: Scope 1 -> 2 -> 3
            items.sort((a, b) => a.scope_txt.localeCompare(b.scope_txt));

            let html = '', total = 0;
            items.forEach(i => {
                total += parseFloat(i.emission_result);
                html += `<tr>
                    <td><span class="badge ${i.scope_cls}">${i.scope_txt}</span></td>
                    <td><b>${i.factor_name}</b><br><small style="color:#888">${i.source_name}</small></td>
                    <td style="text-align:right;">${parseFloat(i.amount).toLocaleString()} ${i.unit}</td>
                    <td style="text-align:right; font-weight:600; color:#2d3436;">${parseFloat(i.emission_result).toFixed(4)}</td>
                </tr>`;
            });
            html += `<tr style="background:#f0fdf4; border-top:2px solid #00b894;"><td colspan="3" style="text-align:right; font-weight:bold; color:#00b894;">รวมสุทธิ:</td><td style="text-align:right; font-weight:bold; color:#00b894; font-size:1.1em;">${total.toFixed(4)}</td></tr>`;
            document.getElementById('modalBody').innerHTML = html;
            document.getElementById('reportModal').style.display = 'block';
        }
        function closeModal() { document.getElementById('reportModal').style.display = 'none'; }
        window.onclick = e => { if(e.target == document.getElementById('reportModal')) closeModal(); }

        // --- UPDATED PRINT FUNCTION (Portrait & Balanced) ---
        // --- UPDATED PRINT FUNCTION (Centered Summary & Right Signature) ---
        function printDailyReport() {
            let total = 0;
            let rows = '';
            let scopeSummary = { 'Scope 1': 0, 'Scope 2': 0, 'Scope 3': 0 };

            // วนลูปสร้างแถวและคำนวณยอดรวม
            currentReportItems.forEach((i, index) => {
                let emission = parseFloat(i.emission_result);
                total += emission;
                
                if(i.scope_txt.includes('1')) scopeSummary['Scope 1'] += emission;
                else if(i.scope_txt.includes('2')) scopeSummary['Scope 2'] += emission;
                else scopeSummary['Scope 3'] += emission;

                rows += `
                    <tr>
                        <td style="text-align:center;">${index + 1}</td>
                        <td style="text-align:center;">${i.scope_txt}</td>
                        <td>${i.factor_name} <span style="color:#555; font-size:0.9em;">(${i.source_name})</span></td>
                        <td style="text-align:right;">${parseFloat(i.amount).toLocaleString()} ${i.unit}</td>
                        <td style="text-align:right;">${emission.toFixed(4)}</td>
                    </tr>
                `;
            });

            // สร้างเนื้อหา HTML สำหรับพิมพ์
            let printContent = `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Report-${currentReportDate}</title>
                    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet">
                    <style>
                        @page { size: A4 portrait; margin: 15mm 20mm; }
                        body { font-family: 'Sarabun', sans-serif; color: #000; -webkit-print-color-adjust: exact; line-height: 1.3; }
                        
                        /* Layout Utility */
                        .text-center { text-align: center; }
                        .text-right { text-align: right; }
                        .bold { font-weight: bold; }
                        .mt-20 { margin-top: 20px; }
                        
                        /* Header */
                        .header { text-align: center; margin-bottom: 25px; border-bottom: 2px solid #000; padding-bottom: 10px; }
                        .header h1 { margin: 0; font-size: 18pt; font-weight: bold; }
                        .header h3 { margin: 5px 0 0; font-size: 14pt; font-weight: normal; }
                        
                        /* Info Box */
                        .info-box { width: 100%; display: flex; justify-content: space-between; margin-bottom: 20px; font-size: 11pt; border: 1px solid #ccc; padding: 10px 15px; border-radius: 5px; }
                        
                        /* Main Table */
                        .main-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; font-size: 10pt; }
                        .main-table th, .main-table td { border: 1px solid #444; padding: 6px 8px; vertical-align: middle; }
                        .main-table th { background-color: #f0f0f0; font-weight: bold; text-align: center; }
                        .total-row { background-color: #e8f5e9; font-weight: bold; }

                        /* Summary Section (Centered) */
                        .summary-container { display: flex; justify-content: center; margin-top: 20px; margin-bottom: 40px; }
                        .summary-box { width: 60%; border: 1px solid #000; padding: 0; }
                        .summary-header { background: #333; color: #fff; text-align: center; padding: 5px; font-weight: bold; font-size: 11pt; }
                        .summary-table { width: 100%; border-collapse: collapse; font-size: 11pt; }
                        .summary-table td { border: none; border-bottom: 1px dotted #ccc; padding: 8px 15px; }
                        .summary-table tr:last-child td { border-bottom: none; font-weight: bold; background: #f9f9f9; }

                        /* Signatures (Bottom) */
                        .footer-area { display: flex; justify-content: space-between; margin-top: 30px; padding: 0 20px; }
                        .sign-box { text-align: center; width: 40%; }
                        .sign-line { border-bottom: 1px dotted #000; height: 30px; margin: 10px auto 5px auto; width: 80%; }
                        .sign-label { font-size: 10pt; color: #333; margin-top: 5px; }
                    </style>
                </head>
                <body>
                    
                    <div class="header">
                        <h1>รายงานสรุปคาร์บอนฟุตพริ้นท์รายวัน</h1>
                        <h3>Carbon Footprint Daily Report</h3>
                    </div>

                    <div class="info-box">
                        <div>
                            <div><strong>ผู้บันทึก:</strong> ${currentUser.name}</div>
                            <div><strong>แผนก:</strong> ${currentUser.dept}</div>
                        </div>
                        <div class="text-right">
                            <div><strong>วันที่บันทึก:</strong> ${currentReportDate}</div>
                            <div><strong>Ref No:</strong> LOG-${new Date().getTime().toString().substr(-6)}</div>
                        </div>
                    </div>

                    <div class="bold" style="margin-bottom:5px;">1. รายละเอียดกิจกรรม (Activity Details)</div>
                    <table class="main-table">
                        <thead>
                            <tr>
                                <th width="5%">#</th>
                                <th width="12%">ขอบเขต</th>
                                <th width="48%">รายการกิจกรรม</th>
                                <th width="20%">ปริมาณ</th>
                                <th width="15%">Emission (kgCO2e)</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${rows}
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td colspan="4" class="text-right">รวมสุทธิ (Grand Total)</td>
                                <td class="text-right">${total.toFixed(4)}</td>
                            </tr>
                        </tfoot>
                    </table>

                    <div class="summary-container">
                        <div class="summary-box">
                            <div class="summary-header">2. สรุปผลตามขอบเขต (Summary by Scope)</div>
                            <table class="summary-table">
                                <tr>
                                    <td>Scope 1 (Direct Emissions)</td>
                                    <td class="text-right">${scopeSummary['Scope 1'].toFixed(4)} kgCO2e</td>
                                </tr>
                                <tr>
                                    <td>Scope 2 (Indirect Energy)</td>
                                    <td class="text-right">${scopeSummary['Scope 2'].toFixed(4)} kgCO2e</td>
                                </tr>
                                <tr>
                                    <td>Scope 3 (Other Indirect)</td>
                                    <td class="text-right">${scopeSummary['Scope 3'].toFixed(4)} kgCO2e</td>
                                </tr>
                                <tr>
                                    <td>รวมทั้งหมด (Total)</td>
                                    <td class="text-right">${total.toFixed(4)} kgCO2e</td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="footer-area">
                        <div class="sign-box">
                            <div class="bold">ผู้ตรวจสอบ (Verified by)</div>
                            <div class="sign-line"></div>
                            <div class="sign-label">(.......................................................)</div>
                            
                            <div class="sign-label">วันที่ ......../......../............</div>
                        </div>

                        <div class="sign-box">
                            <div class="bold">ผู้จัดทำ (Prepared by)</div>
                            <div class="sign-line"></div>
                            <div class="sign-label">(${currentUser.name})</div>
                            
                            <div class="sign-label">วันที่ ......../......../............</div>
                        </div>
                    </div>

                    <script>
                        window.onload = function() {
                            window.print();
                        }
                    <\/script>
                </body>
                </html>
            `;

            let printWindow = window.open('', '', 'height=800,width=1000');
            printWindow.document.write(printContent);
            printWindow.document.close();
        }
    </script>
</body>
</html>