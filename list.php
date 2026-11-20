<?php 
include 'db.php';
mysqli_set_charset($conn,"utf8");

$sports = $conn->query("
    SELECT MIN(sport_id) AS sport_id, sport_name
    FROM sport_type
    GROUP BY sport_name
    ORDER BY sport_name
");

if(!$sports){
    die("Query error: " . $conn->error);
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ตารางการแข่งขัน - หูกวางเกมส์</title>
    <?php include 'tailwind-config.php'; ?>
</head>

<body>
    <div class="container">
        
        <!-- Header -->
        <div class="text-center mb-8 animate-fade">
            <h1 class="text-2xl font-bold mb-2">📅 ตารางการแข่งขัน</h1>
            <p class="text-gray">เลือกกีฬาเพื่อดูตารางการแข่งขัน</p>
        </div>
        
        <!-- Sports Grid -->
        <div class="grid grid-4 mb-10" style="max-width: 900px; margin: 0 auto 40px;">
            <?php while($s = $sports->fetch_assoc()): ?>
            <a href="list2.php?sport_id=<?= $s['sport_id'] ?>" class="sport-card">
                <div class="sport-icon">📋</div>
                <div class="sport-name"><?= htmlspecialchars($s['sport_name']) ?></div>
            </a>
            <?php endwhile; ?>
        </div>
        
        <!-- Back Button -->
        <div class="text-center">
            <a href="home.php" class="btn-back">← Home</a>
        </div>
    </div>
</body>
</html>
