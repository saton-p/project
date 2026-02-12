<?php
session_start();

// --- 1. CONFIG & SESSION ---
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_unset(); session_destroy(); header("Location: login.php"); exit();
}
if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit(); }
require_once 'db_config.php';
$user_id = $_SESSION['user_id'];
$message = "";

// [แก้ไขล่าสุด] เช็คว่าถ้ามีค่า hist_mode (จากการค้นหา) ให้ไป tab history ถ้าไม่มีให้ไป record
$active_tab = (isset($_GET['hist_mode']) || isset($_GET['msg'])) ? 'history' : 'record';
if(isset($_POST['update_profile']) || isset($_POST['change_password'])) $active_tab = 'profile';

// --- 2. LOGIC: DELETE ---
if (isset($_POST['delete_batch'])) {
    try {
        $conn->prepare("DELETE FROM carbon_logs WHERE user_id=? AND log_date=? AND created_at=?")
             ->execute([$user_id, $_POST['del_date'], $_POST['del_time']]);
        // ส่งค่า hist_mode กลับไปเพื่อให้ยังอยู่หน้าเดิมและ Tab เดิม
        header("Location: home.php?hist_mode=".($_POST['hist_mode']??'daily')."&msg=deleted"); exit();
    } catch (Exception $e) { $message = "<div class='notification error'>เกิดข้อผิดพลาด</div>"; }
}
if (isset($_GET['del_log_id'])) {
    try {
        $conn->prepare("DELETE FROM carbon_logs WHERE id=? AND user_id=?")->execute([$_GET['del_log_id'], $user_id]);
        header("Location: home.php?hist_mode=".($_GET['hist_mode']??'daily')."&msg=item_deleted"); exit();
    } catch (Exception $e) { $message = "<div class='notification error'>เกิดข้อผิดพลาด</div>"; }
}

// --- 3. LOGIC: SAVE DATA ---
$date_mode = isset($_POST['date_mode']) ? $_POST['date_mode'] : 'daily';
$submission_dashboard = false;
$submission_sums = [1=>0, 2=>0, 3=>0]; 
$submission_total = 0;
$submission_date_text = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_multiple'])) {
    $final_date = $_POST['log_date_day'] ?? date('Y-m-d');
    $log_display = "วันที่ ".date('d/m/Y', strtotime($final_date));

    if ($date_mode == 'monthly') {
        $final_date = $_POST['log_date_month'] . '-01';
        $log_display = "เดือน ".date('F Y', strtotime($final_date));
    } elseif ($date_mode == 'yearly') {
        $final_date = $_POST['log_date_year'] . '-01-01';
        $log_display = "ปี " . $_POST['log_date_year'];
    } elseif ($date_mode == 'quarterly') {
        $m = ($_POST['log_date_quarter'] - 1) * 3 + 1;
        $final_date = $_POST['log_date_q_year'] . '-' . sprintf("%02d", $m) . '-01';
        $log_display = "ไตรมาส " . $_POST['log_date_quarter'] . "/" . $_POST['log_date_q_year'];
    }
    $submission_date_text = $log_display;
    $timestamp = date('Y-m-d H:i:s');
    $count = 0;

    $stmt_f = $conn->prepare("SELECT f.factor_value, s.scope_id FROM emission_factors f JOIN emission_sources s ON f.source_id = s.source_id WHERE f.factor_id = ?");
    $stmt_ins = $conn->prepare("INSERT INTO carbon_logs (user_id, factor_id, amount, emission_result, log_date, log_type, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");

    if(isset($_POST['activity'])){
        foreach ($_POST['activity'] as $fid => $amt) {
            if (is_numeric($amt) && $amt > 0) {
                $stmt_f->execute([$fid]);
                $info = $stmt_f->fetch();
                if ($info) {
                    $emission = $amt * $info['factor_value'];
                    $stmt_ins->execute([$user_id, $fid, $amt, $emission, $final_date, $date_mode, $timestamp]);
                    $count++;
                    $submission_sums[$info['scope_id']] += $emission;
                }
            }
        }
    }
    $submission_total = array_sum($submission_sums);
    if ($count > 0) { 
        $message = "<div class='notification success'>✅ บันทึกสำเร็จ $count รายการ</div>"; 
        $submission_dashboard = true; 
        $active_tab = 'record'; 
    } else {
        $message = "<div class='notification error'>⚠️ กรุณากรอกข้อมูลตัวเลข</div>"; 
    }
}

// Update Profile
if (isset($_POST['update_profile'])) { 
    try { 
        $conn->prepare("UPDATE users SET full_name=?, email=?, dept_id=? WHERE user_id=?")->execute([$_POST['full_name'], $_POST['email'], $_POST['dept_id'], $user_id]); 
        $_SESSION['full_name'] = $_POST['full_name']; 
        $message = "<div class='notification success'>✅ อัปเดตข้อมูลสำเร็จ</div>"; 
    } catch(Exception $e) {} 
}
if (isset($_POST['change_password'])) { 
    $u = $conn->query("SELECT password FROM users WHERE user_id=$user_id")->fetch(); 
    if (password_verify($_POST['old_password'], $u['password'])) { 
        if ($_POST['new_password'] === $_POST['confirm_password']) { 
            $conn->prepare("UPDATE users SET password=? WHERE user_id=?")->execute([password_hash($_POST['new_password'], PASSWORD_DEFAULT), $user_id]); 
            $message = "<div class='notification success'>✅ เปลี่ยนรหัสผ่านสำเร็จ</div>"; 
        } else { $message = "<div class='notification error'>⚠️ รหัสผ่านใหม่ไม่ตรงกัน</div>"; } 
    } else { $message = "<div class='notification error'>⚠️ รหัสผ่านเดิมไม่ถูกต้อง</div>"; } 
}

