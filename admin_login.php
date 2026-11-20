<?php
include 'db.php';
require_once 'settings_helper.php';
ensure_schema($conn);

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

if (isset($_GET['logout'])) {
    $_SESSION = [];
    session_destroy();
    header('Location: admin_login.php');
    exit;
}

$next = $_GET['next'] ?? 'homeadmin.php';
// อนุญาตให้ redirect ไปเฉพาะหน้าในเว็บนี้
if (str_starts_with($next, '//') || !preg_match('#^/?[A-Za-z0-9_\-/.]+\.php(\?[^\s]*)?$#', $next)) {
    $next = 'homeadmin.php';
}

$hash = get_setting($conn, 'admin_password_hash', '');
$first_time = ($hash === '');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pw = $_POST['password'] ?? '';
    if ($first_time) {
        if (strlen($pw) < 6) $error = 'รหัสผ่านต้องยาวอย่างน้อย 6 ตัวอักษร';
        elseif ($pw !== ($_POST['password2'] ?? '')) $error = 'รหัสผ่านทั้งสองช่องไม่ตรงกัน';
        else {
            set_setting($conn, 'admin_password_hash', password_hash($pw, PASSWORD_DEFAULT));
            $hash = get_setting($conn, 'admin_password_hash');
        }
    }
    if (!$error) {
        if (password_verify($pw, $hash)) {
            session_regenerate_id(true);
            $_SESSION['is_admin'] = true;
            header('Location: ' . $next);
            exit;
        }
        sleep(1);
        $error = 'รหัสผ่านไม่ถูกต้อง';
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
            <h1 style="font-size: 22px; font-weight: 700;"><?= $first_time ? 'ตั้งรหัสผ่าน Admin ครั้งแรก' : 'เข้าสู่ระบบ Admin' ?></h1>
        </div>
        <div class="p-6">
            <?php if ($error): ?>
            <div class="notice mb-4" style="background:#fef2f2; color:#dc2626;">❌ <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="post">
                <div class="mb-4">
                    <label class="form-label">รหัสผ่าน</label>
                    <input type="password" name="password" required autofocus class="form-input">
                </div>
                <?php if ($first_time): ?>
                <div class="mb-4">
                    <label class="form-label">ยืนยันรหัสผ่าน</label>
                    <input type="password" name="password2" required class="form-input">
                </div>
                <?php endif; ?>
                <button type="submit" class="btn btn-primary" style="width: 100%;"><?= $first_time ? '💾 ตั้งรหัสผ่านและเข้าสู่ระบบ' : '➡️ เข้าสู่ระบบ' ?></button>
            </form>
            <div class="text-center mt-4"><a href="home.php" class="btn-back">🏠 กลับหน้าหลัก</a></div>
        </div>
    </div>
</body>
</html>
