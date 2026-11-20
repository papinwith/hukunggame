<?php
include 'db.php';
require_once 'admin_auth.php';
require_once 'settings_helper.php';
ensure_schema($conn);

/* ปีที่เลือก (ค่าเริ่มต้น = ปีปัจจุบันในตั้งค่า) */
$years = [];
$yr = $conn->query("SELECT DISTINCT reg_year FROM sport_registration ORDER BY reg_year DESC");
while ($row = $yr->fetch_assoc()) $years[] = (int)$row['reg_year'];
$cur_year = (int)get_setting($conn, 'current_year');
if (!in_array($cur_year, $years)) array_unshift($years, $cur_year);
$year = isset($_GET['year']) ? (int)$_GET['year'] : $cur_year;

$colors = $conn->query("SELECT color_id, color_name FROM color_team ORDER BY color_id")->fetch_all(MYSQLI_ASSOC);

/* จำนวนสมัครต่อสี */
$per_color = [];
$stmt = $conn->prepare("SELECT color_id, COUNT(*) c, COUNT(DISTINCT student_id) s FROM sport_registration WHERE reg_year=? GROUP BY color_id");
$stmt->bind_param("i", $year); $stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) $per_color[$row['color_id']] = $row;

$tot = $conn->prepare("SELECT COUNT(*) c, COUNT(DISTINCT student_id) s FROM sport_registration WHERE reg_year=?");
$tot->bind_param("i", $year); $tot->execute();
$total = $tot->get_result()->fetch_assoc();

/* กีฬา x สี */
$sports = [];
$stmt = $conn->prepare("SELECT st.sport_id, st.sport_name, st.category, st.max_players, sr.color_id, COUNT(sr.reg_id) c
    FROM sport_type st LEFT JOIN sport_registration sr ON sr.sport_id = st.sport_id AND sr.reg_year = ?
    GROUP BY st.sport_id, sr.color_id ORDER BY st.sport_name, st.category");
$stmt->bind_param("i", $year); $stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $id = $row['sport_id'];
    if (!isset($sports[$id])) $sports[$id] = ['name' => $row['sport_name'], 'cat' => $row['category'], 'max' => (int)$row['max_players'], 'by' => [], 'sum' => 0];
    if ($row['color_id']) { $sports[$id]['by'][$row['color_id']] = (int)$row['c']; $sports[$id]['sum'] += (int)$row['c']; }
}
$max_color = 1;
foreach ($per_color as $pc) $max_color = max($max_color, (int)$pc['c']);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แดชบอร์ด - Admin</title>
    <?php include 'tailwind-config.php'; ?>
</head>

<body>
    <div class="container" style="max-width: 1000px;">

        <div class="text-center mb-8 animate-fade">
            <h1 class="text-2xl font-bold mb-2">📊 แดชบอร์ดผู้สมัคร</h1>
            <p class="text-gray">สรุปจำนวนผู้สมัครแต่ละสีและแต่ละกีฬา แยกรายปี</p>
        </div>

        <!-- Dashboard -->
        <div class="card mb-10">
            <div class="header-gradient" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                <h2 style="font-size: 18px; font-weight: 700;">📊 แดชบอร์ดผู้สมัคร ปี <?= $year ?></h2>
                <form method="get">
                    <select name="year" onchange="this.form.submit()" class="form-input" style="width:auto; padding:6px 12px;">
                        <?php foreach ($years as $y): ?>
                        <option value="<?= $y ?>" <?= $y == $year ? 'selected' : '' ?>>ปี <?= $y ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <div class="p-6">
                <div class="grid grid-2 mb-6">
                    <div style="background:#eef2ff; border-radius:16px; padding:16px; text-align:center;">
                        <div style="font-size:32px; font-weight:700; color:#6366f1;"><?= (int)$total['c'] ?></div>
                        <div class="text-gray text-sm">รายการสมัครทั้งหมด</div>
                    </div>
                    <div style="background:#ecfdf5; border-radius:16px; padding:16px; text-align:center;">
                        <div style="font-size:32px; font-weight:700; color:#059669;"><?= (int)$total['s'] ?></div>
                        <div class="text-gray text-sm">นักกีฬา (นับคนไม่ซ้ำ)</div>
                    </div>
                </div>

                <h3 class="font-bold mb-3">🎨 ผู้สมัครแต่ละสี</h3>
                <div class="mb-6">
                    <?php foreach ($colors as $c):
                        $pc = $per_color[$c['color_id']] ?? ['c' => 0, 's' => 0];
                        $hex = team_color_hex($c['color_name']);
                        $pct = round($pc['c'] / $max_color * 100);
                    ?>
                    <div style="margin-bottom:12px;">
                        <div style="display:flex; justify-content:space-between; font-size:14px; margin-bottom:4px;">
                            <span style="font-weight:600;"><?= htmlspecialchars($c['color_name']) ?></span>
                            <span><?= (int)$pc['c'] ?> รายการ • <?= (int)$pc['s'] ?> คน</span>
                        </div>
                        <div style="background:#f3f4f6; border-radius:50px; height:12px; overflow:hidden;">
                            <div style="width:<?= $pct ?>%; background:<?= $hex ?>; height:100%; border-radius:50px;"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <h3 class="font-bold mb-3">🏅 รายกีฬา (สมัครแล้ว / สูงสุดต่อสี)</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>กีฬา</th>
                                <th>ประเภท</th>
                                <?php foreach ($colors as $c): ?>
                                <th style="text-align:center;"><span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:<?= team_color_hex($c['color_name']) ?>;"></span> <?= htmlspecialchars(preg_replace('/^สี/u', '', $c['color_name'])) ?></th>
                                <?php endforeach; ?>
                                <th style="text-align:center;">รวม</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sports as $sp): ?>
                            <tr>
                                <td style="font-weight:500;"><?= htmlspecialchars($sp['name']) ?></td>
                                <td><?= htmlspecialchars($sp['cat']) ?></td>
                                <?php foreach ($colors as $c):
                                    $n = $sp['by'][$c['color_id']] ?? 0;
                                    $full = $sp['max'] > 0 && $n >= $sp['max'];
                                ?>
                                <td style="text-align:center; <?= $full ? 'color:#dc2626; font-weight:700;' : ($n == 0 ? 'color:#9ca3af;' : '') ?>">
                                    <?= $n ?><?= $sp['max'] > 0 ? ' / ' . $sp['max'] : '' ?><?= $full ? ' เต็ม' : '' ?>
                                </td>
                                <?php endforeach; ?>
                                <td style="text-align:center; font-weight:700;"><?= $sp['sum'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (!$sports): ?>
                            <tr><td colspan="<?= count($colors) + 3 ?>" class="text-center text-gray p-8">ยังไม่มีกีฬา</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="text-center mb-8">
            <a href="homeadmin.php" class="btn-back">🏠 Admin Home</a>
        </div>
    </div>
</body>
</html>
