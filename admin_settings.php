<?php
include 'db.php';
require_once 'admin_auth.php';
require_once 'settings_helper.php';
ensure_schema($conn);

$flash = '';
$flash_ok = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_settings') {
        $max = max(1, (int)($_POST['max_sports_per_student'] ?? 2));
        set_setting($conn, 'max_sports_per_student', (string)$max);
        set_setting($conn, 'registration_open', isset($_POST['registration_open']) ? '1' : '0');

        $start = read_datetime24('registration_start');
        $close = read_datetime24('registration_close');
        if ($start === null || $close === null) {
            $flash_ok = false;
            $time_error = 'วันที่หรือเวลาไม่ถูกต้อง (ใช้เวลาแบบ 24 ชั่วโมง 00:00 – 23:59)';
        } elseif ($start !== '' && $close !== '' && strtotime($start) >= strtotime($close)) {
            $flash_ok = false;
            $time_error = 'เวลาเปิด (' . date('d/m/Y H:i', strtotime($start)) . ') ต้องมาก่อนเวลาปิด (' . date('d/m/Y H:i', strtotime($close)) . ')'
                . (strtotime($close) < time() ? ' — เวลาปิดที่ตั้งไว้ผ่านไปแล้ว กรุณาเลื่อนเวลาปิดด้วย' : '');
        } else {
            set_setting($conn, 'registration_start', $start);
            if ($close !== '') set_setting($conn, 'registration_close', $close);
        }
        set_setting($conn, 'admin_session_timeout', (string)min(1440, max(5, (int)($_POST['admin_session_timeout'] ?? 30))));

        $sizes = array_filter(array_map('trim', explode(',', $_POST['shirt_sizes'] ?? '')), fn($x) => $x !== '');
        if ($sizes) set_setting($conn, 'shirt_sizes', implode(',', array_unique($sizes)));

        $exts = array_filter(array_map(fn($x) => strtolower(trim($x)), explode(',', $_POST['allowed_image_ext'] ?? '')), fn($x) => preg_match('/^[a-z0-9]+$/', $x));
        if ($exts) set_setting($conn, 'allowed_image_ext', implode(',', array_unique($exts)));

        set_setting($conn, 'current_year', (string)max(2000, (int)($_POST['current_year'] ?? 2026)));
        set_setting($conn, 'event_name', trim($_POST['event_name'] ?? '') ?: 'หูกวางเกมส์');
        set_setting($conn, 'event_dates', trim($_POST['event_dates'] ?? ''));
        set_setting($conn, 'card_footer', trim($_POST['card_footer'] ?? ''));
        $gsheet = trim($_POST['gsheet_url'] ?? '');
        if ($gsheet === '' || preg_match('#^https://script\.google\.com/#', $gsheet)) set_setting($conn, 'gsheet_url', $gsheet);
        else { $flash_ok = false; }
        if ($flash_ok) $flash = 'บันทึกการตั้งค่าเรียบร้อย';
        elseif (!empty($time_error)) $flash = 'บันทึกแล้ว ยกเว้นเวลารับสมัคร: ' . $time_error;
        else $flash = 'บันทึกแล้ว ยกเว้น URL Google Sheets (ต้องขึ้นต้นด้วย https://script.google.com/)';
    }

    elseif ($action === 'change_password') {
        $old = $_POST['old_password'] ?? ''; $new = $_POST['new_password'] ?? '';
        $stmt = $conn->prepare("SELECT password_hash FROM sport_admins WHERE id=?");
        $stmt->bind_param("i", $_SESSION['admin_id']);
        $stmt->execute();
        $cur_hash = $stmt->get_result()->fetch_assoc()['password_hash'] ?? '';
        $stmt->close();
        if (!password_verify($old, $cur_hash)) { $flash = 'รหัสผ่านเดิมไม่ถูกต้อง'; $flash_ok = false; }
        elseif (strlen($new) < 6) { $flash = 'รหัสผ่านใหม่ต้องยาวอย่างน้อย 6 ตัวอักษร'; $flash_ok = false; }
        elseif ($new !== ($_POST['new_password2'] ?? '')) { $flash = 'รหัสผ่านใหม่ทั้งสองช่องไม่ตรงกัน'; $flash_ok = false; }
        else {
            $new_hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE sport_admins SET password_hash=? WHERE id=?");
            $stmt->bind_param("si", $new_hash, $_SESSION['admin_id']);
            $stmt->execute();
            $stmt->close();
            $flash = 'เปลี่ยนรหัสผ่านเรียบร้อย';
        }
    }

    elseif ($action === 'add_color') {
        $name = trim($_POST['color_name'] ?? '');
        if ($name !== '') {
            $stmt = $conn->prepare("INSERT INTO color_team (color_name) VALUES (?)");
            $stmt->bind_param("s", $name);
            if ($stmt->execute()) $flash = 'เพิ่มสีเรียบร้อย';
            else { $flash = 'เพิ่มสีไม่สำเร็จ (ตาราง color_team ต้องเป็น AUTO_INCREMENT): ' . $conn->error; $flash_ok = false; }
        }
    }
    elseif ($action === 'rename_color') {
        $id = (int)$_POST['color_id']; $name = trim($_POST['color_name'] ?? '');
        if ($name !== '') {
            $stmt = $conn->prepare("UPDATE color_team SET color_name=? WHERE color_id=?");
            $stmt->bind_param("si", $name, $id);
            $stmt->execute();
            $flash = 'แก้ไขชื่อสีเรียบร้อย';
        }
    }
    elseif ($action === 'delete_color') {
        $id = (int)$_POST['color_id'];
        $q = $conn->query("SELECT (SELECT COUNT(*) FROM faculty WHERE color_id=$id)
            + (SELECT COUNT(*) FROM sport_registration WHERE color_id=$id)
            + (SELECT COUNT(*) FROM student WHERE color_id=$id)
            + (SELECT COUNT(*) FROM match_schedule WHERE team1_id=$id OR team2_id=$id) AS used")->fetch_assoc();
        if ($q['used'] > 0) { $flash = 'ลบไม่ได้: สีนี้ยังมีคณะ ผู้สมัคร หรือตารางแข่งใช้งานอยู่'; $flash_ok = false; }
        else { $conn->query("DELETE FROM color_team WHERE color_id=$id"); $flash = 'ลบสีเรียบร้อย'; }
    }

    elseif ($action === 'add_faculty') {
        $name = trim($_POST['faculty_name'] ?? ''); $cid = (int)($_POST['color_id'] ?? 0);
        if ($name !== '' && $cid > 0) {
            $stmt = $conn->prepare("INSERT INTO faculty (faculty_name, color_id) VALUES (?,?)");
            $stmt->bind_param("si", $name, $cid);
            if ($stmt->execute()) $flash = 'เพิ่มคณะเรียบร้อย';
            else { $flash = 'เพิ่มคณะไม่สำเร็จ: ' . $conn->error; $flash_ok = false; }
        }
    }
    elseif ($action === 'update_faculty') {
        $id = (int)$_POST['faculty_id']; $name = trim($_POST['faculty_name'] ?? ''); $cid = (int)$_POST['color_id'];
        if ($name !== '') {
            $stmt = $conn->prepare("UPDATE faculty SET faculty_name=?, color_id=? WHERE faculty_id=?");
            $stmt->bind_param("sii", $name, $cid, $id);
            $stmt->execute();
            $flash = 'แก้ไขคณะเรียบร้อย';
        }
    }
    elseif ($action === 'delete_faculty') {
        $id = (int)$_POST['faculty_id'];
        $q = $conn->query("SELECT COUNT(*) AS used FROM sport_registration WHERE faculty_id=$id")->fetch_assoc();
        if ($q['used'] > 0) { $flash = 'ลบไม่ได้: คณะนี้มีผู้สมัครอยู่'; $flash_ok = false; }
        else { $conn->query("DELETE FROM faculty WHERE faculty_id=$id"); $flash = 'ลบคณะเรียบร้อย'; }
    }
}

