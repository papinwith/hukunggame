<?php
include 'db.php';
mysqli_set_charset($conn,"utf8");

$sport_id = $_GET['sport_id'] ?? '';
$category = $_GET['category'] ?? '';

if(!$sport_id) die("กรุณาเลือกกีฬา");

$stmt0 = $conn->prepare("SELECT sport_name FROM sport_type WHERE sport_id=? LIMIT 1");
$stmt0->bind_param("i", $sport_id);
$stmt0->execute();
$r0 = $stmt0->get_result()->fetch_assoc();
$sport_name = $r0['sport_name'] ?? '';
$stmt0->close();

/* เลือกประเภท */
if(!$category){
    $stmt = $conn->prepare("SELECT DISTINCT category FROM sport_type WHERE sport_name = ? ORDER BY category");
    $stmt->bind_param("s", $sport_name);
    $stmt->execute();
    $cats = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เลือกประเภท - <?= htmlspecialchars($sport_name) ?></title>
    <?php include 'tailwind-config.php'; ?>
</head>

<body>
    <div class="container">
        <div class="breadcrumb">
            <a href="home.php">🏠 หน้าหลัก</a>
            <span>›</span>
            <a href="list.php">ตารางแข่งขัน</a>
            <span>›</span>
            <span style="color: #1f2937; font-weight: 500;"><?= htmlspecialchars($sport_name) ?></span>
        </div>
        
        <div class="text-center mb-8 animate-fade">
            <h1 class="text-2xl font-bold mb-2">🏆 <?= htmlspecialchars($sport_name) ?></h1>
            <p class="text-gray">เลือกประเภทเพื่อดูตารางแข่งขัน</p>
        </div>
        
        <div class="grid grid-2 mb-10" style="max-width: 500px; margin: 0 auto 40px;">
            <?php while($c = $cats->fetch_assoc()): ?>
            <a href="list2.php?sport_id=<?= $sport_id ?>&category=<?= urlencode($c['category']) ?>" class="sport-card">
                <div class="sport-icon">🎯</div>
                <div class="sport-name"><?= htmlspecialchars($c['category']) ?></div>
                <p style="color: #6b7280; font-size: 14px; margin-top: 4px;">ดูตารางแข่งขัน →</p>
            </a>
            <?php endwhile; ?>
        </div>
        
        <div class="text-center">
            <a href="list.php" class="btn-back">← กลับไปเลือกกีฬา/Back to List</a>
        </div>
    </div>
</body>
</html>
<?php exit; } ?>

<?php
/* ตารางแข่งขัน */
$stmt_find = $conn->prepare("SELECT sport_id FROM sport_type WHERE sport_name=? AND category=? LIMIT 1");
$stmt_find->bind_param("ss", $sport_name, $category);
$stmt_find->execute();
$r_find = $stmt_find->get_result()->fetch_assoc();
$correct_sport_id = $r_find['sport_id'] ?? 0;
$stmt_find->close();

