<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Fetch summary data
$total_siswa = $conn->query("SELECT COUNT(*) as total FROM siswa WHERE status_aktif = 1")->fetch_assoc()['total'];
$hadir_hari_ini = $conn->query("SELECT COUNT(*) as total FROM presensi WHERE tanggal = CURDATE() AND status = 'Hadir'")->fetch_assoc()['total'];

// Fetch attendance statistics for chart
$stats_query = $conn->query("SELECT status, COUNT(*) as count FROM presensi WHERE MONTH(tanggal) = MONTH(CURDATE()) GROUP BY status");
$stats = ['Hadir' => 0, 'Alpa' => 0, 'Bolos' => 0, 'Terlambat' => 0, 'Sakit' => 0, 'Izin' => 0];
while ($row = $stats_query->fetch_assoc()) {
    $stats[$row['status']] = $row['count'];
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - FPM Absensi</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
        body {
            background: linear-gradient(to bottom right, #f0f9ff, #e0f2fe);
            font-family: 'Inter', sans-serif;
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
            <a href="dashboard.php" class="block py-2 px-4 bg-blue-600 rounded mb-2">Dashboard</a>
            <a href="data_siswa.php" class="block py-2 px-4 hover:bg-blue-600 rounded mb-2">Data Siswa</a>
            <a href="data_presensi.php" class="block py-2 px-4 hover:bg-blue-600 rounded mb-2">Data Presensi</a>
            <a href="config_menu.php" class="block py-2 px-4 hover:bg-blue-600 rounded mb-2">Konfigurasi</a>
            <a href="logout.php" class="block py-2 px-4 hover:bg-blue-600 rounded">Logout</a>
        </div>
        <!-- Content -->
        <div class="flex-1 p-6 ml-64">
            <h1 class="text-3xl font-bold mb-6 text-gray-800">Dashboard</h1>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Total Siswa -->
                <div class="bg-white p-6 rounded-lg shadow-lg card">
                    <h3 class="text-lg font-semibold text-gray-700">Total Siswa</h3>
                    <p class="text-3xl font-bold text-blue-600"><?php echo $total_siswa; ?></p>
                </div>
                <!-- Hadir Hari Ini -->
                <div class="bg-white p-6 rounded-lg shadow-lg card">
                    <h3 class="text-lg font-semibold text-gray-700">Hadir Hari Ini</h3>
                    <p class="text-3xl font-bold text-green-600"><?php echo $hadir_hari_ini; ?></p>
                </div>
                <!-- Jam dan Tanggal -->
                <div class="bg-white p-6 rounded-lg shadow-lg card">
                    <h3 class="text-lg font-semibold text-gray-700">Waktu Sekarang</h3>
                    <p class="text-2xl font-bold text-gray-800" id="clock"></p>
                    <p class="text-lg text-gray-600" id="date"></p>
                </div>
            </div>
            <!-- Attendance Chart -->
            <div class="mt-6 bg-white p-6 rounded-lg shadow-lg">
                <h3 class="text-lg font-semibold mb-4 text-gray-700">Statistik Kehadiran Bulan Ini</h3>
                <canvas id="attendanceChart" height="100"></canvas>
            </div>
        </div>
    </div>
    <script>
        // Real-time clock
        function updateClock() {
            const now = new Date();
            const time = now.toLocaleTimeString('id-ID', { hour12: false });
            const date = now.toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            document.getElementById('clock').textContent = time;
            document.getElementById('date').textContent = date;
            setTimeout(updateClock, 1000);
        }
        updateClock();

        // Attendance chart
        const ctx = document.getElementById('attendanceChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Hadir', 'Alpa', 'Bolos', 'Terlambat', 'Sakit', 'Izin'],
                datasets: [{
                    label: 'Jumlah',
                    data: [
                        <?php echo $stats['Hadir']; ?>,
                        <?php echo $stats['Alpa']; ?>,
                        <?php echo $stats['Bolos']; ?>,
                        <?php echo $stats['Terlambat']; ?>,
                        <?php echo $stats['Sakit']; ?>,
                        <?php echo $stats['Izin']; ?>
                    ],
                    backgroundColor: ['#34d399', '#ef4444', '#f59e0b', '#3b82f6', '#8b5cf6', '#ec4899']
                }]
            },
            options: {
                scales: {
                    y: { beginAtZero: true }
                },
                plugins: {
                    legend: { display: false }
                }
            }
        });
    </script>
</body>
</html>