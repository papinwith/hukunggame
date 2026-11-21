<?php
/*
 * ตัวช่วยอ่าน/เขียนค่าตั้งค่าระบบจากฐานข้อมูล (แก้ได้ที่หน้า admin_settings.php)
 * เรียก ensure_schema($conn) ครั้งเดียว ระบบจะสร้างตาราง/คอลัมน์ที่ต้องใช้ให้อัตโนมัติ
 */

function setting_defaults() {
    return [
        'max_sports_per_student' => '2',
        'registration_open'      => '1',
        'registration_start'     => '',   // ว่าง = เปิดรับสมัครทันที
        'registration_close'     => '2026-02-15 23:59:59',
        'admin_session_timeout'  => '30', // นาที: ไม่ใช้งานนานเกินนี้ต้องล็อกอินใหม่
        'shirt_sizes'            => 'XS,S,M,L,XL,2XL,3XL,4XL,5XL',
        'allowed_image_ext'      => 'jpg,jpeg,png',
        'event_name'             => 'หูกวางเกมส์ 2026',
        'current_year'           => '2026',
        'event_dates'            => 'วันที่ 13 – 28 มีนาคม 2568',
        'card_footer'            => 'บัตรนักกีฬาใช้แสดงตัวในวันแข่งขัน',
        // URL ของ Google Apps Script (Web app) สำหรับปุ่ม Sync Google Sheets ในหน้ารายชื่อ (ว่าง = ปิดปุ่ม)
        'gsheet_url'             => 'https://script.google.com/macros/s/AKfycbycq2EUNGnghdp67Z1GUqisHt9FjRV4pf9R9S3quqt3HyKwgMa1wNfoQxephXmh7W3_pQ/exec',
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
    // (ฐานข้อมูลจากระบบ sport เดิมใช้ชื่อ index uniq_student_sport_type ซึ่งทำหน้าที่เดียวกัน)
    if (!index_exists($conn, 'image', 'uniq_image') && !index_exists($conn, 'image', 'uniq_student_sport_type')) {
        $conn->query("DELETE i1 FROM image i1 JOIN image i2
            ON i1.student_id=i2.student_id AND i1.sport_id=i2.sport_id AND i1.image_type=i2.image_type AND i1.image_id < i2.image_id");
        $conn->query("ALTER TABLE image ADD UNIQUE KEY uniq_image (student_id, sport_id, image_type)");
    }

    // สถานะรับเสื้อ / เช็คชื่อเข้าแข่ง (ใช้ในหน้า search.php และ checkin_today.php)
    $status_cols = [
        'shirt_received'    => 'TINYINT(1) DEFAULT 0',
        'shirt_received_at' => 'DATETIME DEFAULT NULL',
        'attendance'        => 'TINYINT(1) DEFAULT 0',
        'attendance_at'     => 'DATETIME DEFAULT NULL',
    ];
    foreach ($status_cols as $col => $def) {
        $r = $conn->query("SHOW COLUMNS FROM sport_registration LIKE '$col'");
        if ($r && $r->num_rows == 0) {
            $conn->query("ALTER TABLE sport_registration ADD COLUMN $col $def");
        }
    }

    // นักศึกษา 1 คน สมัครกีฬาเดิมได้ครั้งเดียว: ล้างแถวซ้ำ (รวมสถานะไว้ที่แถวแรก) แล้วเพิ่ม unique key
    if (!index_exists($conn, 'sport_registration', 'uniq_student_sport')) {
        $conn->query("UPDATE sport_registration sr
            JOIN (
                SELECT MIN(reg_id) AS keep_id,
                       MAX(shirt_received) AS shirt_received, MAX(shirt_received_at) AS shirt_received_at,
                       MAX(attendance) AS attendance, MAX(attendance_at) AS attendance_at
                FROM sport_registration GROUP BY student_id, sport_id
            ) dup ON sr.reg_id = dup.keep_id
            SET sr.shirt_received = dup.shirt_received,
                sr.shirt_received_at = COALESCE(dup.shirt_received_at, sr.shirt_received_at),
                sr.attendance = dup.attendance,
                sr.attendance_at = COALESCE(dup.attendance_at, sr.attendance_at)");
        $conn->query("DELETE sr_old FROM sport_registration sr_old
            JOIN sport_registration sr_keep
              ON sr_old.student_id = sr_keep.student_id AND sr_old.sport_id = sr_keep.sport_id AND sr_old.reg_id > sr_keep.reg_id");
        $conn->query("ALTER TABLE sport_registration ADD UNIQUE KEY uniq_student_sport (student_id, sport_id)");
    }

    // ประวัติเช็คชื่อเข้าแข่งรายวัน
    $conn->query("CREATE TABLE IF NOT EXISTS sport_attendance (
        attendance_id INT(11) NOT NULL AUTO_INCREMENT,
        reg_id INT(11) NOT NULL,
        match_id INT(11) DEFAULT NULL,
        attendance_date DATE NOT NULL,
        checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (attendance_id),
        UNIQUE KEY uniq_reg_date (reg_id, attendance_date),
        KEY idx_match_id (match_id),
        CONSTRAINT sport_attendance_ibfk_1 FOREIGN KEY (reg_id) REFERENCES sport_registration (reg_id) ON DELETE CASCADE,
        CONSTRAINT sport_attendance_ibfk_2 FOREIGN KEY (match_id) REFERENCES match_schedule (match_id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    // บัญชี Admin (หลายบัญชี) — ใช้ตารางเดียวกับระบบ sport เดิม
    $conn->query("CREATE TABLE IF NOT EXISTS sport_admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    // ย้ายรหัสผ่าน Admin แบบเดิม (รหัสเดียวใน settings) มาเป็นบัญชี "Admin" ถ้ายังไม่มีบัญชีใดเลย
    $r = $conn->query("SELECT COUNT(*) c FROM sport_admins");
    if ($r && (int)$r->fetch_assoc()['c'] === 0) {
        $r = $conn->query("SELECT setting_value FROM settings WHERE setting_key='admin_password_hash'");
        $legacy = $r ? $r->fetch_assoc() : null;
        if ($legacy && $legacy['setting_value'] !== '') {
            $stmt = $conn->prepare("INSERT INTO sport_admins (username, password_hash) VALUES ('Admin', ?)");
            $stmt->bind_param("s", $legacy['setting_value']);
            $stmt->execute();
            $stmt->close();
        }
    }

    foreach (setting_defaults() as $k => $v) {
        $stmt = $conn->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?,?)");
        $stmt->bind_param("ss", $k, $v);
        $stmt->execute();
        $stmt->close();
    }
}

function index_exists(mysqli $conn, $table, $index) {
    $index = $conn->real_escape_string($index);
    $r = $conn->query("SHOW INDEX FROM `$table` WHERE Key_name='$index'");
    return $r instanceof mysqli_result && $r->num_rows > 0;
}

/* ===== เวลาแบบ 24 ชั่วโมง (00:00–23:59) =====
 * ไม่ใช้ <input type="time"> / datetime-local เพราะเบราว์เซอร์จะแสดง AM/PM ตามภาษาเครื่อง */

/* แปลง "9:30", "09.30", "0930" -> "09:30" ; ไม่ถูกต้องคืน null */
function normalize_time24($s) {
    $s = trim((string)$s);
    if (!preg_match('/^([01]?\d|2[0-3])[:.]?([0-5]\d)$/', $s, $m)) return null;
    return sprintf('%02d:%s', (int)$m[1], $m[2]);
}

/* ช่องกรอกเวลา 24 ชม. */
function time24_input($name, $value = '', $required = false, $extra = '') {
    $v = $value !== '' && $value !== null ? date('H:i', strtotime($value)) : '';
    return '<input type="text" name="' . htmlspecialchars($name) . '" value="' . $v . '"'
        . ' placeholder="HH:MM (เช่น 16:30)" inputmode="numeric" maxlength="5" autocomplete="off"'
        . ' pattern="([01]?[0-9]|2[0-3])[:.]?[0-5][0-9]" title="เวลาแบบ 24 ชั่วโมง 00:00 – 23:59"'
        . ' class="form-input time24"' . ($required ? ' required' : '') . ($extra ? ' ' . $extra : '') . '>';
}

/* ช่องกรอกวันที่ + เวลา 24 ชม. (ส่งเป็น {name}_date และ {name}_time) */
function datetime24_input($name, $value = '', $raw = null) {
    $ts = $value ? strtotime($value) : 0;
    $d = $raw !== null ? $raw['date'] : ($ts ? date('Y-m-d', $ts) : '');
    if ($raw !== null) {
        $t = normalize_time24($raw['time']) ?? '';
        $time_html = str_replace('value=""', 'value="' . htmlspecialchars($t) . '"', time24_input($name . '_time', ''));
    } else {
        $time_html = time24_input($name . '_time', $ts ? date('H:i', $ts) : '');
    }
    return '<div style="display:flex; gap:8px; flex-wrap:wrap;">'
        . '<input type="date" name="' . htmlspecialchars($name) . '_date" value="' . htmlspecialchars($d) . '" class="form-input" style="flex:1; min-width:150px;">'
        . '<div style="width:140px;">' . $time_html . '</div>'
        . '</div>';
}

/* อ่านค่าจาก datetime24_input -> "Y-m-d H:i:00" ; ว่างทั้งคู่คืน '' ; ไม่ถูกต้องคืน null */
function read_datetime24($name) {
    $d = trim($_POST[$name . '_date'] ?? '');
    $t = trim($_POST[$name . '_time'] ?? '');
    if ($d === '' && $t === '') return '';
    $t = $t === '' ? '00:00' : normalize_time24($t);
    if ($t === null || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return null;
    return "$d $t:00";
}

/* ล้าง session + ลบ cookie ของ session ออกจากเบราว์เซอร์ (ใช้ตอน logout / หมดเวลา) */
function admin_session_destroy() {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $p['path'], 'domain' => $p['domain'],
            'secure' => $p['secure'], 'httponly' => $p['httponly'], 'samesite' => $p['samesite'] ?? 'Lax']);
    }
    session_destroy();
}

/* จำนวนบัญชี Admin ทั้งหมด (0 = ยังไม่เคยตั้งค่า ต้องสร้างบัญชีแรก) */
function admin_count(mysqli $conn) {
    $r = $conn->query("SELECT COUNT(*) c FROM sport_admins");
    return $r ? (int)$r->fetch_assoc()['c'] : 0;
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

/* สถานะรับสมัคร: 'closed' (ปิดด้วยสวิตช์ หรือเลยเวลาปิด), 'not_started' (ยังไม่ถึงเวลาเปิด), 'open' */
function registration_state(mysqli $conn) {
    if (get_setting($conn, 'registration_open') !== '1') return 'closed';
    $start = strtotime(get_setting($conn, 'registration_start'));
    if ($start && time() < $start) return 'not_started';
    $close = strtotime(get_setting($conn, 'registration_close'));
    if ($close && time() > $close) return 'closed';
    return 'open';
}

function registration_is_open(mysqli $conn) {
    return registration_state($conn) === 'open';
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