$stmt2 = $conn->prepare("
    SELECT ms.match_date, ms.match_no, ms.round_name, ms.start_time,
           COALESCE(t1.color_name, 'อันดับที่1ของตาราง') AS team1, 
           COALESCE(t2.color_name, 'อันดับที่2ของตาราง') AS team2, ms.venue
    FROM match_schedule ms
    LEFT JOIN color_team t1 ON ms.team1_id=t1.color_id
    LEFT JOIN color_team t2 ON ms.team2_id=t2.color_id
    WHERE ms.sport_id=?
    ORDER BY ms.match_date, ms.start_time
");
$stmt2->bind_param("i", $correct_sport_id);
$stmt2->execute();
$matches = $stmt2->get_result();

function getTeamColor($name) {
    $name = mb_strtolower($name);
    if(str_contains($name, 'เขียว')) return '#10b981';
    if(str_contains($name, 'น้ำเงิน')) return '#3b82f6';
    if(str_contains($name, 'เหลือง')) return '#fbbf24';
    if(str_contains($name, 'แดง')) return '#ef4444';
    return '#6b7280';
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตาราง <?= htmlspecialchars($sport_name) ?></title>
    <?php include 'tailwind-config.php'; ?>
    <style>
        /* Horizontal Scrollbar Styling */
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            max-width: 100%;
            margin: 0 auto;
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        
        .table-container::-webkit-scrollbar {
            height: 10px;
        }
        
        .table-container::-webkit-scrollbar-track {
            background: linear-gradient(90deg, #e0e7ff, #c7d2fe);
            border-radius: 10px;
        }
        
        .table-container::-webkit-scrollbar-thumb {
            background: linear-gradient(90deg, #6366f1, #8b5cf6);
            border-radius: 10px;
            border: 2px solid #e0e7ff;
        }
        
        .table-container::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(90deg, #4f46e5, #7c3aed);
        }
        
        /* Firefox scrollbar */
        .table-container {
            scrollbar-width: thin;
            scrollbar-color: #6366f1 #e0e7ff;
        }
        
        .table-container table {
            min-width: 800px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="breadcrumb">
            <a href="home.php">🏠 หน้าหลัก</a>
            <span>›</span>
            <a href="list.php">ตารางแข่งขัน</a>
            <span>›</span>
            <a href="list2.php?sport_id=<?= $sport_id ?>"><?= htmlspecialchars($sport_name) ?></a>
            <span>›</span>
            <span style="color: #1f2937; font-weight: 500;"><?= htmlspecialchars($category) ?></span>
        </div>
        
        <div class="text-center mb-6 animate-fade">
            <h1 class="text-2xl font-bold mb-2"><?= htmlspecialchars($sport_name) ?></h1>
            <p style="color: #6366f1; font-size: 18px;">ประเภท: <?= htmlspecialchars($category) ?></p>
        </div>
        
        <div class="text-center mb-8">
            <a href="list2.php?sport_id=<?= $sport_id ?>" class="btn-back" style="margin-right: 8px;">🔄 เลือกประเภท/Back to Category</a>
            <a href="list.php" class="btn-back">🏅 เลือกกีฬา/Back to List</a>
        </div>
        
        <?php if($matches->num_rows > 0): ?>
        <div class="table-container mb-8">
            <table>
                <thead>
                    <tr>
                        <th>วันที่</th>
                        <th>รอบ</th>
                        <th>แมทช์</th>
                        <th>เวลา</th>
                        <th>ทีม 1</th>
                        <th style="text-align: center;">VS</th>
                        <th>ทีม 2</th>
                        <th>สนาม</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($m = $matches->fetch_assoc()): ?>
                    <tr>
                        <td><?= $m['match_date'] ?></td>
                        <td style="color: #6366f1; font-weight: 500;"><?= htmlspecialchars($m['round_name'] ?? '-') ?></td>
                        <td><?= $m['match_no'] ?></td>
                        <td style="font-weight: 600;"><?= substr($m['start_time'],0,5) ?></td>
                        <td>
                            <span style="display: inline-block; width: 12px; height: 12px; border-radius: 50%; background: <?= getTeamColor($m['team1']) ?>; margin-right: 8px;"></span>
                            <?= htmlspecialchars($m['team1']) ?>
                        </td>
                        <td style="text-align: center; color: #9ca3af;">VS</td>
                        <td>
                            <span style="display: inline-block; width: 12px; height: 12px; border-radius: 50%; background: <?= getTeamColor($m['team2']) ?>; margin-right: 8px;"></span>
                            <?= htmlspecialchars($m['team2']) ?>
                        </td>
                        <td style="color: #6b7280;"><?= htmlspecialchars($m['venue']) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="card text-center p-8 mb-8">
            <p style="font-size: 48px; margin-bottom: 16px;">📭</p>
            <p class="text-gray text-lg">ยังไม่มีตารางการแข่งขัน</p>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
