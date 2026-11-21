<?php
include 'db.php';
require_once 'admin_auth.php';
$event_name = get_setting($conn, 'event_name');
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - หูกวางเกมส์</title>
    <?php include 'tailwind-config.php'; ?>
</head>

<body>
    <div class="container" style="max-width: 800px;">
        
        <!-- Header -->
        <div class="text-center mb-10 animate-fade">
            <div style="width: 80px; height: 80px; border-radius: 24px; background: linear-gradient(135deg, #6366f1, #8b5cf6); display: flex; align-items: center; justify-content: center; margin: 0 auto 16px; box-shadow: 0 8px 25px rgba(99, 102, 241, 0.3);">
                <span style="font-size: 40px;">⚙️</span>
            </div>
            <h1 class="text-3xl font-bold mb-2">Admin Panel</h1>
            <p class="text-gray text-lg">ระบบจัดการ<?= htmlspecialchars($event_name) ?></p>
            <p class="text-sm text-gray mt-4">สวัสดี, <?= htmlspecialchars($_SESSION['admin_username'] ?? '') ?></p>
        </div>
        
        <!-- Menu Grid -->
        <div class="grid grid-2 mb-10">
            
            <!-- Dashboard -->
            <a href="admin_dashboard.php" class="card" style="text-decoration: none;">
                <div class="card-body flex items-center gap-4">
                    <div style="width: 56px; height: 56px; border-radius: 16px; background: linear-gradient(135deg, #e0f2fe, #bae6fd); display: flex; align-items: center; justify-content: center; font-size: 24px;">📊</div>
                    <div>
                        <h2 class="font-bold" style="color: #1f2937; font-size: 18px;">แดชบอร์ด</h2>
                        <p class="text-gray text-sm">สรุปผู้สมัครแต่ละสี/กีฬา รายปี</p>
                    </div>
                </div>
            </a>

            <!-- Create Sport -->
            <a href="sport_admin.php" class="card" style="text-decoration: none;">
                <div class="card-body flex items-center gap-4">
                    <div style="width: 56px; height: 56px; border-radius: 16px; background: linear-gradient(135deg, #eef2ff, #e0e7ff); display: flex; align-items: center; justify-content: center; font-size: 24px;">🏅</div>
                    <div>
                        <h2 class="font-bold" style="color: #1f2937; font-size: 18px;">สร้างกีฬา</h2>
                        <p class="text-gray text-sm">เพิ่มประเภทกีฬาใหม่</p>
                    </div>
                </div>
            </a>
            
            <!-- Edit Sport -->
            <a href="sport_admin2.php" class="card" style="text-decoration: none;">
                <div class="card-body flex items-center gap-4">
                    <div style="width: 56px; height: 56px; border-radius: 16px; background: linear-gradient(135deg, #fef3c7, #fde68a); display: flex; align-items: center; justify-content: center; font-size: 24px;">✏️</div>
                    <div>
                        <h2 class="font-bold" style="color: #1f2937; font-size: 18px;">แก้ไขกีฬา</h2>
                        <p class="text-gray text-sm">แก้ไขข้อมูลกีฬา</p>
                    </div>
                </div>
            </a>
            
            <!-- View Students -->
            <a href="allstudent.php" class="card" style="text-decoration: none;">
                <div class="card-body flex items-center gap-4">
                    <div style="width: 56px; height: 56px; border-radius: 16px; background: linear-gradient(135deg, #d1fae5, #a7f3d0); display: flex; align-items: center; justify-content: center; font-size: 24px;">👥</div>
                    <div>
                        <h2 class="font-bold" style="color: #1f2937; font-size: 18px;">นักกีฬาแต่ละสี</h2>
                        <p class="text-gray text-sm">ดูข้อมูลผู้สมัครแข่งขัน</p>
                    </div>
                </div>
            </a>
            
            <!-- Schedule Management -->
            <a href="admin_schedule.php" class="card" style="text-decoration: none;">
                <div class="card-body flex items-center gap-4">
                    <div style="width: 56px; height: 56px; border-radius: 16px; background: linear-gradient(135deg, #fecaca, #fca5a5); display: flex; align-items: center; justify-content: center; font-size: 24px;">📅</div>
                    <div>
                        <h2 class="font-bold" style="color: #1f2937; font-size: 18px;">ตารางการแข่งขัน</h2>
                        <p class="text-gray text-sm">จัดการตารางแข่ง</p>
                    </div>
                </div>
            </a>

            <!-- Scan / Search Card -->
            <a href="search.php" class="card" style="text-decoration: none;">
                <div class="card-body flex items-center gap-4">
                    <div style="width: 56px; height: 56px; border-radius: 16px; background: linear-gradient(135deg, #e9d5ff, #d8b4fe); display: flex; align-items: center; justify-content: center; font-size: 24px;">📇</div>
                    <div>
                        <h2 class="font-bold" style="color: #1f2937; font-size: 18px;">สแกน/ค้นหาบัตร</h2>
                        <p class="text-gray text-sm">สแกน QR รับเสื้อ ดูประวัติ</p>
                    </div>
                </div>
            </a>

            <!-- Check-in Today -->
            <a href="checkin_today.php" class="card" style="text-decoration: none;">
                <div class="card-body flex items-center gap-4">
                    <div style="width: 56px; height: 56px; border-radius: 16px; background: linear-gradient(135deg, #d1fae5, #6ee7b7); display: flex; align-items: center; justify-content: center; font-size: 24px;">✅</div>
                    <div>
                        <h2 class="font-bold" style="color: #1f2937; font-size: 18px;">เช็คชื่อวันนี้</h2>
                        <p class="text-gray text-sm">เช็คชื่อเฉพาะรายการที่แข่งวันนี้</p>
                    </div>
                </div>
            </a>

            <!-- Admin Accounts -->
            <a href="admin_accounts.php" class="card" style="text-decoration: none;">
                <div class="card-body flex items-center gap-4">
                    <div style="width: 56px; height: 56px; border-radius: 16px; background: linear-gradient(135deg, #ede9fe, #ddd6fe); display: flex; align-items: center; justify-content: center; font-size: 24px;">👤</div>
                    <div>
                        <h2 class="font-bold" style="color: #1f2937; font-size: 18px;">บัญชี Admin</h2>
                        <p class="text-gray text-sm">เพิ่ม ลบ เปลี่ยนรหัสผ่าน Admin</p>
                    </div>
                </div>
            </a>

            <!-- Manual -->
            <a href="manual.html" class="card" style="text-decoration: none;">
                <div class="card-body flex items-center gap-4">
                    <div style="width: 56px; height: 56px; border-radius: 16px; background: linear-gradient(135deg, #f3f4f6, #d1d5db); display: flex; align-items: center; justify-content: center; font-size: 24px;">📖</div>
                    <div>
                        <h2 class="font-bold" style="color: #1f2937; font-size: 18px;">คู่มือการใช้งาน</h2>
                        <p class="text-gray text-sm">คู่มือสำหรับผู้ดูแลและผู้พัฒนา</p>
                    </div>
                </div>
            </a>

            <!-- System Settings -->
            <a href="admin_settings.php" class="card" style="text-decoration: none;">
                <div class="card-body flex items-center gap-4">
                    <div style="width: 56px; height: 56px; border-radius: 16px; background: linear-gradient(135deg, #e5e7eb, #d1d5db); display: flex; align-items: center; justify-content: center; font-size: 24px;">🛠️</div>
                    <div>
                        <h2 class="font-bold" style="color: #1f2937; font-size: 18px;">ตั้งค่าระบบ</h2>
                        <p class="text-gray text-sm">ลิมิตสมัคร ไซส์เสื้อ สี คณะ</p>
                    </div>
                </div>
            </a>
        </div>

        <!-- Back to Home -->
        <div class="text-center">
            <a href="home.php" class="btn-back" style="margin-right: 8px;">🏠 กลับหน้าหลัก</a>
            <a href="admin_login.php?logout=1" class="btn-back" style="color: #dc2626;">🚪 ออกจากระบบ</a>
        </div>
    </div>
</body>
</html>
