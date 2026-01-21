<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once '../db_config.php';
$user_id = $_SESSION['user_id'];
$message = "";

// 1. ดึงข้อมูลผู้ใช้
$stmt = $conn->prepare("SELECT users.*, departments.dept_name 
                        FROM users 
                        LEFT JOIN departments ON users.dept_id = departments.dept_id 
                        WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// ---------------------------------------------------------
// 2. LOGIC: บันทึกข้อมูลหลายรายการ (Bulk Insert)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_multiple'])) {
    $log_date = $_POST['log_date'];
    $activities = $_POST['activity']; // รับค่าเป็น Array: [factor_id => amount]
    
    $count_success = 0;

    try {
        // เตรียม Statement ไว้ครั้งเดียว แล้ววนลูป execute (เพื่อประสิทธิภาพ)
        $stmt_factor = $conn->prepare("SELECT factor_value FROM emission_factors WHERE factor_id = ?");
        $sql_insert = "INSERT INTO carbon_logs (user_id, factor_id, amount, emission_result, log_date) 
                       VALUES (?, ?, ?, ?, ?)";
        $stmt_insert = $conn->prepare($sql_insert);

        foreach ($activities as $factor_id => $amount) {
            // เช็คว่ามีการกรอกตัวเลข และค่าต้องมากกว่า 0
            if (is_numeric($amount) && $amount > 0) {
                
                // 2.1 ดึงค่าสัมประสิทธิ์
                $stmt_factor->execute([$factor_id]);
                $factor_row = $stmt_factor->fetch();

                if ($factor_row) {
                    // 2.2 คำนวณผลลัพธ์
                    $emission_result = $amount * $factor_row['factor_value'];

                    // 2.3 บันทึก
                    $stmt_insert->execute([$user_id, $factor_id, $amount, $emission_result, $log_date]);
                    $count_success++;
                }
            }
        }

        if ($count_success > 0) {
            $message = "<div class='alert success'>✅ บันทึกเรียบร้อยจำนวน $count_success รายการ</div>";
        } else {
            $message = "<div class='alert error'>⚠️ กรุณากรอกตัวเลขอย่างน้อย 1 รายการ</div>";
        }

    } catch (PDOException $e) {
        $message = "<div class='alert error'>❌ เกิดข้อผิดพลาด: " . $e->getMessage() . "</div>";
    }
}

// ---------------------------------------------------------
// 3. ดึงรายการกิจกรรม และจัดกลุ่มตาม Source (เพื่อแสดงผลสวยๆ)
// ---------------------------------------------------------
$sql_factors = "SELECT ef.*, es.source_name 
                FROM emission_factors ef 
                LEFT JOIN emission_sources es ON ef.source_id = es.source_id 
                ORDER BY es.source_id ASC, ef.factor_id ASC";
$factors_raw = $conn->query($sql_factors)->fetchAll();

// จัดกลุ่มข้อมูล: $grouped_factors['ชื่อหมวด'] = [รายการ1, รายการ2...]
$grouped_factors = [];
foreach ($factors_raw as $row) {
    $source = $row['source_name'] ?? 'อื่นๆ';
    $grouped_factors[$source][] = $row;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>บันทึกกิจกรรม - Carbon System</title>
    <style>
        /* CSS เดิม (ปรับแต่งเล็กน้อย) */
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
        .container { max-width: 900px; margin: 0 auto; }
        .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        
        /* Form & Group Styles */
        .source-group { margin-bottom: 25px; border: 1px solid #e0e0e0; border-radius: 8px; overflow: hidden; }
        .source-header { background-color: #e8f6f3; padding: 10px 15px; font-weight: bold; color: #0e6251; border-bottom: 1px solid #e0e0e0; display: flex; align-items: center; }
        .source-icon { margin-right: 10px; font-size: 1.2em; }
        
        .activity-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 15px; padding: 15px; }
        
        .activity-item { display: flex; flex-direction: column; }
        .activity-item label { font-size: 0.9em; color: #555; margin-bottom: 5px; font-weight: 600; }
        .input-group { display: flex; align-items: center; }
        .input-group input { flex-grow: 1; padding: 8px; border: 1px solid #ccc; border-radius: 4px 0 0 4px; border-right: none; }
        .input-group .unit { background: #f8f9fa; padding: 8px 12px; border: 1px solid #ccc; border-radius: 0 4px 4px 0; font-size: 0.85em; color: #666; width: 60px; text-align: center; }
        
        .form-control:focus { border-color: #117a65; outline: none; }
        
        .btn-submit { background-color: #117a65; color: white; border: none; padding: 15px 30px; border-radius: 50px; cursor: pointer; font-size: 16px; font-weight: bold; box-shadow: 0 4px 6px rgba(0,0,0,0.1); display: block; margin: 0 auto; min-width: 200px; transition: 0.2s; }
        .btn-submit:hover { background-color: #0e6251; transform: scale(1.02); }

        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; text-align: center; }
        .success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
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
            <li><a href="home.php" class="active">📝 บันทึกข้อมูล</a></li>
            <li><a href="history.php">📊 ประวัติ</a></li>
            <li><a href="profile.php">⚙️ โปรไฟล์</a></li>
        </ul>
        <div class="logout-container">
            <a href="logout.php" class="btn-logout" onclick="return confirm('ยืนยันการออกจากระบบ?')">ออกจากระบบ</a>
        </div>
    </nav>

    <main class="main-content">
        <div class="container">
            
            <div class="card">
                <h2 style="text-align:center; color:#117a65;">🌿 บันทึกข้อมูลคาร์บอนฟุตพริ้นท์</h2>
                <p style="text-align:center; color:#666;">กรอกข้อมูลกิจกรรมที่เกิดขึ้นจริงในแต่ละวัน (กรอกเฉพาะรายการที่มี)</p>
            </div>

            <?php echo $message; ?>

            <form method="POST">
                
                <div class="card" style="display:flex; align-items:center; gap:15px; justify-content:center; background:#e8f8f5;">
                    <label style="font-weight:bold; font-size:1.1em;">📅 วันที่ทำกิจกรรม:</label>
                    <input type="date" name="log_date" value="<?php echo date('Y-m-d'); ?>" 
                           style="padding:8px; border:1px solid #117a65; border-radius:4px; font-size:1em;">
                </div>

                <?php foreach ($grouped_factors as $source_name => $factors): ?>
                    <div class="source-group">
                        <div class="source-header">
                            <span class="source-icon">📌</span>
                            <?php echo htmlspecialchars($source_name); ?>
                        </div>
                        
                        <div class="activity-grid">
                            <?php foreach ($factors as $f): ?>
                                <div class="activity-item">
                                    <label><?php echo htmlspecialchars($f['factor_name']); ?></label>
                                    <div class="input-group">
                                        <input type="number" step="0.01" min="0" 
                                               name="activity[<?php echo $f['factor_id']; ?>]" 
                                               placeholder="0.00">
                                        <div class="unit"><?php echo htmlspecialchars($f['unit']); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div style="margin-bottom: 50px;">
                    <button type="submit" name="save_multiple" class="btn-submit">
                        💾 บันทึกข้อมูลทั้งหมด
                    </button>
                </div>

            </form>

        </div>
    </main>

</body>
</html>