<?php
include 'db.php';
require_once 'admin_auth.php';

$color_id   = (int)($_GET['color_id'] ?? 0);
$sport_name = $_GET['sport_name'] ?? '';
$category   = $_GET['category']   ?? '';
$download   = $_GET['download']   ?? '';
$back_url   = "allstudent2.php?color_id=$color_id&sport_name=".urlencode($sport_name)."&category=".urlencode($category);

if(!$color_id || !$sport_name) die("ข้อมูลไม่ครบ");

/* UPDATE STUDENT */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_student') {
    $u_reg_id = $_POST['reg_id'];
    $u_student_id = $_POST['student_id'];
    $u_sport_id = $_POST['sport_id'];
    $u_student_name = $_POST['student_name'];
    $u_tel = $_POST['tel'];
    $u_shirt_size = $_POST['shirt_size'];
    $u_color_id = $_POST['color_id'];
    if (!in_array($u_shirt_size, setting_list($conn, 'shirt_sizes'), true)) $u_shirt_size = '';

    // 1. Update Student Table
    $stmt1 = $conn->prepare("UPDATE student SET student_name=?, tel=? WHERE student_id=?");
    $stmt1->bind_param("sss", $u_student_name, $u_tel, $u_student_id);
    if(!$stmt1->execute()){
         echo "Error update student: " . $stmt1->error;
    }

    // 2. Update Sport Registration Table
    $stmt2 = $conn->prepare("UPDATE sport_registration SET student_name=? WHERE reg_id=?");
    $stmt2->bind_param("si", $u_student_name, $u_reg_id);
    $stmt2->execute();

    // 3. Update/Insert Player Size
    $stmt3 = $conn->prepare("INSERT INTO player_size (student_id, sport_id, color_id, shirt_size) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE shirt_size=?, color_id=VALUES(color_id)");
    $stmt3->bind_param("siiss", $u_student_id, $u_sport_id, $u_color_id, $u_shirt_size, $u_shirt_size);
    if ($u_shirt_size !== '') $stmt3->execute();

    header("Location: $back_url");
    exit;
}

/* DELETE (POST เท่านั้น) — ลบรูปและไซส์เสื้อของรายการนั้นด้วย */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_registration') {
    $delete_id = (int)$_POST['reg_id'];
    $stmt = $conn->prepare("SELECT student_id, sport_id FROM sport_registration WHERE reg_id=?");
    $stmt->bind_param("i",$delete_id);
    $stmt->execute();
    if ($reg = $stmt->get_result()->fetch_assoc()) {
        $stmt = $conn->prepare("DELETE FROM sport_registration WHERE reg_id=?");
        $stmt->bind_param("i",$delete_id);
        $stmt->execute();
        delete_registration_extras($conn, $reg['student_id'], (int)$reg['sport_id']);
    }
    header("Location: $back_url");
    exit;
}

