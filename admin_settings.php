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

        $close = trim($_POST['registration_close'] ?? '');
        if ($close !== '') set_setting($conn, 'registration_close', str_replace('T', ' ', $close) . (strlen($close) == 16 ? ':00' : ''));

        $sizes = array_filter(array_map('trim', explode(',', $_POST['shirt_sizes'] ?? '')), fn($x) => $x !== '');
        if ($sizes) set_setting($conn, 'shirt_sizes', implode(',', array_unique($sizes)));

        $exts = array_filter(array_map(fn($x) => strtolower(trim($x)), explode(',', $_POST['allowed_image_ext'] ?? '')), fn($x) => preg_match('/^[a-z0-9]+$/', $x));
        if ($exts) set_setting($conn, 'allowed_image_ext', implode(',', array_unique($exts)));

        set_setting($conn, 'current_year', (string)max(2000, (int)($_POST['current_year'] ?? 2026)));
        set_setting($conn, 'event_name', trim($_POST['event_name'] ?? '') ?: 'หูกวางเกมส์');
        set_setting($conn, 'event_dates', trim($_POST['event_dates'] ?? ''));
        set_setting($conn, 'card_footer', trim($_POST['card_footer'] ?? ''));
        $flash = 'บันทึกการตั้งค่าเรียบร้อย';
    }

    elseif ($action === 'change_password') {
        $old = $_POST['old_password'] ?? ''; $new = $_POST['new_password'] ?? '';
        if (!password_verify($old, get_setting($conn, 'admin_password_hash', ''))) { $flash = 'รหัสผ่านเดิมไม่ถูกต้อง'; $flash_ok = false; }
        elseif (strlen($new) < 6) { $flash = 'รหัสผ่านใหม่ต้องยาวอย่างน้อย 6 ตัวอักษร'; $flash_ok = false; }
        elseif ($new !== ($_POST['new_password2'] ?? '')) { $flash = 'รหัสผ่านใหม่ทั้งสองช่องไม่ตรงกัน'; $flash_ok = false; }
        else { set_setting($conn, 'admin_password_hash', password_hash($new, PASSWORD_DEFAULT)); $flash = 'เปลี่ยนรหัสผ่านเรียบร้อย'; }
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
$close_local = $s['registration_close'] ? date('Y-m-d\TH:i', strtotime($s['registration_close'])) : '';
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
                <div class="mb-4">
                    <label class="form-label">วัน-เวลาปิดรับสมัคร</label>
                    <input type="datetime-local" name="registration_close" value="<?= $close_local ?>" class="form-input">
                </div>
                <div class="mb-4">
                    <label style="cursor:pointer; font-weight:600;">
                        <input type="checkbox" name="registration_open" value="1" <?= $s['registration_open'] === '1' ? 'checked' : '' ?>>
                        เปิดรับสมัคร (ติ๊กออกเพื่อปิดรับสมัครทันที)
                    </label>
                </div>
                <div class="mb-4">
                    <label class="form-label">ไซส์เสื้อ (คั่นด้วยจุลภาค)</label>
                    <input type="text" name="shirt_sizes" value="<?= htmlspecialchars($s['shirt_sizes']) ?>" class="form-input">
                </div>
                <div class="mb-4">
                    <label class="form-label">นามสกุลไฟล์รูปที่อนุญาต (คั่นด้วยจุลภาค)</label>
                    <input type="text" name="allowed_image_ext" value="<?= htmlspecialchars($s['allowed_image_ext']) ?>" class="form-input">
                </div>
                <div class="mb-6">
                    <label class="form-label">ข้อความท้ายบัตรนักกีฬา</label>
                    <input type="text" name="card_footer" value="<?= htmlspecialchars($s['card_footer']) ?>" class="form-input">
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%;">💾 บันทึกการตั้งค่า</button>
            </form>
        </div>
    </div>

    <!-- Admin password -->
    <div class="card mb-8">
        <div class="header-gradient"><h2 style="font-size: 18px; font-weight: 700;">🔐 เปลี่ยนรหัสผ่าน Admin</h2></div>
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
