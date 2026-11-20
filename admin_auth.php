<?php
/*
 * ป้องกันหน้า Admin: ต้องล็อกอินก่อน + ตรวจ CSRF token ทุกครั้งที่ POST
 * ใช้: require_once 'admin_auth.php'; (หลังสร้าง $conn แล้ว)
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

if (empty($_SESSION['is_admin'])) {
    header('Location: admin_login.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? 'homeadmin.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !hash_equals(csrf_token(), $_POST['csrf'] ?? '')) {
    http_response_code(403);
    die('❌ คำขอไม่ถูกต้อง (CSRF) กรุณารีเฟรชหน้าแล้วลองใหม่');
}
