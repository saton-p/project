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

// --- Logic: Delete Operations ---
// ลบแบบ Batch
if (isset($_POST['delete_batch'])) {
    try {
        $del_stmt = $conn->prepare("DELETE FROM carbon_logs WHERE user_id = ? AND log_date = ? AND created_at = ?");
        $del_stmt->execute([$user_id, $_POST['del_date'], $_POST['del_time']]);
        
        $redirect_url = "home.php?hist_mode=" . ($_POST['hist_mode'] ?? 'monthly');
        foreach(['d','m','y','q_q','q_y','y_y'] as $k) if(isset($_POST[$k])) $redirect_url .= "&$k=".$_POST[$k];
        header("Location: " . $redirect_url . "&msg=deleted");
        exit();
    } catch (PDOException $e) { $message = "<div class='notification error'>Error</div>"; }
}
// ลบราย Item
if (isset($_GET['del_log_id'])) {
    try {
        $conn->prepare("DELETE FROM carbon_logs WHERE id=? AND user_id=?")->execute([$_GET['del_log_id'], $user_id]);
        $redirect_url = "home.php?hist_mode=" . ($_GET['hist_mode'] ?? 'monthly');
        header("Location: " . $redirect_url . "&msg=item_deleted");
        exit();
    } catch (PDOException $e) { $message = "<div class='notification error'>Error</div>"; }
}
// Message Handler
if (isset($_GET['msg'])) {
    $active_tab = 'history'; // ถ้ามาจาก msg (เช่น ลบเสร็จ) ให้ไป History
    if($_GET['msg']=='deleted') $message = "<div class='notification success'>🗑️ ลบใบงานเรียบร้อย</div>";
    if($_GET['msg']=='item_deleted') $message = "<div class='notification success'>🗑️ ลบรายการย่อยเรียบร้อย</div>";
}

$date_mode = isset($_POST['date_mode']) ? $_POST['date_mode'] : 'daily';

// Helper Scope
function getScope($name) {
    if (strpos($name, 'ไฟฟ้า') !== false) return 2;
    if (strpos($name, 'น้ำมัน') !== false || strpos($name, 'เชื้อเพลิง') !== false || stripos($name, 'LPG') !== false) return 1;
    return 3;
}

// ตัวแปร Dashboard (หน้าบันทึก)
$show_submission_dashboard = false;
$submission_sums = [1 => 0, 2 => 0, 3 => 0];
$submission_total = 0;
$submission_mode_text = "";
$submission_date_text = "";

// --- 3. POST HANDLERS (SAVE) ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_multiple'])) {
    
    $mode = $_POST['date_mode'];
    $final_date = date('Y-m-d'); 
    $log_date_display = "";

    if ($mode == 'daily') {
        $final_date = $_POST['log_date_day'];
        $log_date_display = date('d/m/Y', strtotime($final_date));
        $submission_mode_text = "รายวัน";
    } elseif ($mode == 'monthly') {
        $final_date = $_POST['log_date_month'] . '-01';
        $log_date_display = date('F Y', strtotime($final_date));
        $submission_mode_text = "รายเดือน";
    } elseif ($mode == 'yearly') {
        $final_date = $_POST['log_date_year'] . '-01-01';
        $log_date_display = "ปี " . $_POST['log_date_year'];
        $submission_mode_text = "รายปี";
    } elseif ($mode == 'quarterly') {
        $q_y = $_POST['log_date_q_year'];
        $q_q = $_POST['log_date_quarter'];
        $month_start = ($q_q - 1) * 3 + 1;
        $final_date = "$q_y-" . str_pad($month_start, 2, '0', STR_PAD_LEFT) . "-01";
        $log_date_display = "ไตรมาส $q_q ปี $q_y";
        $submission_mode_text = "รายไตรมาส";
    }
    $submission_date_text = $log_date_display;

    $activities = $_POST['activity'];
    $count = 0;
    $current_timestamp = date('Y-m-d H:i:s'); 

    try {
        $stmt_f = $conn->prepare("SELECT factor_value, source_id, factor_name FROM emission_factors WHERE factor_id = ?");
        $stmt_src = $conn->prepare("SELECT source_name FROM emission_sources WHERE source_id = ?");
        $stmt_ins = $conn->prepare("INSERT INTO carbon_logs (user_id, factor_id, amount, emission_result, log_date, log_type, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        foreach ($activities as $fid => $amt) {
            if (is_numeric($amt) && $amt > 0) {
                $stmt_f->execute([$fid]);
                $f_row = $stmt_f->fetch();
                if ($f_row) {
                    $res = $amt * $f_row['factor_value'];
                    $stmt_ins->execute([$user_id, $fid, $amt, $res, $final_date, $mode, $current_timestamp]);
                    $count++;

                    // Calculate Dashboard Sums Immediately
                    $stmt_src->execute([$f_row['source_id']]);
                    $src_row = $stmt_src->fetch();
                    $sc = getScope($src_row['source_name']);
                    $submission_sums[$sc] += $res;
                }
            }
        }
        $submission_total = array_sum($submission_sums);

        if ($count > 0) {
            $message = "<div class='notification success'>✅ บันทึกสำเร็จ $count รายการ</div>";
            $show_submission_dashboard = true; 
        } else {
            $message = "<div class='notification error'>⚠️ กรุณากรอกตัวเลข</div>";
        }
        
        // [FIXED] บังคับให้อยู่หน้า Record ไม่เด้งไป History
        $active_tab = 'record'; 

    } catch (PDOException $e) { 
        if(strpos($e->getMessage(), "Unknown column 'log_type'") !== false) {
             $message = "<div class='notification error'>Error: Please add 'log_type' column to DB.</div>";
        } else {
             $message = "<div class='notification error'>Error: " . $e->getMessage() . "</div>";
        }
    }
}

