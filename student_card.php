<?php

include 'db.php';
require_once 'settings_helper.php';
ensure_schema($conn);
include 'admin_scan_helpers.php';
$event_name = get_setting($conn, 'event_name');

$searchId = trim($_GET['student_id'] ?? '');
$results = [];

if ($searchId !== '') {
    $results = loadStudentSports($conn, $searchId);
}

$colorThemes = [
    1 => ['badge' => '#ef4444', 'soft' => '#fee2e2'],
    2 => ['badge' => '#f59e0b', 'soft' => '#fef3c7'],
    3 => ['badge' => '#10b981', 'soft' => '#d1fae5'],
    4 => ['badge' => '#3b82f6', 'soft' => '#dbeafe'],
];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ค้นหาบัตรนักกีฬา</title>
    <?php include 'tailwind-config.php'; ?>
    <style>
        .student-card-shell {
            max-width: 1120px;
            margin: 0 auto;
            padding: 32px 16px 64px;
        }

        .student-search-panel {
            max-width: 780px;
            margin: 0 auto 28px;
            background: rgba(255,255,255,0.96);
            border: 1px solid #e5e7eb;
            border-radius: 26px;
            box-shadow: 0 16px 40px rgba(15, 23, 42, 0.08);
            padding: 24px;
        }

        .student-search-form {
            display: flex;
            gap: 16px;
            align-items: center;
        }

        .student-search-input {
            flex: 1;
        }

        .helper-links {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            margin-bottom: 26px;
        }

        .helper-links a {
            color: #4f46e5;
            font-weight: 700;
            text-decoration: none;
        }

        .helper-links a:hover {
            text-decoration: underline;
        }

        .helper-divider {
            color: #cbd5e1;
        }

        .student-result-list {
            display: flex;
            flex-direction: column;
            gap: 28px;
            align-items: center;
        }

        .student-summary {
            width: 100%;
            max-width: 960px;
            background: rgba(255,255,255,0.97);
            border: 1px solid #e5e7eb;
            border-radius: 24px;
            box-shadow: 0 16px 40px rgba(15, 23, 42, 0.08);
            padding: 24px;
        }

        .student-summary-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 18px;
        }

        .student-badges {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 14px;
        }

        .student-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 999px;
            color: #111827;
            font-size: 14px;
            font-weight: 700;
        }

        .sports-info-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .sports-info-card {
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid #dbe4f0;
            border-radius: 20px;
            padding: 18px;
        }

        .sports-info-card h3 {
            color: #111827;
            margin-bottom: 4px;
        }

        .sports-meta,
        .sports-copy {
            color: #6b7280;
            font-size: 14px;
        }

        .sports-block {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 14px 16px;
            margin-top: 12px;
        }

        .sports-label {
            color: #64748b;
            font-size: 13px;
            margin-bottom: 4px;
        }

        .sports-strong {
            color: #111827;
            font-weight: 700;
        }

        .sports-strong.indigo { color: #4338ca; }
        .sports-strong.green { color: #047857; }
        .sports-strong.amber { color: #b45309; }

        .id-card {
            width: 900px;
            max-width: 100%;
            min-height: 500px;
            background: #ffffff;
            border-radius: 22px;
            box-shadow: 0 16px 36px rgba(15, 23, 42, 0.1);
            position: relative;
            overflow: hidden;
            display: flex;
            border: 2px solid #e5e7eb;
        }

        .id-card-color-bar {
            width: 24px;
            height: 100%;
            flex-shrink: 0;
        }

        .id-card-content {
            flex: 1;
            padding: 30px;
            display: flex;
            flex-direction: column;
        }

        .id-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 24px;
            border-bottom: 2px solid #f3f4f6;
            padding-bottom: 16px;
        }

        .id-card-title {
            font-size: 28px;
            font-weight: 800;
            color: #111827;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .id-card-subtitle {
            font-size: 16px;
            color: #6b7280;
            font-weight: 600;
        }

        .id-card-body {
            display: flex;
            gap: 30px;
            flex: 1;
        }

        .id-card-photo-container {
            width: 160px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .id-card-photo {
            width: 140px;
            height: 180px;
            object-fit: cover;
            border-radius: 12px;
            border: 4px solid #f3f4f6;
            background: #e5e7eb;
            margin-bottom: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }

        .id-card-qr {
            width: 100px;
            height: 100px;
            padding: 4px;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
        }

        .id-card-details {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .detail-row {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 13px;
            color: #6b7280;
            text-transform: uppercase;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .detail-value {
            font-size: 22px;
            font-weight: 700;
            color: #1f2937;
        }

        .detail-value.name {
            font-size: 26px;
            color: #111827;
        }

        .card-bottom-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: auto;
        }

        .card-bottom-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border: 1px solid #e5e7eb;
            border-radius: 14px;
            background: #f8fafc;
        }

        .card-bottom-item strong {
            color: #111827;
            display: block;
        }

        .card-bottom-item span {
            color: #6b7280;
            font-size: 13px;
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

        @media (max-width: 900px) {
            .sports-info-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 720px) {
            .student-search-form,
            .student-summary-header,
            .id-card-body,
            .card-bottom-item {
                flex-direction: column;
                align-items: stretch;
            }

            .student-search-form .btn {
                width: 100%;
            }

            .id-card-photo-container {
                width: 100%;
                flex-direction: row;
                justify-content: space-between;
            }

            .id-card-content {
                padding: 22px;
            }
        }

        @media print {
            body { background: white; }
            .no-print { display: none !important; }
            .id-card {
                box-shadow: none;
                border: 1px solid #000;
            }
            @page { margin: 10mm; }
        }
    </style>
</head>
<body>
    <div class="student-card-shell no-print">
        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold mb-2">📇 ค้นหาบัตรนักกีฬา</h1>
            <p class="text-gray">สำหรับนักศึกษา ใช้ค้นหาบัตรด้วยรหัสนักศึกษาเพื่อดูข้อมูลการแข่งขันของตัวเอง</p>
        </div>

        <div class="student-search-panel">
            <form method="GET" action="student_card.php" class="student-search-form">
                <input type="text" name="student_id" value="<?= htmlspecialchars($searchId) ?>" placeholder="ระบุรหัสนักศึกษา" class="form-input student-search-input" required autofocus autocomplete="off">
                <button type="submit" class="btn btn-primary">ค้นหาบัตร</button>
            </form>
        </div>

        <div class="helper-links">
            <a href="home.php">← กลับหน้าหลัก</a>
            <?php if (!empty($results)): ?>
            <span class="helper-divider">|</span>
            <button onclick="window.print()" class="btn btn-success">🖨️ พิมพ์บัตร</button>
            <?php endif; ?>
        </div>
    </div>

    <div class="student-result-list">
        <?php if ($searchId !== '' && empty($results)): ?>
            <div class="empty-state no-print">
                <div class="text-5xl mb-4">📭</div>
                <h3 class="text-xl font-bold mb-2" style="color:#1f2937;">ไม่พบข้อมูลบัตรนักกีฬา</h3>
                <p style="color:#6b7280;">กรุณาตรวจสอบรหัสนักศึกษาอีกครั้ง หรือติดต่อผู้ดูแลหากลงทะเบียนแล้วแต่ยังไม่พบข้อมูล</p>
            </div>
        <?php endif; ?>

        <?php foreach ($results as $student):
            $theme = $colorThemes[$student['color_id']] ?? ['badge' => '#6b7280', 'soft' => '#e5e7eb'];
            $qrData = $student['student_id'];
            $qrUrl = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($qrData);
            $avatar = !empty($student['profile_img']) ? $student['profile_img'] : 'https://ui-avatars.com/api/?name=' . urlencode($student['student_name']) . '&background=random&size=200';
        ?>
            <div class="id-card">
                <div class="id-card-color-bar" style="background: <?= htmlspecialchars($theme['badge']) ?>;"></div>
                <div class="id-card-content">
                    <div class="id-card-header">
                        <div>
                            <div class="id-card-title">ATHLETE ID CARD</div>
                            <div class="id-card-subtitle"><?= htmlspecialchars($event_name) ?></div>
                        </div>
                        <div style="font-size:32px; opacity:0.8;">🏅</div>
                    </div>

                    <div class="id-card-body">
                        <div class="id-card-photo-container">
                            <img src="<?= htmlspecialchars($avatar) ?>" alt="Athlete Photo" class="id-card-photo" crossorigin="anonymous">
                            <img src="<?= htmlspecialchars($qrUrl) ?>" alt="QR Code" class="id-card-qr" crossorigin="anonymous">
                        </div>

                        <div class="id-card-details">
                            <div class="detail-row">
                                <span class="detail-label">Athlete Name / ชื่อ-นามสกุล</span>
                                <span class="detail-value name"><?= htmlspecialchars($student['student_name']) ?></span>
                            </div>

                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                                <div class="detail-row">
                                    <span class="detail-label">Student ID / รหัสนักศึกษา</span>
                                    <span class="detail-value"><?= htmlspecialchars($student['student_id']) ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Faculty / คณะ</span>
                                    <span class="detail-value" style="font-size:18px; line-height:1.2;"><?= htmlspecialchars($student['faculty_name']) ?></span>
                                </div>
                            </div>

                            <div class="detail-row">
                                <span class="detail-label">Team / สีประจำคณะ</span>
                                <span class="detail-value" style="font-size:18px;"><?= htmlspecialchars($student['color_name']) ?></span>
                            </div>

                            <div class="card-bottom-list">
                                <?php foreach ($student['sports'] as $sport): ?>
                                    <div class="card-bottom-item">
                                        <div>
                                            <strong><?= htmlspecialchars($sport['sport_name']) ?> (<?= htmlspecialchars($sport['category']) ?>)</strong>
                                            <span>ขนาดเสื้อ <?= htmlspecialchars($sport['shirt_size']) ?></span>
                                        </div>
                                        <div style="text-align:right;">
                                            <strong style="color: <?= $sport['shirt_received'] ? '#059669' : '#d97706' ?>;">
                                                <?= $sport['shirt_received'] ? 'รับเสื้อแล้ว' : 'รอรับเสื้อ' ?>
                                            </strong>
                                            <span>
                                                <?php if ($sport['today_match']): ?>
                                                    แข่งวันนี้ <?= date('H:i', strtotime($sport['today_match']['start_time'])) ?> น.
                                                <?php elseif ($sport['next_match']): ?>
                                                    นัดถัดไป <?= date('d/m/Y', strtotime($sport['next_match']['match_date'])) ?>
                                                <?php else: ?>
                                                    รอประกาศตารางแข่ง
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>