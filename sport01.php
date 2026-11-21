<?php
/* ================== DB ================== */
include 'db.php';

require_once 'settings_helper.php';
ensure_schema($conn);
$max_sports   = max(1, (int)get_setting($conn,'max_sports_per_student'));
$shirt_sizes  = setting_list($conn,'shirt_sizes');
$allowed_ext  = array_map('strtolower', setting_list($conn,'allowed_image_ext'));
$reg_close    = get_setting($conn,'registration_close');
$reg_open     = registration_is_open($conn);
$event_name   = get_setting($conn,'event_name');
$event_dates  = get_setting($conn,'event_dates');

/* ================== GET ================== */
$sport_id = isset($_GET['sport_id']) ? (int)$_GET['sport_id'] : 0;
if($sport_id==0) die("ไม่พบกีฬา");

/* ================== SPORT INFO ================== */
$sport_name = "";
$category   = "";

$max_players = 0;

$sp = mysqli_prepare($conn,"SELECT sport_name, category, max_players FROM sport_type WHERE sport_id=?");
mysqli_stmt_bind_param($sp,"i",$sport_id);
mysqli_stmt_execute($sp);
mysqli_stmt_bind_result($sp,$sport_name,$category,$max_players);
mysqli_stmt_fetch($sp);
mysqli_stmt_close($sp);

/* ================== COLOR ================== */
$colors = [];
$slots  = [];   // จำนวนผู้สมัครปัจจุบัน/สูงสุด ต่อสี
$cs = mysqli_query($conn,"SELECT color_id,color_name FROM color_team ORDER BY color_id");
while($row = mysqli_fetch_assoc($cs)){
    $colors[] = $row;
    $slots[$row['color_id']] = ['count'=>registered_count($conn,$sport_id,$row['color_id']), 'max'=>(int)$max_players];
}

/* ================== FACULTY BY COLOR ================== */
$faculties_by_color = [];
$fs = mysqli_query($conn,"SELECT faculty_id, faculty_name, color_id FROM faculty ORDER BY color_id, faculty_name");
while($row = mysqli_fetch_assoc($fs)){
    $faculties_by_color[$row['color_id']][] = ['id'=>$row['faculty_id'], 'name'=>$row['faculty_name']];
}

