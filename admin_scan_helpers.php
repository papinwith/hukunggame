<?php

function findTodayMatch(mysqli $conn, int $sportId, string $category, int $colorId): ?array
{
    $sql = "SELECT match_id, match_date, start_time, round_name, venue
            FROM match_schedule
            WHERE sport_id = ?
              AND category = ?
              AND match_date = CURDATE()
              AND (team1_id = ? OR team2_id = ?)
            ORDER BY start_time ASC, match_no ASC
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isii", $sportId, $category, $colorId, $colorId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc() ?: null;
    $stmt->close();

    return $row;
}

function findUpcomingMatches(mysqli $conn, int $sportId, string $category, int $colorId): array
{
    $sql = "SELECT match_id, match_date, start_time, round_name, venue
            FROM match_schedule
            WHERE sport_id = ?
              AND category = ?
              AND match_date >= CURDATE()
              AND (team1_id = ? OR team2_id = ?)
            ORDER BY match_date ASC, start_time ASC, match_no ASC
            LIMIT 3";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isii", $sportId, $category, $colorId, $colorId);
    $stmt->execute();
    $result = $stmt->get_result();
    $matches = [];
    while ($row = $result->fetch_assoc()) {
        $matches[] = $row;
    }
    $stmt->close();

    return $matches;
}

function findAttendanceToday(mysqli $conn, int $regId): ?array
{
    $stmt = $conn->prepare("SELECT attendance_id, checked_at FROM sport_attendance WHERE reg_id = ? AND attendance_date = CURDATE() LIMIT 1");
    $stmt->bind_param("i", $regId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc() ?: null;
    $stmt->close();

    return $row;
}

function findAttendanceHistory(mysqli $conn, int $regId): array
{
    $stmt = $conn->prepare(
        "SELECT sa.attendance_date, sa.checked_at, ms.round_name, ms.venue, ms.start_time
         FROM sport_attendance sa
         LEFT JOIN match_schedule ms ON sa.match_id = ms.match_id
         WHERE sa.reg_id = ?
         ORDER BY sa.attendance_date DESC, sa.checked_at DESC"
    );
    $stmt->bind_param("i", $regId);
    $stmt->execute();
    $result = $stmt->get_result();
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    $stmt->close();

    return $history;
}

function performAttendanceCheckin(mysqli $conn, int $regId): array
{
    $stmt = $conn->prepare("SELECT reg_id, sport_id, category, color_id FROM sport_registration WHERE reg_id = ? LIMIT 1");
    $stmt->bind_param("i", $regId);
    $stmt->execute();
    $registration = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$registration) {
        return ['type' => 'error', 'text' => 'ไม่พบรายการลงทะเบียนกีฬา'];
    }

    $todayMatch = findTodayMatch($conn, (int) $registration['sport_id'], $registration['category'], (int) $registration['color_id']);

    if (!$todayMatch) {
        return ['type' => 'error', 'text' => 'วันนี้ยังไม่มีกำหนดแข่งของกีฬานี้สำหรับสีที่ลงทะเบียน'];
    }

    $stmt = $conn->prepare("INSERT INTO sport_attendance (reg_id, match_id, attendance_date) VALUES (?, ?, CURDATE()) ON DUPLICATE KEY UPDATE checked_at = checked_at");
    $matchId = (int) $todayMatch['match_id'];
    $stmt->bind_param("ii", $regId, $matchId);
    $stmt->execute();
    $affectedRows = $stmt->affected_rows;
    $stmt->close();

    if ($affectedRows <= 0) {
        return ['type' => 'error', 'text' => 'รายการนี้เช็คชื่อวันนี้ไปแล้ว'];
    }

    $stmt = $conn->prepare("UPDATE sport_registration SET attendance = 1, attendance_at = NOW() WHERE reg_id = ?");
    $stmt->bind_param("i", $regId);
    $stmt->execute();
    $stmt->close();

    return ['type' => 'success', 'text' => 'เช็คชื่อเข้าแข่งเรียบร้อยแล้ว'];
}

function loadStudentSports(mysqli $conn, string $studentId): array
{
    $results = [];
    $sql = "SELECT 
                sr.reg_id,
                sr.student_id,
                sr.student_name,
                sr.sport_id,
                s.tel,
                f.faculty_name,
                c.color_name,
                c.color_id,
                sr.sport_name,
                sr.category,
                sr.shirt_received,
                sr.shirt_received_at,
                sr.attendance,
                sr.attendance_at,
                ps.shirt_size,
                p.image_path AS profile_img
            FROM sport_registration sr
            JOIN student s ON sr.student_id = s.student_id
            JOIN faculty f ON sr.faculty_id = f.faculty_id
            JOIN color_team c ON sr.color_id = c.color_id
            LEFT JOIN player_size ps ON sr.student_id = ps.student_id AND sr.sport_id = ps.sport_id
            LEFT JOIN (
                SELECT student_id, sport_id, MAX(image_id) AS image_id
                FROM image
                WHERE image_type = 'profile'
                GROUP BY student_id, sport_id
            ) latest_profile ON latest_profile.student_id = sr.student_id AND latest_profile.sport_id = sr.sport_id
            LEFT JOIN image p ON p.image_id = latest_profile.image_id
            WHERE sr.student_id = ?";

    $stmt = $conn->prepare($sql);
    $searchParam = trim($studentId);
    $stmt->bind_param("s", $searchParam);
    $stmt->execute();
    $res = $stmt->get_result();

    while ($row = $res->fetch_assoc()) {
        $sid = $row['student_id'];
        if (!isset($results[$sid])) {
            $results[$sid] = $row;
            $results[$sid]['sports'] = [];
        }

        $todayAttendance = findAttendanceToday($conn, (int) $row['reg_id']);
        $attendanceHistory = findAttendanceHistory($conn, (int) $row['reg_id']);
        $upcomingMatches = findUpcomingMatches($conn, (int) $row['sport_id'], $row['category'], (int) $row['color_id']);
        $todayMatch = null;
        $nextMatch = null;

        foreach ($upcomingMatches as $match) {
            if ($match['match_date'] === date('Y-m-d') && !$todayMatch) {
                $todayMatch = $match;
                continue;
            }

            if (!$nextMatch) {
                $nextMatch = $match;
            }
        }

        $results[$sid]['sports'][] = [
            'reg_id' => $row['reg_id'],
            'sport_id' => $row['sport_id'],
            'sport_name' => $row['sport_name'],
            'category' => $row['category'],
            'shirt_size' => $row['shirt_size'] ?: '-',
            'shirt_received' => $row['shirt_received'],
            'shirt_received_at' => $row['shirt_received_at'],
            'attendance' => $row['attendance'],
            'attendance_at' => $row['attendance_at'],
            'today_attendance' => $todayAttendance,
            'attendance_history' => $attendanceHistory,
            'today_match' => $todayMatch,
            'next_match' => $nextMatch,
        ];
    }

    $stmt->close();

    return $results;
}