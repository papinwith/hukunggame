<?php
include 'db.php';
require_once 'admin_auth.php';
require_once 'settings_helper.php';
ensure_schema($conn);

$sql = "SELECT st.*, (SELECT COUNT(*) FROM sport_registration sr WHERE sr.sport_id = st.sport_id) AS applicants FROM sport_type st ORDER BY st.sport_id DESC";
$result = $conn->query($sql);

if (!$result) { die("Query Error: " . $conn->error); }
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายการกีฬา - Admin</title>
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <?php include 'tailwind-config.php'; ?>
</head>

<body>
    <div class="container" style="max-width: 900px;">
        
        <!-- Header -->
        <div class="text-center mb-8 animate-fade">
            <h1 class="text-2xl font-bold mb-2">📋 รายการกีฬา</h1>
            <p class="text-gray">จัดการข้อมูลกีฬาและประเภทการแข่งขัน</p>
        </div>
        
        <!-- Table Card -->
        <div class="table-container mb-8 animate-slide">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>ชื่อกีฬา</th>
                        <th>ประเภท</th>
                        <th>สถานที่แข่ง</th>
                        <th>สมัครแล้ว / สูงสุดต่อสี</th>
                        <th>จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td style="text-align: center; color: #6b7280;"><?= $row['sport_id'] ?></td>
                            <td style="font-weight: 500; color: #1f2937;"><?= htmlspecialchars($row['sport_name']) ?></td>
                            <td>
                                <span style="background: #eef2ff; color: #6366f1; padding: 4px 12px; border-radius: 50px; font-size: 14px; font-weight: 600;">
                                    <?= htmlspecialchars($row['category']) ?>
                                </span>
                            </td>
                            <td style="color: #4b5563;"><?= htmlspecialchars($row['venue_type']) ?></td>
                            <td style="text-align: center; color: #4b5563;"><?= (int)$row['applicants'] ?> / <?= $row['max_players'] > 0 ? (int)$row['max_players'] : '∞' ?></td>
                            <td style="text-align: center;">
                                <a href="sport_edit.php?id=<?= $row['sport_id'] ?>" style="color: #d97706; font-weight: 700; text-decoration: none; margin-right: 12px;">✏️ แก้ไข</a>
                                <form method="post" action="sport_delete.php" style="display: inline;" onsubmit="return confirm('ลบกีฬานี้? ผู้สมัคร <?= (int)$row['applicants'] ?> คน รูป และตารางแข่งของกีฬานี้จะถูกลบทั้งหมด');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= $row['sport_id'] ?>">
                                    <button type="submit" style="background: none; border: none; cursor: pointer; font: inherit; color: #dc2626; font-weight: 700;">🗑️ ลบ</button>
                                </form>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6"class="text-center p-8 text-gray">ไม่พบข้อมูล</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Navigation -->
        <div class="text-center">
            <a href="homeadmin.php" class="btn-back" style="margin-right: 8px;">🏠 Admin Home</a>
            <a href="sport_admin.php" class="btn btn-primary" style="padding: 12px 24px; font-size: 16px;">➕ สร้างกีฬาใหม่</a>
        </div>
        
    </div>
</body>
</html>
