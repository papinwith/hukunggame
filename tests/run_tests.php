<?php
/**
 * ชุดทดสอบ integration: login guard ของหน้า Admin + จัดการบัญชี Admin (api/admins.php)
 *
 * วิธีรัน (เปิด Apache + MySQL ใน XAMPP ก่อน):
 *   php tests/run_tests.php <base_url> <admin_username> <admin_password>
 *   เช่น php tests/run_tests.php http://localhost/hukunggame-main/hukunggame-main Admin mypassword
 *
 * เทสจะสร้าง/ลบบัญชีทดสอบที่ขึ้นต้นด้วย "TESTADMIN_" เท่านั้น ไม่กระทบบัญชีจริง
 */

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if ($argc < 4) {
    fwrite(STDERR, "ใช้: php tests/run_tests.php <base_url> <admin_username> <admin_password>\n");
    exit(2);
}
$baseUrl = rtrim($argv[1], '/');
$adminUser = $argv[2];
$adminPass = $argv[3];

$passed = 0;
$failed = 0;
$failures = [];

function check(bool $condition, string $message): void
{
    global $passed, $failed, $failures;
    if ($condition) { $passed++; echo "  [PASS] $message\n"; }
    else { $failed++; $failures[] = $message; echo "  [FAIL] $message\n"; }
}

function http(string $method, string $url, ?string $jar, ?array $form = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 10,
    ]);
    if ($jar) { curl_setopt($ch, CURLOPT_COOKIEJAR, $jar); curl_setopt($ch, CURLOPT_COOKIEFILE, $jar); }
    if ($form !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form));

    $raw = curl_exec($ch);
    if ($raw === false) throw new RuntimeException('HTTP request failed: ' . curl_error($ch) . " ($url)");
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $headers = substr($raw, 0, $headerSize);
    $body = substr($raw, $headerSize);
    $location = preg_match('/^Location:\s*(.+)$/mi', $headers, $m) ? trim($m[1]) : null;
    return ['status' => $status, 'body' => $body, 'location' => $location, 'json' => json_decode($body, true)];
}

function csrf_from(string $html): ?string
{
    return preg_match('/name="csrf" value="([^"]+)"/', $html, $m) ? $m[1] : null;
}

$testUser = 'TESTADMIN_' . time() . '_' . random_int(100, 999);
$guestJar = tempnam(sys_get_temp_dir(), 'hk_guest_');
$adminJar = tempnam(sys_get_temp_dir(), 'hk_admin_');
$testId = null;

echo "== Integration Tests ==\nBase URL: $baseUrl\n\n";

