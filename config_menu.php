<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $start_check_in = $_POST['start_check_in'];
    $end_check_in = $_POST['end_check_in'];
    $start_check_out = $_POST['start_check_out'];
    $end_check_out = $_POST['end_check_out'];
    $holiday_1 = isset($_POST['holiday_1']) ? 1 : 0;
    $holiday_2 = $_POST['holiday_2'];

    $stmt = $conn->prepare("INSERT INTO config (start_check_in, end_check_in, start_check_out, end_check_out, holiday_1, holiday_2) 
                            VALUES (?, ?, ?, ?, ?, ?) 
                            ON DUPLICATE KEY UPDATE 
                            start_check_in = ?, end_check_in = ?, start_check_out = ?, end_check_out = ?, holiday_1 = ?, holiday_2 = ?");
    $stmt->bind_param("ssssisssssis", 
        $start_check_in, $end_check_in, $start_check_out, $end_check_out, $holiday_1, $holiday_2,
        $start_check_in, $end_check_in, $start_check_out, $end_check_out, $holiday_1, $holiday_2
    );
    $stmt->execute();
    $message = "Konfigurasi berhasil disimpan!";
}

// Fetch current config
$config = $conn->query("SELECT * FROM config LIMIT 1")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konfigurasi - FPM Absensi</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(to bottom right, #f0f9ff, #e0f2fe);
        }
        .card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        .tooltip {
            position: relative;
        }
        .tooltip:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            background: #1e40af;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            z-index: 10;
        }
    </style>
</head>
<body class="min-h-screen">
    <div class="flex">
        <!-- Sidebar -->
        <div class="w-64 bg-gradient-to-b from-blue-800 to-blue-900 text-white h-screen p-4 fixed">
            <h2 class="text-2xl font-bold mb-6">FPM Absensi</h2>
            <a href="dashboard.php" class="block py-2 px-4 hover:bg-blue-600 rounded mb-2">Dashboard</a>
            <a href="data_siswa.php" class="block py-2 px-4 hover:bg-blue-600 rounded mb-2">Data Siswa</a>
            <a href="data_presensi.php" class="block py-2 px-4 hover:bg-blue-600 rounded mb-2">Data Presensi</a>
            <a href="config_menu.php" class="block py-2 px-4 bg-blue-600 rounded mb-2">Konfigurasi</a>
            <a href="logout.php" class="block py-2 px-4 hover:bg-blue-600 rounded">Logout</a>
        </div>
        <!-- Content -->
        <div class="flex-1 p-6 ml-64">
            <h1 class="text-3xl font-bold mb-6 text-gray-800">Konfigurasi</h1>
            <div class="bg-white p-6 rounded-lg shadow-lg card">
                <h2 class="text-xl font-semibold mb-4 text-gray-700">Pengaturan Absensi</h2>
                <?php if (isset($message)) { echo "<p class='text-green-500'>$message</p>"; } ?>
                <form method="POST">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700">Mulai Jam Masuk</label>
                            <input type="time" name="start_check_in" value="<?php echo $config['start_check_in'] ?? '07:00'; ?>" class="w-full p-2 border rounded" required>
                        </div>
                        <div>
                            <label class="block text-gray-700">Akhir Jam Masuk</label>
                            <input type="time" name="end_check_in" value="<?php echo $config['end_check_in'] ?? '08:00'; ?>" class="w-full p-2 border rounded" required>
                        </div>
                        <div>
                            <label class="block text-gray-700">Mulai Jam Keluar</label>
                            <input type="time" name="start_check_out" value="<?php echo $config['start_check_out'] ?? '15:00'; ?>" class="w-full p-2 border rounded" required>
                        </div>
                        <div>
                            <label class="block text-gray-700">Akhir Jam Keluar</label>
                            <input type="time" name="end_check_out" value="<?php echo $config['end_check_out'] ?? '16:00'; ?>" class="w-full p-2 border rounded" required>
                        </div>
                        <div class="col-span-2">
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="holiday_1" value="1" <?php echo ($config['holiday_1'] ?? 0) ? 'checked' : ''; ?> class="mr-2">
                                <span class="text-gray-700">Hari Libur: Sabtu & Minggu</span>
                            </label>
                        </div>
                        <div class="col-span-2">
                            <label class="block text-gray-700">Hari Libur Tambahan (pisahkan dengan koma, format YYYY-MM-DD)</label>
                            <input type="text" name="holiday_2" value="<?php echo $config['holiday_2'] ?? ''; ?>" class="w-full p-2 border rounded" placeholder="2025-12-25,2025-12-26">
                        </div>
                    </div>
                    <button type="submit" class="mt-4 bg-blue-500 text-white p-2 rounded tooltip" data-tooltip="Simpan konfigurasi">Simpan</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>