<?php
session_start();

// --- [เพิ่มส่วนนี้] Logic สำหรับออกจากระบบ (Logout) ---
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_unset();    // ล้างค่า Session
    session_destroy();  // ทำลาย Session
    header("Location: login.php"); // เด้งกลับไปหน้า Login
    exit();
}
// -----------------------------------------------------

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../db_config.php';
$user_id = $_SESSION['user_id'];

// 1. ดึงข้อมูล User (สำหรับ Sidebar)
$stmt_u = $conn->prepare("SELECT full_name FROM users WHERE user_id = ?");
$stmt_u->execute([$user_id]);
$user = $stmt_u->fetch();

// 2. ตัวกรอง เดือน/ปี
$filter_month = isset($_GET['m']) ? $_GET['m'] : date('m');
$filter_year = isset($_GET['y']) ? $_GET['y'] : date('Y');

// 3. ดึงข้อมูล Logs
$sql = "SELECT log.*, f.factor_name, f.unit, s.source_name 
        FROM carbon_logs log
        JOIN emission_factors f ON log.factor_id = f.factor_id
        JOIN emission_sources s ON f.source_id = s.source_id
        WHERE log.user_id = ? 
        AND MONTH(log.log_date) = ? 
        AND YEAR(log.log_date) = ?
        ORDER BY log.log_date DESC, log.id DESC";

$stmt = $conn->prepare($sql);
$stmt->execute([$user_id, $filter_month, $filter_year]);
$logs = $stmt->fetchAll();

// -----------------------------------------------------------
// 4. LOGIC: จัดกลุ่มข้อมูลตาม "วันที่"
// -----------------------------------------------------------
$daily_reports = []; 
$grand_total = 0;
$sum_scope_1 = 0;
$sum_scope_2 = 0;
$sum_scope_3 = 0;

foreach ($logs as $row) {
    $date = $row['log_date'];
    $src = $row['source_name'];
    $emission = $row['emission_result'];
    
    // กำหนด Scope
    $scope_tag = "";
    $scope_class = "";

    if (strpos($src, 'ไฟฟ้า') !== false) {
        $sum_scope_2 += $emission;
        $scope_tag = "Scope 2";
        $scope_class = "badge-scope2";
    } elseif (strpos($src, 'น้ำมัน') !== false || strpos($src, 'เชื้อเพลิง') !== false) {
        $sum_scope_1 += $emission;
        $scope_tag = "Scope 1";
        $scope_class = "badge-scope1";
    } else {
        $sum_scope_3 += $emission;
        $scope_tag = "Scope 3";
        $scope_class = "badge-scope3";
    }

    $row['scope_tag'] = $scope_tag;
    $row['scope_class'] = $scope_class;

    // สร้าง Group วันที่
    if (!isset($daily_reports[$date])) {
        $daily_reports[$date] = [
            'date' => $date,
            'total_emission' => 0,
            'item_count' => 0,
            'items' => []
        ];
    }

    $daily_reports[$date]['items'][] = $row;
    $daily_reports[$date]['total_emission'] += $emission;
    $daily_reports[$date]['item_count']++;
}

