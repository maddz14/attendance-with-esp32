<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $id_presensi = $_POST['id_presensi'];
    $status = $_POST['status'];
    $stmt = $conn->prepare("UPDATE presensi SET status = ? WHERE id_presensi = ?");
    $stmt->bind_param("si", $status, $id_presensi);
    $stmt->execute();
    header("Location: data_presensi.php");
    exit;
}

// Filter parameters
$date_filter = $_GET['date'] ?? '';
$month_filter = $_GET['month'] ?? date('Y-m');
$where_clause = "WHERE 1=1";
$params = [];
$param_types = "";

if ($date_filter) {
    $where_clause .= " AND p.tanggal = ?";
    $params[] = $date_filter;
    $param_types .= "s";
}
if ($month_filter) {
    $where_clause .= " AND DATE_FORMAT(p.tanggal, '%Y-%m') = ?";
    $params[] = $month_filter;
    $param_types .= "s";
}

// Fetch presensi data
$query = "SELECT p.*, s.nama_siswa, k.nama_kelas 
          FROM presensi p 
          JOIN siswa s ON p.id_siswa = s.id_siswa 
          JOIN kelas k ON s.id_kelas = k.id_kelas 
          $where_clause 
          ORDER BY p.tanggal DESC";
$stmt = $conn->prepare($query);
if ($params) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$presensi = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Presensi - FPM Absensi</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <style>
        body {
            background: linear-gradient(to bottom right, #f0f9ff, #e0f2fe);
        }
        .table-container {
            max-height: 500px;
            overflow-y: auto;
        }
        .status-select {
            transition: all 0.3s ease;
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
            <a href="data_presensi.php" class="block py-2 px-4 bg-blue-600 rounded mb-2">Data Presensi</a>
            <a href="config_menu.php" class="block py-2 px-4 hover:bg-blue-600 rounded mb-2">Konfigurasi</a>
            <a href="logout.php" class="block py-2 px-4 hover:bg-blue-600 rounded">Logout</a>
        </div>
        <!-- Content -->
        <div class="flex-1 p-6 ml-64">
            <h1 class="text-3xl font-bold mb-6 text-gray-800">Data Presensi</h1>
            <!-- Filters -->
            <div class="bg-white p-4 rounded-lg shadow-lg mb-6">
                <form class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-gray-700">Filter Tanggal</label>
                        <input type="date" name="date" value="<?php echo $date_filter; ?>" class="w-full p-2 border rounded">
                    </div>
                    <div>
                        <label class="block text-gray-700">Filter Bulan</label>
                        <input type="month" name="month" value="<?php echo $month_filter; ?>" class="w-full p-2 border rounded">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="bg-blue-500 text-white p-2 rounded w-full">Filter</button>
                    </div>
                    <div class="flex items-end space-x-2">
                        <button type="button" onclick="exportToExcel()" class="bg-green-500 text-white p-2 rounded w-full tooltip" data-tooltip="Export ke Excel">Excel</button>
                        <button type="button" onclick="exportToPDF()" class="bg-red-500 text-white p-2 rounded w-full tooltip" data-tooltip="Export ke PDF">PDF</button>
                    </div>
                </form>
            </div>
            <!-- Table -->
            <div class="bg-white p-4 rounded-lg shadow-lg table-container">
                <table class="w-full border">
                    <thead class="bg-gray-200 sticky top-0">
                        <tr>
                            <th class="p-2 border">Nama Siswa</th>
                            <th class="p-2 border">Kelas</th>
                            <th class="p-2 border">Tanggal</th>
                            <th class="p-2 border">Jam Masuk</th>
                            <th class="p-2 border">Jam Pulang</th>
                            <th class="p-2 border">Status</th>
                            <th class="p-2 border">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $presensi->fetch_assoc()) { ?>
                            <tr>
                                <td class="p-2 border"><?php echo $row['nama_siswa']; ?></td>
                                <td class="p-2 border"><?php echo $row['nama_kelas']; ?></td>
                                <td class="p-2 border"><?php echo $row['tanggal']; ?></td>
                                <td class="p-2 border"><?php echo $row['jam_masuk'] ?: '-'; ?></td>
                                <td class="p-2 border"><?php echo $row['jam_pulang'] ?: '-'; ?></td>
                                <td class="p-2 border">
                                    <form method="POST" class="inline">
                                        <input type="hidden" name="id_presensi" value="<?php echo $row['id_presensi']; ?>">
                                        <select name="status" class="p-1 border rounded status-select" onchange="this.form.submit()">
                                            <option value="Hadir" <?php echo $row['status'] == 'Hadir' ? 'selected' : ''; ?>>Hadir</option>
                                            <option value="Alpa" <?php echo $row['status'] == 'Alpa' ? 'selected' : ''; ?>>Alpa</option>
                                            <option value="Bolos" <?php echo $row['status'] == 'Bolos' ? 'selected' : ''; ?>>Bolos</option>
                                            <option value="Terlambat" <?php echo $row['status'] == 'Terlambat' ? 'selected' : ''; ?>>Terlambat</option>
                                            <option value="Sakit" <?php echo $row['status'] == 'Sakit' ? 'selected' : ''; ?>>Sakit</option>
                                            <option value="Izin" <?php echo $row['status'] == 'Izin' ? 'selected' : ''; ?>>Izin</option>
                                        </select>
                                    </form>
                                </td>
                                <td class="p-2 border"><?php echo $row['keterangan'] ?: '-'; ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <script>
        function exportToExcel() {
            const table = document.querySelector('table');
            const ws = XLSX.utils.table_to_sheet(table);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Presensi');
            XLSX.writeFile(wb, 'presensi.xlsx');
        }

        function exportToPDF() {
            const { jsPDF } = window.jspdf;
            const doc = new jsPDF();
            doc.autoTable({ html: 'table' });
            doc.save('presensi.pdf');
        }
    </script>
</body>
</html>