/* ================== POST ================== */
$msg = "";
if($_SERVER['REQUEST_METHOD']=="POST"){
    $student_id = $_POST['student_id'] ?? '';
    $name       = $_POST['student_name'] ?? '';
    $tel        = $_POST['tel'] ?? '';
    $faculty    = (int)($_POST['faculty_id'] ?? 0);
    $color      = (int)($_POST['color_id'] ?? 0);
    $size       = $_POST['shirt_size'] ?? '';

    if(!$reg_open){ $msg="❌ ปิดรับสมัครแล้ว"; goto end_post; }
    if($color===0 || $faculty===0){ $msg="❌ กรุณาเลือกสีและคณะ"; goto end_post; }
    if(!in_array($size, $shirt_sizes, true)){ $msg="❌ กรุณาเลือกไซส์เสื้อ"; goto end_post; }

    /* Check max players of this sport per color */
    if($max_players > 0){
        $already = registered_count($conn,$sport_id,$color);
        if($already >= $max_players){ $msg="❌ กีฬานี้ของสีที่เลือกมีผู้สมัครครบ {$max_players} คนแล้ว"; goto end_post; }
    }

    /* Check duplicate sport */
    $chk = mysqli_prepare($conn,"SELECT 1 FROM sport_registration WHERE student_id=? AND sport_id=?");
    mysqli_stmt_bind_param($chk,"si",$student_id,$sport_id);
    mysqli_stmt_execute($chk);
    mysqli_stmt_store_result($chk);
    if(mysqli_stmt_num_rows($chk)>0){ $msg="❌ สมัครกีฬานี้ไปแล้ว"; }
    else{
        /* Check limit sports per student (settings) */
        $cnt = mysqli_prepare($conn,"SELECT COUNT(*) FROM sport_registration WHERE student_id=?");
        mysqli_stmt_bind_param($cnt,"s",$student_id);
        mysqli_stmt_execute($cnt);
        mysqli_stmt_bind_result($cnt,$total);
        mysqli_stmt_fetch($cnt);
        mysqli_stmt_close($cnt);

        if($total>=$max_sports){ $msg="❌ สมัครได้ไม่เกิน {$max_sports} กีฬา"; }
        else{

            // 1. Check File Presence
            if(!isset($_FILES['profile_image']) || $_FILES['profile_image']['error']!=0){ $msg="❌ กรุณาอัปโหลดรูปนักศึกษา"; goto end_post; }
            if(!isset($_FILES['student_card']) || $_FILES['student_card']['error']!=0){ $msg="❌ กรุณาอัปโหลดรูปบัตรนักศึกษา"; goto end_post; }

            // 2. Validate Profile Image
            $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
            if(!in_array($ext, $allowed_ext)){ $msg="❌ รูปนักศึกษาต้องเป็นไฟล์รูปภาพ (JPG, PNG) เท่านั้น"; goto end_post; }
            if(getimagesize($_FILES['profile_image']['tmp_name']) === false){ $msg="❌ รูปนักศึกษาไม่ใช่ไฟล์รูปภาพที่ถูกต้อง"; goto end_post; }
            
            // 3. Validate Student Card
            $ext = strtolower(pathinfo($_FILES['student_card']['name'], PATHINFO_EXTENSION));
            if(!in_array($ext, $allowed_ext)){ $msg="❌ รูปบัตรนักศึกษาต้องเป็นไฟล์รูปภาพ (JPG, PNG) เท่านั้น"; goto end_post; }
            if(getimagesize($_FILES['student_card']['tmp_name']) === false){ $msg="❌ รูปบัตรนักศึกษาไม่ใช่ไฟล์รูปภาพที่ถูกต้อง"; goto end_post; }

            // 4. Duplicate Check
            if(md5_file($_FILES['profile_image']['tmp_name']) === md5_file($_FILES['student_card']['tmp_name'])){
                $msg="❌ รูปนักศึกษาและรูปบัตรนักศึกษาต้องไม่ซ้ำกัน"; 
                goto end_post; 
            }

            /* Insert/Update student */
            $stu = mysqli_prepare($conn,"INSERT INTO student (student_id,student_name,tel,faculty_id,color_id) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE student_name=VALUES(student_name), tel=VALUES(tel), faculty_id=VALUES(faculty_id), color_id=VALUES(color_id)");
            mysqli_stmt_bind_param($stu,"ssssi",$student_id,$name,$tel,$faculty,$color);
            mysqli_stmt_execute($stu);
            mysqli_stmt_close($stu);

            /* Insert Registration */
            $reg = mysqli_prepare($conn,"INSERT INTO sport_registration (student_id,student_name,faculty_id,color_id,sport_id,sport_name,category,reg_year) VALUES (?,?,?,?,?,?,?,?)");
            $reg_year = (int)get_setting($conn,'current_year');
            mysqli_stmt_bind_param($reg,"ssiiissi",$student_id,$name,$faculty,$color,$sport_id,$sport_name,$category,$reg_year);
            try {
                $reg_ok = mysqli_stmt_execute($reg);
            } catch (mysqli_sql_exception $e) {
                $reg_ok = false;
            }
            if (!$reg_ok) {
                // 1062 = สมัครซ้ำ (กดส่งพร้อมกัน 2 ครั้ง ชน unique key student_id+sport_id)
                $msg = (mysqli_errno($conn) === 1062) ? "❌ สมัครกีฬานี้ไปแล้ว" : "❌ บันทึกข้อมูลไม่สำเร็จ กรุณาลองใหม่อีกครั้ง";
                mysqli_stmt_close($reg);
                goto end_post;
            }
            $new_reg_id = mysqli_insert_id($conn);
            mysqli_stmt_close($reg);

            /* กันสมัครพร้อมกันจนเกิน max: นับซ้ำหลังบันทึก ถ้าเกินให้ยกเลิกรายการตัวเอง */
            if($max_players > 0 && registered_count($conn,$sport_id,$color) > $max_players){
                mysqli_query($conn,"DELETE FROM sport_registration WHERE reg_id=".(int)$new_reg_id);
                $msg="❌ กีฬานี้ของสีที่เลือกมีผู้สมัครครบ {$max_players} คนแล้ว";
                goto end_post;
            }

            /* Upload Image */
            $uploadBase = "uploads/";
            $profileDir = $uploadBase."profile/";
            $cardDir    = $uploadBase."student_card/";
            if (!is_dir($profileDir)) mkdir($profileDir, 0777, true);
            if (!is_dir($cardDir)) mkdir($cardDir, 0777, true);

            function uploadImage($file, $dir, $student_id, $sport_id, $type, $conn){
                global $allowed_ext;
                if($file['error'] !== UPLOAD_ERR_OK) return;
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                if(!in_array(strtolower($ext), $allowed_ext)) return;
                $newName = $student_id."_".$sport_id."_".$type.".".$ext;
                $path = $dir.$newName;
                if(move_uploaded_file($file['tmp_name'], $path)){
                    $stmt = mysqli_prepare($conn,"INSERT INTO image (student_id, sport_id, image_type, image_path) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE image_path = VALUES(image_path)");
                    mysqli_stmt_bind_param($stmt,"siss",$student_id,$sport_id,$type,$path);
                    mysqli_stmt_execute($stmt);
                    mysqli_stmt_close($stmt);
                }
            }
            uploadImage($_FILES['profile_image'], $profileDir, $student_id, $sport_id, 'profile', $conn);
            uploadImage($_FILES['student_card'], $cardDir, $student_id, $sport_id, 'student_card', $conn);

            /* Player Size */
            $ps = mysqli_prepare($conn,"INSERT INTO player_size (student_id,sport_id,color_id,shirt_size) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE shirt_size=VALUES(shirt_size)");
            mysqli_stmt_bind_param($ps,"siis",$student_id,$sport_id,$color,$size);
            mysqli_stmt_execute($ps);
            mysqli_stmt_close($ps);

            echo "<script>alert('สมัครสำเร็จ');window.location.href='sport.php';</script>";
            exit;
        }
    }
    mysqli_stmt_close($chk);
}
end_post:
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สมัคร <?= htmlspecialchars($sport_name) ?> - หูกวางเกมส์</title>
    <?php include 'tailwind-config.php'; ?>