/* CSV */
if($category && $download=='csv'){
    header('Content-Type:text/csv; charset=utf-8');
    header('Content-Disposition:attachment; filename=participants.csv');
    $out=fopen('php://output','w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM ให้ Excel อ่านภาษาไทยถูก
    fputcsv($out,['รหัส','ชื่อ','เบอร์','คณะ','สี','กีฬา','ประเภท','ไซซ์']);
    $stmt=$conn->prepare("SELECT sr.student_id,sr.student_name,s.tel,f.faculty_name,c.color_name,sr.sport_name,sr.category,ps.shirt_size FROM sport_registration sr JOIN student s ON sr.student_id=s.student_id JOIN faculty f ON sr.faculty_id=f.faculty_id JOIN color_team c ON sr.color_id=c.color_id LEFT JOIN player_size ps ON sr.student_id=ps.student_id AND sr.sport_id=ps.sport_id WHERE sr.color_id=? AND sr.sport_name=? AND sr.category=?");
    $stmt->bind_param("iss",$color_id,$sport_name,$category);
    $stmt->execute();
    $res=$stmt->get_result();
    while($r=$res->fetch_assoc()){ fputcsv($out,[$r['student_id'],$r['student_name'],$r['tel'],$r['faculty_name'],$r['color_name'],$r['sport_name'],$r['category'],$r['shirt_size']??'-']); }
    fclose($out);
    exit;
}

/* SYNC TO GOOGLE SHEETS (POST เท่านั้น, URL ตั้งได้ที่หน้า admin_settings.php) */
$gsheet_url = get_setting($conn, 'gsheet_url');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'sync_gsheet' && $category) {
    if ($gsheet_url === '' || !preg_match('#^https://script\.google\.com/#', $gsheet_url)) {
        header("Location: $back_url&sync_result=nourl");
        exit;
    }
    $stmt=$conn->prepare("SELECT sr.student_id,sr.student_name,s.tel,f.faculty_name,c.color_name,sr.sport_name,sr.category,ps.shirt_size FROM sport_registration sr JOIN student s ON sr.student_id=s.student_id JOIN faculty f ON sr.faculty_id=f.faculty_id JOIN color_team c ON sr.color_id=c.color_id LEFT JOIN player_size ps ON sr.student_id=ps.student_id AND sr.sport_id=ps.sport_id WHERE sr.color_id=? AND sr.sport_name=? AND sr.category=?");
    $stmt->bind_param("iss",$color_id,$sport_name,$category);
    $stmt->execute();
    $res=$stmt->get_result();
    $rows = [];
    while($r=$res->fetch_assoc()){
        $rows[] = [
            'id' => $r['student_id'], 'name' => $r['student_name'], 'tel' => $r['tel'],
            'faculty' => $r['faculty_name'], 'color' => $r['color_name'], 'sport' => $r['sport_name'],
            'category' => $r['category'], 'size' => $r['shirt_size'] ?? '-',
        ];
    }
    if (empty($rows)) {
        header("Location: $back_url&sync_result=empty");
        exit;
    }

    // ส่งข้อมูลทั้งหมดในคำขอเดียว (Batch Sync)
    $ch = curl_init($gsheet_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['data' => $rows]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err = curl_error($ch);
    curl_close($ch);

    if ($http_code == 200) {
        header("Location: $back_url&sync_result=success&sync_count=" . count($rows));
        exit;
    }
    echo "<div style='font-family: sans-serif; padding: 20px; border: 1px solid #ff4444; background: #fff5f5; border-radius: 8px;'>";
    echo "<h2 style='color: #cc0000;'>Sync Failed (HTTP " . (int)$http_code . ")</h2>";
    echo "<p><b>สาเหตุที่พบบ่อย:</b> Google Script ไม่ได้ตั้งสิทธิ์การเข้าถึงเป็น \"ทุกคน (Anyone)\" หรือ URL ไม่ถูกต้อง</p>";
    echo "<div style='background: #eee; padding: 10px; border-radius: 4px; overflow: auto; max-height: 300px;'>";
    echo "<b>Response:</b><br><pre>" . htmlspecialchars($curl_err ?: (string)$response) . "</pre>";
    echo "</div><br><a href='" . htmlspecialchars($back_url) . "' style='display: inline-block; padding: 10px 20px; background: #6366f1; color: white; text-decoration: none; border-radius: 6px;'>← กลับ</a>";
    echo "</div>";
    exit;
}

$colorNames = [];
$colorBg = [];
$cn = $conn->query("SELECT color_id, color_name FROM color_team");
while($row = $cn->fetch_assoc()){
    $colorNames[$row['color_id']] = preg_replace('/^สี/u', '', $row['color_name']);
    $colorBg[$row['color_id']] = team_color_hex($row['color_name']);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายชื่อผู้สมัคร - Admin</title>
    <?php include 'tailwind-config.php'; ?>
</head>

<body>
    <div class="container">
        
        <!-- Header -->
        <div class="text-center mb-6 animate-fade">
            <h1 class="text-2xl font-bold mb-2"><?= htmlspecialchars($sport_name) ?></h1>
            <p>
                <span style="display: inline-block; width: 16px; height: 16px; border-radius: 50%; background: <?= $colorBg[$color_id] ?? '#6b7280' ?>; margin-right: 6px; vertical-align: middle;"></span>
                <span class="text-gray">สี<?= $colorNames[$color_id] ?? 'ไม่ระบุ' ?></span>
            </p>
        </div>
        
        <!-- Navigation -->
        <div class="text-center mb-6">
            <a href="allstudent.php" class="btn-back" style="margin-right: 8px;">← กลับ</a>
            <?php if($category): ?>
            <a href="?color_id=<?= $color_id ?>&sport_name=<?= urlencode($sport_name) ?>" class="btn-back">🔄 เลือกประเภท</a>
            <?php endif; ?>
        </div>
        
        <?php if(!$category): ?>
        <!-- Select Category -->
        <div style="max-width: 500px; margin: 0 auto;">
            <h2 class="text-center font-bold text-lg mb-4">📋 เลือกประเภท</h2>
            <div class="grid grid-2">
                <?php
                $stmt=$conn->prepare("SELECT DISTINCT category FROM sport_type WHERE sport_name=?");
                $stmt->bind_param("s",$sport_name);
                $stmt->execute();
                $cats=$stmt->get_result();
                while($c=$cats->fetch_assoc()):
                ?>
                <a href="?color_id=<?= $color_id ?>&sport_name=<?= urlencode($sport_name) ?>&category=<?= urlencode($c['category']) ?>" class="sport-card">
                    <div class="sport-icon">🎯</div>
                    <div class="sport-name"><?= htmlspecialchars($c['category']) ?></div>
                </a>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if($category): ?>
        <!-- Download -->
        <div class="text-center mb-6">
            <a href="?color_id=<?= $color_id ?>&sport_name=<?= urlencode($sport_name) ?>&category=<?= urlencode($category) ?>&download=csv" class="btn btn-success" style="padding: 12px 24px; font-size: 14px;">⬇️ ดาวน์โหลด CSV</a>
            <a href="player_card.php?color_id=<?= $color_id ?>&sport_name=<?= urlencode($sport_name) ?>&category=<?= urlencode($category) ?>" target="_blank" class="btn btn-primary" style="padding: 12px 24px; font-size: 14px;">🪪 พิมพ์บัตรนักกีฬาทั้งหมด</a>
            <?php if ($gsheet_url !== ''): ?>
            <form method="post" style="display:inline;" onsubmit="return confirm('ส่งข้อมูลรายชื่อนี้ไปยัง Google Sheets?')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="sync_gsheet">
                <button type="submit" class="btn" style="padding: 12px 24px; font-size: 14px; background: #4285F4; color: white; border: none; cursor: pointer;">📊 Sync Google Sheets</button>
            </form>
            <?php endif; ?>
        </div>

        <?php $sync_result = $_GET['sync_result'] ?? ''; if ($sync_result !== ''): ?>
        <?php if ($sync_result === 'success'): ?>
        <div class="notice" style="background: #dcfce7; color: #166534; border: 1px solid #bbf7d0; margin-bottom: 20px; text-align: center;">✅ ส่งข้อมูล <?= (int)($_GET['sync_count'] ?? 0) ?> รายการไป Google Sheets เรียบร้อยแล้ว!</div>
        <?php elseif ($sync_result === 'empty'): ?>
        <div class="notice" style="margin-bottom: 20px; text-align: center;">ℹ️ ไม่มีรายชื่อให้ส่ง</div>
        <?php else: ?>
        <div class="notice" style="background:#fef2f2; color:#dc2626; margin-bottom: 20px; text-align: center;">❌ ยังไม่ได้ตั้งค่า URL Google Sheets (ไปที่ ตั้งค่าระบบ)</div>
        <?php endif; ?>
        <?php endif; ?>
        
        <div class="text-center mb-4">
            <span style="display: inline-block; background: #eef2ff; color: #6366f1; padding: 8px 20px; border-radius: 50px; font-weight: 700; font-size: 14px;">ประเภท: <?= htmlspecialchars($category) ?></span>
        </div>
        
        <?php
        // Added sr.sport_id to SELECT
        $stmt=$conn->prepare("SELECT sr.reg_id,sr.student_id,sr.student_name,sr.sport_id,s.tel,f.faculty_name,c.color_name,sr.sport_name,sr.category,ps.shirt_size,p.image_path AS profile_img,cd.image_path AS card_img FROM sport_registration sr JOIN student s ON sr.student_id=s.student_id JOIN faculty f ON sr.faculty_id=f.faculty_id JOIN color_team c ON sr.color_id=c.color_id LEFT JOIN player_size ps ON sr.student_id=ps.student_id AND sr.sport_id=ps.sport_id LEFT JOIN image p ON p.student_id=sr.student_id AND p.sport_id=sr.sport_id AND p.image_type='profile' LEFT JOIN image cd ON cd.student_id=sr.student_id AND cd.sport_id=sr.sport_id AND cd.image_type='student_card' WHERE sr.color_id=? AND sr.sport_name=? AND sr.category=?");
        $stmt->bind_param("iss",$color_id,$sport_name,$category);
        $stmt->execute();
        $res=$stmt->get_result();
        ?>
        
        <?php if($res->num_rows > 0): ?>
        <div class="table-container mb-8">
            <table>
                <thead>
                    <tr>
                        <th>รูป</th>
                        <th>บัตร</th>
                        <th>รหัส</th>
                        <th>ชื่อ</th>
                        <th>เบอร์</th>
                        <th>คณะ</th>
                        <th>ไซซ์</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($r=$res->fetch_assoc()): ?>
                    <tr>
                        <td style="text-align: center;">
                            <?php if($r['profile_img']): ?>
                            <img src="<?= htmlspecialchars($r['profile_img']) ?>" style="width: 48px; height: 48px; border-radius: 12px; object-fit: cover; cursor: pointer;" onclick="openImage(this.src)">
                            <?php else: ?>
                            <span class="text-gray">-</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;">
                            <?php if($r['card_img']): ?>
                            <img src="<?= htmlspecialchars($r['card_img']) ?>" style="width: 64px; height: 40px; border-radius: 8px; object-fit: cover; cursor: pointer;" onclick="openImage(this.src)">
                            <?php else: ?>
                            <span class="text-gray">-</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-family: monospace;"><?= htmlspecialchars($r['student_id']) ?></td>
                        <td style="font-weight: 500;"><?= htmlspecialchars($r['student_name']) ?></td>
                        <td><?= htmlspecialchars($r['tel']) ?></td>
                        <td><?= htmlspecialchars($r['faculty_name']) ?></td>
                        <td style="text-align: center;">
                            <span style="background: #eef2ff; color: #6366f1; padding: 4px 12px; border-radius: 50px; font-weight: 700; font-size: 12px;"><?= htmlspecialchars($r['shirt_size'] ?? '-') ?></span>
                        </td>
                        <td style="text-align: center;">
                            <a href="player_card.php?reg_id=<?= $r['reg_id'] ?>" target="_blank" style="text-decoration:none; font-size:1.2em; margin-right:5px;" title="พิมพ์บัตรนักกีฬา">🪪</a>
                            <a href="search.php?student_id=<?= urlencode($r['student_id']) ?>" target="_blank" style="text-decoration:none; font-size:1.2em; margin-right:5px;" title="สแกน/รับเสื้อ/เช็คชื่อ">📇</a>
                            <button onclick='openEditModal(<?= htmlspecialchars(json_encode($r), ENT_QUOTES, "UTF-8") ?>)' class="btn-icon" style="background:none; border:none; cursor:pointer; font-size:1.2em; margin-right:5px;" title="แก้ไข">✏️</button>
                            <form method="post" style="display:inline;" onsubmit="return confirm('ยืนยันการลบ? รูปและไซส์เสื้อของรายการนี้จะถูกลบด้วย')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete_registration">
                                <input type="hidden" name="reg_id" value="<?= $r['reg_id'] ?>">
                                <button type="submit" style="background:none; border:none; cursor:pointer; color: #ef4444; font-size:1.2em;" title="ลบ">🗑️</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="card text-center p-8 mb-8">
            <p style="font-size: 48px; margin-bottom: 16px;">📭</p>
            <p class="text-gray text-lg">ไม่พบผู้สมัคร</p>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <!-- Lightbox -->
    <div class="lightbox" id="lightbox" onclick="closeImage()">
        <img id="lightbox-img">
    </div>

    <!-- Edit Modal -->
    <div id="editModal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:999; justify-content:center; align-items:center;">
        <div class="card" style="width:90%; max-width:400px; position:relative; animation: slideUp 0.3s ease-out;">
            <h2 class="font-bold text-lg mb-4 text-center">✏️ แก้ไขข้อมูล</h2>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_student">
                <input type="hidden" name="reg_id" id="edit_reg_id">
                <input type="hidden" name="student_id" id="edit_student_id">
                <input type="hidden" name="sport_id" id="edit_sport_id">
                <input type="hidden" name="color_id" value="<?= $color_id ?>">

                <div class="mb-4">
                    <label class="form-label">ชื่อ-นามสกุล</label>
                    <input type="text" name="student_name" id="edit_student_name" class="form-input" required>
                </div>
                <div class="mb-4">
                    <label class="form-label">เบอร์โทร</label>
                    <input type="text" name="tel" id="edit_tel" class="form-input" required>
                </div>
                <div class="mb-6">
                    <label class="form-label">ไซซ์เสื้อ</label>
                    <select name="shirt_size" id="edit_shirt_size" class="form-input">
                        <option value="">- ระบุ -</option>
                        <?php foreach(setting_list($conn,'shirt_sizes') as $sz): ?>
                        <option value="<?= htmlspecialchars($sz) ?>"><?= htmlspecialchars($sz) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex gap-2" style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary" style="flex:1;">💾 บันทึก</button>
                    <button type="button" onclick="closeEditModal()" class="btn" style="flex:1; background:#f3f4f6; color:#374151;">ยกเลิก</button>
                </div>
            </form>
        </div>
    </div>
    
    <style>
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        /* Horizontal scrollbar for table */
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            max-width: 100%;
        }
        
        .table-container table {
            min-width: 800px;
            white-space: nowrap;
        }
        
        /* Custom scrollbar styling */
        .table-container::-webkit-scrollbar {
            height: 10px;
        }
        
        .table-container::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 10px;
        }
        
        .table-container::-webkit-scrollbar-thumb {
            background: linear-gradient(90deg, #6366f1, #8b5cf6);
            border-radius: 10px;
        }
        
        .table-container::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(90deg, #4f46e5, #7c3aed);
        }
    </style>

    <script>
    function openImage(src) { document.getElementById('lightbox-img').src = src; document.getElementById('lightbox').style.display = 'flex'; }
    function closeImage() { document.getElementById('lightbox').style.display = 'none'; }

    function openEditModal(data) {
        document.getElementById('edit_reg_id').value = data.reg_id;
        document.getElementById('edit_student_id').value = data.student_id;
        document.getElementById('edit_sport_id').value = data.sport_id; // Now available
        document.getElementById('edit_student_name').value = data.student_name;
        document.getElementById('edit_tel').value = data.tel;
        document.getElementById('edit_shirt_size').value = data.shirt_size || ''; // Handle null size
        document.getElementById('editModal').style.display = 'flex';
    }
    
    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
    }

    // Close modal if clicked outside
    document.getElementById('editModal').addEventListener('click', function(e) {
        if(e.target === this) closeEditModal();
    });
    </script>
</body>
</html>