// --- 4. DATA FETCHING ---
$user = $conn->query("SELECT * FROM users WHERE user_id=$user_id")->fetch();
$departments = $conn->query("SELECT * FROM departments ORDER BY dept_id ASC")->fetchAll();

$sql = "SELECT f.*, s.source_name, s.scope_id 
        FROM emission_factors f 
        JOIN emission_sources s ON f.source_id = s.source_id 
        ORDER BY s.scope_id ASC, s.source_id ASC, f.factor_id ASC";
$stmt = $conn->query($sql);
$factors_all = $stmt->fetchAll();

$scope_grouped = [1 => [], 2 => [], 3 => []];
foreach ($factors_all as $row) {
    $scope_grouped[$row['scope_id']][$row['source_name']][] = $row;
}

// --- 5. HISTORY DATA (Updated Filter Logic) ---
$hist_mode = $_GET['hist_mode'] ?? 'daily'; 

$sql_hist = "SELECT log.*, f.factor_name, f.unit, s.source_name, s.scope_id 
             FROM carbon_logs log 
             JOIN emission_factors f ON log.factor_id=f.factor_id 
             JOIN emission_sources s ON f.source_id=s.source_id 
             WHERE log.user_id=?";

if ($hist_mode == 'daily') { 
    $d = $_GET['d'] ?? date('Y-m-d'); 
    $sql_hist .= " AND log.log_date = '$d'"; 
} elseif ($hist_mode == 'quarterly') { 
    $qy = $_GET['q_y'] ?? date('Y'); 
    $qq = $_GET['q_q'] ?? ceil(date('n')/3); 
    $sql_hist .= " AND YEAR(log.log_date) = '$qy' AND QUARTER(log.log_date) = '$qq'"; 
} elseif ($hist_mode == 'yearly') { 
    $yy = $_GET['y_y'] ?? date('Y'); 
    $sql_hist .= " AND YEAR(log.log_date) = '$yy'"; 
} else { // monthly
    $m = $_GET['m'] ?? date('n'); 
    $y = $_GET['y'] ?? date('Y'); 
    $sql_hist .= " AND MONTH(log.log_date) = '$m' AND YEAR(log.log_date) = '$y'"; 
}

$sql_hist .= " ORDER BY log.log_date DESC, log.created_at DESC";
$stmt_hist = $conn->prepare($sql_hist);
$stmt_hist->execute([$user_id]);
$logs_data = $stmt_hist->fetchAll();

