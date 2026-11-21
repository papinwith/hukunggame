<?php
/*
 * API จัดการบัญชี Admin (ใช้โดย admin_accounts.php)
 *   GET  -> รายชื่อบัญชี
 *   POST -> action=create|change_password|delete (ส่งแบบ form พร้อม csrf)
 */
define('ADMIN_AUTH_JSON', true);
include __DIR__ . '/../db.php';
require_once __DIR__ . '/../admin_auth.php';

header('Content-Type: application/json; charset=utf-8');

function respond(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function admins_list(mysqli $conn): array
{
    $admins = [];
    $result = $conn->query("SELECT id, username, created_at FROM sport_admins ORDER BY username ASC");
    while ($row = $result->fetch_assoc()) {
        $admins[] = ['id' => (int)$row['id'], 'username' => $row['username'], 'created_at' => $row['created_at']];
    }
    return $admins;
}

$current_id = (int)$_SESSION['admin_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    respond(['status' => 'success', 'admins' => admins_list($conn), 'currentAdminId' => $current_id]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['status' => 'error', 'message' => 'Method not allowed'], 405);
}

$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($username === '' || mb_strlen($username) > 50) respond(['status' => 'error', 'message' => 'กรุณาระบุ username ให้ถูกต้อง']);
    if (mb_strlen($password) < 6) respond(['status' => 'error', 'message' => 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร']);

    $stmt = $conn->prepare("SELECT 1 FROM sport_admins WHERE username = ?");
    $stmt->bind_param('s', $username);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) respond(['status' => 'error', 'message' => 'มี username นี้อยู่แล้ว']);
    $stmt->close();

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("INSERT INTO sport_admins (username, password_hash) VALUES (?, ?)");
    $stmt->bind_param('ss', $username, $hash);
    if (!$stmt->execute()) respond(['status' => 'error', 'message' => 'สร้างบัญชีไม่สำเร็จ']);
    $stmt->close();

    respond(['status' => 'success', 'admins' => admins_list($conn)]);
}

if ($action === 'change_password') {
    $admin_id = (int)($_POST['admin_id'] ?? 0);
    $password = (string)($_POST['password'] ?? '');

    if ($admin_id <= 0) respond(['status' => 'error', 'message' => 'บัญชีไม่ถูกต้อง']);
    if (mb_strlen($password) < 6) respond(['status' => 'error', 'message' => 'รหัสผ่านต้องมีอย่างน้อย 6 ตัวอักษร']);

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE sport_admins SET password_hash = ? WHERE id = ?");
    $stmt->bind_param('si', $hash, $admin_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected === 0) respond(['status' => 'error', 'message' => 'ไม่พบบัญชีนี้']);
    respond(['status' => 'success', 'admins' => admins_list($conn)]);
}

if ($action === 'delete') {
    $admin_id = (int)($_POST['admin_id'] ?? 0);

    if ($admin_id <= 0) respond(['status' => 'error', 'message' => 'บัญชีไม่ถูกต้อง']);
    if ($admin_id === $current_id) respond(['status' => 'error', 'message' => 'ไม่สามารถลบบัญชีที่กำลังใช้งานอยู่ได้']);
    if (admin_count($conn) <= 1) respond(['status' => 'error', 'message' => 'ต้องมีบัญชี Admin เหลืออย่างน้อย 1 บัญชี']);

    $stmt = $conn->prepare("DELETE FROM sport_admins WHERE id = ?");
    $stmt->bind_param('i', $admin_id);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected === 0) respond(['status' => 'error', 'message' => 'ไม่พบบัญชีนี้']);
    respond(['status' => 'success', 'admins' => admins_list($conn)]);
}

respond(['status' => 'error', 'message' => 'Unknown action'], 400);
