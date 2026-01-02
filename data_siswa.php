<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Tambah siswa
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah'])) {
    $nis = $_POST['nis'];
    $nama_siswa = $_POST['nama_siswa'];
    $id_kelas = $_POST['id_kelas'];
    $id_finger = $_POST['id_finger'];
    $status_aktif = isset($_POST['status_aktif']) ? 1 : 0;

    $stmt = $conn->prepare("INSERT INTO siswa (nis, nama_siswa, id_kelas, id_finger, status_aktif) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssiii", $nis, $nama_siswa, $id_kelas, $id_finger, $status_aktif);
    $stmt->execute();
    header("Location: data_siswa.php");
    exit;
}

// Enroll fingerprint
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['enroll'])) {
    $id_finger = $_POST['id_finger'];
    // Send enroll command to NodeMCU
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "http://nodemcu_ip/enroll");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['finger_id' => $id_finger]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);
    $message = json_decode($response, true)['message'] ?? 'Enroll initiated';
}

// Ambil data siswa
$siswa = $conn->query("SELECT s.*, k.nama_kelas FROM siswa s JOIN kelas k ON s.id_kelas = k.id_kelas");
$kelas = $conn->query("SELECT * FROM kelas");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Siswa - FPM Absensi</title>
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
            <a href="data_siswa.php" class="block py-2 px-4 bg-blue-600 rounded mb-2">Data Siswa</a>
            <a href="data_presensi.php" class="block py-2 px-4 hover:bg-blue-600 rounded mb-2">Data Presensi</a>
            <a href="config_menu.php" class="block py-2 px-4 hover:bg-blue-600 rounded mb-2">Konfigurasi</a>
            <a href="logout.php" class="block py-2 px-4 hover:bg-blue-600 rounded">Logout</a>
        </div>
        <!-- Content -->
        <div class="flex-1 p-6 ml-64">
            <h1 class="text-3xl font-bold mb-6 text-gray-800">Data Siswa</h1>
            <!-- Form Tambah Siswa -->
            <div class="bg-white p-6 rounded-lg shadow-lg mb-6 card">
                <h2 class="text-xl font-semibold mb-4 text-gray-700">Tambah Siswa</h2>
                <?php if (isset($message)) { echo "<p class='text-green-500'>$message</p>"; } ?>
                <form method="POST">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-700">NIS</label>
                            <input type="text" name="nis" class="w-full p-2 border rounded" required>
                        </div>
                        <div>
                            <label class="block text-gray-700">Nama Siswa</label>
                            <input type="text" name="nama_siswa" class="w-full p-2 border rounded" required>
                        </div>
                        <div>
                            <label class="block text-gray-700">Kelas</label>
                            <select name="id_kelas" class="w-full p-2 border rounded" required>
                                <?php while ($row = $kelas->fetch_assoc()) { ?>
                                    <option value="<?php echo $row['id_kelas']; ?>"><?php echo $row['nama_kelas']; ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-700">ID Finger</label>
                            <input type="number" name="id_finger" class="w-full p-2 border rounded" required>
                        </div>
                        <div class="col-span-2">
                            <label class="inline-flex items-center">
                                <input type="checkbox" name="status_aktif" value="1" checked class="mr-2">
                                <span class="text-gray-700">Status Aktif</span>
                            </label>
                        </div>
                    </div>
                    <div class="mt-4 flex space-x-2">
                        <button type="submit" name="tambah" class="bg-blue-500 text-white p-2 rounded tooltip" data-tooltip="Simpan siswa">Simpan</button>
                        <button type="submit" name="enroll" class="bg-green-500 text-white p-2 rounded tooltip" data-tooltip="Sinkronkan fingerprint ke NodeMCU">Sinkron</button>
                    </div>
                </form>
            </div>
            <!-- Tabel Siswa -->
            <div class="bg-white p-6 rounded-lg shadow-lg card">
                <table class="w-full border">
                    <thead class="bg-gray-200">
                        <tr>
                            <th class="p-2 border">NIS</th>
                            <th class="p-2 border">Nama</th>
                            <th class="p-2 border">Kelas</th>
                            <th class="p-2 border">ID Finger</th>
                            <th class="p-2 border">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $siswa->fetch_assoc()) { ?>
                            <tr>
                                <td class="p-2 border"><?php echo $row['nis']; ?></td>
                                <td class="p-2 border"><?php echo $row['nama_siswa']; ?></td>
                                <td class="p-2 border"><?php echo $row['nama_kelas']; ?></td>
                                <td class="p-2 border"><?php echo $row['id_finger']; ?></td>
                                <td class="p-2 border"><?php echo $row['status_aktif'] ? 'Aktif' : 'Tidak Aktif'; ?></td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>