$colors = $conn->query("SELECT * FROM color_team ORDER BY color_id")->fetch_all(MYSQLI_ASSOC);
$faculties = $conn->query("SELECT f.*, c.color_name FROM faculty f LEFT JOIN color_team c ON f.color_id=c.color_id ORDER BY f.color_id, f.faculty_name")->fetch_all(MYSQLI_ASSOC);

$s = [];
foreach (array_keys(setting_defaults()) as $k) $s[$k] = get_setting($conn, $k);
// เวลารับสมัครบันทึกไม่ผ่าน: แสดงค่าที่เพิ่งกรอกไว้ ไม่ต้องกรอกใหม่
$keep_dt = !empty($time_error);
$posted_dt = fn($name) => ['date' => $_POST[$name . '_date'] ?? '', 'time' => $_POST[$name . '_time'] ?? ''];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตั้งค่าระบบ - Admin</title>
    <?php include 'tailwind-config.php'; ?>
</head>
<body>
<div class="container" style="max-width: 900px;">
    <div class="text-center mb-8 animate-fade">
        <h1 class="text-2xl font-bold mb-2">🛠️ ตั้งค่าระบบ</h1>
        <p class="text-gray">แก้ค่าต่าง ๆ ได้จากหลังบ้าน ไม่ต้องแก้โค้ด</p>
    </div>

    <?php if ($flash): ?>
    <div class="notice mb-6" style="background: <?= $flash_ok ? '#ecfdf5' : '#fef2f2' ?>; color: <?= $flash_ok ? '#059669' : '#dc2626' ?>;">
        <?= $flash_ok ? '✅' : '❌' ?> <?= htmlspecialchars($flash) ?>
    </div>
    <?php endif; ?>

    <!-- General settings -->
    <div class="card mb-8">
        <div class="header-gradient"><h2 style="font-size: 18px; font-weight: 700;">⚙️ ตั้งค่าทั่วไป</h2></div>
        <div class="p-6">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="save_settings">

                <div class="mb-4">
                    <label class="form-label">ชื่องาน</label>
                    <input type="text" name="event_name" value="<?= htmlspecialchars($s['event_name']) ?>" class="form-input">
                </div>
                <div class="mb-4">
                    <label class="form-label">ข้อความวันแข่งขัน (แสดงบนหน้าสมัคร)</label>
                    <input type="text" name="event_dates" value="<?= htmlspecialchars($s['event_dates']) ?>" class="form-input">
                </div>
                <div class="mb-4">
                    <label class="form-label">ปีที่เปิดรับสมัครปัจจุบัน (ค.ศ. ใช้แยกแดชบอร์ดรายปี)</label>
                    <input type="number" name="current_year" min="2000" value="<?= (int)$s['current_year'] ?>" class="form-input">
                </div>
                <div class="mb-4">
                    <label class="form-label">สมัครได้สูงสุดกี่กีฬาต่อคน</label>
                    <input type="number" name="max_sports_per_student" min="1" value="<?= (int)$s['max_sports_per_student'] ?>" class="form-input">
                </div>
                <?php $state = registration_state($conn); ?>
                <div class="mb-4" style="padding:10px 14px; border-radius:12px; background:<?= $state === 'open' ? '#ecfdf5' : ($state === 'not_started' ? '#fffbeb' : '#fef2f2') ?>;">
                    สถานะตอนนี้: <b><?= $state === 'open' ? '🟢 เปิดรับสมัครอยู่' : ($state === 'not_started' ? '🟡 ยังไม่ถึงเวลาเปิดรับสมัคร' : '🔴 ปิดรับสมัคร') ?></b>
                </div>
                <div class="mb-4">
                    <label class="form-label">วัน-เวลาเปิดรับสมัคร (เว้นว่าง = เปิดทันที, เวลาแบบ 24 ชม.)</label>
                    <?= datetime24_input('registration_start', $s['registration_start'], $keep_dt ? $posted_dt('registration_start') : null) ?>
                </div>
                <div class="mb-4">
                    <label class="form-label">วัน-เวลาปิดรับสมัคร (เวลาแบบ 24 ชม.)</label>
                    <?= datetime24_input('registration_close', $s['registration_close'], $keep_dt ? $posted_dt('registration_close') : null) ?>
                </div>
                <div class="mb-4">
                    <label style="cursor:pointer; font-weight:600;">
                        <input type="checkbox" name="registration_open" value="1" <?= $s['registration_open'] === '1' ? 'checked' : '' ?>>
                        เปิดรับสมัคร (ติ๊กออกเพื่อปิดรับสมัครทันที)
                    </label>
                </div>
                <div class="mb-4">
                    <label class="form-label">ออกจากระบบ Admin อัตโนมัติเมื่อไม่ได้ใช้งาน (นาที, 5–1440)</label>
                    <input type="number" name="admin_session_timeout" min="5" max="1440" value="<?= (int)$s['admin_session_timeout'] ?>" class="form-input">
                </div>
                <div class="mb-4">
                    <label class="form-label">ไซส์เสื้อ (คั่นด้วยจุลภาค)</label>
                    <input type="text" name="shirt_sizes" value="<?= htmlspecialchars($s['shirt_sizes']) ?>" class="form-input">
                </div>
                <div class="mb-4">
                    <label class="form-label">นามสกุลไฟล์รูปที่อนุญาต (คั่นด้วยจุลภาค)</label>
                    <input type="text" name="allowed_image_ext" value="<?= htmlspecialchars($s['allowed_image_ext']) ?>" class="form-input">
                </div>
                <div class="mb-4">
                    <label class="form-label">ข้อความท้ายบัตรนักกีฬา</label>
                    <input type="text" name="card_footer" value="<?= htmlspecialchars($s['card_footer']) ?>" class="form-input">
                </div>
                <div class="mb-6">
                    <label class="form-label">URL Google Apps Script สำหรับ Sync Google Sheets (เว้นว่าง = ปิดปุ่ม Sync)</label>
                    <input type="url" name="gsheet_url" value="<?= htmlspecialchars($s['gsheet_url']) ?>" placeholder="https://script.google.com/macros/s/.../exec" class="form-input">
                    <p class="text-gray text-sm" style="margin-top:4px;">วิธีสร้าง Script ดูได้ในคู่มือ (manual.html) หน้า 7</p>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">💾 บันทึกการตั้งค่า</button>
            </form>
        </div>
    </div>

    <!-- Admin password -->
    <div class="card mb-8">
        <div class="header-gradient"><h2 style="font-size: 18px; font-weight: 700;">🔐 เปลี่ยนรหัสผ่านของฉัน (<?= htmlspecialchars($_SESSION['admin_username'] ?? '') ?>)</h2></div>
        <div class="p-6">
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="change_password">
                <div class="mb-4">
                    <label class="form-label">รหัสผ่านเดิม</label>
                    <input type="password" name="old_password" required class="form-input">
                </div>
                <div class="mb-4">
                    <label class="form-label">รหัสผ่านใหม่ (อย่างน้อย 6 ตัว)</label>
                    <input type="password" name="new_password" required class="form-input">
                </div>
                <div class="mb-6">
                    <label class="form-label">ยืนยันรหัสผ่านใหม่</label>
                    <input type="password" name="new_password2" required class="form-input">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">🔑 เปลี่ยนรหัสผ่าน</button>
            </form>
            <p class="text-center text-sm mt-4"><a href="admin_accounts.php" style="color:#6366f1;">👤 จัดการบัญชี Admin อื่น ๆ</a></p>
        </div>
    </div>

    <!-- Colors -->
    <div class="card mb-8">
        <div class="header-gradient"><h2 style="font-size: 18px; font-weight: 700;">🎨 สีทีม</h2></div>
        <div class="p-6">
            <?php foreach ($colors as $c): ?>
            <form method="post" style="display:flex; gap:8px; margin-bottom:8px; align-items:center; flex-wrap:wrap;">
                <?= csrf_field() ?>
                <input type="hidden" name="color_id" value="<?= $c['color_id'] ?>">
                <input type="text" name="color_name" value="<?= htmlspecialchars($c['color_name']) ?>" class="form-input" style="flex:1; min-width:140px;">
                <button name="action" value="rename_color" class="btn btn-primary" style="padding:8px 16px;">บันทึก</button>
                <button name="action" value="delete_color" class="btn-back" style="padding:8px 16px; border:none; cursor:pointer; color:#dc2626;" onclick="return confirm('ลบสีนี้?')">🗑️ ลบ</button>
            </form>
            <?php endforeach; ?>
            <form method="post" style="display:flex; gap:8px; margin-top:16px; flex-wrap:wrap;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_color">
                <input type="text" name="color_name" placeholder="ชื่อสีใหม่ เช่น สีม่วง" required class="form-input" style="flex:1; min-width:140px;">
                <button class="btn btn-primary" style="padding:8px 16px;">➕ เพิ่มสี</button>
            </form>
        </div>
    </div>

    <!-- Faculties -->
    <div class="card mb-8">
        <div class="header-gradient"><h2 style="font-size: 18px; font-weight: 700;">🏫 คณะ</h2></div>
        <div class="p-6">
            <?php foreach ($faculties as $f): ?>
            <form method="post" style="display:flex; gap:8px; margin-bottom:8px; align-items:center; flex-wrap:wrap;">
                <?= csrf_field() ?>
                <input type="hidden" name="faculty_id" value="<?= $f['faculty_id'] ?>">
                <input type="text" name="faculty_name" value="<?= htmlspecialchars($f['faculty_name']) ?>" class="form-input" style="flex:1; min-width:160px;">
                <select name="color_id" class="form-input" style="width:auto;">
                    <?php foreach ($colors as $c): ?>
                    <option value="<?= $c['color_id'] ?>" <?= $c['color_id'] == $f['color_id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['color_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button name="action" value="update_faculty" class="btn btn-primary" style="padding:8px 16px;">บันทึก</button>
                <button name="action" value="delete_faculty" class="btn-back" style="padding:8px 16px; border:none; cursor:pointer; color:#dc2626;" onclick="return confirm('ลบคณะนี้?')">🗑️ ลบ</button>
            </form>
            <?php endforeach; ?>
            <form method="post" style="display:flex; gap:8px; margin-top:16px; flex-wrap:wrap;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add_faculty">
                <input type="text" name="faculty_name" placeholder="ชื่อคณะใหม่" required class="form-input" style="flex:1; min-width:160px;">
                <select name="color_id" required class="form-input" style="width:auto;">
                    <?php foreach ($colors as $c): ?>
                    <option value="<?= $c['color_id'] ?>"><?= htmlspecialchars($c['color_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-primary" style="padding:8px 16px;">➕ เพิ่มคณะ</button>
            </form>
        </div>
    </div>

    <p class="text-center text-gray text-sm mb-6">💡 จำนวนผู้สมัครสูงสุดต่อสีของแต่ละกีฬา ตั้งได้ที่หน้า "แก้ไขกีฬา"</p>

    <div class="text-center mb-8">
        <a href="homeadmin.php" class="btn-back">🏠 Admin Home</a>
    </div>
</div>
</body>
</html>
