<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Database connection
$conn = new mysqli("localhost", "root", "", "attendance_system");
if ($conn->connect_error) {
    die(json_encode(["status" => "error", "message" => "Database connection failed"]));
}

// Get POST data
$data = json_decode(file_get_contents("php://input"), true);
$device_id = $data['device_id'] ?? '';
$finger_id = $data['finger_id'] ?? 0;
$timestamp = $data['timestamp'] ?? '';

// Validate input
if (empty($device_id) || $finger_id <= 0 || empty($timestamp)) {
    echo json_encode(["status" => "error", "message" => "Invalid input"]);
    exit;
}

// Get configuration
$config = $conn->query("SELECT * FROM config LIMIT 1")->fetch_assoc();
$check_in_start = $config['start_check_in'] ?? '07:00';
$check_in_end = $config['end_check_in'] ?? '08:00';
$check_out_start = $config['start_check_out'] ?? '15:00';
$check_out_end = $config['end_check_out'] ?? '16:00';
$holiday_1 = $config['holiday_1'] ?? 0;
$holiday_2 = explode(',', $config['holiday_2'] ?? '');

// Check if today is a holiday
$today = new DateTime();
$is_holiday = false;
if ($holiday_1 && in_array($today->format('N'), [6, 7])) {
    $is_holiday = true;
}
if (in_array($today->format('Y-m-d'), $holiday_2)) {
    $is_holiday = true;
}
if ($is_holiday) {
    echo json_encode(["status" => "error", "message" => "Hari ini adalah hari libur"]);
    exit;
}

// Get student and class details
$stmt = $conn->prepare("SELECT s.id_siswa, s.nama_siswa, s.id_kelas, s.chat_id_telegram, k.jadwal_masuk, k.jadwal_pulang 
                        FROM siswa s 
                        JOIN kelas k ON s.id_kelas = k.id_kelas 
                        WHERE s.id_finger = ? AND k.device_id = ?");
$stmt->bind_param("is", $finger_id, $device_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

if (!$student) {
    echo json_encode(["status" => "error", "message" => "Student or class not found"]);
    exit;
}

// Parse timestamp
$attendance_time = new DateTime($timestamp);
$date = $attendance_time->format('Y-m-d');
$time = $attendance_time->format('H:i:s');

// Determine if this is check-in or check-out
$check_in_time = new DateTime($check_in_start);
$check_out_time = new DateTime($check_out_start);
$attendance_time_obj = new DateTime($time);

// Check if already checked in today
$stmt = $conn->prepare("SELECT jam_masuk, jam_pulang FROM presensi WHERE id_siswa = ? AND tanggal = ?");
$stmt->bind_param("is", $student['id_siswa'], $date);
$stmt->execute();
$result = $stmt->get_result();
$record = $result->fetch_assoc();

$status = "Bolos";
$keterangan = "";
$is_check_in = true;

if ($record) {
    // Already checked in, this is check-out
    if ($record['jam_masuk'] && !$record['jam_pulang']) {
        $is_check_in = false;
        $status = "Hadir"; // Mark as Hadir on check-out
        $stmt = $conn->prepare("UPDATE presensi SET jam_pulang = ?, status = ?, notifikasi_dikirim = 0 WHERE id_siswa = ? AND tanggal = ?");
        $stmt->bind_param("ssis", $time, $status, $student['id_siswa'], $date);
        $stmt->execute();
    } else {
        echo json_encode(["status" => "error", "message" => "Already checked in and out today"]);
        exit;
    }
} else {
    // New check-in
    $tolerance = new DateInterval('PT60M'); // 60 minutes tolerance
    $latest_check_in = clone $check_in_time;
    $latest_check_in->add($tolerance);

    if ($attendance_time_obj > $latest_check_in) {
        $keterangan = "Terlambat lebih dari 60 menit";
    } elseif ($attendance_time_obj > $check_in_time) {
        $status = "Terlambat";
        $diff = $attendance_time_obj->diff($check_in_time);
        $keterangan = "Terlambat " . $diff->i . " menit";
    }

    $stmt = $conn->prepare("INSERT INTO presensi (id_siswa, tanggal, jam_masuk, status, keterangan, device_id) 
                           VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $student['id_siswa'], $date, $time, $status, $keterangan, $device_id);
    $stmt->execute();
}

echo json_encode(["status" => "success", "message" => $is_check_in ? "Check-in recorded" : "Check-out recorded"]);

$conn->close();
?>