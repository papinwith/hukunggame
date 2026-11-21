<?php
// สคริปต์สำหรับผู้พัฒนา: สรุปรายชื่อนักกีฬาจาก hukangame.sql ลงไฟล์ test.text
// รันจาก command line เท่านั้น: php parse.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
chdir(__DIR__);
$sql = file_get_contents('hukangame.sql');

$colors = [
    1 => 'สีแดง/Red',
    2 => 'สีเหลือง/Yellow',
    3 => 'สีเขียว/Green',
    4 => 'สีน้ำเงิน/Blue'
];

$match = preg_match('/INSERT INTO `sport_registration` \(`reg_id`, `student_id`, `student_name`, `faculty_id`, `color_id`, `sport_id`, `sport_name`, `category`[^)]*\) VALUES(.*?);\s*$/sm', $sql, $matches);

if (!$match) {
    echo "No sport_registration insert found\n";
    exit;
}

$valuesStr = $matches[1];
$pattern = '/\(\s*\d+\s*,\s*\'[^\']*\'\s*,\s*\'((?:[^\'\\\\]|\\\\.)*)\'\s*,\s*\d+\s*,\s*(\d+)\s*,\s*\d+\s*,\s*\'((?:[^\'\\\\]|\\\\.)*)\'\s*,\s*\'((?:[^\'\\\\]|\\\\.)*)\'\s*(?:,[^()]*)?\)/s';

preg_match_all($pattern, $valuesStr, $out, PREG_SET_ORDER);

$data = [];
foreach ($out as $row) {
    $student_name = stripslashes($row[1]);
    $color_id = (int)$row[2];
    $sport_name = stripslashes($row[3]);
    $category = stripslashes($row[4]);
    
    $color_name = isset($colors[$color_id]) ? $colors[$color_id] : "Unknown Color ($color_id)";
    
    if(!isset($data[$color_name])) $data[$color_name] = [];
    if(!isset($data[$color_name][$sport_name])) $data[$color_name][$sport_name] = [];
    if(!isset($data[$color_name][$sport_name][$category])) $data[$color_name][$sport_name][$category] = [];
    
    $data[$color_name][$sport_name][$category][] = $student_name;
}

$output = "ข้อมูลแยกตามสีทีม กีฬา ประเภทกีฬา และรายชื่อนักกีฬา\n\n";
foreach($data as $color => $sports) {
    $output .= "====== $color ======\n";
    foreach($sports as $sport => $categories) {
        $output .= "  กีฬา: $sport\n";
        foreach($categories as $category => $students) {
            $output .= "    ประเภท: $category\n";
            $output .= "      รายชื่อผู้ลงแข่ง:\n";
            foreach($students as $idx => $student) {
                $output .= "        $student\n";
            }
        }
    }
    $output .= "\n";
}

file_put_contents('test.text', $output);
echo "Successfully wrote to test.text. Total matches extracted: " . count($out) . "\n";
?>