// Update Profile & Password
if (isset($_POST['update_profile'])) {
    try { $conn->prepare("UPDATE users SET full_name=?, email=?, dept_id=? WHERE user_id=?")->execute([$_POST['full_name'], $_POST['email'], $_POST['dept_id'], $user_id]); $_SESSION['full_name'] = $_POST['full_name']; $message = "<div class='notification success'>✅ อัปเดตสำเร็จ</div>"; $active_tab = 'profile'; } catch(Exception $e) {}
}
if (isset($_POST['change_password'])) {
    $u = $conn->query("SELECT password FROM users WHERE user_id=$user_id")->fetch();
    if (password_verify($_POST['old_password'], $u['password'])) {
        if ($_POST['new_password'] === $_POST['confirm_password']) {
            $conn->prepare("UPDATE users SET password=? WHERE user_id=?")->execute([password_hash($_POST['new_password'], PASSWORD_DEFAULT), $user_id]);
            $message = "<div class='notification success'>✅ เปลี่ยนรหัสผ่านสำเร็จ</div>";
        } else { $message = "<div class='notification error'>⚠️ รหัสผ่านใหม่ไม่ตรงกัน</div>"; }
    } else { $message = "<div class='notification error'>⚠️ รหัสผ่านเดิมผิด</div>"; }
    $active_tab = 'profile';
}

// --- 5. COMMON DATA ---
$user = $conn->query("SELECT users.*, departments.dept_name FROM users LEFT JOIN departments ON users.dept_id = departments.dept_id WHERE user_id=$user_id")->fetch();
$departments = $conn->query("SELECT * FROM departments ORDER BY dept_id ASC")->fetchAll();
$factors_raw = $conn->query("SELECT ef.*, es.source_name, es.source_id FROM emission_factors ef LEFT JOIN emission_sources es ON ef.source_id=es.source_id ORDER BY es.source_id, ef.factor_id")->fetchAll();
$scope_grouped = [1 => [], 2 => [], 3 => []];
foreach ($factors_raw as $r) { $scope_grouped[getScope($r['source_name'])][$r['source_name']][] = $r; }


// --- 6. HISTORY DATA ---
$hist_mode = $_GET['hist_mode'] ?? 'monthly';
$sql_cond = ""; $params = [$user_id];
$check_col = $conn->query("SHOW COLUMNS FROM carbon_logs LIKE 'log_type'")->fetch();
if($check_col) { $sql_cond .= " AND log.log_type = ?"; $params[] = $hist_mode; }

if ($hist_mode == 'daily') {
    $d = $_GET['d'] ?? date('Y-m-d'); $sql_cond .= " AND log.log_date = ?"; $params[] = $d;
} elseif ($hist_mode == 'quarterly') {
    $qy = $_GET['q_y'] ?? date('Y'); $qq = $_GET['q_q'] ?? ceil(date('n')/3); $sql_cond .= " AND YEAR(log.log_date) = ? AND QUARTER(log.log_date) = ?"; $params[] = $qy; $params[] = $qq;
} elseif ($hist_mode == 'yearly') {
    $yy = $_GET['y_y'] ?? date('Y'); $sql_cond .= " AND YEAR(log.log_date) = ?"; $params[] = $yy;
} else { 
    $m = $_GET['m'] ?? date('m'); $y = $_GET['y'] ?? date('Y'); $sql_cond .= " AND MONTH(log.log_date) = ? AND YEAR(log.log_date) = ?"; $params[] = $m; $params[] = $y;
}

if(isset($_GET['hist_mode'])) $active_tab = 'history';

$stmt_hist = $conn->prepare("SELECT log.id, log.emission_result, log.amount, log.log_date, log.created_at, f.factor_name, f.unit, s.source_name FROM carbon_logs log JOIN emission_factors f ON log.factor_id=f.factor_id JOIN emission_sources s ON f.source_id=s.source_id WHERE log.user_id=? $sql_cond ORDER BY log.log_date DESC, log.created_at DESC");
$stmt_hist->execute($params);
$logs_data = $stmt_hist->fetchAll();

