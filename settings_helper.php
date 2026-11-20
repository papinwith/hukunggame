<?php
/*
 * ตัวช่วยอ่าน/เขียนค่าตั้งค่าระบบจากฐานข้อมูล (แก้ได้ที่หน้า admin_settings.php)
 * เรียก ensure_schema($conn) ครั้งเดียว ระบบจะสร้างตาราง/คอลัมน์ที่ต้องใช้ให้อัตโนมัติ
 */

function setting_defaults() {
    return [
        'max_sports_per_student' => '2',
        'registration_open'      => '1',
        'registration_close'     => '2026-02-15 23:59:59',
        'shirt_sizes'            => 'XS,S,M,L,XL,2XL,3XL,4XL,5XL',
        'allowed_image_ext'      => 'jpg,jpeg,png',
        'event_name'             => 'หูกวางเกมส์ 2026',
        'current_year'           => '2026',
        'event_dates'            => 'วันที่ 13 – 28 มีนาคม 2568',
        'card_footer'            => 'บัตรนักกีฬาใช้แสดงตัวในวันแข่งขัน',
    ];
}

function ensure_schema(mysqli $conn) {
    static $done = false;
    if ($done) return;
    $done = true;

    $conn->query("CREATE TABLE IF NOT EXISTS settings (
        setting_key VARCHAR(50) NOT NULL PRIMARY KEY,
        setting_value TEXT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // จำนวนผู้สมัครสูงสุดต่อสีของแต่ละกีฬา (0 = ไม่จำกัด)
    $r = $conn->query("SHOW COLUMNS FROM sport_type LIKE 'max_players'");
    if ($r && $r->num_rows == 0) {
        $conn->query("ALTER TABLE sport_type ADD COLUMN max_players INT NOT NULL DEFAULT 0");
    }

    // ปีที่สมัคร (ใช้แยกแดชบอร์ดรายปี) ข้อมูลเดิมถือเป็นปี 2026
    $r = $conn->query("SHOW COLUMNS FROM sport_registration LIKE 'reg_year'");
    if ($r && $r->num_rows == 0) {
        $conn->query("ALTER TABLE sport_registration ADD COLUMN reg_year INT NOT NULL DEFAULT 2026");
    }

    // ไซส์เสื้อให้แก้ได้จากหลังบ้าน จึงเปลี่ยนจาก enum เป็น varchar
    $r = $conn->query("SHOW COLUMNS FROM player_size LIKE 'shirt_size'");
    if ($r && ($col = $r->fetch_assoc()) && stripos($col['Type'], 'enum') === 0) {
        $conn->query("ALTER TABLE player_size MODIFY shirt_size VARCHAR(20) NOT NULL");
    }

    // รูปต่อ (นักศึกษา, กีฬา, ประเภทรูป) ต้องมีแค่ 1 แถว ไม่งั้นรายชื่อ/บัตรจะขึ้นซ้ำ
    $r = $conn->query("SHOW INDEX FROM image WHERE Key_name='uniq_image'");
    if ($r && $r->num_rows == 0) {
        $conn->query("DELETE i1 FROM image i1 JOIN image i2
            ON i1.student_id=i2.student_id AND i1.sport_id=i2.sport_id AND i1.image_type=i2.image_type AND i1.image_id < i2.image_id");
        $conn->query("ALTER TABLE image ADD UNIQUE KEY uniq_image (student_id, sport_id, image_type)");
    }

    foreach (setting_defaults() as $k => $v) {
        $stmt = $conn->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?,?)");
        $stmt->bind_param("ss", $k, $v);
        $stmt->execute();
        $stmt->close();
    }
}

function get_setting(mysqli $conn, $key, $default = '') {
    $stmt = $conn->prepare("SELECT setting_value FROM settings WHERE setting_key=?");
    $stmt->bind_param("s", $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row) return $row['setting_value'];
    $defs = setting_defaults();
    return $defs[$key] ?? $default;
}

function set_setting(mysqli $conn, $key, $value) {
    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
    $stmt->bind_param("ss", $key, $value);
    $stmt->execute();
    $stmt->close();
}

function setting_list(mysqli $conn, $key) {
    $items = array_map('trim', explode(',', get_setting($conn, $key)));
    return array_values(array_filter($items, fn($x) => $x !== ''));
}

/* ปิดรับสมัครหรือยัง (ปิดด้วยสวิตช์ หรือเลยเวลาปิด) */
function registration_is_open(mysqli $conn) {
    if (get_setting($conn, 'registration_open') !== '1') return false;
    $close = strtotime(get_setting($conn, 'registration_close'));
    return !$close || time() <= $close;
}

/* สีสำหรับแสดงผลจากชื่อสีทีม */
function team_color_hex($name) {
    $map = ['แดง' => '#ef4444', 'เหลือง' => '#fbbf24', 'เขียว' => '#10b981', 'น้ำเงิน' => '#3b82f6', 'ฟ้า' => '#0ea5e9', 'ม่วง' => '#7c3aed', 'ส้ม' => '#ea580c', 'ชมพู' => '#db2777'];
    foreach ($map as $k => $v) if (mb_strpos((string)$name, $k) !== false) return $v;
    return '#6b7280';
}

/* ชื่อคลาส CSS ของการ์ดสีใน tailwind-config.php (green/blue/yellow/red) หรือ '' ถ้าเป็นสีอื่น */
function team_css_key($name) {
    $map = ['เขียว' => 'green', 'น้ำเงิน' => 'blue', 'เหลือง' => 'yellow', 'แดง' => 'red'];
    foreach ($map as $k => $v) if (mb_strpos((string)$name, $k) !== false) return $v;
    return '';
}

/* รายชื่อสีพร้อมคณะในแต่ละสี (ใช้แสดงการ์ดสี) */
function colors_with_faculties(mysqli $conn) {
    $colors = [];
    foreach ($conn->query("SELECT color_id, color_name FROM color_team ORDER BY color_id")->fetch_all(MYSQLI_ASSOC) as $c) {
        $c['faculties'] = [];
        $colors[$c['color_id']] = $c;
    }
    foreach ($conn->query("SELECT faculty_name, color_id FROM faculty ORDER BY faculty_id")->fetch_all(MYSQLI_ASSOC) as $f) {
        if (isset($colors[$f['color_id']])) $colors[$f['color_id']]['faculties'][] = $f['faculty_name'];
    }
    return array_values($colors);
}

/* ลบไฟล์รูป + แถว image/player_size ของนักศึกษาในกีฬานั้น (ใช้ตอนลบผู้สมัคร) */
function delete_registration_extras(mysqli $conn, $student_id, $sport_id) {
    $stmt = $conn->prepare("SELECT image_path FROM image WHERE student_id=? AND sport_id=?");
    $stmt->bind_param("si", $student_id, $sport_id);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $img) {
        $path = __DIR__ . '/' . $img['image_path'];
        if (str_starts_with($img['image_path'], 'uploads/') && is_file($path)) @unlink($path);
    }
    $stmt->close();
    foreach (['image', 'player_size'] as $table) {
        $stmt = $conn->prepare("DELETE FROM $table WHERE student_id=? AND sport_id=?");
        $stmt->bind_param("si", $student_id, $sport_id);
        $stmt->execute();
        $stmt->close();
    }
}

/* จำนวนผู้สมัครปัจจุบัน ต่อสี ของกีฬา (sport_id) */
function registered_count(mysqli $conn, $sport_id, $color_id) {
    $stmt = $conn->prepare("SELECT COUNT(*) c FROM sport_registration WHERE sport_id=? AND color_id=?");
    $stmt->bind_param("ii", $sport_id, $color_id);
    $stmt->execute();
    $c = (int)$stmt->get_result()->fetch_assoc()['c'];
    $stmt->close();
    return $c;
}