$grand_total = $sum_scope_1 + $sum_scope_2 + $sum_scope_3;
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ประวัติการบันทึก - Carbon System</title>
    <style>
        /* CSS หลัก */
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 0; background-color: #f4f6f9; display: flex; height: 100vh; overflow: hidden; }
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
        
        /* Summary Cards */
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px; }
        .sum-card { padding: 15px; border-radius: 8px; color: white; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .sum-card h4 { margin: 0; font-size: 0.9em; opacity: 0.9; }
        .sum-card .value { font-size: 1.8em; font-weight: bold; margin: 5px 0; }
        .sum-card .unit { font-size: 0.8em; opacity: 0.8; }
        
        .bg-scope1 { background: linear-gradient(135deg, #e74c3c, #c0392b); }
        .bg-scope2 { background: linear-gradient(135deg, #f39c12, #d35400); }
        .bg-scope3 { background: linear-gradient(135deg, #3498db, #2980b9); }
        .bg-total  { background: linear-gradient(135deg, #27ae60, #1e8449); }

        /* Report List Styles */
        .report-list { display: flex; flex-direction: column; gap: 15px; }
        .report-card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; border-left: 5px solid #117a65; transition: transform 0.2s; }
        .report-card:hover { transform: translateX(5px); }
        
        .report-date { font-weight: bold; font-size: 1.1em; color: #333; }
        .report-meta { font-size: 0.9em; color: #666; margin-top: 5px; }
        .report-total { text-align: right; margin-right: 20px; }
        .report-total .num { font-size: 1.4em; font-weight: bold; color: #117a65; }
        .report-total .unit { font-size: 0.8em; color: #888; }
        
        .btn-view { padding: 8px 20px; background-color: #2c3e50; color: white; border: none; border-radius: 20px; cursor: pointer; font-size: 0.9em; transition: 0.2s; display: flex; align-items: center; gap: 5px; }
        .btn-view:hover { background-color: #1a252f; }

        /* --- Modal Styles (ปรับปรุงความกว้างตรงนี้) --- */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
        
        .modal-content { 
            background-color: #fefefe; 
            margin: 3% auto; /* ขยับขึ้นบนนิดหน่อย */
            padding: 0; 
            border: 1px solid #888; 
            
            /* แก้ไขความกว้าง */
            width: 900px; 
            max-width: 95%; /* เผื่อจอเล็ก */
            
            border-radius: 8px; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.2); 
            animation: slideDown 0.3s; 
        }
        
        @keyframes slideDown { from { opacity: 0; transform: translateY(-50px); } to { opacity: 1; transform: translateY(0); } }
        
        .modal-header { padding: 20px; background-color: #f8f9fa; border-bottom: 1px solid #eee; border-radius: 8px 8px 0 0; display: flex; justify-content: space-between; align-items: center; }
        .modal-body { padding: 25px; max-height: 70vh; overflow-y: auto; } /* เพิ่ม padding */
        .modal-footer { padding: 15px; background-color: #f8f9fa; border-top: 1px solid #eee; text-align: right; border-radius: 0 0 8px 8px; }
        .close { color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer; }
        .close:hover { color: #333; }

        /* Table in Modal */
        .detail-table { width: 100%; border-collapse: collapse; }
        .detail-table th, .detail-table td { padding: 12px; text-align: left; border-bottom: 1px solid #eee; }
        .detail-table th { background-color: #f9f9f9; color: #555; font-size: 0.9em; text-transform: uppercase; }
        .detail-table tr:last-child td { border-bottom: none; }
        
        /* Badge Scope */
        .badge { padding: 3px 8px; border-radius: 12px; font-size: 0.75em; font-weight: bold; }
        .badge-scope1 { background-color: #fdedec; color: #c0392b; border: 1px solid #e6b0aa; }
        .badge-scope2 { background-color: #fef5e7; color: #d35400; border: 1px solid #fad7a0; }
        .badge-scope3 { background-color: #ebf5fb; color: #2980b9; border: 1px solid #a9cce3; }
        
        .filter-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; background: white; padding: 15px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
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
            <li><a href="history.php" class="active">📊 ประวัติ</a></li>
            <li><a href="profile.php">⚙️ โปรไฟล์</a></li>
        </ul>
        <div class="logout-container">
            <a href="history.php?action=logout" class="btn-logout" onclick="return confirm('ยืนยันการออกจากระบบ?')">ออกจากระบบ</a>
        </div>
    </nav>

    <main class="main-content">
        <div class="container">
            
            <h2 style="color:#333;">📊 สรุปผลการดำเนินงาน</h2>

            <div class="summary-grid">
                <div class="sum-card bg-scope1">
                    <h4>Scope 1 (น้ำมัน)</h4>
                    <div class="value"><?php echo number_format($sum_scope_1, 2); ?></div>
                    <div class="unit">kgCO2e</div>
                </div>
                <div class="sum-card bg-scope2">
                    <h4>Scope 2 (ไฟฟ้า)</h4>
                    <div class="value"><?php echo number_format($sum_scope_2, 2); ?></div>
                    <div class="unit">kgCO2e</div>
                </div>
                <div class="sum-card bg-scope3">
                    <h4>Scope 3 (อื่นๆ)</h4>
                    <div class="value"><?php echo number_format($sum_scope_3, 2); ?></div>
                    <div class="unit">kgCO2e</div>
                </div>
                <div class="sum-card bg-total">
                    <h4>ยอดรวมเดือนนี้</h4>
                    <div class="value"><?php echo number_format($grand_total, 2); ?></div>
                    <div class="unit">kgCO2e</div>
                </div>
            </div>

            <div class="filter-bar">
                <h3 style="margin:0; font-size:1.1em;">📅 รายงานประจำวัน (Daily Reports)</h3>
                <form method="GET" style="display:flex; gap:10px;">
                    <select name="m" style="padding:8px; border:1px solid #ddd; border-radius:4px;">
                        <?php for($i=1; $i<=12; $i++) { $sel=($i==$filter_month)?'selected':''; echo "<option value='$i' $sel>เดือน $i</option>"; } ?>
                    </select>
                    <select name="y" style="padding:8px; border:1px solid #ddd; border-radius:4px;">
                        <?php for($y=date('Y'); $y>=date('Y')-2; $y--) { $sel=($y==$filter_year)?'selected':''; echo "<option value='$y' $sel>".($y+543)."</option>"; } ?>
                    </select>
                    <button type="submit" style="padding:8px 15px; background:#2c3e50; color:white; border:none; border-radius:4px; cursor:pointer;">ค้นหา</button>
                </form>
            </div>

            <div class="report-list">
                <?php if (count($daily_reports) > 0): ?>
                    <?php foreach ($daily_reports as $date => $report): ?>
                        <div class="report-card">
                            <div>
                                <div class="report-date">
                                    📄 รายงานวันที่ <?php echo date('d/m/Y', strtotime($date)); ?>
                                </div>
                                <div class="report-meta">
                                    จำนวน: <?php echo $report['item_count']; ?> รายการ
                                </div>
                            </div>
                            
                            <div style="display:flex; align-items:center;">
                                <div class="report-total">
                                    <div class="num"><?php echo number_format($report['total_emission'], 4); ?></div>
                                    <div class="unit">kgCO2e</div>
                                </div>
                                
                                <button class="btn-view" onclick='openReportModal("<?php echo date("d/m/Y", strtotime($date)); ?>", <?php echo json_encode($report["items"]); ?>)'>
                                    👁️ ดูข้อมูล
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="text-align:center; padding:40px; color:#888; background:white; border-radius:8px;">
                        ไม่พบข้อมูลการบันทึกในเดือนนี้
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </main>

    <div id="reportModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 style="margin:0;">📄 รายละเอียดการบันทึก</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            
            <div class="modal-body">
                <div style="margin-bottom:15px; font-weight:bold; color:#117a65; font-size:1.1em; border-bottom:1px solid #eee; padding-bottom:10px;">
                    วันที่: <span id="modalDate"></span>
                </div>

                <table class="detail-table">
                    <thead>
                        <tr>
                            <th width="15%">Scope</th>
                            <th width="40%">กิจกรรม</th>
                            <th width="20%">ปริมาณ</th>
                            <th width="25%">Emission (kgCO2e)</th>
                        </tr>
                    </thead>
                    <tbody id="modalTableBody"></tbody>
                </table>
            </div>
            
            <div class="modal-footer">
                <button onclick="closeModal()" style="padding:8px 25px; border:1px solid #ddd; background:#eee; border-radius:4px; cursor:pointer; font-weight:bold;">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>

    <script>
        function openReportModal(dateText, items) {
            document.getElementById('modalDate').innerText = dateText;
            var tbody = document.getElementById('modalTableBody');
            tbody.innerHTML = ''; 

            var dayTotal = 0;

            items.forEach(function(item) {
                dayTotal += parseFloat(item.emission_result);

                var tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><span class="badge ${item.scope_class}">${item.scope_tag}</span></td>
                    <td>
                        <strong>${item.factor_name}</strong><br>
                        <span style="font-size:0.85em; color:#888;">${item.source_name}</span>
                    </td>
                    <td>${parseFloat(item.amount).toLocaleString()} ${item.unit}</td>
                    <td style="font-weight:bold; color:#333;">${parseFloat(item.emission_result).toFixed(4)}</td>
                `;
                tbody.appendChild(tr);
            });

            var totalTr = document.createElement('tr');
            totalTr.style.backgroundColor = "#f0fdf4";
            totalTr.innerHTML = `
                <td colspan="3" style="text-align:right; font-weight:bold; color:#117a65;">รวมสุทธิ:</td>
                <td style="font-weight:bold; color:#117a65; font-size:1.2em;">${dayTotal.toFixed(4)}</td>
            `;
            tbody.appendChild(totalTr);

            document.getElementById('reportModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('reportModal').style.display = 'none';
        }

        window.onclick = function(event) {
            var modal = document.getElementById('reportModal');
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>

</body>
</html>