$daily_reports = []; $sums = [1=>0, 2=>0, 3=>0]; $grand_total = 0;
foreach ($logs_data as $row) {
    $sc = getScope($row['source_name']);
    $sums[$sc] += $row['emission_result'];
    $row['scope_txt'] = "Scope $sc"; $row['scope_cls'] = "badge-scope$sc";
    $key = $row['log_date'] . '_' . $row['created_at'];
    if(!isset($daily_reports[$key])) { $daily_reports[$key] = ['log_date' => $row['log_date'], 'created_at' => $row['created_at'], 'total' => 0, 'count' => 0, 'items' => []]; }
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
    <title>Carbon Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #00b894; --primary-dark: #00a383; --secondary: #2d3436; --bg-color: #f1f3f6; --white: #ffffff; --shadow: 0 4px 15px rgba(0,0,0,0.05); --border-radius: 12px; --text-color: #2d3436; --text-muted: #636e72; }
        body { font-family: 'Prompt', sans-serif; margin: 0; background: var(--bg-color); display: flex; height: 100vh; overflow: hidden; color: var(--text-color); }
        .sidebar { width: 260px; background: linear-gradient(180deg, #16a085 0%, #1abc9c 100%); color: var(--white); display: flex; flex-direction: column; flex-shrink: 0; box-shadow: 4px 0 10px rgba(0,0,0,0.1); z-index: 10; }
        .sidebar-header { padding: 30px 20px; text-align: center; font-weight: 600; font-size: 1.4em; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .user-profile { padding: 25px 20px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.05); }
        .user-avatar { width: 60px; height: 60px; background: rgba(255,255,255,0.2); border-radius: 50%; margin: 0 auto 10px; display: flex; align-items: center; justify-content: center; font-size: 1.8em; }
        .sidebar-menu { list-style: none; padding: 20px 0; margin: 0; flex-grow: 1; }
        .sidebar-menu li button { width: 100%; text-align: left; padding: 15px 25px; background: none; border: none; color: rgba(255,255,255,0.8); cursor: pointer; font-family: 'Prompt', sans-serif; font-size: 1em; transition: all 0.3s; border-left: 5px solid transparent; display: flex; align-items: center; gap: 10px; }
        .sidebar-menu li button:hover { background: rgba(255,255,255,0.1); color: var(--white); padding-left: 30px; }
        .sidebar-menu li button.active { background: rgba(255,255,255,0.15); color: var(--white); border-left-color: #f1c40f; font-weight: 500; }
        .logout-container { margin-top: auto; padding: 20px; }
        .btn-logout { display: block; width: 100%; padding: 12px; background: rgba(231, 76, 60, 0.9); color: white; text-align: center; text-decoration: none; border-radius: 8px; transition: 0.3s; font-weight: 500; }
        .main-content { flex-grow: 1; padding: 30px 40px; overflow-y: auto; scroll-behavior: smooth; }
        .container { max-width: 950px; margin: 0 auto; padding-bottom: 50px; }
        .tab-content { display: none; animation: slideUp 0.4s ease-out; }
        .tab-content.active { display: block; }
        @keyframes slideUp { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
        .card { background: var(--white); padding: 25px 30px; border-radius: var(--border-radius); box-shadow: var(--shadow); margin-bottom: 25px; border: 1px solid #eee; }
        .header-section { margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
        .header-section h2 { margin: 0; color: var(--secondary); font-weight: 600; }
        .sub-text { color: var(--text-muted); font-size: 0.9em; margin-top: 5px; }
        .grid-inputs { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; }
        .input-wrapper { margin-bottom: 5px; }
        .input-label { font-size: 0.9em; color: var(--text-muted); margin-bottom: 6px; display: block; font-weight: 500; }
        .modern-input-group { display: flex; align-items: center; border: 1px solid #dfe6e9; border-radius: 8px; background: #fff; transition: 0.3s; overflow: hidden; }
        .modern-input-group:focus-within { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(0, 184, 148, 0.1); }
        .modern-input-group input, .modern-input-group select { flex-grow: 1; border: none; padding: 12px 15px; font-size: 1em; outline: none; font-family: 'Prompt', sans-serif; width: 100%; background:transparent; }
        .modern-input-group .unit-badge { background: #f1f2f6; color: #636e72; padding: 0 15px; height: 45px; display: flex; align-items: center; font-size: 0.85em; border-left: 1px solid #dfe6e9; font-weight: 500; }
        .btn-submit { background: var(--primary); color: white; border: none; padding: 14px; border-radius: 50px; cursor: pointer; width: 100%; font-size: 1.1em; font-weight: 600; box-shadow: 0 4px 10px rgba(0, 184, 148, 0.3); transition: all 0.3s; margin-top: 10px; font-family: 'Prompt', sans-serif; }
        .btn-submit:hover { background: var(--primary-dark); transform: translateY(-2px); }
        .notification { padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-size: 0.95em; }
        .notification.success { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
        .notification.error { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }
        .scope-header { margin: 30px 0 15px 0; padding-bottom: 10px; border-bottom: 2px solid #eee; display: flex; align-items: center; gap: 10px; }
        .scope-header h3 { margin: 0; font-size: 1.3em; font-weight: 600; }
        .scope-badge { padding: 5px 12px; border-radius: 20px; font-size: 0.8em; font-weight: bold; color: white; }
        .s1-bg { background: #d35400; } .s2-bg { background: #f39c12; } .s3-bg { background: #2980b9; } .s1-color { color: #d35400; } .s2-color { color: #f39c12; } .s3-color { color: #2980b9; }
        .radio-group { display: flex; gap: 20px; margin-bottom: 15px; flex-wrap: wrap; }
        .radio-item { display: flex; align-items: center; gap: 8px; cursor: pointer; background: white; padding: 8px 15px; border-radius: 20px; border: 1px solid #dfe6e9; transition: 0.2s; }
        .radio-item:hover { border-color: var(--primary); background: #e0f2f1; }
        .date-input-container { 
            background: #ffffff;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            padding: 20px;
            margin-bottom: 25px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        .date-input-container label {
            white-space: nowrap; 
            font-size: 1.1em;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #00695c;
            font-weight: 600;
        }
        .date-input { display: none; width: auto; align-items: center; gap: 10px; }
        .date-input.active { display: flex; }
        .date-field {
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 10px 15px;
            color: #2d3436;
            font-weight: 500;
            transition: 0.3s;
            width: auto;
            min-width: 200px;
            font-family: 'Prompt', sans-serif;
            font-size: 1em;
        }
        .date-field:focus { border-color: var(--primary); background: #fff; outline: none; }
        
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .sum-card { padding: 20px; border-radius: 12px; color: white; text-align: center; position: relative; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.1); }
        .sum-card h4 { margin: 0; font-size: 0.9em; opacity: 0.9; font-weight: 400; }
        .sum-card h2 { margin: 10px 0; font-size: 2em; font-weight: 600; }
        .bg-s1 { background: linear-gradient(135deg, #ff7675, #d63031); } .bg-s2 { background: linear-gradient(135deg, #fdcb6e, #e17055); } .bg-s3 { background: linear-gradient(135deg, #74b9ff, #0984e3); } .bg-all { background: linear-gradient(135deg, #55efc4, #00b894); }
        .report-card { background: white; padding: 18px 25px; border-radius: 10px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; border-left: 5px solid transparent; transition: transform 0.2s; box-shadow: 0 2px 5px rgba(0,0,0,0.02); border: 1px solid #f0f0f0; }
        .report-card:hover { transform: translateX(5px); border-color: #e0e0e0; }
        .report-card.scope-mix { border-left-color: var(--primary); }
        .btn-view { background: #dfe6e9; color: var(--secondary); border: none; padding: 6px 15px; border-radius: 20px; cursor: pointer; font-size: 0.85em; font-weight: 500; transition: 0.2s; font-family: 'Prompt', sans-serif; }
        .btn-delete-batch { background: #e74c3c; color: white; border: none; padding: 6px 15px; border-radius: 20px; cursor: pointer; font-size: 0.85em; font-weight: 500; transition: 0.2s; font-family: 'Prompt'; }
        .profile-grid { display: grid; grid-template-columns: 300px 1fr; gap: 25px; }
        .profile-pic-large { width: 100px; height: 100px; background: #e0f2f1; color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 3em; margin: 0 auto 15px; }
        .data-row { display: flex; justify-content: space-between; padding: 12px 0; border-bottom: 1px solid #f0f0f0; font-size: 0.95em; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); backdrop-filter: blur(3px); }
        .modal-content { background: #fff; margin: 3% auto; padding: 0; border-radius: 12px; width: 800px; max-width: 90%; overflow: hidden; animation: slideDown 0.3s; }
        .modal-header { background: #f8f9fa; padding: 15px 25px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .modal-body { padding: 25px; max-height: 60vh; overflow-y: auto; }
        .modal-footer { padding: 15px 25px; background: #f8f9fa; border-top: 1px solid #eee; display: flex; justify-content: flex-end; gap: 10px; }
        .detail-table { width: 100%; border-collapse: collapse; }
        .detail-table th { text-align: left; padding: 12px; color: var(--text-muted); font-weight: 500; border-bottom: 2px solid #eee; font-size: 0.9em; }
        .detail-table td { padding: 12px; border-bottom: 1px solid #eee; font-size: 0.95em; vertical-align: middle; }
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 0.75em; font-weight: 600; text-transform: uppercase; }
        .badge-scope1 { background: #ffeaa7; color: #d35400; } .badge-scope2 { background: #dfe6e9; color: #2d3436; } .badge-scope3 { background: #74b9ff; color: #0984e3; }
        .btn-print { background: #34495e; color: white; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-family: 'Prompt'; display: flex; align-items: center; gap: 5px; }
        .btn-close-modal { background: white; border: 1px solid #ddd; color: #666; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-family: 'Prompt'; }
        .btn-item-del { background: #fab1a0; color: #d63031; border: none; padding: 4px 10px; border-radius: 4px; cursor: pointer; font-size: 0.8em; }

        @media (max-width: 768px) { .sidebar { width: 70px; } .sidebar-header span, .user-profile span, .sidebar-menu li button span { display: none; } .sidebar-menu li button { justify-content: center; padding: 15px; } .profile-grid { grid-template-columns: 1fr; } .main-content { padding: 20px; } }
    </style>
    
    <script>
        function toggleDateInput(mode) {
            document.querySelectorAll('.date-input').forEach(el => el.classList.remove('active'));
            const target = document.getElementById('input-' + mode);
            if(target) target.classList.add('active');
        }
        function toggleHistoryFilter(mode) {
            document.querySelectorAll('.hist-filter').forEach(el => el.style.display = 'none');
            const target = document.getElementById('hist-input-' + mode);
            if(target) target.style.display = 'flex';
        }
        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.sidebar-menu button').forEach(el => el.classList.remove('active'));
            document.getElementById('tab-' + tabName).classList.add('active');
            document.getElementById('btn-' + tabName).classList.add('active');
        }
    </script>
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
            <li><button onclick="switchTab('history')" class="<?php echo $active_tab=='history'?'active':''; ?>" id="btn-history">📊 <span>รายงานกิจกรรม</span></button></li>
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
                
                <div class="header-section"><div><h2>บันทึกกิจกรรมประจำวัน</h2><div class="sub-text">กรอกข้อมูลการใช้พลังงานและทรัพยากรเพื่อคำนวณคาร์บอนฟุตพริ้นท์</div></div></div>
                <form method="POST">
                    <div style="margin-bottom: 10px; font-weight: 500; color: var(--text-muted);">เลือกรูปแบบการบันทึกเวลา:</div>
                    <div class="radio-group">
                        <label class="radio-item"><input type="radio" name="date_mode" value="daily" onchange="toggleDateInput('daily')" <?php echo ($date_mode=='daily')?'checked':''; ?>> รายวัน</label>
                        <label class="radio-item"><input type="radio" name="date_mode" value="monthly" onchange="toggleDateInput('monthly')" <?php echo ($date_mode=='monthly')?'checked':''; ?>> รายเดือน</label>
                        <label class="radio-item"><input type="radio" name="date_mode" value="quarterly" onchange="toggleDateInput('quarterly')" <?php echo ($date_mode=='quarterly')?'checked':''; ?>> รายไตรมาส</label>
                        <label class="radio-item"><input type="radio" name="date_mode" value="yearly" onchange="toggleDateInput('yearly')" <?php echo ($date_mode=='yearly')?'checked':''; ?>> รายปี</label>
                    </div>
                    <div class="date-input-container">
                        <label>📅 ช่วงเวลา:</label>
                        <div id="input-daily" class="date-input <?php echo ($date_mode=='daily')?'active':''; ?>"><input type="date" name="log_date_day" value="<?php echo $_POST['log_date_day'] ?? date('Y-m-d'); ?>" class="date-field"></div>
                        <div id="input-monthly" class="date-input <?php echo ($date_mode=='monthly')?'active':''; ?>"><input type="month" name="log_date_month" value="<?php echo $_POST['log_date_month'] ?? date('Y-m'); ?>" class="date-field"></div>
                        <div id="input-quarterly" class="date-input <?php echo ($date_mode=='quarterly')?'active':''; ?>">
                            <select name="log_date_q_year" class="date-field"><?php for($y=date('Y'); $y>=date('Y')-2; $y--) echo "<option value='$y' ".(($_POST['log_date_q_year']??'')==$y?'selected':'').">$y</option>"; ?></select>
                            <select name="log_date_quarter" class="date-field">
                                <?php for($q=1;$q<=4;$q++) echo "<option value='$q' ".(($_POST['log_date_quarter']??'')==$q?'selected':'').">ไตรมาส $q</option>"; ?>
                            </select>
                        </div>
                        <div id="input-yearly" class="date-input <?php echo ($date_mode=='yearly')?'active':''; ?>"><select name="log_date_year" class="date-field"><?php for($y=date('Y'); $y>=date('Y')-5; $y--) echo "<option value='$y' ".(($_POST['log_date_year']??'')==$y?'selected':'').">$y</option>"; ?></select></div>
                    </div>
                    <?php foreach([1,2,3] as $s_id): if(!empty($scope_grouped[$s_id])): ?>
                        <div class="scope-header">
                            
                            <span class="scope-badge s<?php echo $s_id; ?>-bg">Scope <?php echo $s_id; ?></span>
                            <h3 class="s<?php echo $s_id; ?>-color"><?php echo ($s_id==1) ? 'การปล่อยก๊าซเรือนกระจกทางตรง (Direct Emissions)' : (($s_id==2) ? 'การปล่อยก๊าซเรือนกระจกทางอ้อม (Indirect Emissions)' : 'การปล่อยก๊าซเรือนกระจกทางอ้อมอื่นๆ (Other Indirect Emissions)'); ?></h3>
                        </div>
                        <?php foreach ($scope_grouped[$s_id] as $src => $facts): ?>
                        <div class="card">
                            <div style="display:flex; align-items:center; gap:10px; margin-bottom:20px; border-bottom:1px solid #eee; padding-bottom:10px;">
                                <span style="font-size:1.5em;"><?php echo ($s_id==1)?'⛽':(($s_id==2)?'⚡':'♻️'); ?></span><h3 style="margin:0; color:#2d3436; font-size:1.1em;"><?php echo htmlspecialchars($src); ?></h3>
                            </div>
                            <div class="grid-inputs">
                                <?php foreach ($facts as $f): ?>
                                <div class="input-wrapper">
                                    <label class="input-label"><?php echo $f['factor_name']; ?></label>
                                    <div class="modern-input-group"><input type="number" step="0.01" min="0" name="activity[<?php echo $f['factor_id']; ?>]" placeholder="0.00"><span class="unit-badge"><?php echo $f['unit']; ?></span></div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; endforeach; ?>
                    <button type="submit" name="save_multiple" class="btn-submit">💾 บันทึกข้อมูลทั้งหมด</button>
                </form>

                <?php if ($show_submission_dashboard): ?>
                <div style="border-top: 2px dashed #ccc; margin: 40px 0;"></div>
                <div class="header-section" style="margin-top: 20px;">
                    <h2>สรุปผลการบันทึกข้อมูลล่าสุด</h2>
                    <div class="sub-text">รูปแบบ: <?php echo $submission_mode_text; ?> | ช่วงเวลา: <?php echo $submission_date_text; ?></div>
                </div>
                <div class="summary-grid">
                    <div class="sum-card bg-s1"><h4>Scope 1 (Direct Emissions)</h4><h2><?php echo number_format($submission_sums[1],2); ?></h2><small>kgCO2e</small></div>
                    <div class="sum-card bg-s2"><h4>Scope 2 (Indirect Emissions)</h4><h2><?php echo number_format($submission_sums[2],2); ?></h2><small>kgCO2e</small></div>
                    <div class="sum-card bg-s3"><h4>Scope 3 (Other Indirect Emissions)</h4><h2><?php echo number_format($submission_sums[3],2); ?></h2><small>kgCO2e</small></div>
                    <div class="sum-card bg-all"><h4>Total Emission</h4><h2><?php echo number_format($submission_total,2); ?></h2><small>kgCO2e</small></div>
                </div>
                <?php endif; ?>
            </div>

            <div id="tab-history" class="tab-content <?php echo $active_tab=='history'?'active':''; ?>">
                <div class="header-section"><h2>รายงานผลการดำเนินงาน</h2></div>
                <div class="summary-grid">
                    <div class="sum-card bg-s1"><h4>Scope 1 (Direct Emissions)</h4><h2><?php echo number_format($sums[1],2); ?></h2><small>kgCO2e</small></div>
                    <div class="sum-card bg-s2"><h4>Scope 2 (Indirect Emissions)</h4><h2><?php echo number_format($sums[2],2); ?></h2><small>kgCO2e</small></div>
                    <div class="sum-card bg-s3"><h4>Scope 3 (Other Indirect Emissions)</h4><h2><?php echo number_format($sums[3],2); ?></h2><small>kgCO2e</small></div>
                    <div class="sum-card bg-all"><h4>Total Emission</h4><h2><?php echo number_format($grand_total,2); ?></h2><small>kgCO2e</small></div>
                </div>
                
                <div class="card" style="padding:15px 25px;">
                    <div style="font-weight:600; color:var(--text-muted); margin-bottom:10px;">🗓 ค้นหารายงานตามช่วงเวลา:</div>
                    <form method="GET" style="display:flex; flex-wrap:wrap; align-items:center; gap:15px;">
                        <div style="display:flex; gap:15px;">
                            <label><input type="radio" name="hist_mode" value="daily" onchange="toggleHistoryFilter('daily')" <?php echo ($hist_mode=='daily')?'checked':''; ?>> รายวัน</label>
                            <label><input type="radio" name="hist_mode" value="monthly" onchange="toggleHistoryFilter('monthly')" <?php echo ($hist_mode=='monthly')?'checked':''; ?>> รายเดือน</label>
                            <label><input type="radio" name="hist_mode" value="quarterly" onchange="toggleHistoryFilter('quarterly')" <?php echo ($hist_mode=='quarterly')?'checked':''; ?>> รายไตรมาส</label>
                            <label><input type="radio" name="hist_mode" value="yearly" onchange="toggleHistoryFilter('yearly')" <?php echo ($hist_mode=='yearly')?'checked':''; ?>> รายปี</label>
                        </div>
                        <div id="hist-input-daily" class="hist-filter" style="display:<?php echo ($hist_mode=='daily')?'flex':'none'; ?>; gap:5px;"><input type="date" name="d" value="<?php echo $_GET['d'] ?? date('Y-m-d'); ?>" class="date-field"></div>
                        <div id="hist-input-monthly" class="hist-filter" style="display:<?php echo ($hist_mode=='monthly')?'flex':'none'; ?>; gap:5px;">
                            <select name="m" class="date-field"><?php for($i=1;$i<=12;$i++) echo "<option value='$i' ".($i==($filter_m)?'selected':'').">เดือน $i</option>"; ?></select>
                            <select name="y" class="date-field"><?php for($y=date('Y');$y>=date('Y')-2;$y--) echo "<option value='$y' ".($y==$filter_y?'selected':'').">".($y+543)."</option>"; ?></select>
                        </div>
                        <div id="hist-input-quarterly" class="hist-filter" style="display:<?php echo ($hist_mode=='quarterly')?'flex':'none'; ?>; gap:5px;">
                            <select name="q_q" class="date-field"><option value="1" <?php echo (($_GET['q_q']??'')=='1')?'selected':''; ?>>Q1</option><option value="2" <?php echo (($_GET['q_q']??'')=='2')?'selected':''; ?>>Q2</option><option value="3" <?php echo (($_GET['q_q']??'')=='3')?'selected':''; ?>>Q3</option><option value="4" <?php echo (($_GET['q_q']??'')=='4')?'selected':''; ?>>Q4</option></select>
                            <select name="q_y" class="date-field"><?php for($y=date('Y');$y>=date('Y')-2;$y--) echo "<option value='$y' ".($y==($_GET['q_y']??date('Y'))?'selected':'').">".($y+543)."</option>"; ?></select>
                        </div>
                        <div id="hist-input-yearly" class="hist-filter" style="display:<?php echo ($hist_mode=='yearly')?'flex':'none'; ?>; gap:5px;"><select name="y_y" class="date-field"><?php for($y=date('Y');$y>=date('Y')-5;$y--) echo "<option value='$y' ".($y==($_GET['y_y']??date('Y'))?'selected':'').">".($y+543)."</option>"; ?></select></div>
                        <button type="submit" style="padding:8px 20px; background:var(--secondary); color:white; border:none; border-radius:6px; cursor:pointer; font-family:'Prompt';">🔍 ค้นหา</button>
                    </form>
                </div>

                <div style="display:flex; flex-direction:column; gap:10px;">
                    <?php if(empty($daily_reports)): ?>
                        <div style='text-align:center; padding:40px; color:#aaa;'>ไม่พบข้อมูล</div>
                    <?php else: foreach ($daily_reports as $key => $rpt): 
                        $date_time_parts = explode('_', $key); $date_part = $date_time_parts[0]; $time_part = $date_time_parts[1];
                        
                        $display_title = "";
                        if($check_col && isset($rpt['items'][0]['log_type'])) {
                            $lt = $rpt['items'][0]['log_type'];
                            if($lt == 'daily') $display_title = "วันที่ " . date('d/m/Y', strtotime($date_part));
                            elseif($lt == 'monthly') $display_title = "เดือน " . date('F Y', strtotime($date_part));
                            elseif($lt == 'yearly') $display_title = "ปี " . date('Y', strtotime($date_part));
                            elseif($lt == 'quarterly') {
                                $m = date('n', strtotime($date_part)); $q = ceil($m/3);
                                $display_title = "ไตรมาส $q ปี " . date('Y', strtotime($date_part));
                            }
                        } else {
                            if($hist_mode == 'daily') $display_title = "วันที่ " . date('d/m/Y', strtotime($date_part));
                            else $display_title = date('d/m/Y', strtotime($date_part));
                        }
                        
                        $json_items = json_encode($rpt['items']); $safe_json_items = htmlspecialchars($json_items, ENT_QUOTES, 'UTF-8');
                    ?>
                    <div class="report-card scope-mix">
                        <div>
                            <div style="font-weight:600; font-size:1.05em; color:var(--text-color);">
                                📄 <?php echo $display_title; ?> 
                                <span style="font-size:0.8em; color:#999; margin-left:5px;">(บันทึกเมื่อ: <?php echo date('d/m/Y H:i', strtotime($rpt['created_at'])); ?>)</span>
                            </div>
                            <small style="color:var(--text-muted);">จำนวน <?php echo $rpt['count']; ?> รายการ</small>
                        </div>
                        <div style="display:flex; align-items:center; gap:10px;">
                            <div style="text-align:right; margin-right:10px;">
                                <div style="color:var(--primary-dark); font-weight:700; font-size:1.2em;"><?php echo number_format($rpt['total'],4); ?></div>
                                <div style="font-size:0.7em; color:#888;">kgCO2e</div>
                            </div>
                            <button class="btn-view" data-date="<?php echo $display_title; ?>" data-items="<?php echo $safe_json_items; ?>" onclick="openModal(this)">👁️ รายละเอียด</button>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('⚠️ คำเตือน: คุณต้องการลบใบงานนี้ทั้งใบใช่หรือไม่?');">
                                <input type="hidden" name="delete_batch" value="1">
                                <input type="hidden" name="del_date" value="<?php echo $date_part; ?>">
                                <input type="hidden" name="del_time" value="<?php echo $rpt['created_at']; ?>">
                                <input type="hidden" name="hist_mode" value="<?php echo $hist_mode; ?>">
                                <?php if(isset($_GET['d'])): ?><input type="hidden" name="d" value="<?php echo $_GET['d']; ?>"><?php endif; ?>
                                <?php if(isset($_GET['m'])): ?><input type="hidden" name="m" value="<?php echo $_GET['m']; ?>"><?php endif; ?>
                                <?php if(isset($_GET['y'])): ?><input type="hidden" name="y" value="<?php echo $_GET['y']; ?>"><?php endif; ?>
                                <?php if(isset($_GET['q_q'])): ?><input type="hidden" name="q_q" value="<?php echo $_GET['q_q']; ?>"><?php endif; ?>
                                <?php if(isset($_GET['q_y'])): ?><input type="hidden" name="q_y" value="<?php echo $_GET['q_y']; ?>"><?php endif; ?>
                                <?php if(isset($_GET['y_y'])): ?><input type="hidden" name="y_y" value="<?php echo $_GET['y_y']; ?>"><?php endif; ?>
                                <button type="submit" class="btn-delete-batch">🗑️ ลบ</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
            
            <div id="tab-profile" class="tab-content <?php echo $active_tab=='profile'?'active':''; ?>">
                <div class="header-section"><h2>จัดการข้อมูลส่วนตัว</h2></div>
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
                             <form method="POST"><div class="grid-inputs"><div class="input-wrapper"><label class="input-label">ชื่อ</label><div class="modern-input-group"><input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>"></div></div><div class="input-wrapper"><label class="input-label">อีเมล</label><div class="modern-input-group"><input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>"></div></div><div class="input-wrapper" style="grid-column: span 2;">
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
                                    </div></div><button type="submit" name="update_profile" class="btn-submit" style="margin-top:10px; padding:10px;">บันทึก</button></form>
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
            <div class="modal-header"><h3 style="margin:0;">📄 รายละเอียดกิจกรรม</h3><span class="close" onclick="closeModal()" style="cursor:pointer; font-size:1.5em; color:#aaa;">&times;</span></div>
            <div style="padding:15px 25px; background:#f1f3f6;"><span style="color:var(--text-muted);">ช่วงเวลาที่บันทึก:</span> <strong style="color:var(--primary-dark); font-size:1.1em;" id="modalDate"></strong></div>
            <div class="modal-body"><table class="detail-table"><thead><tr><th>Scope</th><th>รายการกิจกรรม</th><th style="text-align:right;">ปริมาณ</th><th style="text-align:right;">Emission</th><th style="text-align:center;">Action</th></tr></thead><tbody id="modalBody"></tbody></table></div>
            <div class="modal-footer"><button type="button" class="btn-print" onclick="printDailyReport()">🖨️ พิมพ์รายงาน</button><button type="button" class="btn-close-modal" onclick="closeModal()">ปิดหน้าต่าง</button></div>
        </div>
    </div>
    <script>
        let currentReportDate = ""; let currentReportItems = []; const currentUser = { name: "<?php echo htmlspecialchars($user['full_name']); ?>", dept: "<?php echo htmlspecialchars($user['dept_name']); ?>" };
        function openModal(btn) {
            const dateStr = btn.getAttribute('data-date'); const items = JSON.parse(btn.getAttribute('data-items'));
            document.getElementById('modalDate').innerText = dateStr; currentReportDate = dateStr; currentReportItems = items; items.sort((a, b) => a.scope_txt.localeCompare(b.scope_txt));
            let html = '', total = 0;
            items.forEach(i => { total += parseFloat(i.emission_result); html += `<tr><td><span class="badge ${i.scope_cls}">${i.scope_txt}</span></td><td><b>${i.factor_name}</b><br><small style="color:#888">${i.source_name}</small></td><td style="text-align:right;">${parseFloat(i.amount).toLocaleString()} ${i.unit}</td><td style="text-align:right; font-weight:600; color:#2d3436;">${parseFloat(i.emission_result).toFixed(4)}</td><td style="text-align:center;"><a href="home.php?del_log_id=${i.id}&hist_mode=<?php echo $hist_mode; ?>" onclick="return confirm('ยืนยันที่จะลบรายการนี้?')" class="btn-item-del">🗑️ ลบ</a></td></tr>`; });
            html += `<tr style="background:#f0fdf4; border-top:2px solid #00b894;"><td colspan="4" style="text-align:right; font-weight:bold; color:#00b894;">รวมสุทธิ:</td><td style="text-align:right; font-weight:bold; color:#00b894; font-size:1.1em;">${total.toFixed(4)}</td></tr>`;
            document.getElementById('modalBody').innerHTML = html; document.getElementById('reportModal').style.display = 'block';
        }
        function closeModal() { document.getElementById('reportModal').style.display = 'none'; } window.onclick = e => { if(e.target == document.getElementById('reportModal')) closeModal(); }
        function printDailyReport() {
            let total = 0; let rows = ''; let scopeSummary = { 'Scope 1': 0, 'Scope 2': 0, 'Scope 3': 0 };
            currentReportItems.forEach((i, index) => { let emission = parseFloat(i.emission_result); total += emission; if(i.scope_txt.includes('1')) scopeSummary['Scope 1'] += emission; else if(i.scope_txt.includes('2')) scopeSummary['Scope 2'] += emission; else scopeSummary['Scope 3'] += emission; rows += `<tr><td style="text-align:center;">${index + 1}</td><td style="text-align:center;">${i.scope_txt}</td><td>${i.factor_name} <span style="color:#555; font-size:0.9em;">(${i.source_name})</span></td><td style="text-align:right;">${parseFloat(i.amount).toLocaleString()} ${i.unit}</td><td style="text-align:right;">${emission.toFixed(4)}</td></tr>`; });
            let printContent = `<!DOCTYPE html><html><head><title>Report-${currentReportDate}</title><link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@400;600;700&display=swap" rel="stylesheet"><style>@page{size:A4 portrait;margin:15mm 20mm}body{font-family:'Sarabun',sans-serif;color:#000;line-height:1.3}.text-right{text-align:right}.text-center{text-align:center}.header{text-align:center;margin-bottom:25px;border-bottom:2px solid #000;padding-bottom:10px}.header h1{margin:0;font-size:18pt;font-weight:bold}.header h3{margin:5px 0 0;font-size:14pt;font-weight:normal}.info-box{width:100%;display:flex;justify-content:space-between;margin-bottom:20px;font-size:11pt;border:1px solid #ccc;padding:10px 15px;border-radius:5px}.main-table{width:100%;border-collapse:collapse;margin-bottom:20px;font-size:10pt}.main-table th,.main-table td{border:1px solid #444;padding:6px 8px;vertical-align:middle}.main-table th{background-color:#f0f0f0;font-weight:bold;text-align:center}.total-row{background-color:#e8f5e9;font-weight:bold}.summary-container{display:flex;justify-content:center;margin-top:20px;margin-bottom:40px}.summary-box{width:60%;border:1px solid #000;padding:0}.summary-header{background:#333;color:#fff;text-align:center;padding:5px;font-weight:bold;font-size:11pt}.summary-table{width:100%;border-collapse:collapse;font-size:11pt}.summary-table td{border:none;border-bottom:1px dotted #ccc;padding:8px 15px}.summary-table tr:last-child td{border-bottom:none;font-weight:bold;background:#f9f9f9}.footer-area{display:flex;justify-content:space-between;margin-top:30px;padding:0 20px}.sign-box{text-align:center;width:40%}.sign-line{border-bottom:1px dotted #000;height:30px;margin:10px auto 5px auto;width:80%}.sign-label{font-size:10pt;color:#333;margin-top:5px}</style></head><body><div class="header"><h1>รายงานสรุปคาร์บอนฟุตพริ้นท์รายวัน</h1><h3>Carbon Footprint Daily Report</h3></div><div class="info-box"><div><div><strong>ผู้บันทึก:</strong> ${currentUser.name}</div><div><strong>แผนก:</strong> ${currentUser.dept}</div></div><div class="text-right"><div><strong>ช่วงเวลา:</strong> ${currentReportDate}</div><div><strong>Ref No:</strong> LOG-${new Date().getTime().toString().substr(-6)}</div></div></div><div style="font-weight:bold;margin-bottom:5px;">1. รายละเอียดกิจกรรม (Activity Details)</div><table class="main-table"><thead><tr><th width="5%">#</th><th width="12%">ขอบเขต</th><th width="48%">รายการกิจกรรม</th><th width="20%">ปริมาณ</th><th width="15%">Emission (kgCO2e)</th></tr></thead><tbody>${rows}</tbody><tfoot><tr class="total-row"><td colspan="4" class="text-right">รวมสุทธิ (Grand Total)</td><td class="text-right">${total.toFixed(4)}</td></tr></tfoot></table><div class="summary-container"><div class="summary-box"><div class="summary-header">2. สรุปผลตามขอบเขต (Summary by Scope)</div><table class="summary-table"><tr><td>Scope 1 (Direct Emissions)</td><td class="text-right">${scopeSummary['Scope 1'].toFixed(4)} kgCO2e</td></tr><tr><td>Scope 2 (Indirect Energy)</td><td class="text-right">${scopeSummary['Scope 2'].toFixed(4)} kgCO2e</td></tr><tr><td>Scope 3 (Other Indirect)</td><td class="text-right">${scopeSummary['Scope 3'].toFixed(4)} kgCO2e</td></tr><tr><td>รวมทั้งหมด (Total)</td><td class="text-right">${total.toFixed(4)} kgCO2e</td></tr></table></div></div><div class="footer-area"><div class="sign-box"><div style="font-weight:bold">ผู้ตรวจสอบ (Verified by)</div><div class="sign-line"></div><div class="sign-label">(.......................................................)</div><div class="sign-label">ตำแหน่ง: หัวหน้าแผนก/ผู้จัดการ</div><div class="sign-label">วันที่ ......../......../............</div></div><div class="sign-box"><div style="font-weight:bold">ผู้จัดทำ (Prepared by)</div><div class="sign-line"></div><div class="sign-label">(${currentUser.name})</div><div class="sign-label">ตำแหน่ง: เจ้าหน้าที่บันทึกข้อมูล</div><div class="sign-label">วันที่ ......../......../............</div></div></div><script>window.onload = function() { window.print(); }<\/script></body></html>`;
            let printWindow = window.open('', '', 'height=800,width=1000'); printWindow.document.write(printContent); printWindow.document.close();
        }
    </script>
</body>
</html>