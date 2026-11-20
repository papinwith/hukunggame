<?php
/*
 * บัตรนักกีฬา
 *   player_card.php?reg_id=ID                              -> บัตรรายคน
 *   player_card.php?color_id=&sport_name=&category=        -> บัตรทั้งทีม (พิมพ์ทีเดียว)
 */
include 'db.php';
require_once 'admin_auth.php';
require_once 'settings_helper.php';
ensure_schema($conn);

$reg_id     = (int)($_GET['reg_id'] ?? 0);
$color_id   = (int)($_GET['color_id'] ?? 0);
$sport_name = $_GET['sport_name'] ?? '';
$category   = $_GET['category'] ?? '';

$select = "SELECT sr.reg_id, sr.student_id, sr.student_name, sr.sport_name, sr.category, sr.color_id,
                  f.faculty_name, c.color_name, ps.shirt_size, p.image_path AS profile_img
           FROM sport_registration sr
           JOIN faculty f ON sr.faculty_id = f.faculty_id
           JOIN color_team c ON sr.color_id = c.color_id
           LEFT JOIN player_size ps ON sr.student_id = ps.student_id AND sr.sport_id = ps.sport_id
           LEFT JOIN image p ON p.student_id = sr.student_id AND p.sport_id = sr.sport_id AND p.image_type = 'profile'";

if ($reg_id) {
    $stmt = $conn->prepare($select . " WHERE sr.reg_id = ?");
    $stmt->bind_param("i", $reg_id);
} elseif ($color_id && $sport_name !== '') {
    $sql = $select . " WHERE sr.color_id = ? AND sr.sport_name = ?" . ($category !== '' ? " AND sr.category = ?" : "") . " ORDER BY sr.student_name";
    $stmt = $conn->prepare($sql);
    if ($category !== '') $stmt->bind_param("iss", $color_id, $sport_name, $category);
    else $stmt->bind_param("is", $color_id, $sport_name);
} else {
    die("ข้อมูลไม่ครบ");
}
$stmt->execute();
$cards = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$event_name  = get_setting($conn, 'event_name');
$card_footer = get_setting($conn, 'card_footer');

function card_color($name) {
    $map = ['แดง' => '#dc2626', 'เหลือง' => '#d97706', 'เขียว' => '#059669', 'น้ำเงิน' => '#2563eb', 'ฟ้า' => '#0ea5e9', 'ม่วง' => '#7c3aed', 'ส้ม' => '#ea580c', 'ชมพู' => '#db2777'];
    foreach ($map as $k => $v) if (mb_strpos($name, $k) !== false) return $v;
    return '#4b5563';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>บัตรนักกีฬา - <?= htmlspecialchars($event_name) ?></title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Sarabun', 'Segoe UI', Tahoma, sans-serif; background: #f3f4f6; margin: 0; padding: 16px; color: #1f2937; }
        .toolbar { text-align: center; margin-bottom: 16px; }
        .toolbar button, .toolbar a { font: inherit; padding: 10px 22px; border-radius: 10px; border: none; background: #6366f1; color: #fff; cursor: pointer; text-decoration: none; display: inline-block; margin: 0 4px; }
        .toolbar a.secondary { background: #e5e7eb; color: #374151; }
        .sheet { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; }
        .pcard { width: 54mm; height: 86mm; background: #fff; border-radius: 3mm; overflow: hidden; border: 1px solid #d1d5db; display: flex; flex-direction: column; page-break-inside: avoid; break-inside: avoid; }
        .pcard .top { color: #fff; text-align: center; padding: 3mm 2mm 2mm; }
        .pcard .top .ev { font-size: 9pt; font-weight: 700; line-height: 1.2; }
        .pcard .top .tm { font-size: 8pt; opacity: .95; }
        .pcard .photo { width: 28mm; height: 34mm; margin: 3mm auto 2mm; border: 1.5mm solid; border-radius: 2mm; overflow: hidden; background: #e5e7eb; display: flex; align-items: center; justify-content: center; color: #9ca3af; font-size: 8pt; }
        .pcard .photo img { width: 100%; height: 100%; object-fit: cover; }
        .pcard .name { text-align: center; font-weight: 700; font-size: 10pt; padding: 0 2mm; line-height: 1.2; }
        .pcard .sid { text-align: center; font-family: monospace; font-size: 9pt; margin-top: 1mm; }
        .pcard .info { text-align: center; font-size: 8pt; color: #4b5563; margin-top: 1mm; padding: 0 2mm; line-height: 1.3; }
        .pcard .sport { margin: 2mm 3mm 0; text-align: center; font-weight: 700; font-size: 9pt; padding: 1mm 2mm; border-radius: 2mm; color: #fff; }
        .pcard .foot { margin-top: auto; display: flex; justify-content: space-between; align-items: center; font-size: 7pt; color: #6b7280; padding: 1.5mm 3mm; border-top: 1px dashed #d1d5db; }
        .pcard .size { font-weight: 700; color: #1f2937; }
        .note { text-align: center; font-size: 7pt; color: #9ca3af; padding: 0 2mm 1.5mm; }
        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none; }
            .sheet { gap: 4mm; justify-content: flex-start; }
            .pcard { border: 1px solid #9ca3af; }
            @page { margin: 8mm; }
            * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button onclick="window.print()">🖨️ พิมพ์บัตร (<?= count($cards) ?> ใบ)</button>
        <a href="javascript:history.back()" class="secondary">← กลับ</a>
    </div>

    <?php if (!$cards): ?>
        <p style="text-align:center; color:#6b7280;">ไม่พบข้อมูลนักกีฬา</p>
    <?php else: ?>
    <div class="sheet">
        <?php foreach ($cards as $c): $col = card_color($c['color_name']); ?>
        <div class="pcard">
            <div class="top" style="background: <?= $col ?>;">
                <div class="ev"><?= htmlspecialchars($event_name) ?></div>
                <div class="tm">ทีม<?= htmlspecialchars(preg_replace('/^สี/u', 'สี', $c['color_name'])) ?></div>
            </div>
            <div class="photo" style="border-color: <?= $col ?>;">
                <?php if ($c['profile_img']): ?><img src="<?= htmlspecialchars($c['profile_img']) ?>" alt=""><?php else: ?>ไม่มีรูป<?php endif; ?>
            </div>
            <div class="name"><?= htmlspecialchars($c['student_name']) ?></div>
            <div class="sid"><?= htmlspecialchars($c['student_id']) ?></div>
            <div class="info"><?= htmlspecialchars($c['faculty_name']) ?></div>
            <div class="sport" style="background: <?= $col ?>;"><?= htmlspecialchars($c['sport_name']) ?> • <?= htmlspecialchars($c['category']) ?></div>
            <div class="foot">
                <span>No. <?= str_pad($c['reg_id'], 4, '0', STR_PAD_LEFT) ?></span>
                <span>ไซส์ <span class="size"><?= htmlspecialchars($c['shirt_size'] ?? '-') ?></span></span>
            </div>
            <?php if ($card_footer !== ''): ?><div class="note"><?= htmlspecialchars($card_footer) ?></div><?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</body>
</html>