try {
    echo "-- ต้อง login ก่อนเข้าหน้า Admin --\n";
    $guarded = [
        'homeadmin.php', 'admin_dashboard.php', 'admin_settings.php', 'sport_admin.php', 'sport_admin2.php',
        'allstudent.php', 'admin_schedule.php', 'search.php', 'checkin_today.php', 'admin_accounts.php',
    ];
    foreach ($guarded as $page) {
        $res = http('GET', "$baseUrl/$page", $guestJar);
        check($res['status'] === 302 && str_contains((string)$res['location'], 'admin_login.php'), "$page โดยไม่ login -> redirect ไป admin_login.php");
    }
    $res = http('GET', "$baseUrl/api/admins.php", $guestJar);
    check($res['status'] === 401 && ($res['json']['status'] ?? '') === 'error', 'api/admins.php โดยไม่ login -> 401 JSON');

    echo "\n-- หน้าสาธารณะ --\n";
    foreach (['home.php', 'student_card.php'] as $page) {
        $res = http('GET', "$baseUrl/$page", $guestJar);
        check($res['status'] === 200, "$page เปิดได้โดยไม่ต้อง login");
    }

    echo "\n-- Login --\n";
    $res = http('GET', "$baseUrl/admin_login.php", $adminJar);
    $csrf = csrf_from($res['body']);
    check(!empty($csrf), 'ดึง CSRF token จากหน้า login ได้');

    $res = http('POST', "$baseUrl/admin_login.php", $adminJar, ['csrf' => $csrf, 'username' => $adminUser, 'password' => 'wrong-password-xyz']);
    check(str_contains($res['body'], 'ไม่ถูกต้อง'), 'รหัสผ่านผิด -> แสดงข้อความ error');

    $res = http('POST', "$baseUrl/admin_login.php", $adminJar, ['csrf' => $csrf, 'username' => $adminUser, 'password' => $adminPass]);
    check($res['status'] === 302 && str_contains((string)$res['location'], 'homeadmin.php'), 'login สำเร็จ -> redirect ไป homeadmin.php');

    $res = http('GET', "$baseUrl/homeadmin.php", $adminJar);
    check($res['status'] === 200, 'เข้า homeadmin.php หลัง login ได้');
    check(str_contains($res['body'], 'สวัสดี, ' . htmlspecialchars($adminUser)), 'homeadmin.php แสดงชื่อผู้ดูแล');
    foreach (['search.php', 'checkin_today.php', 'admin_accounts.php'] as $link) {
        check(str_contains($res['body'], $link), "homeadmin.php มีลิงก์ไป $link");
    }
    foreach (['search.php', 'checkin_today.php'] as $page) {
        $r = http('GET', "$baseUrl/$page", $adminJar);
        check($r['status'] === 200, "เปิด $page หลัง login ได้");
    }

    echo "\n-- CRUD บัญชี Admin --\n";
    $res = http('GET', "$baseUrl/admin_accounts.php", $adminJar);
    $csrf = preg_match('/const csrfToken = "([^"]+)"/', $res['body'], $m) ? $m[1] : null;
    check(!empty($csrf), 'ดึง CSRF token จาก admin_accounts.php ได้');

    $res = http('GET', "$baseUrl/api/admins.php", $adminJar);
    $currentId = $res['json']['currentAdminId'] ?? 0;
    check(($res['json']['status'] ?? '') === 'success' && $currentId > 0, 'GET api/admins.php -> success + currentAdminId');

    $res = http('POST', "$baseUrl/api/admins.php", $adminJar, ['action' => 'create', 'username' => $testUser, 'password' => 'x']);
    check($res['status'] === 403, 'POST ไม่มี CSRF -> 403');

    $res = http('POST', "$baseUrl/api/admins.php", $adminJar, ['csrf' => $csrf, 'action' => 'create', 'username' => $testUser, 'password' => 'testpass123']);
    check(($res['json']['status'] ?? '') === 'success', 'สร้างบัญชีใหม่ -> success');
    foreach ($res['json']['admins'] ?? [] as $a) if ($a['username'] === $testUser) $testId = $a['id'];
    check($testId !== null, 'พบบัญชีใหม่ในรายการ');

    $res = http('POST', "$baseUrl/api/admins.php", $adminJar, ['csrf' => $csrf, 'action' => 'create', 'username' => $testUser, 'password' => 'testpass123']);
    check(($res['json']['status'] ?? '') === 'error', 'username ซ้ำ -> error');

    $res = http('POST', "$baseUrl/api/admins.php", $adminJar, ['csrf' => $csrf, 'action' => 'create', 'username' => $testUser . '_s', 'password' => 'abc']);
    check(($res['json']['status'] ?? '') === 'error', 'รหัสผ่านสั้นเกินไป -> error');

    $res = http('POST', "$baseUrl/api/admins.php", $adminJar, ['csrf' => $csrf, 'action' => 'delete', 'admin_id' => $currentId]);
    check(($res['json']['status'] ?? '') === 'error', 'ลบบัญชีตัวเอง -> error');

    $res = http('POST', "$baseUrl/api/admins.php", $adminJar, ['csrf' => $csrf, 'action' => 'change_password', 'admin_id' => $testId, 'password' => 'newpass456']);
    check(($res['json']['status'] ?? '') === 'success', 'เปลี่ยนรหัสผ่านบัญชีทดสอบ -> success');

    $res = http('POST', "$baseUrl/api/admins.php", $adminJar, ['csrf' => $csrf, 'action' => 'delete', 'admin_id' => $testId]);
    check(($res['json']['status'] ?? '') === 'success', 'ลบบัญชีทดสอบ -> success');
    check(!in_array($testUser, array_column($res['json']['admins'] ?? [], 'username'), true), 'หลังลบ ไม่พบบัญชีทดสอบในรายการ');
    $testId = null;

    echo "\n-- Logout --\n";
    $res = http('GET', "$baseUrl/admin_login.php?logout=1", $adminJar);
    check($res['status'] === 302, 'logout -> redirect');
    $res = http('GET', "$baseUrl/homeadmin.php", $adminJar);
    check($res['status'] === 302 && str_contains((string)$res['location'], 'admin_login.php'), 'หลัง logout เข้า homeadmin.php ไม่ได้');
} finally {
    echo "\n-- Cleanup --\n";
    require __DIR__ . '/../db.php';
    $conn->query("DELETE FROM sport_admins WHERE username LIKE 'TESTADMIN\\_%'");
    echo "  ลบบัญชีทดสอบ (TESTADMIN_*) แล้ว\n";
    @unlink($guestJar);
    @unlink($adminJar);
}

echo "\n== สรุป: ผ่าน $passed  ล้มเหลว $failed ==\n";
foreach ($failures as $f) echo "  - $f\n";
exit($failed > 0 ? 1 : 0);
