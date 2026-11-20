<?php
// แสดง error บนหน้าเว็บเฉพาะตอนเปิดจากเครื่องตัวเอง (localhost) เพื่อ debug
error_reporting(E_ALL);
$is_local = in_array($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1', ['127.0.0.1', '::1'], true);
ini_set('display_errors', $is_local ? 1 : 0);

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "hukangame";

// ป้องกัน warning ด้วย @
$conn = @new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    http_response_code(500);
    if (!$is_local) die("❌ ไม่สามารถเชื่อมต่อฐานข้อมูลได้ กรุณาลองใหม่ภายหลัง");
    die("❌ Connection failed: " . $conn->connect_error . "<br><br>กรุณาตรวจสอบ:<br>1. ชื่อฐานข้อมูล: $dbname<br>2. Username: $username<br>3. Password<br>4. MySQL กำลังทำงานอยู่หรือไม่");
}

// ตั้งค่า character set
$conn->set_charset("utf8mb4");
// End of file (omitting closing tag to prevent whitespace issues)