</head>

<body>
    <div class="container" style="max-width: 600px;">
        
        <!-- Header -->
        <div class="text-center mb-8 animate-fade">
            <h1 class="text-3xl font-bold mb-2">
                <span class="gradient-text"><?= htmlspecialchars($event_name) ?></span>
            </h1>
            <?php if ($event_dates !== ''): ?>
            <p style="color: #6366f1; font-weight: 600;">📅 <?= htmlspecialchars($event_dates) ?></p>
            <?php endif; ?>
        </div>

        <!-- Team Info Cards (จากข้อมูลสี/คณะในหลังบ้าน) -->
        <div class="grid grid-2 mb-6">
            <?php foreach (colors_with_faculties($conn) as $tc): $k = team_css_key($tc['color_name']); $hex = team_color_hex($tc['color_name']); ?>
            <div class="<?= $k ? 'team-' . $k : '' ?>" style="padding: 16px; margin-bottom: 0;<?= $k ? '' : ' border:2px solid ' . $hex . '; border-radius:24px;' ?>">
                <h3 style="color:<?= $hex ?>; font-weight:700;"><?= htmlspecialchars($tc['color_name']) ?></h3>
                <p style="font-size:12px; color:#4b5563;"><?= htmlspecialchars(implode(' • ', $tc['faculties'])) ?></p>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Countdown -->
        <div class="notice mb-6">
            <p id="countdown-text" style="font-weight: 700; color: #4b5563;">⏳ กำลังคำนวณเวลาที่เหลือ...</p>
        </div>
        
        <!-- Message Box -->
        <?php if(!empty($msg)): ?>
        <div class="notice mb-6" style="background: <?= str_contains($msg,'❌') ? '#fef2f2' : '#ecfdf5' ?>; color: <?= str_contains($msg,'❌') ? '#dc2626' : '#059669' ?>;">
            <?= htmlspecialchars($msg) ?>
        </div>
        <?php endif; ?>
        
        <!-- Back Button -->
        <div class="text-center mb-6">
            <a href="typeos_sport.php?sport_name=<?= urlencode($sport_name) ?>" class="btn-back">
                ← กลับไปเลือกประเภท/go back to type
            </a>
        </div>
        
        <!-- Registration Form -->
        <div id="register-section" class="card">
            <div class="header-gradient">
                <h2 style="font-size: 20px; font-weight: 700;">📝 แบบฟอร์มสมัคร</h2>
            </div>
            
            <div class="p-6">
                <form method="post" id="registerForm" enctype="multipart/form-data">
                    
                    <div class="mb-4">
                        <label class="form-label">📷 รูปนักศึกษา/Student Image</label>
                        <input type="file" name="profile_image" accept="image/png, image/jpeg, image/jpg" required class="form-input">
                    </div>
                    
                    <div class="mb-4 text-center p-4 bg-gray-50 rounded-2xl" style="background: #f9fafb; border-radius: 12px;">
                        <p class="form-label">ตัวอย่างสำเนาบัตรนักศึกษา/Student ID Card</p>
                        <img src="img/img04.jpg" style="max-width: 150px; border-radius: 8px; cursor: pointer;" onclick="openImage(this.src)">
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">🎫 สำเนาบัตรนักศึกษา/Student ID Card</label>
                        <input type="file" name="student_card" accept="image/png, image/jpeg, image/jpg" required class="form-input">
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">🆔 รหัสนักศึกษา (10หลัก)/Student ID</label>
                        <input type="text" name="student_id" maxlength="10" inputmode="numeric" pattern="[0-9]{10}" placeholder="กรอกรหัสนักศึกษา 10 หลัก" oninput="this.value=this.value.replace(/[^0-9]/g,'')" required class="form-input">
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">👤 ชื่อ-นามสกุล/Student Name</label>
                        <input type="text" name="student_name" required class="form-input">
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">📱 เบอร์ติดต่อ/Phone Number</label>
                        <input type="text" name="tel" maxlength="10" inputmode="numeric" pattern="[0-9]{10}" oninput="this.value=this.value.replace(/[^0-9]/g,'')" required class="form-input">
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">🎨 เลือกสี/Color</label>
                        <select id="color_team" name="color_id" required class="form-input">
                            <option value="">-- เลือกสี --</option>
                            <?php foreach($colors as $c): ?>
                                <option value="<?= $c['color_id'] ?>"><?= $c['color_name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p id="slot-info" style="font-size:13px; margin-top:6px; color:#6b7280;"></p>
                    </div>
                    <div class="mb-6">
                        <label class="form-label">🏫 คณะ/Faculty</label>
                        <select id="faculty_name" name="faculty_id" required class="form-input">
                            <option value="">-- เลือกสีก่อน --</option>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">👕 ไซส์เสื้อ/Shirt Size</label>  
                        <img src="img/img05.jpg" style="max-width: 100%; border-radius: 8px; margin-bottom: 12px; cursor: pointer;" onclick="openImage(this.src)">
                        <div class="grid grid-4">
                            <?php foreach($shirt_sizes as $s): ?>
                            <label style="cursor: pointer;">
                                <input type="radio" name="shirt_size" value="<?= $s ?>" required style="display:none;" onchange="updateSize(this)">
                                <span class="size-box" style="display: block; text-align: center; padding: 8px; background: #f3f4f6; border-radius: 8px; font-weight: 700; color: #4b5563; transition: all 0.2s;"><?= $s ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <style>
                            input[type="radio"]:checked + .size-box {
                                background: #6366f1; color: white;
                            }
                        </style>
                        <script>
                            function updateSize(el) {
                                document.querySelectorAll('.size-box').forEach(b => {
                                    b.style.background = '#f3f4f6';
                                    b.style.color = '#4b5563';
                                });
                                if(el.checked) {
                                    el.nextElementSibling.style.background = '#6366f1';
                                    el.nextElementSibling.style.color = 'white';
                                }
                            }
                        </script>
                    </div>
                    
                    
                    
                    <div class="grid grid-2 mb-6">
                        <div>
                            <label class="form-label">⚽ กีฬา/Sport</label>
                            <input type="text" value="<?= $sport_name ?>" readonly class="form-input" style="background: #f3f4f6; color: #6b7280;">
                        </div>
                        <div>
                            <label class="form-label">📋 ประเภท/Category</label>
                            <input type="text" value="<?= $category ?>" readonly class="form-input" style="background: #f3f4f6; color: #6b7280;">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        ✅ ยืนยันการสมัคร/Confirm
                    </button>
                    
                </form>
            </div>
        </div>
        
        <div class="text-center mt-6 p-4">
             <p class="text-gray text-sm">📍 หากมีปัญหาติดต่อเจ้าหน้าที่ อาคาร 17 ชั้น 3/If you have any problems, contact the staff at Building 17, Floor 3</p>
        </div>
    </div>
    
    <!-- Lightbox -->
    <div class="lightbox" id="lightbox" onclick="closeImage()">
        <img id="lightbox-img">
    </div>
    
    <script>
    const facultiesByColor = <?= json_encode($faculties_by_color, JSON_UNESCAPED_UNICODE); ?>;
    
    const slots = <?= json_encode($slots); ?>;

    document.getElementById("color_team").addEventListener("change", function() {
        const info = document.getElementById("slot-info");
        const s = slots[this.value];
        if (s && s.max > 0) {
            const left = s.max - s.count;
            info.textContent = left > 0 ? `สมัครแล้ว ${s.count}/${s.max} คน (เหลือ ${left} ที่)` : `❌ เต็มแล้ว ${s.count}/${s.max} คน`;
            info.style.color = left > 0 ? '#059669' : '#dc2626';
        } else {
            info.textContent = '';
        }
        const facultySelect = document.getElementById("faculty_name");
        facultySelect.innerHTML = '<option value="">-- เลือกคณะ --</option>';
        
        const colorId = this.value;
        if (facultiesByColor[colorId]) {
            facultiesByColor[colorId].forEach(f => {
                const opt = document.createElement("option");
                opt.value = f.id;
                opt.textContent = f.name;
                facultySelect.appendChild(opt);
            });
        }
    });
    
    // Lightbox
    function openImage(src) { document.getElementById("lightbox-img").src = src; document.getElementById("lightbox").style.display = "flex"; }
    function closeImage() { document.getElementById("lightbox").style.display = "none"; }
    
    // Countdown
    const registrationOpen = <?= $reg_open ? 'true' : 'false' ?>;
    const closeTime = registrationOpen ? new Date(<?= json_encode(str_replace(' ', 'T', $reg_close)) ?>).getTime() : 0;
    const text = document.getElementById("countdown-text");
    const reg = document.getElementById("register-section");
    
    setInterval(() => {
        const d = registrationOpen ? closeTime - new Date().getTime() : 0;
        if(d <= 0) {
            text.innerHTML = "❌ หมดเวลาลงทะเบียนแล้ว/Registration closed";
            reg.style.display = "none";
            return;
        }
        const day = Math.floor(d/(1000*60*60*24));
        const h = Math.floor(d%(1000*60*60*24)/(1000*60*60));
        const m = Math.floor(d%(1000*60*60)/(1000*60));
        const s = Math.floor(d%(1000*60)/1000);
        text.innerHTML = `⏰ ปิดรับสมัครในอีก ${day} วัน ${h} ชม. ${m} นาที ${s} วินาที`;
    }, 1000);
    
    // Form Confirm
    document.getElementById("registerForm").addEventListener("submit", function(e) {
        if(!confirm("คุณต้องการสมัครกีฬานี้ใช่หรือไม่?")) {
            e.preventDefault();
        }
    });
    </script>
</body>
</html>
