<?php
include 'db.php';
require_once 'admin_auth.php';
$color_id = (int)($_GET['color_id'] ?? 0);
$colors = colors_with_faculties($conn);
$current = null;
foreach ($colors as $c) if ($c['color_id'] == $color_id) $current = $c;
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เลือกสี/กีฬา - Admin</title>
    <?php include 'tailwind-config.php'; ?>
</head>

<body>
    <div class="container" style="max-width: 900px;">
        
        <!-- Header -->
        <div class="text-center mb-8 animate-fade">
            <h1 class="text-2xl font-bold mb-2">👥 ผู้เข้าแข่งขัน</h1>
            <p class="text-gray">เลือกสีและกีฬาเพื่อดูรายชื่อนักกีฬา</p>
        </div>
        
        <!-- Navigation -->
        <div class="text-center mb-8">
            <a href="homeadmin.php" class="btn-back" style="margin-right: 8px;">← Admin Home</a>
            <?php if($color_id): ?>
            <a href="allstudent.php" class="btn-back">🔄 เลือกสีใหม่</a>
            <?php endif; ?>
        </div>
        
        <?php if(!$color_id): ?>
        <!-- Select Color -->
        <div class="mb-8">
            <h2 class="text-center font-bold text-lg mb-6">🎨 เลือกสี</h2>
            
            <div class="grid grid-2">
                <?php foreach ($colors as $c): $k = team_css_key($c['color_name']); $hex = team_color_hex($c['color_name']); ?>
                <a href="?color_id=<?= $c['color_id'] ?>" class="<?= $k ? 'team-' . $k : '' ?>" style="text-decoration: none;<?= $k ? '' : ' display:block; border:2px solid ' . $hex . '; border-radius:24px; padding:24px; margin-bottom:16px;' ?>">
                    <div class="flex items-center">
                        <div class="team-dot<?= $k ? ' dot-' . $k : '' ?>"<?= $k ? '' : ' style="background:' . $hex . ';"' ?>></div>
                        <div>
                            <h3 class="<?= $k ? 'team-title-' . $k : '' ?>"<?= $k ? '' : ' style="color:' . $hex . '; font-size:28px; font-weight:700; margin-bottom:8px;"' ?>><?= htmlspecialchars($c['color_name']) ?></h3>
                            <p class="<?= $k ? 'team-desc-' . $k : 'text-gray' ?>"><?= htmlspecialchars(implode(' • ', $c['faculties'])) ?></p>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if($color_id): ?>
        <!-- Select Sport -->
        <div>
            <h2 class="text-center font-bold text-lg mb-4">🏅 เลือกกีฬา</h2>
            
            <div class="notice mb-6">
                <span style="display: inline-block; width: 20px; height: 20px; border-radius: 50%; background: <?= team_color_hex($current['color_name'] ?? '') ?>; margin-right: 8px; vertical-align: middle;"></span>
                <span class="font-bold">กำลังดู: <?= htmlspecialchars($current['color_name'] ?? 'ไม่ระบุ') ?></span>
            </div>
            
            <div class="grid grid-4">
                <?php
                $sql = "SELECT DISTINCT sport_name FROM sport_type ORDER BY sport_name";
                $sports = $conn->query($sql);
                
                if($sports && $sports->num_rows > 0) {
                    while($s = $sports->fetch_assoc()):
                ?>
                <a href="allstudent2.php?color_id=<?= $color_id ?>&sport_name=<?= urlencode($s['sport_name']) ?>" class="sport-card">
                    <div class="sport-icon">🎯</div>
                    <div class="sport-name"><?= htmlspecialchars($s['sport_name']) ?></div>
                </a>
                <?php 
                    endwhile;
                } else {
                    echo '<div class="notice" style="grid-column: span 4;"><p class="text-gray">⚠️ ไม่พบข้อมูลกีฬา</p></div>';
                }
                ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