$daily_reports = []; $sums = [1=>0, 2=>0, 3=>0];
foreach ($logs_data as $row) {
    $sums[$row['scope_id']] += $row['emission_result'];
    $key = $row['log_date'].'_'.$row['created_at'];
    if(!isset($daily_reports[$key])) $daily_reports[$key] = ['date'=>$row['log_date'], 'time'=>$row['created_at'], 'items'=>[], 'total'=>0, 'count'=>0];
    $daily_reports[$key]['items'][] = $row;
    $daily_reports[$key]['total'] += $row['emission_result'];
    $daily_reports[$key]['count']++;
}
$grand_total = array_sum($sums);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Carbon Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { 
            --primary: #10b981; 
            --primary-dark: #059669; 
            --secondary: #334155; 
            --bg-body: #f3f4f6; 
            --white: #ffffff; 
            --text-main: #1f2937;
            --text-muted: #6b7280;
            --danger: #ef4444;
            --warning: #f59e0b;
            --shadow-sm: 0 1px 2px 0 rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
            --radius: 16px;
        }
        
        body { font-family: 'Prompt', sans-serif; margin: 0; background: var(--bg-body); color: var(--text-main); display: flex; height: 100vh; overflow: hidden; }
        * { box-sizing: border-box; outline: none; } a { text-decoration: none; }
        
        /* Sidebar */
        .sidebar { width: 280px; background: white; display: flex; flex-direction: column; z-index: 50; transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: var(--shadow-md); }
        .sidebar-header { padding: 25px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid #e5e7eb; }
        .brand { font-size: 1.5em; font-weight: 700; color: var(--primary); display: flex; align-items: center; gap: 10px; }
        .user-card { padding: 30px 20px; text-align: center; background: linear-gradient(180deg, rgba(16,185,129,0.05) 0%, rgba(255,255,255,0) 100%); }
        .avatar { width: 80px; height: 80px; background: #d1fae5; color: var(--primary-dark); border-radius: 50%; margin: 0 auto 15px; display: flex; align-items: center; justify-content: center; font-size: 2em; box-shadow: 0 4px 10px rgba(16,185,129,0.2); }
        .user-name { font-weight: 600; font-size: 1.1em; margin-bottom: 5px; }
        .user-role { color: var(--text-muted); font-size: 0.9em; }
        
        .menu { list-style: none; padding: 20px; margin: 0; flex-grow: 1; overflow-y: auto; }
        .menu li { margin-bottom: 8px; }
        .menu-btn { width: 100%; padding: 12px 15px; border-radius: 12px; border: none; background: transparent; color: var(--text-muted); font-family: 'Prompt'; font-size: 1em; cursor: pointer; display: flex; align-items: center; gap: 12px; transition: all 0.2s; font-weight: 500; }
        .menu-btn:hover { background: #f3f4f6; color: var(--primary); }
        .menu-btn.active { background: #ecfdf5; color: var(--primary-dark); font-weight: 600; box-shadow: var(--shadow-sm); }
        .logout-wrap { padding: 20px; border-top: 1px solid #e5e7eb; }
        .btn-logout { display: block; width: 100%; padding: 12px; background: #fee2e2; color: #991b1b; text-align: center; border-radius: 12px; font-weight: 500; transition: 0.2s; }
        .btn-logout:hover { background: #fecaca; }

        /* Main Content */
        .main-content { flex: 1; padding: 30px; overflow-y: auto; scroll-behavior: smooth; position: relative; }
        .container { max-width: 1000px; margin: 0 auto; padding-bottom: 100px; }
        
        /* Tabs */
        .tab-pane { display: none; animation: slideUp 0.4s ease-out; }
        .tab-pane.active { display: block; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

        /* Components */
        .page-header { margin-bottom: 30px; } .page-title { font-size: 1.8em; font-weight: 700; color: var(--secondary); margin: 0; } .page-desc { color: var(--text-muted); margin-top: 5px; }
        .card { background: white; border-radius: var(--radius); padding: 25px; box-shadow: var(--shadow-sm); margin-bottom: 25px; border: 1px solid #f3f4f6; transition: transform 0.2s; }
        
        /* [FIXED] Stepper CSS */
        .stepper-container { width: 100%; margin-bottom: 30px; position: relative; }
        .stepper { display: flex; justify-content: space-between; align-items: center; position: relative; padding: 0 10px; }
        .stepper::before { content: ''; position: absolute; top: 22px; left: 0; right: 0; height: 4px; background: #e5e7eb; z-index: 0; border-radius: 2px; }
        .step-item { flex: 1; text-align: center; position: relative; z-index: 1; opacity: 0.5; transition: 0.3s; cursor: pointer; display: flex; flex-direction: column; align-items: center; }
        .step-item.active { opacity: 1; transform: scale(1.05); }
        .step-circle { width: 45px; height: 45px; background: white; border: 4px solid #e5e7eb; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: var(--text-muted); margin-bottom: 8px; transition: 0.3s; }
        .step-item.active .step-circle { border-color: var(--primary); background: var(--primary); color: white; box-shadow: 0 0 0 4px rgba(16,185,129,0.2); }
        .step-label { font-size: 0.95em; font-weight: 600; color: var(--secondary); }
        .step-content { display: none; animation: slideUp 0.3s; }
        .step-content.active { display: block; }

        /* Form */
        .topic-group { margin-bottom: 25px; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; background: #f9fafb; }
        .topic-title { font-weight: 600; color: var(--secondary); margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #e5e7eb; display: flex; align-items: center; gap: 10px; }
        .grid-form { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-size: 0.9em; color: var(--text-muted); font-weight: 500; }
        .input-group { display: flex; border: 1px solid #d1d5db; border-radius: 10px; overflow: hidden; background: white; transition: 0.2s; }
        .input-group:focus-within { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(16,185,129,0.1); }
        .input-group input { flex: 1; border: none; padding: 12px 15px; font-size: 1em; font-family: 'Prompt'; width: 100%; color: var(--text-main); }
        .input-unit { background: #f3f4f6; padding: 0 15px; display: flex; align-items: center; font-size: 0.85em; color: var(--text-muted); border-left: 1px solid #d1d5db; font-weight: 500; }

        .date-section { display: flex; flex-wrap: wrap; gap: 20px; align-items: center; margin-bottom: 10px; }
        .radio-pills { display: flex; gap: 10px; flex-wrap: wrap; }
        .radio-pills label { cursor: pointer; } .radio-pills input { display: none; }
        .radio-pills span { padding: 8px 16px; background: #f3f4f6; border-radius: 20px; font-size: 0.9em; color: var(--text-muted); transition: 0.2s; border: 1px solid transparent; display: block; }
        .radio-pills input:checked + span { background: #ecfdf5; color: var(--primary-dark); border-color: var(--primary); font-weight: 600; }
        .date-control { padding: 10px 15px; border: 1px solid #d1d5db; border-radius: 10px; font-family: 'Prompt'; font-size: 1em; color: var(--secondary); background: white; }

        .btn { padding: 12px 24px; border-radius: 10px; border: none; font-size: 1em; font-family: 'Prompt'; font-weight: 600; cursor: pointer; transition: 0.2s; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
        .btn:active { transform: scale(0.98); }
        .btn-primary { background: var(--primary); color: white; box-shadow: 0 4px 6px rgba(16,185,129,0.2); }
        .btn-primary:hover { background: var(--primary-dark); }
        .btn-secondary { background: #9ca3af; color: white; }
        .btn-secondary:hover { background: #6b7280; }
        .btn-success { background: #22c55e; color: white; width: 100%; box-shadow: 0 4px 6px rgba(34,197,94,0.2); }
        .action-row { display: flex; justify-content: space-between; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; }

        /* Stats & Logs */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { padding: 25px; border-radius: 16px; color: white; text-align: center; position: relative; overflow: hidden; box-shadow: var(--shadow-md); }
        .stat-card h3 { font-size: 2.2em; margin: 10px 0; font-weight: 700; }
        .stat-card span { opacity: 0.9; font-size: 0.95em; }
        .bg-1 { background: linear-gradient(135deg, #f87171, #ef4444); } .bg-2 { background: linear-gradient(135deg, #fbbf24, #f59e0b); } .bg-3 { background: linear-gradient(135deg, #60a5fa, #3b82f6); } .bg-all { background: linear-gradient(135deg, #34d399, #10b981); }

        .log-item { display: flex; justify-content: space-between; align-items: center; padding: 15px; border-bottom: 1px solid #f3f4f6; transition: 0.2s; }
        .log-item:last-child { border-bottom: none; }
        .log-item:hover { background: #f9fafb; }
        .log-date { font-weight: 600; color: var(--secondary); font-size: 1.05em; }
        .log-time { font-size: 0.85em; color: var(--text-muted); }
        .log-val { font-weight: 700; color: var(--primary-dark); font-size: 1.2em; text-align: right; }
        .log-actions { display: flex; gap: 8px; margin-left: 15px; }
        .btn-icon { padding: 6px 12px; border-radius: 8px; border: none; cursor: pointer; font-size: 0.9em; font-family: 'Prompt'; }
        .btn-view { background: #e5e7eb; color: var(--secondary); }
        .btn-view:hover { background: #d1d5db; }
        .btn-del { background: #fee2e2; color: #dc2626; }
        .btn-del:hover { background: #fecaca; }

        .notification { padding: 15px; border-radius: 12px; margin-bottom: 20px; text-align: center; font-weight: 500; animation: slideUp 0.3s; }
        .notification.success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .notification.error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

        /* Modal */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); animation: fadeIn 0.2s; }
        .modal-content { background: white; margin: 5% auto; width: 90%; max-width: 800px; border-radius: 20px; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); display: flex; flex-direction: column; max-height: 85vh; }
        .modal-header { padding: 20px 25px; border-bottom: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; background: #f9fafb; }
        .modal-body { padding: 25px; overflow-y: auto; flex: 1; }
        .modal-footer { padding: 20px 25px; border-top: 1px solid #e5e7eb; display: flex; justify-content: flex-end; gap: 10px; background: #f9fafb; }
        .detail-table { width: 100%; border-collapse: collapse; font-size: 0.95em; }
        .detail-table th { text-align: left; padding: 12px; color: var(--text-muted); border-bottom: 2px solid #e5e7eb; font-weight: 600; }
        .detail-table td { padding: 12px; border-bottom: 1px solid #f3f4f6; vertical-align: middle; }

        /* Mobile */
        .hamburger { display: none; background: none; border: none; font-size: 1.8em; color: var(--text-main); cursor: pointer; }
        @media (max-width: 768px) {
            .sidebar { position: fixed; top: 0; left: 0; width: 100%; height: auto; max-height: 70px; overflow: hidden; }
            .sidebar.open { max-height: 100vh; overflow-y: auto; }
            .sidebar-header { height: 70px; padding: 0 20px; }
            .hamburger { display: block; }
            .user-card { display: none; }
            .menu { padding-top: 0; }
            .menu-btn { padding: 15px 20px; justify-content: flex-start; border-radius: 0; border-bottom: 1px solid #f3f4f6; }
            .menu-btn span { margin-left: 10px; display: inline-block !important; }
            .main-content { padding: 90px 20px 20px 20px; }
            .stepper-container { overflow-x: auto; padding-bottom: 10px; }
            .step-item { min-width: 110px; }
            .log-item { flex-direction: column; align-items: flex-start; gap: 10px; }
            .log-actions { width: 100%; justify-content: space-between; margin: 0; margin-top: 10px; }
            .btn-icon { flex: 1; text-align: center; }
            .modal-content { height: 95vh; margin: 2.5vh auto; width: 95%; }
        }
        @keyframes fadeIn { from{opacity:0} to{opacity:1} }
    </style>
    
    <script>
        function switchTab(tabId) {
            document.querySelectorAll('.tab-pane').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.menu-btn').forEach(el => el.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            document.getElementById('btn-'+tabId).classList.add('active');
            document.querySelector('.sidebar').classList.remove('open');
            window.scrollTo(0,0);
        }
        function toggleMenu() { document.querySelector('.sidebar').classList.toggle('open'); }
        
        function toggleDate(mode) {
            document.querySelectorAll('.date-input').forEach(el => el.style.display='none');
            const el = document.getElementById('input-'+mode);
            if(el) el.style.display='block'; // or 'flex'
        }

        // [NEW] ฟังก์ชัน Toggle สำหรับหน้าค้นหาประวัติ
        function toggleHistDate(mode) {
            document.querySelectorAll('.hist-input').forEach(el => el.style.display='none');
            const el = document.getElementById('hist-input-'+mode);
            if(el) el.style.display='flex'; 
        }
        
        let currentStep = 1;
        function showStep(step) {
            document.querySelectorAll('.step-content').forEach(el => el.style.display='none');
            document.querySelectorAll('.step-item').forEach(el => el.classList.remove('active'));
            document.getElementById('step-content-'+step).style.display='block';
            document.getElementById('step-item-'+step).classList.add('active');
            currentStep = step;
            window.scrollTo(0,0);
        }
        function nextStep() { if(currentStep < 3) showStep(currentStep + 1); }
        function prevStep() { if(currentStep > 1) showStep(currentStep - 1); }

        let currentLogItems = []; let currentLogDate = "";
        
        function openModal(btn) {
            currentLogDate = btn.getAttribute('data-date');
            currentLogItems = JSON.parse(btn.getAttribute('data-items'));
            document.getElementById('modalDate').innerText = currentLogDate;
            let html = '<table class="detail-table"><thead><tr><th>รายการ</th><th style="text-align:right;">ปริมาณ</th><th style="text-align:right;">Emission (kgCO2e)</th><th></th></tr></thead><tbody>';
            currentLogItems.forEach(i => { 
                html += `<tr><td><div style="font-weight:600;color:#374151">${i.factor_name}</div><div style="font-size:0.85em;color:#9ca3af">${i.source_name || ''}</div></td><td style="text-align:right;">${parseFloat(i.amount).toLocaleString()} ${i.unit}</td><td style="text-align:right;font-weight:600;color:#059669">${parseFloat(i.emission_result).toFixed(4)}</td><td style="text-align:center"><a href="home.php?del_log_id=${i.id}&hist_mode=<?php echo $hist_mode; ?>" onclick="return confirm('ลบ?')" class="btn-del" style="padding:5px 10px;text-decoration:none;border-radius:6px;font-size:0.8em">ลบ</a></td></tr>`; 
            });
            html += '</tbody></table>';
            document.getElementById('modalBody').innerHTML = html;
            document.getElementById('reportModal').style.display = 'block';
        }
        function closeModal() { document.getElementById('reportModal').style.display = 'none'; }
        
        function printReport() {
            const w = window.open('', '', 'height=800,width=1000');
            let rows = ''; let total = 0;
            currentLogItems.forEach((item, index) => {
                let e = parseFloat(item.emission_result); total += e;
                let sc = item.scope_id ? item.scope_id : (item.scope_txt ? item.scope_txt.replace('Scope ','') : '-');
                rows += `<tr><td style="text-align:center">${index+1}</td><td style="text-align:center">Scope ${sc}</td><td>${item.factor_name}</td><td style="text-align:right">${parseFloat(item.amount).toLocaleString()} ${item.unit}</td><td style="text-align:right">${e.toFixed(4)}</td></tr>`;
            });
            w.document.write(`<html><head><title>Report</title><link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;700&display=swap" rel="stylesheet"><style>body{font-family:'Sarabun',sans-serif;padding:20px;color:#000}h1{text-align:center;font-size:24px;margin:0}h3{text-align:center;font-weight:normal;margin:5px 0 20px 0}table{width:100%;border-collapse:collapse;margin-top:20px}th,td{border:1px solid #000;padding:8px;font-size:14px}th{background:#f0f0f0;text-align:center}.text-right{text-align:right}.footer{margin-top:50px;display:flex;justify-content:space-between;padding:0 30px}</style></head><body><h1>รายงานสรุปคาร์บอนฟุตพริ้นท์</h1><h3>วันที่บันทึก: ${currentLogDate}</h3><table><thead><tr><th width="5%">#</th><th width="15%">Scope</th><th>รายการ (Activity)</th><th width="15%">ปริมาณ</th><th width="20%">Emission (kgCO2e)</th></tr></thead><tbody>${rows}</tbody><tfoot><tr style="font-weight:bold;background:#f9f9f9"><td colspan="4" class="text-right">รวมสุทธิ (Grand Total)</td><td class="text-right">${total.toFixed(4)}</td></tr></tfoot></table><div class="footer"><div style="text-align:center">ผู้จัดทำ<br><br>..........................<br>( <?php echo htmlspecialchars($user['full_name']); ?> )<br>ผู้บันทึกข้อมูล</div><div style="text-align:center">ผู้ตรวจสอบ<br><br>..........................<br>( .......................... )<br>หัวหน้าแผนก</div></div></body></html>`);
            w.document.close();
            setTimeout(() => { w.print(); }, 500);
        }
    </script>
</head>
<body>

    <nav class="sidebar">
        <div class="sidebar-header"><div class="brand"><span>🍃</span> CarbonSys</div><button class="hamburger" onclick="toggleMenu()">☰</button></div>
        <div class="user-card"><div class="avatar">👤</div><div class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></div><div class="user-role">@<?php echo htmlspecialchars($user['username']); ?></div></div>
        <ul class="menu">
            <li><button onclick="switchTab('record')" class="menu-btn <?php echo $active_tab=='record'?'active':''; ?>" id="btn-record">📝 <span>บันทึกข้อมูล</span></button></li>
            <li><button onclick="switchTab('history')" class="menu-btn <?php echo $active_tab=='history'?'active':''; ?>" id="btn-history">📊 <span>ประวัติกิจกรรม</span></button></li>
            <li><button onclick="switchTab('profile')" class="menu-btn <?php echo $active_tab=='profile'?'active':''; ?>" id="btn-profile">⚙️ <span>ข้อมูลส่วนตัว</span></button></li>
        </ul>
        <div class="logout-wrap"><a href="home.php?action=logout" class="btn-logout" onclick="return confirm('ยืนยัน?')">ออกจากระบบ</a></div>
    </nav>

    <main class="main-content">
        <div class="container">
            <?php echo $message; ?>

            <div id="record" class="tab-pane <?php echo $active_tab=='record'?'active':''; ?>">
                <div class="page-header"><h2 class="page-title">บันทึกกิจกรรมประจำวัน</h2><p class="page-desc">กรอกข้อมูลการใช้พลังงานเพื่อคำนวณคาร์บอนฟุตพริ้นท์</p></div>
                <form method="POST">
                    <div class="card">
                        <div class="form-group"><label>เลือกรูปแบบเวลา:</label>
                            <div class="date-section">
                                <div class="radio-pills">
                                    <label><input type="radio" name="date_mode" value="daily" checked onclick="toggleDate('daily')"><span>รายวัน</span></label>
                                    <label><input type="radio" name="date_mode" value="monthly" onclick="toggleDate('monthly')"><span>รายเดือน</span></label>
                                    <label><input type="radio" name="date_mode" value="quarterly" onclick="toggleDate('quarterly')"><span>รายไตรมาส</span></label>
                                    <label><input type="radio" name="date_mode" value="yearly" onclick="toggleDate('yearly')"><span>รายปี</span></label>
                                </div>
                                <div style="flex:1">
                                    <div id="input-daily" class="date-input"><input type="date" name="log_date_day" value="<?php echo date('Y-m-d'); ?>" class="date-control" style="width:100%"></div>
                                    <div id="input-monthly" class="date-input" style="display:none"><input type="month" name="log_date_month" value="<?php echo date('Y-m'); ?>" class="date-control" style="width:100%"></div>
                                    <div id="input-quarterly" class="date-input" style="display:none; gap:10px;"><select name="log_date_q_year" class="date-control" style="flex:1"><?php for($y=date('Y'); $y>=date('Y')-2; $y--) echo "<option value='$y'>$y</option>"; ?></select><select name="log_date_quarter" class="date-control" style="flex:1"><?php for($q=1;$q<=4;$q++) echo "<option value='$q'>ไตรมาส $q</option>"; ?></select></div>
                                    <div id="input-yearly" class="date-input" style="display:none"><select name="log_date_year" class="date-control" style="width:100%"><?php for($y=date('Y'); $y>=date('Y')-5; $y--) echo "<option value='$y'>$y</option>"; ?></select></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="stepper-container">
                        <div class="stepper">
                            <div id="step-item-1" class="step-item active" onclick="showStep(1)"><div class="step-circle">1</div><div class="step-label">Scope 1</div></div>
                            <div id="step-item-2" class="step-item" onclick="showStep(2)"><div class="step-circle">2</div><div class="step-label">Scope 2</div></div>
                            <div id="step-item-3" class="step-item" onclick="showStep(3)"><div class="step-circle">3</div><div class="step-label">Scope 3</div></div>
                        </div>
                    </div>

                    <?php foreach([1,2,3] as $s_id): ?>
                        <div id="step-content-<?php echo $s_id; ?>" class="step-content" style="display: <?php echo ($s_id==1)?'block':'none'; ?>;">
                            <div style="margin-bottom:20px; padding:15px; background:var(--primary); color:white; border-radius:12px; font-weight:600;">
                                <?php if($s_id==1) echo "🔥 Scope 1: Direct Emissions"; elseif($s_id==2) echo "⚡ Scope 2: Indirect Energy"; else echo "♻️ Scope 3: Other Indirect"; ?>
                            </div>
                            <?php if(!empty($scope_grouped[$s_id])): foreach ($scope_grouped[$s_id] as $src_name => $items): ?>
                                <div class="topic-group"><div class="topic-title">📌 <?php echo htmlspecialchars($src_name); ?></div>
                                <div class="grid-form"><?php foreach ($items as $f): ?><div class="form-group"><label><?php echo $f['factor_name']; ?></label><div class="input-group"><input type="number" step="0.01" min="0" name="activity[<?php echo $f['factor_id']; ?>]" placeholder="0.00"><div class="input-unit"><?php echo $f['unit']; ?></div></div></div><?php endforeach; ?></div></div>
                            <?php endforeach; else: ?><div class="card" style="text-align:center; padding:40px; color:#9ca3af;">ไม่มีรายการใน Scope นี้</div><?php endif; ?>
                            <div class="action-row">
                                <div><?php if($s_id > 1): ?><button type="button" class="btn btn-secondary" onclick="prevStep()">⬅ ย้อนกลับ</button><?php endif; ?></div>
                                <div><?php if($s_id < 3): ?><button type="button" class="btn btn-primary" onclick="nextStep()">ถัดไป ➜</button><?php else: ?><button type="submit" name="save_multiple" class="btn btn-success">💾 บันทึกทั้งหมด</button><?php endif; ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </form>

                <?php if ($submission_dashboard): ?>
                    <div style="margin-top:40px;">
                        <h3 style="margin-bottom:20px;">🎉 สรุปยอดที่เพิ่งบันทึก (<?php echo $submission_date_text; ?>)</h3>
                        <div class="stats-grid">
                            <div class="stat-card bg-1"><h3><?php echo number_format($submission_sums[1],2); ?></h3><span>Scope 1</span></div>
                            <div class="stat-card bg-2"><h3><?php echo number_format($submission_sums[2],2); ?></h3><span>Scope 2</span></div>
                            <div class="stat-card bg-3"><h3><?php echo number_format($submission_sums[3],2); ?></h3><span>Scope 3</span></div>
                            <div class="stat-card bg-all"><h3><?php echo number_format($submission_total,2); ?></h3><span>Total</span></div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div id="history" class="tab-pane <?php echo $active_tab=='history'?'active':''; ?>">
                <div class="page-header"><h2 class="page-title">ประวัติการบันทึก</h2></div>
                <div class="stats-grid">
                    <div class="stat-card bg-1"><h3><?php echo number_format($sums[1],2); ?></h3><span>Scope 1</span></div>
                    <div class="stat-card bg-2"><h3><?php echo number_format($sums[2],2); ?></h3><span>Scope 2</span></div>
                    <div class="stat-card bg-3"><h3><?php echo number_format($sums[3],2); ?></h3><span>Scope 3</span></div>
                    <div class="stat-card bg-all"><h3><?php echo number_format($grand_total,2); ?></h3><span>Grand Total</span></div>
                </div>
                
                <div class="card">
                    <form method="GET" style="display:flex; flex-wrap:wrap; gap:15px; align-items:center;">
                        <span style="font-weight:600; color:var(--secondary);">🗓 ค้นหา:</span>
                        
                        <div class="radio-pills">
                            <label><input type="radio" name="hist_mode" value="daily" <?php echo ($hist_mode=='daily')?'checked':''; ?> onclick="toggleHistDate('daily')"><span>รายวัน</span></label>
                            <label><input type="radio" name="hist_mode" value="monthly" <?php echo ($hist_mode=='monthly')?'checked':''; ?> onclick="toggleHistDate('monthly')"><span>รายเดือน</span></label>
                            <label><input type="radio" name="hist_mode" value="quarterly" <?php echo ($hist_mode=='quarterly')?'checked':''; ?> onclick="toggleHistDate('quarterly')"><span>รายไตรมาส</span></label>
                            <label><input type="radio" name="hist_mode" value="yearly" <?php echo ($hist_mode=='yearly')?'checked':''; ?> onclick="toggleHistDate('yearly')"><span>รายปี</span></label>
                        </div>

                        <div style="flex:1; min-width: 200px;">
                            <div id="hist-input-daily" class="hist-input" style="display:<?php echo ($hist_mode=='daily')?'flex':'none'; ?>;">
                                <input type="date" name="d" value="<?php echo $_GET['d'] ?? date('Y-m-d'); ?>" class="date-control" style="width:100%">
                            </div>
                            
                            <div id="hist-input-monthly" class="hist-input" style="display:<?php echo ($hist_mode=='monthly')?'flex':'none'; ?>; gap:10px;">
                                <select name="m" class="date-control" style="flex:1">
                                    <?php for($i=1;$i<=12;$i++) echo "<option value='$i' ".($i==($_GET['m']??date('n'))?'selected':'').">เดือน $i</option>"; ?>
                                </select>
                                <select name="y" class="date-control" style="flex:1">
                                    <?php for($y=date('Y');$y>=date('Y')-2;$y--) echo "<option value='$y' ".($y==($_GET['y']??date('Y'))?'selected':'').">".($y+543)."</option>"; ?>
                                </select>
                            </div>

                            <div id="hist-input-quarterly" class="hist-input" style="display:<?php echo ($hist_mode=='quarterly')?'flex':'none'; ?>; gap:10px;">
                                <select name="q_q" class="date-control" style="flex:1">
                                    <option value="1" <?php echo (($_GET['q_q']??'')=='1')?'selected':''; ?>>Q1</option>
                                    <option value="2" <?php echo (($_GET['q_q']??'')=='2')?'selected':''; ?>>Q2</option>
                                    <option value="3" <?php echo (($_GET['q_q']??'')=='3')?'selected':''; ?>>Q3</option>
                                    <option value="4" <?php echo (($_GET['q_q']??'')=='4')?'selected':''; ?>>Q4</option>
                                </select>
                                <select name="q_y" class="date-control" style="flex:1">
                                    <?php for($y=date('Y');$y>=date('Y')-2;$y--) echo "<option value='$y' ".($y==($_GET['q_y']??date('Y'))?'selected':'').">".($y+543)."</option>"; ?>
                                </select>
                            </div>

                            <div id="hist-input-yearly" class="hist-input" style="display:<?php echo ($hist_mode=='yearly')?'flex':'none'; ?>;">
                                <select name="y_y" class="date-control" style="width:100%">
                                    <?php for($y=date('Y');$y>=date('Y')-5;$y--) echo "<option value='$y' ".($y==($_GET['y_y']??date('Y'))?'selected':'').">".($y+543)."</option>"; ?>
                                </select>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-secondary" style="padding:8px 15px; font-size:0.9em;">🔍 ค้นหา</button>
                    </form>
                </div>

                <div class="card" style="padding:0; overflow:hidden;">
                    <?php if(empty($daily_reports)): ?><div style="padding:40px; text-align:center; color:#9ca3af;">ไม่พบข้อมูล</div><?php else: foreach($daily_reports as $rpt): 
                        $json_items = htmlspecialchars(json_encode($rpt['items']), ENT_QUOTES, 'UTF-8'); ?>
                        <div class="log-item"><div><div class="log-date">📅 <?php echo date('d/m/Y', strtotime($rpt['date'])); ?></div><div class="log-time">เวลา: <?php echo date('H:i', strtotime($rpt['time'])); ?> | <?php echo $rpt['count']; ?> รายการ</div></div><div style="display:flex; align-items:center; gap:20px; flex-wrap:wrap;"><div class="log-val"><?php echo number_format($rpt['total'],4); ?> <span style="font-size:0.5em; color:#9ca3af;">kgCO2e</span></div><div class="log-actions"><button class="btn-icon btn-view" data-date="<?php echo $rpt['date']; ?>" data-items="<?php echo $json_items; ?>" onclick="openModal(this)">👁️ รายละเอียด</button><form method="POST" onsubmit="return confirm('ลบ?')" style="margin:0;"><input type="hidden" name="delete_batch" value="1"><input type="hidden" name="del_date" value="<?php echo $rpt['date']; ?>"><input type="hidden" name="del_time" value="<?php echo $rpt['time']; ?>"><button class="btn-icon btn-del">🗑️</button></form></div></div></div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <div id="profile" class="tab-pane <?php echo $active_tab=='profile'?'active':''; ?>">
                <div class="page-header"><h2 class="page-title">ข้อมูลส่วนตัว</h2></div>
                <div style="display:grid; grid-template-columns: 1fr 2fr; gap:25px;" class="profile-grid">
                    <div class="card" style="text-align:center;"><div class="avatar" style="width:100px; height:100px; font-size:2.5em; margin-bottom:20px;">👤</div><h3 style="margin:0;"><?php echo htmlspecialchars($user['full_name']); ?></h3><p style="color:var(--text-muted);">@<?php echo htmlspecialchars($user['username']); ?></p><div style="margin-top:20px; border-top:1px solid #f3f4f6; padding-top:15px; text-align:left;"><p><strong>แผนก:</strong> <?php echo htmlspecialchars($user['dept_name'] ?? '-'); ?></p><p><strong>อีเมล:</strong> <?php echo htmlspecialchars($user['email']); ?></p></div></div>
                    <div class="card"><h4 style="margin-top:0; border-bottom:1px solid #f3f4f6; padding-bottom:10px;">✏️ แก้ไขข้อมูล</h4><form method="POST"><div class="grid-form"><div class="form-group"><label>ชื่อ-นามสกุล</label><div class="input-group"><input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>"></div></div><div class="form-group"><label>อีเมล</label><div class="input-group"><input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>"></div></div><div class="form-group" style="grid-column: 1/-1;"><label>แผนก</label><div class="input-group"><select name="dept_id" style="width:100%; border:none; padding:12px; font-family:'Prompt';"><?php foreach ($departments as $d): ?><option value="<?php echo $d['dept_id']; ?>" <?php echo ($d['dept_id']==$user['dept_id'])?'selected':''; ?>><?php echo htmlspecialchars($d['dept_name']); ?></option><?php endforeach; ?></select></div></div></div><button type="submit" name="update_profile" class="btn btn-primary" style="margin-top:15px;">บันทึกการเปลี่ยนแปลง</button></form><br><h4 style="margin-top:0; border-bottom:1px solid #f3f4f6; padding-bottom:10px; color:#ef4444;">🔒 เปลี่ยนรหัสผ่าน</h4><form method="POST"><div class="grid-form"><div class="form-group"><label>รหัสเดิม</label><div class="input-group"><input type="password" name="old_password"></div></div><div class="form-group"><label>รหัสใหม่</label><div class="input-group"><input type="password" name="new_password"></div></div><div class="form-group"><label>ยืนยัน</label><div class="input-group"><input type="password" name="confirm_password"></div></div></div><button type="submit" name="change_password" class="btn btn-secondary" style="margin-top:15px; background:#ef4444;">เปลี่ยนรหัสผ่าน</button></form></div>
                </div>
            </div>
        </div>
    </main>

    <div id="reportModal" class="modal"><div class="modal-content"><div class="modal-header"><h3 style="margin:0; color:var(--secondary);">📄 รายละเอียด</h3><span onclick="closeModal()" style="cursor:pointer; font-size:1.5em; color:var(--text-muted);">&times;</span></div><div style="padding:15px 25px; background:#ecfdf5; color:#065f46; display:flex; justify-content:space-between; align-items:center;"><div>วันที่: <strong id="modalDate"></strong></div><button onclick="printReport()" class="btn btn-primary" style="padding:6px 15px; font-size:0.9em;">🖨️ พิมพ์รายงาน</button></div><div class="modal-body" id="modalBody"></div><div class="modal-footer"><button class="btn btn-secondary" onclick="closeModal()">ปิดหน้าต่าง</button></div></div></div>

</body>
</html>