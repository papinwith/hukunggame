<?php
include 'db.php';
require_once 'settings_helper.php';
ensure_schema($conn);

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

if (isset($_GET['logout'])) {
    admin_session_destroy();
    header('Location: admin_login.php');
    exit;
}

$next = $_GET['next'] ?? 'homeadmin.php';
// อนุญาตให้ redirect ไปเฉพาะหน้าในเว็บนี้
if (str_starts_with($next, '//') || !preg_match('#^/?[A-Za-z0-9_\-/.]+\.php(\?[^\s]*)?$#', $next)) {
    $next = 'homeadmin.php';
}

// ล็อกอินค้างอยู่และยังไม่หมดเวลา -> ไปหน้า Admin เลย, ถ้าหมดเวลาแล้วล้าง session ทิ้ง
if (!empty($_SESSION['is_admin'])) {
    $timeout = max(5, (int)get_setting($conn, 'admin_session_timeout', '30')) * 60;
    if (!empty($_SESSION['admin_id']) && (time() - (int)($_SESSION['last_activity'] ?? 0)) <= $timeout) {
        header('Location: ' . $next);
        exit;
    }
    admin_session_destroy();
    session_id(session_create_id());
    session_start();
}

if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$csrf = $_SESSION['csrf'];

// ยังไม่มีบัญชี Admin เลย -> ให้สร้างบัญชีแรก
$first_time = admin_count($conn) === 0;
$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $pw = (string)($_POST['password'] ?? '');

    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        $error = 'คำขอไม่ถูกต้อง กรุณาลองใหม่';
    } elseif ($first_time) {
        if ($username === '' || mb_strlen($username) > 50) $error = 'กรุณาระบุ username (ไม่เกิน 50 ตัวอักษร)';
        elseif (mb_strlen($pw) < 6) $error = 'รหัสผ่านต้องยาวอย่างน้อย 6 ตัวอักษร';
        elseif ($pw !== ($_POST['password2'] ?? '')) $error = 'รหัสผ่านทั้งสองช่องไม่ตรงกัน';
        elseif (admin_count($conn) === 0) {
            $hash = password_hash($pw, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO sport_admins (username, password_hash) VALUES (?, ?)");
            $stmt->bind_param("ss", $username, $hash);
            $stmt->execute();
            $stmt->close();
        }
    }

    if (!$error) {
        $stmt = $conn->prepare("SELECT id, username, password_hash FROM sport_admins WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($admin && password_verify($pw, $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['is_admin'] = true;
            $_SESSION['admin_id'] = (int)$admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['last_activity'] = time();
            $_SESSION['csrf'] = bin2hex(random_bytes(32)); // token ใหม่หลังล็อกอิน ไม่ใช้ต่อจากก่อนล็อกอิน
            header('Location: ' . $next);
            exit;
        }
        sleep(1);
        $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
        $first_time = admin_count($conn) === 0;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ Admin</title>
    <?php include 'tailwind-config.php'; ?>
</head>
<body style="display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 16px;">
    <div class="card" style="width: 100%; max-width: 400px;">
        <div class="header-gradient">
            <div style="font-size: 40px; margin-bottom: 8px;">🔐</div>
            <h1 style="font-size: 22px; font-weight: 700;"><?= $first_time ? 'สร้างบัญชี Admin แรก' : 'เข้าสู่ระบบ Admin' ?></h1>
        </div>
        <div class="p-6">
            <?php if ($error): ?>
            <div class="notice mb-4" style="background:#fef2f2; color:#dc2626;">❌ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="post" autocomplete="off">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                <div class="mb-4">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" value="<?= htmlspecialchars($username) ?>" required maxlength="50" autofocus class="form-input">
                </div>
                <div class="mb-4">
                    <label class="form-label">รหัสผ่าน</label>
                    <input type="password" name="password" required class="form-input">
                </div>
                <?php if ($first_time): ?>
                <div class="mb-4">
                    <label class="form-label">ยืนยันรหัสผ่าน</label>
                    <input type="password" name="password2" required class="form-input">
                </div>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary" style="width: 100%;"><?= $first_time ? '💾 สร้างบัญชีและเข้าสู่ระบบ' : '➡️ เข้าสู่ระบบ' ?></button>
            </form>
            <div class="text-center mt-4"><a href="home.php" class="btn-back">🏠 กลับหน้าหลัก</a></div>
        </div>
    </div>
</body>
</html>
