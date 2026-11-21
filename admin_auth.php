<?php
/*
 * ป้องกันหน้า Admin: ต้องล็อกอินก่อน + ตรวจ CSRF token ทุกครั้งที่ POST
 * ใช้: require_once 'admin_auth.php'; (หลังสร้าง $conn แล้ว)
 * สำหรับ API ที่ตอบเป็น JSON ให้ define('ADMIN_AUTH_JSON', true) ก่อน require
 */
require_once __DIR__ . '/settings_helper.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
ensure_schema($conn);

function csrf_token() {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function admin_auth_fail($code, $message, $redirect = null) {
    if (defined('ADMIN_AUTH_JSON')) {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['status' => 'error', 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($redirect) {
        header('Location: ' . $redirect);
        exit;
    }
    http_response_code($code);
    die('❌ ' . $message);
}

/* ล็อกอินแล้ว และบัญชียังไม่ถูกลบ */
$admin_ok = false;
if (!empty($_SESSION['is_admin']) && !empty($_SESSION['admin_id'])) {
    $stmt = $conn->prepare("SELECT username FROM sport_admins WHERE id=?");
    $stmt->bind_param("i", $_SESSION['admin_id']);
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) {
        $admin_ok = true;
        $_SESSION['admin_username'] = $row['username'];
    }
    $stmt->close();
}

if (!$admin_ok) {
    unset($_SESSION['is_admin'], $_SESSION['admin_id'], $_SESSION['admin_username']);
    $login = (defined('ADMIN_AUTH_BASE') ? ADMIN_AUTH_BASE : '') . 'admin_login.php';
    admin_auth_fail(401, 'กรุณาเข้าสู่ระบบ', $login . '?next=' . urlencode($_SERVER['REQUEST_URI'] ?? 'homeadmin.php'));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals(csrf_token(), (string)($_POST['csrf'] ?? ''))) {
    admin_auth_fail(403, 'คำขอไม่ถูกต้อง (CSRF) กรุณารีเฟรชหน้าแล้วลองใหม่');
}
