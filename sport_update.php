<?php
include 'db.php';
require_once 'admin_auth.php';
require_once 'settings_helper.php';
ensure_schema($conn);

$id          = $_POST['sport_id'];
$name        = $_POST['sport_name'];
$category    = $_POST['category'];
$venue_type  = $_POST['venue_type'];
$max_players = max(0, (int)($_POST['max_players'] ?? 0));

$stmt = $conn->prepare("
    UPDATE sport_type
    SET sport_name=?, category=?, venue_type=?, max_players=?
    WHERE sport_id=?
");
$stmt->bind_param("sssii",$name,$category,$venue_type,$max_players,$id);

if ($stmt->execute()) {
    header("Location: sport_admin2.php");
    exit();
} else {
    echo "Update Error";
}
