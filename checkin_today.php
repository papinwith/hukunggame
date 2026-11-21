<?php

include 'db.php';
require_once 'admin_auth.php';
include 'admin_scan_helpers.php';

$searchId = $_GET['student_id'] ?? '';
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $regId = (int) ($_POST['reg_id'] ?? 0);
    $action = $_POST['action'];

    if ($action === 'check_attendance') {
        $status = performAttendanceCheckin($conn, $regId);
        $msg = $status['type'] . '|' . $status['text'];
    }

    $searchId = trim($_POST['student_id'] ?? $searchId);
}

$results = [];
if ($searchId !== '') {
    $loadedResults = loadStudentSports($conn, $searchId);
    foreach ($loadedResults as $studentId => $student) {
        $todaySports = array_values(array_filter($student['sports'], static fn($sport) => !empty($sport['today_match'])));
        if (!empty($todaySports)) {
            $student['sports'] = $todaySports;
            $results[$studentId] = $student;
        }
    }
}

$todaySchedule = [];
$scheduleQuery = $conn->query(
    "SELECT ms.match_id, ms.match_date, ms.start_time, ms.round_name, ms.venue, st.sport_name, ms.category
     FROM match_schedule ms
     JOIN sport_type st ON ms.sport_id = st.sport_id
     WHERE ms.match_date = CURDATE()
     ORDER BY ms.start_time ASC, ms.match_no ASC"
);
if ($scheduleQuery instanceof mysqli_result) {
    while ($row = $scheduleQuery->fetch_assoc()) {
        $todaySchedule[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เช็คชื่อการแข่งขันวันนี้ - ผู้ดูแล</title>
    <?php include 'tailwind-config.php'; ?>
    <style>
        .checkin-shell {
            max-width: 1100px;
            margin: 0 auto;
            padding: 32px 16px 64px;
        }

        .hero-panel {
            max-width: 820px;
            margin: 0 auto 32px;
            background: rgba(255,255,255,0.96);
            border-radius: 24px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 16px 40px rgba(15, 23, 42, 0.08);
            padding: 24px;
        }

        .checkin-form {
            display: flex;
            gap: 16px;
            align-items: center;
        }

        .checkin-input {
            flex: 1;
        }

        .overview-grid {
            display: grid;
            grid-template-columns: minmax(0, 2fr) minmax(280px, 1fr);
            gap: 24px;
            margin-bottom: 40px;
        }

        .panel-card {
            background: rgba(255,255,255,0.96);
            border-radius: 24px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 14px 35px rgba(15, 23, 42, 0.08);
            padding: 24px;
        }

        .schedule-list,
        .quick-links,
        .student-result-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .schedule-item,
        .quick-link,
        .sport-checkin-card,
        .empty-card {
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            padding: 16px;
        }

        .schedule-item-row,
        .result-header,
        .result-status-row,
        .student-banner {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
        }

        .quick-link {
            text-decoration: none;
            color: inherit;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .quick-link:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(15, 23, 42, 0.07);
        }

        .student-card {
            background: rgba(255,255,255,0.97);
            border-radius: 24px;
            box-shadow: 0 16px 40px rgba(15, 23, 42, 0.08);
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }

        .student-banner {
            background: linear-gradient(135deg, #047857 0%, #10b981 100%);
            color: #fff;
            padding: 20px 24px;
        }

        .banner-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 14px;
            border-radius: 12px;
            background: rgba(255,255,255,0.14);
            color: #fff;
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
        }

        .student-content {
            padding: 24px;
        }

        .student-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }

        .sport-checkin-card {
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        }

        .mini-info {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 16px;
            padding: 14px 16px;
            margin-bottom: 14px;
        }

        .mini-label {
            color: #64748b;
            font-size: 13px;
            margin-bottom: 4px;
        }

        .value-strong { font-weight: 700; color: #111827; }
        .value-emerald { font-weight: 700; color: #047857; }
        .value-indigo { font-weight: 700; color: #4338ca; }
        .value-blue { font-weight: 700; color: #2563eb; }
        .muted-copy { color: #6b7280; font-size: 14px; }

        .confirm-btn {
            border: none;
            border-radius: 12px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            font-weight: 700;
            font-size: 14px;
            padding: 11px 16px;
            cursor: pointer;
        }

        @media (max-width: 900px) {
            .overview-grid,
            .student-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 720px) {
            .checkin-form,
            .schedule-item-row,
            .result-header,
            .result-status-row,
            .student-banner {
                flex-direction: column;
                align-items: flex-start;
            }

            .checkin-form .btn,
            .confirm-btn,
            .banner-link {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="checkin-shell">
        <?php if($msg):
            list($type, $text) = explode('|', $msg);
            $msgBg = $type === 'success' ? 'bg-green-100 border-green-400 text-green-700' : 'bg-red-100 border-red-400 text-red-700';
        ?>
        <div class="<?= $msgBg ?> border px-4 py-3 rounded relative mb-6 text-center" role="alert">
            <span class="block sm:inline"><?= htmlspecialchars($text) ?></span>
        </div>
        <?php endif; ?>

        <div class="text-center mb-8">
            <h1 class="text-3xl font-bold mb-2">เช็คชื่อการแข่งขันวันนี้</h1>
            <p class="text-gray-500">หน้าสำหรับผู้ดูแล ใช้สแกนบัตรหรือกรอกรหัสนักศึกษาเพื่อเช็คชื่อเฉพาะรายการที่มีแข่งวันนี้</p>
        </div>

        <div class="hero-panel">
            <form method="GET" action="checkin_today.php" class="checkin-form" id="checkinForm">
                <button type="button" class="btn px-4" id="btnScan" style="background:#f3f4f6; border:1px solid #d1d5db; border-radius:8px;" title="สแกนด้วยกล้อง">📷</button>
                <input type="text" name="student_id" id="student_id_input" value="<?= htmlspecialchars($searchId) ?>" placeholder="ระบุรหัสนักศึกษา" class="form-input checkin-input" required autofocus autocomplete="off">
                <button type="submit" class="btn btn-primary px-8">ค้นหา</button>
            </form>
            <div id="qr-reader" style="width:100%; display:none; margin-top:15px; border-radius:8px; overflow:hidden; border:2px dashed #cbd5e1;"></div>
        </div>

        <div class="overview-grid">
            <div class="panel-card">
                <div class="result-header" style="margin-bottom:16px;">
                    <h2 class="text-xl font-bold">ตารางแข่งวันนี้</h2>
                    <span class="muted-copy"><?= date('d/m/Y') ?></span>
                </div>
                <?php if (!empty($todaySchedule)): ?>
                    <div class="schedule-list">
                        <?php foreach ($todaySchedule as $match): ?>
                            <div class="schedule-item">
                                <div class="schedule-item-row">
                                    <div>
                                        <div class="value-strong"><?= htmlspecialchars($match['sport_name']) ?> (<?= htmlspecialchars($match['category']) ?>)</div>
                                        <div class="muted-copy"><?= htmlspecialchars($match['round_name']) ?> • <?= htmlspecialchars($match['venue']) ?></div>
                                    </div>
                                    <div class="value-emerald"><?= date('H:i', strtotime($match['start_time'])) ?> น.</div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="muted-copy">วันนี้ยังไม่มีตารางแข่ง</div>
                <?php endif; ?>
            </div>

            <div class="panel-card">
                <h2 class="text-xl font-bold mb-4">ทางลัด</h2>
                <div class="quick-links">
                    <a href="search.php" class="quick-link">
                        <div class="value-indigo">สแกนบัตรและดูประวัติ</div>
                        <div class="muted-copy">ดูข้อมูลการรับเสื้อและประวัติเช็คชื่อย้อนหลัง</div>
                    </a>
                    <a href="homeadmin.php" class="quick-link">
                        <div class="value-strong">กลับแดชบอร์ดผู้ดูแล</div>
                        <div class="muted-copy">กลับไปหน้าจัดการหลักของผู้ดูแล</div>
                    </a>
                </div>
            </div>
        </div>

        <div class="student-result-list">
            <?php if ($searchId !== '' && empty($results)): ?>
                <div class="panel-card" style="text-align:center;">
                    <div class="text-4xl mb-3">🗓️</div>
                    <div class="text-xl font-bold mb-2" style="color:#1f2937;">วันนี้ไม่พบรายการแข่งของนักศึกษาคนนี้</div>
                    <div class="muted-copy">ตรวจสอบรหัสนักศึกษาอีกครั้ง หรือดูข้อมูลทั้งหมดที่หน้าสแกนบัตรหลัก</div>
                </div>
            <?php endif; ?>

            <?php foreach ($results as $student): ?>
                <div class="student-card">
                    <div class="student-banner">
                        <div>
                            <div class="text-2xl font-bold"><?= htmlspecialchars($student['student_name']) ?></div>
                            <div class="text-sm opacity-90">รหัส <?= htmlspecialchars($student['student_id']) ?> • <?= htmlspecialchars($student['faculty_name']) ?> • <?= htmlspecialchars($student['color_name']) ?></div>
                        </div>
                        <a href="search.php?student_id=<?= urlencode($student['student_id']) ?>" class="banner-link">ดูข้อมูลเต็ม</a>
                    </div>

                    <div class="student-content">
                    <div class="student-grid">
                        <?php foreach ($student['sports'] as $sport): ?>
                            <div class="sport-checkin-card">
                                <div class="result-header" style="margin-bottom:16px; align-items:flex-start;">
                                    <div>
                                        <div class="text-lg font-bold" style="color:#111827;"><?= htmlspecialchars($sport['sport_name']) ?></div>
                                        <div class="muted-copy"><?= htmlspecialchars($sport['category']) ?></div>
                                    </div>
                                    <div style="text-align:right;">
                                        <div class="mini-label">ขนาดเสื้อ</div>
                                        <div class="value-indigo"><?= htmlspecialchars($sport['shirt_size']) ?></div>
                                    </div>
                                </div>

                                <div class="mini-info">
                                    <div class="mini-label">รายการแข่งวันนี้</div>
                                    <div class="value-emerald"><?= date('H:i', strtotime($sport['today_match']['start_time'])) ?> น.</div>
                                    <div class="muted-copy"><?= htmlspecialchars($sport['today_match']['round_name']) ?> • <?= htmlspecialchars($sport['today_match']['venue']) ?></div>
                                </div>

                                <div class="mini-info" style="margin-bottom:0;">
                                <div class="result-status-row">
                                    <div>
                                        <div class="value-strong">สถานะเช็คชื่อ</div>
                                        <?php if ($sport['today_attendance']): ?>
                                            <div class="muted-copy" style="color:#2563eb;">เช็คชื่อแล้วเวลา <?= date('H:i', strtotime($sport['today_attendance']['checked_at'])) ?> น.</div>
                                        <?php else: ?>
                                            <div class="muted-copy">ยังไม่เช็คชื่อ</div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($sport['today_attendance']): ?>
                                        <span class="value-blue">พร้อมแข่งขัน</span>
                                    <?php else: ?>
                                        <form method="POST" style="margin:0;">
                                        <?= csrf_field() ?>
                                            <input type="hidden" name="reg_id" value="<?= $sport['reg_id'] ?>">
                                            <input type="hidden" name="action" value="check_attendance">
                                            <input type="hidden" name="student_id" value="<?= htmlspecialchars($student['student_id']) ?>">
                                            <button type="submit" class="confirm-btn">เช็คชื่อเข้าแข่ง</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const btnScan = document.getElementById('btnScan');
            const qrReaderDiv = document.getElementById('qr-reader');
            const inputField = document.getElementById('student_id_input');
            const checkinForm = document.getElementById('checkinForm');
            let html5QrcodeScanner = null;

            btnScan.addEventListener('click', function() {
                if (qrReaderDiv.style.display === 'none') {
                    qrReaderDiv.style.display = 'block';

                    if (!html5QrcodeScanner) {
                        html5QrcodeScanner = new Html5QrcodeScanner(
                            'qr-reader', { fps: 10, qrbox: 250 }, false
                        );
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

            function onScanSuccess(decodedText) {
                let studentId = decodedText;
                try {
                    if (decodedText.includes('student_id=')) {
                        const url = new URL(decodedText);
                        studentId = url.searchParams.get('student_id') || decodedText;
                    }
                } catch (error) {}

                inputField.value = studentId;
                qrReaderDiv.style.display = 'none';
                if (html5QrcodeScanner) {
                    html5QrcodeScanner.clear();
                    html5QrcodeScanner = null;
                }
                checkinForm.submit();
            }

            function onScanFailure(error) {
            }
        });
    </script>
</body>
</html>