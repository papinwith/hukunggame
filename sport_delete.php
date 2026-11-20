<?php
include 'db.php';
require_once 'admin_auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: sport_admin2.php");
    exit();
}

$id = (int)($_POST['id'] ?? 0);

if (!$id) {
    die("ไม่พบรหัสกีฬา");
}

// เก็บรายการไฟล์รูปไว้ลบหลัง commit สำเร็จ
$files = [];
$stmt = $conn->prepare("SELECT image_path FROM image WHERE sport_id=?");
$stmt->bind_param("i",$id);
$stmt->execute();
foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $img) $files[] = $img['image_path'];

$conn->begin_transaction();

try {

    $stmt = $conn->prepare("DELETE FROM match_schedule WHERE sport_id=?");
    $stmt->bind_param("i",$id);
    $stmt->execute();

    $stmt = $conn->prepare("DELETE FROM sport_registration WHERE sport_id=?");
    $stmt->bind_param("i",$id);
    $stmt->execute();

    $stmt = $conn->prepare("DELETE FROM register_sport WHERE sport_id=?");
    $stmt->bind_param("i",$id);
    $stmt->execute();

    $stmt = $conn->prepare("DELETE FROM player_size WHERE sport_id=?");
    $stmt->bind_param("i",$id);
    $stmt->execute();

    $stmt = $conn->prepare("DELETE FROM image WHERE sport_id=?");
    $stmt->bind_param("i",$id);
    $stmt->execute();

    $stmt = $conn->prepare("DELETE FROM sport_type WHERE sport_id=?");
    $stmt->bind_param("i",$id);
    $stmt->execute();

    $conn->commit();

    foreach ($files as $f) {
        if (str_starts_with($f, 'uploads/') && is_file(__DIR__ . '/' . $f)) @unlink(__DIR__ . '/' . $f);
    }

    header("Location: sport_admin2.php");
    exit();

} catch (Throwable $e) {
    $conn->rollback();
    echo "Delete Error: ".$e->getMessage();
}
