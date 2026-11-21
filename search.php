<?php

include 'db.php';
require_once 'admin_auth.php';
include 'admin_scan_helpers.php';
$event_year = get_setting($conn, 'current_year');
$search_id = $_GET['student_id'] ?? '';

// ค้นหาและอัปเดตสถานะ
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $reg_id = (int) ($_POST['reg_id'] ?? 0);
    $action = $_POST['action'];
    
    if ($action === 'receive_shirt') {
        $stmt = $conn->prepare("UPDATE sport_registration SET shirt_received = 1, shirt_received_at = NOW() WHERE reg_id = ? AND shirt_received = 0");
        $stmt->bind_param("i", $reg_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $msg = "success|รับเสื้อเรียบร้อยแล้ว";
        } else {
            $msg = "error|ไม่สามารถรับเสื้อได้ หรือรับไปแล้ว";
        }
        $stmt->close();
    } elseif ($action === 'check_attendance') {
        $status = performAttendanceCheckin($conn, $reg_id);
        $msg = $status['type'] . '|' . $status['text'];
    }

    $search_id = trim($_POST['student_id'] ?? $search_id);
}

// หากมีการค้นหา
$results = [];
if ($search_id) {
    $results = loadStudentSports($conn, $search_id);
}

// สีแบ่งตาม color_id
$colorThemes = [
    1 => ['accent' => '#dc2626', 'accent_dark' => '#991b1b', 'soft' => '#fee2e2'],
    2 => ['accent' => '#d97706', 'accent_dark' => '#92400e', 'soft' => '#fef3c7'],
    3 => ['accent' => '#059669', 'accent_dark' => '#065f46', 'soft' => '#d1fae5'],
    4 => ['accent' => '#2563eb', 'accent_dark' => '#1e40af', 'soft' => '#dbeafe'],
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สแกนบัตรนักกีฬา - ผู้ดูแล</title>
    <?php include 'tailwind-config.php'; ?>
    <style>
        .id-card {
            --team-color: #4f46e5;
            --team-dark: #312e81;
            --team-soft: #e0e7ff;
            width: 940px;
            max-width: calc(100% - 32px);
            min-height: 540px;
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.14);
            position: relative;
            overflow: hidden;
            display: grid;
            grid-template-columns: 300px minmax(0, 1fr);
            margin: 0 auto 34px auto;
            border: 1px solid rgba(148, 163, 184, 0.45);
        }

        .id-card::before {
            content: "";
            position: absolute;
            inset: 0 0 auto 0;
            height: 9px;
            background: var(--team-color);
        }

        .id-card-visual {
            background: linear-gradient(160deg, var(--team-dark) 0%, var(--team-color) 68%, #111827 100%);
            color: #fff;
            padding: 34px 26px 28px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .card-event-label {
            width: 100%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0;
        }

        .event-year {
            padding: 6px 10px;
            background: rgba(255,255,255,0.16);
            border: 1px solid rgba(255,255,255,0.24);
            border-radius: 999px;
        }

        .id-card-photo {
            width: 174px;
            height: 218px;
            object-fit: cover;
            border-radius: 14px;
            border: 5px solid rgba(255,255,255,0.92);
            background: #e5e7eb;
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.28);
        }

        .id-card-qr-box {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 12px;
            border-radius: 14px;
            background: rgba(255,255,255,0.14);
            border: 1px solid rgba(255,255,255,0.24);
        }

        .id-card-qr {
            width: 96px;
            height: 96px;
            padding: 5px;
            background: white;
            border: 1px solid rgba(255,255,255,0.75);
            border-radius: 10px;
            flex-shrink: 0;
        }

        .qr-copy {
            min-width: 0;
            font-size: 13px;
            line-height: 1.35;
            color: rgba(255,255,255,0.86);
        }

        .qr-copy strong {
            display: block;
            color: #fff;
            font-size: 18px;
            line-height: 1.2;
            word-break: break-word;
        }

        .id-card-content {
            padding: 34px 34px 30px;
            display: flex;
            flex-direction: column;
            gap: 22px;
            min-width: 0;
        }

        .id-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 18px;
            padding-bottom: 18px;
            border-bottom: 1px solid #e5e7eb;
        }

        .id-card-title {
            color: #111827;
            font-size: 30px;
            font-weight: 800;
            line-height: 1.1;
        }

        .id-card-subtitle {
            color: #64748b;
            font-size: 15px;
            font-weight: 700;
            margin-top: 5px;
        }

        .team-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 112px;
            padding: 9px 14px;
            border-radius: 999px;
            background: var(--team-soft);
            color: var(--team-dark);
            font-size: 14px;
            font-weight: 800;
            text-align: center;
            border: 1px solid rgba(148, 163, 184, 0.35);
        }

        .id-card-body {
            display: flex;
            flex-direction: column;
            gap: 18px;
            flex: 1;
            min-width: 0;
        }

        .student-name-block {
            padding: 18px 20px;
            border-radius: 16px;
            background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
            border: 1px solid #e5e7eb;
        }

        .detail-label {
            display: block;
            font-size: 13px;
            color: #64748b;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .detail-value {
            display: block;
            font-size: 21px;
            font-weight: 800;
            color: #1f2937;
            line-height: 1.25;
            word-break: break-word;
        }

        .detail-value.name {
            color: #0f172a;
            font-size: 30px;
            line-height: 1.2;
        }

        .id-detail-grid {
            display: grid;
            grid-template-columns: minmax(0, 0.85fr) minmax(0, 1.15fr);
            gap: 14px;
        }

        .detail-tile {
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            padding: 14px 16px;
            background: #fff;
            min-width: 0;
        }

        .sports-card-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: auto;
        }

        .sports-card-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            color: #334155;
            font-weight: 800;
            font-size: 15px;
        }

        .sport-row-card {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 14px;
            align-items: center;
            padding: 12px 14px;
            border: 1px solid #e5e7eb;
            border-left: 5px solid var(--team-color);
            border-radius: 14px;
            background: #ffffff;
        }

        .sport-title {
            color: #111827;
            font-weight: 800;
            line-height: 1.25;
        }

        .sport-meta {
            color: #64748b;
            font-size: 13px;
            margin-top: 3px;
        }

        .sport-status-group {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 6px;
            text-align: right;
        }

        .status-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 28px;
            padding: 5px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            white-space: nowrap;
        }

        .status-chip.done { background: #dcfce7; color: #166534; }
        .status-chip.wait { background: #fef3c7; color: #92400e; }
        .status-chip.match { background: #dbeafe; color: #1e40af; }
        .status-chip.neutral { background: #f1f5f9; color: #475569; }

        .page-shell {
            max-width: 1120px;
            margin: 0 auto;
            padding: 32px 16px 64px;
        }

        .search-panel {
            max-width: 780px;
            margin: 0 auto 32px;
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid rgba(226, 232, 240, 0.95);
            border-radius: 24px;
            box-shadow: 0 16px 40px rgba(15, 23, 42, 0.08);
            padding: 24px;
        }

        .search-form {
            display: flex;
            gap: 16px;
            align-items: center;
        }

        .search-input {
            flex: 1;
        }

        .top-links {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
            flex-wrap: wrap;
            margin-bottom: 32px;
        }

        .inline-link {
            color: #4f46e5;
            font-weight: 700;
            text-decoration: none;
        }

        .inline-link.alt {
            color: #059669;
        }

        .inline-link:hover {
            text-decoration: underline;
        }

        .divider {
            color: #cbd5e1;
        }

        .empty-state {
            max-width: 640px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.96);
            border-radius: 24px;
            box-shadow: 0 14px 35px rgba(15, 23, 42, 0.08);
            padding: 40px;
            text-align: center;
        }

        .admin-panel {
            width: 100%;
            max-width: 960px;
            margin: 0 auto 24px;
        }

        .admin-panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding: 18px 22px;
            border-radius: 24px 24px 0 0;
            background: linear-gradient(135deg, #312e81 0%, #4338ca 100%);
            color: #fff;
        }

        .admin-panel-body {
            background: rgba(255, 255, 255, 0.97);
            border: 1px solid #e5e7eb;
            border-top: none;
            border-radius: 0 0 24px 24px;
            box-shadow: 0 14px 35px rgba(15, 23, 42, 0.08);
            padding: 24px;
        }

        .sports-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .sport-admin-card {
            border: 1px solid #dbe4f0;
            border-radius: 20px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            padding: 20px;
            box-shadow: inset 0 1px 0 rgba(255,255,255,0.8);
        }

        .sport-admin-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 16px;
        }

        .sport-admin-id {
            color: #94a3b8;
            font-size: 12px;
            font-family: Consolas, monospace;
            font-weight: 700;
        }

        .stack-3 {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .info-box {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 14px 16px;
        }

        .info-label {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 4px;
        }

        .info-value {
            color: #111827;
            font-weight: 700;
        }

        .info-value.indigo { color: #4338ca; }
        .info-value.green { color: #047857; }
        .info-value.amber { color: #b45309; }
        .info-value.blue { color: #2563eb; }
        .muted-text { color: #6b7280; font-size: 14px; }
        .small-text { color: #475569; font-size: 14px; }

        .action-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 12px 14px;
        }

        .action-label {
            font-size: 14px;
            font-weight: 700;
            color: #1f2937;
        }

        .status-text { font-size: 14px; font-weight: 700; }
        .status-text.green { color: #059669; }
        .status-text.blue { color: #2563eb; }
        .status-text.gray { color: #9ca3af; }

        .mini-btn {
            border: none;
            border-radius: 12px;
            padding: 10px 14px;
            color: #fff;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
        }

        .mini-btn.indigo { background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%); }
        .mini-btn.green { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }

        .history-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .history-item {
            font-size: 13px;
            color: #475569;
            background: #f8fafc;
            border-radius: 12px;
            padding: 9px 10px;
        }

        .history-sub {
            color: #6b7280;
            margin-top: 2px;
        }

        .results-shell {
            padding-bottom: 64px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        @media (max-width: 900px) {
            .sports-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 720px) {
            .search-form {
                flex-direction: column;
                align-items: stretch;
            }

            .search-form .btn {
                width: 100%;
            }

            .admin-panel-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .action-row {
                flex-direction: column;
                align-items: flex-start;
            }

            .mini-btn {
                width: 100%;
            }

            .id-card {
                width: calc(100% - 24px);
                height: auto;
                grid-template-columns: 1fr;
            }

            .id-card-visual {
                padding: 26px 20px 22px;
            }

            .id-card-content {
                padding: 24px 20px 22px;
            }

            .id-card-header,
            .id-card-body {
                flex-direction: column;
            }

            .id-card-title {
                font-size: 26px;
            }

            .detail-value.name {
                font-size: 25px;
            }

            .id-detail-grid,
            .sport-row-card {
                grid-template-columns: 1fr;
            }

            .sport-status-group {
                align-items: stretch;
                text-align: left;
            }

            .status-chip {
                width: 100%;
            }
        }

        .print-only { display: none; }
        
        @media print {
            body { font-size: 12pt; background: white; margin: 0; padding: 0; }
            .no-print { display: none !important; }
            .id-card { 
                box-shadow: none; 
                margin: 0 0 12px; 
                border: 1px solid #000;
                break-inside: avoid;
                page-break-inside: avoid;
            }
            .id-card::before,
            .id-card-visual,
            .team-pill,
            .status-chip,
            .sport-row-card {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            @page { margin: 0; size: auto; }
        }
    </style>
</head>
<body>
    <div class="page-shell no-print">
        
        <?php if($msg): 
            list($type, $text) = explode('|', $msg);
            $msgBg = $type === 'success' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700';
        ?>
        <div class="<?= $msgBg ?> border px-4 py-3 rounded relative mb-6 text-center animate-bounce" role="alert">
            <span class="block sm:inline"><?= htmlspecialchars($text) ?></span>
        </div>
        <?php endif; ?>

        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold mb-2">🔍 สแกนบัตรและตรวจสถานะนักกีฬา</h1>
            <p class="text-gray-500">หน้าสำหรับผู้ดูแล ใช้สแกนหรือพิมพ์รหัสนักศึกษาเพื่อดูรายการกีฬา รับเสื้อ และเช็คชื่อวันแข่ง</p>
        </div>

        <div class="search-panel">
            <form method="GET" action="search.php" class="search-form" id="searchForm">
                <button type="button" class="btn px-4" id="btnScan" style="background: #f3f4f6; border: 1px solid #d1d5db; border-radius: 8px;" title="สแกนด้วยกล้อง">📷</button>
                <input type="text" name="student_id" id="student_id_input" value="<?= htmlspecialchars($search_id) ?>" placeholder="ระบุรหัสนักศึกษา (เช่น 66012345)" class="form-input search-input" required autofocus autocomplete="off">
                <button type="submit" class="btn btn-primary px-8">ค้นหา</button>
            </form>
            <div id="qr-reader" style="width: 100%; display: none; margin-top: 15px; border-radius: 8px; overflow: hidden; border: 2px dashed #cbd5e1;"></div>
        </div>

        <div class="top-links">
            <a href="homeadmin.php" class="inline-link">← กลับหน้าแดชบอร์ดผู้ดูแล</a>
            <span class="divider">|</span>
            <a href="checkin_today.php" class="inline-link alt">ไปหน้าเช็คชื่อวันนี้</a>
            <?php if (!empty($results)): ?>
            <span class="divider">|</span>
            <button onclick="window.print()" class="btn btn-success">🖨️ พิมพ์บัตรทั้งหมด</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- ผลลัพธ์การค้นหา -->
    <div class="results-shell">
        <?php if ($search_id && empty($results)): ?>
            <div class="empty-state no-print">
                <div class="text-5xl mb-4">📭</div>
                <h3 class="text-xl font-bold mb-2" style="color:#1f2937;">ไม่พบข้อมูลนักกีฬา</h3>
                <p class="muted-text">กรุณาตรวจสอบรหัสนักศึกษา <b><?= htmlspecialchars($search_id) ?></b> อีกครั้ง <br>หรือนักศึกษายังไม่ได้ลงทะเบียนแข่งขัน</p>
            </div>
        <?php endif; ?>

        <?php foreach ($results as $index => $r): 
            $theme = $colorThemes[$r['color_id']] ?? ['accent' => '#64748b', 'accent_dark' => '#334155', 'soft' => '#e2e8f0'];
            $qrData = $r['student_id'];
            $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($qrData);
            
            // หาภาพโปรไฟล์ (ถ้าไม่มีใช้ภาพ placeholder)
            $fallbackAvatar = 'https://ui-avatars.com/api/?name=' . urlencode($r['student_name']) . '&background=2563eb&color=ffffff&size=240';
            $avatar = !empty($r['profile_img']) ? $r['profile_img'] : $fallbackAvatar;
        ?>
        
        <!-- แผงควบคุมสิทธิ์ Admin (ไม่พิมพ์) -->
        <div class="admin-panel no-print">
            <div class="admin-panel-header">
                <h3 class="font-bold">🛠️ แผงควบคุมรายการกีฬา: <?= htmlspecialchars($r['student_id']) ?></h3>
                <span class="text-xs opacity-70">สำหรับผู้ดูแล</span>
            </div>
            <div class="admin-panel-body">
                <div class="sports-grid">
                    <?php foreach($r['sports'] as $sp): ?>
                    <div class="sport-admin-card">
                        <div class="sport-admin-top">
                            <div>
                                <h4 class="font-bold text-lg"><?= htmlspecialchars($sp['sport_name']) ?></h4>
                                <p class="muted-text"><?= htmlspecialchars($sp['category']) ?></p>
                            </div>
                            <span class="sport-admin-id">#<?= $sp['reg_id'] ?></span>
                        </div>
                        
                        <div class="stack-3">
                            <div class="info-box">
                                <div class="info-label">ขนาดเสื้อที่ต้องรับ</div>
                                <div class="info-value indigo"><?= htmlspecialchars($sp['shirt_size']) ?></div>
                            </div>

                            <!-- รับเสื้อ -->
                            <div class="action-row">
                                <span class="action-label">👕 รับเสื้อกีฬา</span>
                                <?php if($sp['shirt_received']): ?>
                                    <span class="status-text green">✅ รับแล้ว (<?= date('H:i', strtotime($sp['shirt_received_at'])) ?>)</span>
                                <?php else: ?>
                                    <form method="POST" style="margin:0;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="reg_id" value="<?= $sp['reg_id'] ?>">
                                        <input type="hidden" name="action" value="receive_shirt">
                                        <input type="hidden" name="student_id" value="<?= htmlspecialchars($r['student_id']) ?>">
                                        <button type="submit" class="mini-btn indigo">ยืนยันการรับ</button>
                                    </form>
                                <?php endif; ?>
                            </div>

                            <div class="info-box">
                                <div class="info-label">กำหนดการแข่ง</div>
                                <?php if($sp['today_match']): ?>
                                    <div class="info-value green">วันนี้ <?= date('H:i', strtotime($sp['today_match']['start_time'])) ?> น.</div>
                                    <div class="small-text"><?= htmlspecialchars($sp['today_match']['round_name']) ?> • <?= htmlspecialchars($sp['today_match']['venue']) ?></div>
                                <?php elseif($sp['next_match']): ?>
                                    <div class="info-value amber">แข่งครั้งถัดไป <?= date('d/m/Y', strtotime($sp['next_match']['match_date'])) ?> <?= date('H:i', strtotime($sp['next_match']['start_time'])) ?> น.</div>
                                    <div class="small-text"><?= htmlspecialchars($sp['next_match']['round_name']) ?> • <?= htmlspecialchars($sp['next_match']['venue']) ?></div>
                                <?php else: ?>
                                    <div class="muted-text">ยังไม่พบตารางแข่งที่ตรงกับสีและประเภทนี้</div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- เช็คชื่อเข้าแข่ง -->
                            <div class="action-row">
                                <span class="action-label">สนาม 🏟️ เช็คชื่อเข้าแข่ง</span>
                                <?php if($sp['today_attendance']): ?>
                                    <span class="status-text blue">✅ เช็คชื่อแล้ว (<?= date('H:i', strtotime($sp['today_attendance']['checked_at'])) ?>)</span>
                                <?php elseif($sp['today_match']): ?>
                                    <form method="POST" style="margin:0;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="reg_id" value="<?= $sp['reg_id'] ?>">
                                        <input type="hidden" name="action" value="check_attendance">
                                        <input type="hidden" name="student_id" value="<?= htmlspecialchars($r['student_id']) ?>">
                                        <button type="submit" class="mini-btn green">เช็คชื่อเข้าแข่ง</button>
                                    </form>
                                <?php else: ?>
                                    <span class="status-text gray">รอวันแข่ง</span>
                                <?php endif; ?>
                            </div>

                            <div class="info-box">
                                <div class="info-label" style="margin-bottom:8px;">ประวัติย้อนหลัง</div>
                                <div class="history-list">
                                    <div class="small-text">
                                        <span style="font-weight:700;">รับเสื้อ:</span>
                                        <?= $sp['shirt_received_at'] ? 'รับเมื่อ ' . date('d/m/Y H:i', strtotime($sp['shirt_received_at'])) . ' น.' : 'ยังไม่มีประวัติรับเสื้อ' ?>
                                    </div>
                                    <?php if (!empty($sp['attendance_history'])): ?>
                                        <?php foreach($sp['attendance_history'] as $history): ?>
                                            <div class="history-item">
                                                เช็คชื่อ <?= date('d/m/Y', strtotime($history['attendance_date'])) ?> เวลา <?= date('H:i', strtotime($history['checked_at'])) ?> น.
                                                <?php if (!empty($history['round_name']) || !empty($history['venue'])): ?>
                                                    <div class="history-sub"><?= htmlspecialchars(trim(($history['round_name'] ?? '') . ' • ' . ($history['venue'] ?? ''), ' •')) ?></div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="muted-text">ยังไม่มีประวัติการเช็คชื่อ</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="id-card" style="--team-color: <?= htmlspecialchars($theme['accent']) ?>; --team-dark: <?= htmlspecialchars($theme['accent_dark']) ?>; --team-soft: <?= htmlspecialchars($theme['soft']) ?>;">
            <div class="id-card-visual">
                <div class="card-event-label">
                    <span>HUKAN GAME</span>
                    <span class="event-year"><?= htmlspecialchars($event_year) ?></span>
                </div>

                <img src="<?= htmlspecialchars($avatar) ?>" alt="รูปนักกีฬา" class="id-card-photo" crossorigin="anonymous" onerror="this.onerror=null;this.src='<?= htmlspecialchars($fallbackAvatar, ENT_QUOTES) ?>';">

                <div class="id-card-qr-box">
                    <img src="<?= htmlspecialchars($qrUrl) ?>" alt="คิวอาร์โค้ดรหัสนักศึกษา" class="id-card-qr" crossorigin="anonymous">
                    <div class="qr-copy">
                        สแกนเพื่อตรวจสอบบัตร
                        <strong><?= htmlspecialchars($r['student_id']) ?></strong>
                    </div>
                </div>
            </div>

            <div class="id-card-content">
                <div class="id-card-header">
                    <div>
                        <div class="id-card-title">บัตรนักกีฬา</div>
                        <div class="id-card-subtitle">ใช้สำหรับยืนยันตัวตน รับเสื้อ และเช็คชื่อเข้าแข่งขัน</div>
                    </div>
                    <span class="team-pill"><?= htmlspecialchars($r['color_name']) ?></span>
                </div>

                <div class="id-card-body">
                    <div class="student-name-block">
                        <span class="detail-label">ชื่อ-นามสกุลนักกีฬา</span>
                        <span class="detail-value name"><?= htmlspecialchars($r['student_name']) ?></span>
                    </div>

                    <div class="id-detail-grid">
                        <div class="detail-tile">
                            <span class="detail-label">รหัสนักศึกษา</span>
                            <span class="detail-value"><?= htmlspecialchars($r['student_id']) ?></span>
                        </div>
                        <div class="detail-tile">
                            <span class="detail-label">คณะ</span>
                            <span class="detail-value" style="font-size:18px;"><?= htmlspecialchars($r['faculty_name']) ?></span>
                        </div>
                    </div>

                    <div class="sports-card-list">
                        <div class="sports-card-heading">
                            <span>รายการกีฬาที่ลงทะเบียน</span>
                            <span><?= count($r['sports']) ?> รายการ</span>
                        </div>

                        <?php foreach($r['sports'] as $sp): ?>
                            <div class="sport-row-card">
                                <div>
                                    <div class="sport-title"><?= htmlspecialchars($sp['sport_name']) ?> (<?= htmlspecialchars($sp['category']) ?>)</div>
                                    <div class="sport-meta">ขนาดเสื้อ <?= htmlspecialchars($sp['shirt_size']) ?></div>
                                    <div class="sport-meta">
                                        <?php if($sp['today_match']): ?>
                                            แข่งวันนี้ <?= date('H:i', strtotime($sp['today_match']['start_time'])) ?> น. • <?= htmlspecialchars($sp['today_match']['venue']) ?>
                                        <?php elseif($sp['next_match']): ?>
                                            นัดถัดไป <?= date('d/m/Y', strtotime($sp['next_match']['match_date'])) ?> เวลา <?= date('H:i', strtotime($sp['next_match']['start_time'])) ?> น.
                                        <?php else: ?>
                                            รอประกาศตารางแข่งขัน
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="sport-status-group">
                                    <span class="status-chip <?= $sp['shirt_received'] ? 'done' : 'wait' ?>">
                                        <?= $sp['shirt_received'] ? 'รับเสื้อแล้ว' : 'รอรับเสื้อ' ?>
                                    </span>
                                    <span class="status-chip <?= $sp['today_attendance'] ? 'match' : 'neutral' ?>">
                                        <?= $sp['today_attendance'] ? 'เช็คชื่อแล้ว' : ($sp['today_match'] ? 'รอเช็คชื่อ' : 'ยังไม่ถึงวันแข่ง') ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const btnScan = document.getElementById('btnScan');
            const qrReaderDiv = document.getElementById('qr-reader');
            const inputField = document.getElementById('student_id_input');
            const searchForm = document.getElementById('searchForm');
            let html5QrcodeScanner = null;

            btnScan.addEventListener('click', function() {
                if (qrReaderDiv.style.display === 'none') {
                    qrReaderDiv.style.display = 'block';

                    if (!html5QrcodeScanner) {
                        html5QrcodeScanner = new Html5QrcodeScanner(
                            "qr-reader", { fps: 10, qrbox: 250 }, false);

                        html5QrcodeScanner.render(onScanSuccess, onScanFailure);
                    }
                } else {
                    qrReaderDiv.style.display = 'none';
                    if (html5QrcodeScanner) {
                        html5QrcodeScanner.clear();
                        html5QrcodeScanner = null;
                    }
                }
            });

            function onScanSuccess(decodedText, decodedResult) {
                let studentId = decodedText;
                try {
                    if (decodedText.includes('student_id=')) {
                        const url = new URL(decodedText);
                        studentId = url.searchParams.get('student_id') || decodedText;
                    }
                } catch(e) {}

                inputField.value = studentId;
                qrReaderDiv.style.display = 'none';
                if (html5QrcodeScanner) {
                    html5QrcodeScanner.clear();
                    html5QrcodeScanner = null;
                }

                searchForm.submit();
            }

            function onScanFailure(error) {}
        });
    </script>
</body>
</html>
