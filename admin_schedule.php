<?php
include 'db.php';
require_once 'admin_auth.php';
date_default_timezone_set('Asia/Bangkok');

$action = $_GET['action'] ?? '';
$get_match_id = $_GET['match_id'] ?? '';
$error = '';
$success = $_GET['success'] ?? '';

function e($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }

/* DELETE (POST เท่านั้น) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $del_id = (int)($_POST['match_id'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM match_schedule WHERE match_id=?");
    $stmt->bind_param("i", $del_id);
    if($stmt->execute() && $stmt->affected_rows > 0){
        header("Location: admin_schedule.php?success=".urlencode('ลบข้อมูลสำเร็จ'));
        exit();
    }
    $error = "❌ ไม่พบรายการที่จะลบ";
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $match_id = $_POST['match_id'] ?? '';
    $match_date = $_POST['match_date'];
    $match_no = $_POST['match_no'];
    $sport_name = $_POST['sport_name'];
    $category = $_POST['category'];
    $round_name = $_POST['round_name'];
    $start_time = $_POST['start_time'];
    $team1_id = $_POST['team1_id'];
    $team2_id = $_POST['team2_id'];
    $venue = $_POST['venue'];
    $result = $_POST['result'];
    $note = $_POST['note'];

    if ($team1_id == $team2_id) { $error = "❌ ทีมต้องไม่ซ้ำกัน"; }

    $stmt = $conn->prepare("SELECT sport_id FROM sport_type WHERE sport_name=? AND category=? LIMIT 1");
    $stmt->bind_param("ss", $sport_name, $category);
    $stmt->execute();
    $sport = $stmt->get_result()->fetch_assoc();
    $sport_id = $sport['sport_id'] ?? null;

    if (!$sport_id && !$error) { $error = "❌ ไม่พบกีฬาหรือประเภท"; }

    if (!$error) {
        if ($match_id !== '') {
            $stmt = $conn->prepare("UPDATE match_schedule SET match_date=?,match_no=?,sport_id=?,category=?,round_name=?,start_time=?,team1_id=?,team2_id=?,venue=?,result=?,note=? WHERE match_id=?");
            $stmt->bind_param("siisssiisssi",$match_date,$match_no,$sport_id,$category,$round_name,$start_time,$team1_id,$team2_id,$venue,$result,$note,$match_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO match_schedule (match_date,match_no,sport_id,category,round_name,start_time,team1_id,team2_id,venue,result,note) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
            $stmt->bind_param("siisssiisss",$match_date,$match_no,$sport_id,$category,$round_name,$start_time,$team1_id,$team2_id,$venue,$result,$note);
        }
        if($stmt->execute()){
            $msg = ($match_id !== '') ? 'อัปเดตสำเร็จ' : 'เพิ่มสำเร็จ';
            header("Location: admin_schedule.php?success=".urlencode($msg));
            exit();
        }
        $error = "❌ บันทึกไม่สำเร็จ";
    }
}

$sports = [];
$res = $conn->query("SELECT sport_name, GROUP_CONCAT(DISTINCT category) AS categories FROM sport_type GROUP BY sport_name ORDER BY sport_name");
if($res) { while($r = $res->fetch_assoc()){ $sports[$r['sport_name']] = explode(',', $r['categories']); } }

$teams = $conn->query("SELECT * FROM color_team");

$where = "";
if ($action === 'edit' && $get_match_id !== '') { $where = " WHERE m.match_id = " . intval($get_match_id); }

$matches = $conn->query("SELECT m.*, s.sport_name, t1.color_name as team1_name, t2.color_name as team2_name FROM match_schedule m LEFT JOIN sport_type s ON m.sport_id = s.sport_id LEFT JOIN color_team t1 ON m.team1_id = t1.color_id LEFT JOIN color_team t2 ON m.team2_id = t2.color_id $where ORDER BY m.match_date ASC, CAST(m.match_no AS UNSIGNED) ASC");

$edit_match = null;
if ($action === 'edit' && $get_match_id !== '') {
    $stmt = $conn->prepare("SELECT m.*, s.sport_name FROM match_schedule m LEFT JOIN sport_type s ON m.sport_id = s.sport_id WHERE m.match_id=?");
    $stmt->bind_param("i", $get_match_id);
    $stmt->execute();
    $edit_match = $stmt->get_result()->fetch_assoc();
}

// บันทึกไม่ผ่าน: แสดงค่าที่กรอกไว้เดิม ไม่ต้องกรอกใหม่
$form = $edit_match;
if ($error && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'delete') {
    $form = $_POST;
}
$is_edit = !empty($form['match_id']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการตารางแข่งขัน - Admin</title>
    <?php include 'tailwind-config.php'; ?>
</head>

<body>
    <div class="container">

        <!-- Header -->
        <div class="text-center mb-6 animate-fade">
            <h1 class="text-2xl font-bold mb-2">📅 จัดการตารางแข่งขัน</h1>
        </div>

        <!-- Messages -->
        <?php if($error): ?>
        <div style="background: #fef2f2; border: 1px solid #fca5a5; color: #dc2626; border-radius: 16px; padding: 16px; margin-bottom: 16px; display: flex; align-items: center; gap: 12px; max-width: 800px; margin: 0 auto 16px;">
            <span style="font-size: 20px;">❌</span>
            <span class="font-medium"><?= e($error) ?></span>
        </div>
        <?php endif; ?>

        <?php if($success): ?>
        <div style="background: #ecfdf5; border: 1px solid #a7f3d0; color: #059669; border-radius: 16px; padding: 16px; margin-bottom: 16px; display: flex; align-items: center; gap: 12px; max-width: 800px; margin: 0 auto 16px;">
            <span style="font-size: 20px;">✅</span>
            <span class="font-medium"><?= e($success) ?></span>
        </div>
        <?php endif; ?>

        <!-- Form -->
        <div class="card mb-8" style="max-width: 800px; margin: 0 auto 32px;" id="editForm">
            <div class="header-gradient">
                <h2 style="font-size: 18px; font-weight: 700;"><?= $is_edit ? '✏️ แก้ไขข้อมูล' : '➕ เพิ่มตารางแข่งขัน' ?></h2>
            </div>

            <form method="post" class="p-6">
                <?= csrf_field() ?>
                <input type="hidden" name="match_id" value="<?= e($form['match_id'] ?? '') ?>">

                <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 16px;">
                    <div>
                        <label class="form-label">วันที่</label>
                        <input type="date" name="match_date" required value="<?= e($form['match_date'] ?? '') ?>" class="form-input" style="padding: 12px;">
                    </div>
                    <div>
                        <label class="form-label">แมทช์</label>
                        <input type="number" name="match_no" required value="<?= e($form['match_no'] ?? '') ?>" class="form-input" style="padding: 12px;">
                    </div>
                    <div>
                        <label class="form-label">เวลา</label>
                        <input type="time" name="start_time" required value="<?= !empty($form['start_time']) ? date('H:i',strtotime($form['start_time'])) : '' ?>" class="form-input" style="padding: 12px;">
                    </div>
                    <div>
                        <label class="form-label">รอบ</label>
                        <input type="text" name="round_name" value="<?= e($form['round_name'] ?? '') ?>" class="form-input" style="padding: 12px;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                    <div>
                        <label class="form-label">กีฬา</label>
                        <select name="sport_name" id="sport_name" required class="form-input" style="padding: 12px;">
                            <option value="">-- เลือกกีฬา --</option>
                            <?php foreach($sports as $name=>$cats): ?>
                            <option value="<?= e($name) ?>" <?= ($form && ($form['sport_name'] ?? '')==$name)?'selected':'' ?>><?= e($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">ประเภท</label>
                        <select name="category" id="category" required class="form-input" style="padding: 12px;">
                            <option value="">-- เลือกประเภท --</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                    <div>
                        <label class="form-label">ทีม 1</label>
                        <select name="team1_id" class="form-input" style="padding: 12px;">
                            <?php while($t=$teams->fetch_assoc()): ?>
                            <option value="<?= $t['color_id'] ?>" <?= ($form && ($form['team1_id'] ?? '')==$t['color_id'])?'selected':'' ?>><?= e($t['color_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">ทีม 2</label>
                        <select name="team2_id" class="form-input" style="padding: 12px;">
                            <?php $teams->data_seek(0); while($t=$teams->fetch_assoc()): ?>
                            <option value="<?= $t['color_id'] ?>" <?= ($form && ($form['team2_id'] ?? '')==$t['color_id'])?'selected':'' ?>><?= e($t['color_name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                    <div>
                        <label class="form-label">สนาม</label>
                        <input type="text" name="venue" value="<?= e($form['venue'] ?? '') ?>" class="form-input" style="padding: 12px;">
                    </div>
                    <div>
                        <label class="form-label">ผล</label>
                        <input type="text" name="result" value="<?= e($form['result'] ?? '') ?>" class="form-input" style="padding: 12px;">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label">หมายเหตุ</label>
                    <textarea name="note" rows="2" class="form-input" style="resize: none;"><?= e($form['note'] ?? '') ?></textarea>
                </div>

                <div style="display: flex; gap: 12px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;"><?= $is_edit ? '💾 อัปเดต' : '➕ เพิ่ม' ?></button>
                    <?php if($is_edit): ?>
                    <a href="admin_schedule.php" class="btn-back">ยกเลิก</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Table -->
        <?php if($matches && $matches->num_rows > 0): ?>
        <div class="table-container mb-8">
            <table>
                <thead>
                    <tr>
                        <th>วันที่</th>
                        <th>แมทช์</th>
                        <th>กีฬา</th>
                        <th>ประเภท</th>
                        <th>ทีม 1</th>
                        <th>ทีม 2</th>
                        <th>เวลา</th>
                        <th>สนาม</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($m=$matches->fetch_assoc()): ?>
                    <tr style="<?= ($edit_match && $edit_match['match_id']==$m['match_id'])?'background:#fef3c7;':''; ?>">
                        <td><?= e($m['match_date']) ?></td>
                        <td style="text-align: center;"><?= e($m['match_no']) ?></td>
                        <td><?= e($m['sport_name']) ?></td>
                        <td><?= e($m['category']) ?></td>
                        <td>
                            <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: <?= team_color_hex($m['team1_name'] ?? '') ?>; margin-right: 6px;"></span>
                            <?= e($m['team1_name']) ?>
                        </td>
                        <td>
                            <span style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; background: <?= team_color_hex($m['team2_name'] ?? '') ?>; margin-right: 6px;"></span>
                            <?= e($m['team2_name']) ?>
                        </td>
                        <td style="text-align: center; font-weight: 600;"><?= date('H.i', strtotime($m['start_time'])) ?></td>
                        <td><?= e($m['venue']) ?></td>
                        <td style="text-align: center;">
                            <a href="?action=edit&match_id=<?= $m['match_id'] ?>#editForm" style="color: #f59e0b; font-weight: 700; text-decoration: none; margin-right: 8px;">✏️</a>
                            <form method="post" style="display: inline;" onsubmit="return confirm('ลบ?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="match_id" value="<?= $m['match_id'] ?>">
                                <button type="submit" style="background: none; border: none; cursor: pointer; color: #ef4444; font-weight: 700;">🗑️</button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- Back Button -->
        <div class="text-center">
            <a href="homeadmin.php" class="btn-back">🏠 หน้าหลัก Admin</a>
        </div>
    </div>

    <script>
    const sports = <?= json_encode($sports, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_AMP); ?>;
    const sportSel = document.getElementById('sport_name');
    const catSel = document.getElementById('category');

    function loadCategory(selected=''){
        catSel.innerHTML = '<option value="">-- เลือกประเภท --</option>';
        if(!sports[sportSel.value]) return;
        sports[sportSel.value].forEach(c=>{
            const o=document.createElement('option');
            o.value=c; o.textContent=c;
            if(c===selected) o.selected=true;
            catSel.appendChild(o);
        });
    }
    sportSel.addEventListener('change',()=>loadCategory());
    <?php if(!empty($form['category'])): ?>loadCategory(<?= json_encode($form['category'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_APOS | JSON_HEX_AMP) ?>);<?php endif; ?>
    </script>
</body>
</html>
