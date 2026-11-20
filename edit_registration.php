<?php
include 'db.php';
require_once 'admin_auth.php';

$id = (int)($_GET['id'] ?? 0);
$sql = "SELECT sr.*, ps.shirt_size FROM sport_registration sr LEFT JOIN player_size ps ON sr.student_id = ps.student_id AND sr.sport_id = ps.sport_id WHERE sr.reg_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
if (!$data) die("ไม่พบข้อมูลผู้สมัคร");

$shirt_sizes = setting_list($conn, 'shirt_sizes');
$sport_types = $conn->query("SELECT sport_id, sport_name, category FROM sport_type ORDER BY sport_name, category")->fetch_all(MYSQLI_ASSOC);
$error = '';

if (isset($_POST['save'])) {
    $student_name = trim($_POST['student_name'] ?? '');
    $new_sport_id = (int)($_POST['sport_id'] ?? 0);
    $shirt_size   = $_POST['shirt_size'] ?? '';
    $old_sport_id = (int)$data['sport_id'];

    // เลือกกีฬาจาก sport_type เท่านั้น เพื่อให้ sport_id / ชื่อกีฬา / ประเภท ตรงกันเสมอ
    $stmt = $conn->prepare("SELECT sport_name, category FROM sport_type WHERE sport_id=?");
    $stmt->bind_param("i", $new_sport_id);
    $stmt->execute();
    $sport = $stmt->get_result()->fetch_assoc();

    if ($student_name === '') $error = 'กรุณากรอกชื่อ';
    elseif (!$sport) $error = 'ไม่พบกีฬาที่เลือก';
    elseif (!in_array($shirt_size, $shirt_sizes, true)) $error = 'ไซส์เสื้อไม่ถูกต้อง';
    elseif ($new_sport_id !== $old_sport_id) {
        $stmt = $conn->prepare("SELECT 1 FROM sport_registration WHERE student_id=? AND sport_id=? AND reg_id<>?");
        $stmt->bind_param("sii", $data['student_id'], $new_sport_id, $id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) $error = 'นักศึกษาคนนี้สมัครกีฬานั้นอยู่แล้ว';
    }

    if (!$error) {
        $stmt = $conn->prepare("UPDATE sport_registration SET student_name=?, sport_id=?, sport_name=?, category=? WHERE reg_id=?");
        $stmt->bind_param("sissi", $student_name, $new_sport_id, $sport['sport_name'], $sport['category'], $id);
        $stmt->execute();

        if ($new_sport_id !== $old_sport_id) {
            // ย้ายรูปและไซส์เสื้อไปผูกกับกีฬาใหม่
            foreach (['image', 'player_size'] as $table) {
                $stmt = $conn->prepare("UPDATE $table SET sport_id=? WHERE student_id=? AND sport_id=?");
                $stmt->bind_param("isi", $new_sport_id, $data['student_id'], $old_sport_id);
                $stmt->execute();
            }
        }

        $stmt = $conn->prepare("INSERT INTO player_size (student_id, sport_id, color_id, shirt_size) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE shirt_size=VALUES(shirt_size)");
        $stmt->bind_param("siis", $data['student_id'], $new_sport_id, $data['color_id'], $shirt_size);
        $stmt->execute();

        echo "<script>alert('แก้ไขข้อมูลเรียบร้อยแล้ว');window.location.href='allstudent.php';</script>";
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>แก้ไขผู้สมัคร - Admin</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <?php include 'tailwind-config.php'; ?>
</head>

<body>
    <div class="container" style="max-width: 500px;">

        <!-- Header -->
        <div class="text-center mb-6 animate-fade">
            <h1 class="text-2xl font-bold mb-2">✏️ แก้ไขข้อมูลผู้สมัคร</h1>
        </div>

        <!-- Form -->
        <div class="card">
            <div class="header-gradient">
                <h2 style="font-size: 18px; font-weight: 700;">ข้อมูลนักศึกษา</h2>
            </div>

            <div class="p-6">
                <?php if ($error): ?>
                <div class="notice mb-4" style="background:#fef2f2; color:#dc2626;">❌ <?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <form method="post">
                    <?= csrf_field() ?>

                    <div class="mb-4">
                        <label class="form-label">ชื่อ-นามสกุล</label>
                        <input type="text" name="student_name" value="<?= htmlspecialchars($data['student_name']) ?>" required class="form-input">
                    </div>

                    <div class="mb-4">
                        <label class="form-label">กีฬา / ประเภท</label>
                        <select name="sport_id" required class="form-input">
                            <?php foreach ($sport_types as $st): ?>
                                <option value="<?= $st['sport_id'] ?>" <?= $st['sport_id'] == $data['sport_id'] ? 'selected' : '' ?>><?= htmlspecialchars($st['sport_name'] . ' — ' . $st['category']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-6">
                        <label class="form-label">ไซส์เสื้อ</label>
                        <select name="shirt_size" class="form-input">
                            <?php foreach ($shirt_sizes as $size): ?>
                                <option value="<?= htmlspecialchars($size) ?>" <?= ($data['shirt_size'] ?? '') === $size ? 'selected' : '' ?>><?= htmlspecialchars($size) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" name="save" class="btn btn-primary" style="width: 100%; margin-bottom: 12px;">💾 บันทึกการเปลี่ยนแปลง</button>
                    <a href="allstudent.php" class="btn-back" style="display: block; text-align: center;">❌ ยกเลิก</a>

                </form>
            </div>
        </div>

    </div>
</body>
</html>
