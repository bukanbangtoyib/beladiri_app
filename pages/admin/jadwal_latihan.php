<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

include '../../config/database.php';
include '../../auth/PermissionManager.php';
include '../../helpers/navbar.php';

// Initialize permission manager
$permission_manager = new PermissionManager(
    $conn,
    $_SESSION['user_id'],
    $_SESSION['role'],
    $_SESSION['pengurus_id'] ?? null,
    $_SESSION['ranting_id'] ?? null
);

// Store untuk global use
$GLOBALS['permission_manager'] = $permission_manager;

// Check permission untuk action ini
if (!$permission_manager->can('anggota_read')) {
    die("❌ Akses ditolak!");
}

// Ambil filter dari GET
$ranting_id = isset($_GET['ranting_id']) ? (int)$_GET['ranting_id'] : 0;
$filter_negara = isset($_GET['filter_negara']) ? (int)$_GET['filter_negara'] : 0;
$filter_provinsi = isset($_GET['filter_provinsi']) ? (int)$_GET['filter_provinsi'] : 0;
$filter_kota = isset($_GET['filter_kota']) ? (int)$_GET['filter_kota'] : 0;
$filter_hari = isset($_GET['filter_hari']) ? $_GET['filter_hari'] : '';
$error = '';
$success = '';

// Proses tambah/edit jadwal
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? 'add';
    
    if ($action == 'add') {
        $hari = $_POST['hari'] ?? '';
        $jam_mulai = $_POST['jam_mulai'] ?? '';
        $jam_selesai = $_POST['jam_selesai'] ?? '';
        
        if (empty($ranting_id)) {
            $error = "Pilih unit/ranting terlebih dahulu!";
        } else {
            $sql = "INSERT INTO jadwal_latihan (ranting_id, hari, jam_mulai, jam_selesai) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isss", $ranting_id, $hari, $jam_mulai, $jam_selesai);
            
            if ($stmt->execute()) {
                $success = "Jadwal latihan berhasil ditambahkan!";
            } else {
                $error = "Error: " . $stmt->error;
            }
        }
    } elseif ($action == 'delete') {
        $jadwal_id = (int)$_POST['jadwal_id'];
        $conn->query("DELETE FROM jadwal_latihan WHERE id = $jadwal_id");
        $success = "Jadwal latihan berhasil dihapus!";
    }
}

// Ambil daftar negara
$negara_result = $conn->query("SELECT id, nama FROM negara ORDER BY nama");

// Ambil daftar provinsi berdasarkan negara
$provinsi_result = null;
if ($filter_negara > 0) {
    $provinsi_result = $conn->query("SELECT id, nama, negara_id FROM provinsi WHERE negara_id = $filter_negara ORDER BY nama");
} else {
    $provinsi_result = $conn->query("SELECT id, nama, negara_id FROM provinsi ORDER BY nama");
}

// Ambil daftar kota berdasarkan provinsi
$kota_result = null;
if ($filter_provinsi > 0) {
    $kota_result = $conn->query("SELECT id, nama, provinsi_id FROM kota WHERE provinsi_id = $filter_provinsi ORDER BY nama");
} elseif ($filter_negara > 0) {
    // Get cities from all provinces in the selected country
    $kota_result = $conn->query("SELECT k.id, k.nama, k.provinsi_id FROM kota k 
        LEFT JOIN provinsi p ON k.provinsi_id = p.id 
        WHERE p.negara_id = $filter_negara ORDER BY k.nama");
} else {
    $kota_result = $conn->query("SELECT id, nama, provinsi_id FROM kota ORDER BY nama");
}

// Ambil daftar ranting berdasarkan kota
$ranting_result = null;
if ($filter_kota > 0) {
    $ranting_result = $conn->query("SELECT id, nama_ranting FROM ranting WHERE kota_id = $filter_kota ORDER BY nama_ranting");
} elseif ($filter_provinsi > 0) {
    // Get ranting from all cities in the selected province
    $ranting_result = $conn->query("SELECT r.id, r.nama_ranting FROM ranting r
        LEFT JOIN kota k ON r.kota_id = k.id
        WHERE k.provinsi_id = $filter_provinsi ORDER BY r.nama_ranting");
} elseif ($filter_negara > 0) {
    // Get ranting from all cities in the selected country
    $ranting_result = $conn->query("SELECT r.id, r.nama_ranting FROM ranting r
        LEFT JOIN kota k ON r.kota_id = k.id
        LEFT JOIN provinsi p ON k.provinsi_id = p.id
        WHERE p.negara_id = $filter_negara ORDER BY r.nama_ranting");
} else {
    $ranting_result = $conn->query("SELECT id, nama_ranting FROM ranting ORDER BY nama_ranting");
}

// Ambil jadwal dengan filter
$jadwal_result = null;
$jadwal_where = [];

// Join dengan ranting, kota, provinsi, dan negara untuk filter cascade
$join_clause = "FROM jadwal_latihan j 
    LEFT JOIN ranting r ON j.ranting_id = r.id 
    LEFT JOIN kota k ON r.kota_id = k.id 
    LEFT JOIN provinsi prov ON k.provinsi_id = prov.id";

if ($ranting_id > 0) {
    $jadwal_where[] = "j.ranting_id = $ranting_id";
}
if ($filter_kota > 0) {
    $jadwal_where[] = "r.kota_id = $filter_kota";
}
if ($filter_provinsi > 0) {
    $jadwal_where[] = "k.provinsi_id = $filter_provinsi";
}
if ($filter_negara > 0) {
    $jadwal_where[] = "prov.negara_id = $filter_negara";
}
if ($filter_hari) {
    $jadwal_where[] = "j.hari = '" . $conn->real_escape_string($filter_hari) . "'";
}

$where_clause = count($jadwal_where) > 0 ? "WHERE " . implode(" AND ", $jadwal_where) : "";
$jadwal_result = $conn->query("SELECT j.*, r.nama_ranting $join_clause $where_clause ORDER BY FIELD(j.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'), j.jam_mulai");

$hari_options = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
$is_readonly = $_SESSION['role'] == 'tamu';
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jadwal Latihan - Sistem Beladiri</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background-color: #f5f5f5; }
        
        .container { max-width: 1100px; margin: 20px auto; padding: 0 20px; }
        
        .card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
        }
        
        h1 { color: #333; margin-bottom: 10px; }
        h3 { color: #333; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #667eea; }
        
        .filter-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }
        
        select, input { 
            padding: 10px; 
            border: 1px solid #ddd; 
            border-radius: 5px; 
            font-size: 14px;
            font-family: 'Segoe UI', sans-serif;
        }
        
        select:focus, input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        select:disabled {
            background-color: #f5f5f5;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
            align-items: end;
        }
        
        .btn { 
            padding: 10px 15px; 
            border: none; 
            border-radius: 5px; 
            cursor: pointer; 
            font-weight: 600; 
            font-size: 13px; 
            transition: all 0.3s;
        }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5568d3; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-reset { background: #6c757d; color: white; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #f8f9fa; padding: 12px; text-align: left; border: 1px solid #ddd; font-weight: 600; }
        td { padding: 12px; border: 1px solid #ddd; }
        tr:hover { background: #f9f9f9; }
        
        .no-data { text-align: center; padding: 40px; color: #999; }
        
        .alert {
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
            border-left: 4px solid;
        }
        
        .alert-error { background: #fff5f5; color: #c00; border-left-color: #dc3545; }
        .alert-success { background: #f0fdf4; color: #060; border-left-color: #28a745; }
        
        .info-text {
            background: #f0f7ff;
            border-left: 3px solid #667eea;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
            font-size: 13px;
            color: #333;
        }
        
        .info-text strong {
            color: #667eea;
        }
    </style>
</head>
<body>
    <?php renderNavbar('⏰ Jadwal Latihan'); ?>
    
    <div class="container">
        <?php if ($error): ?>
            <div class="alert alert-error">⚠️ <?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">✓ <?php echo $success; ?></div>
        <?php endif; ?>
        
        <!-- Filter Section -->
        <div class="card">
            <h3>🔍 Filter Jadwal Latihan (Cascade)</h3>
            
            <div class="info-text">
                <strong>ℹ️ Cara Menggunakan:</strong> Pilih Negara terlebih dahulu, lalu Provinsi akan menampilkan list yang ada di bawahnya, kemudian Kota, dan terakhir Unit/Ranting.
            </div>
            
            <form method="GET" style="margin-bottom: 20px;">
                <div class="filter-section">
                    <div class="filter-row">
                        <!-- Filter 1: Negara -->
                        <div class="form-group">
                            <label for="filter_negara">🌍 Negara</label>
                            <select name="filter_negara" id="filter_negara" onchange="updateProvinsi()">
                                <option value="">-- Pilih Negara --</option>
                                <?php 
                                $negara_result->data_seek(0);
                                while ($row = $negara_result->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $row['id']; ?>" <?php echo $filter_negara == $row['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($row['nama']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <!-- Filter 2: Provinsi -->
                        <div class="form-group">
                            <label for="filter_provinsi">📍 Provinsi</label>
                            <select name="filter_provinsi" id="filter_provinsi" onchange="updateKota()" <?php echo $filter_negara == 0 ? 'disabled' : ''; ?>>
                                <option value="">-- Pilih Provinsi --</option>
                                <?php 
                                if ($provinsi_result) {
                                    $provinsi_result->data_seek(0);
                                    while ($row = $provinsi_result->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $row['id']; ?>" <?php echo $filter_provinsi == $row['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($row['nama']); ?>
                                        </option>
                                    <?php endwhile;
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Filter 3: Kota -->
                        <div class="form-group">
                            <label for="filter_kota">🏛️ Kota / Kabupaten</label>
                            <select name="filter_kota" id="filter_kota" onchange="updateRanting()" <?php echo $filter_provinsi == 0 ? 'disabled' : ''; ?>>
                                <option value="">-- Pilih Kota --</option>
                                <?php 
                                if ($kota_result) {
                                    $kota_result->data_seek(0);
                                    while ($row = $kota_result->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $row['id']; ?>" <?php echo $filter_kota == $row['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($row['nama']); ?>
                                        </option>
                                    <?php endwhile;
                                }
                                ?>
                            </select>
                        </div>

                        <!-- Filter 4: Ranting -->
                        <div class="form-group">
                            <label for="ranting_id">🢂 Unit/Ranting</label>
                            <select name="ranting_id" id="ranting_id" onchange="this.form.submit()" <?php echo $filter_kota == 0 ? 'disabled' : ''; ?>>
                                <option value="">-- Pilih Ranting --</option>
                                <?php 
                                if ($ranting_result && $ranting_result->num_rows > 0) {
                                    $ranting_result->data_seek(0);
                                    while ($row = $ranting_result->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $row['id']; ?>" <?php echo ($ranting_id == $row['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($row['nama_ranting']); ?>
                                        </option>
                                    <?php endwhile;
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="filter-row" style="margin-top: 15px;">
                        <!-- Filter 5: Hari -->
                        <div class="form-group">
                            <label for="filter_hari">📅 Hari</label>
                            <select name="filter_hari" id="filter_hari" onchange="this.form.submit()">
                                <option value="">-- Semua Hari --</option>
                                <?php 
                                $hari_options = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
                                foreach ($hari_options as $hari): 
                                ?>
                                    <option value="<?php echo $hari; ?>" <?php echo $filter_hari == $hari ? 'selected' : ''; ?>>
                                        <?php echo $hari; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group" style="display: flex; align-items: flex-end; gap: 10px;">
                            <button type="submit" class="btn btn-primary">🔍 Filter</button>
                            <a href="jadwal_latihan.php" class="btn btn-reset">🔄 Reset</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
                
        <!-- Daftar Jadwal -->
        <?php $show_jadwal = ($ranting_id > 0 || $filter_hari || $filter_kota || $filter_provinsi || $filter_negara); ?>
        <?php if ($show_jadwal): ?>
        <div class="card">
            <h3>📋 Jadwal Latihan</h3>
            
            <?php if ($jadwal_result && $jadwal_result->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Unit/Ranting</th>
                        <th>Hari</th>
                        <th>Jam Mulai</th>
                        <th>Jam Selesai</th>
                        <th style="width: 100px;">Durasi</th>
                        <?php if (!$is_readonly): ?><th style="width: 80px;">Aksi</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $jadwal_result->fetch_assoc()): 
                        $mulai = strtotime($row['jam_mulai']);
                        $selesai = strtotime($row['jam_selesai']);
                        $durasi = round(($selesai - $mulai) / 3600);
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($row['nama_ranting'] ?? '-'); ?></strong></td>
                        <td><strong><?php echo $row['hari']; ?></strong></td>
                        <td><?php echo date('H:i', $mulai); ?></td>
                        <td><?php echo date('H:i', $selesai); ?></td>
                        <td><?php echo $durasi; ?> jam</td>
                        <?php if (!$is_readonly): ?>
                        <td>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="jadwal_id" value="<?php echo $row['id']; ?>">
                                <button type="submit" class="btn btn-danger" onclick="return confirm('Hapus jadwal ini?')">Hapus</button>
                            </form>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-data">
                <p>🔭 Belum ada jadwal latihan untuk filter yang dipilih</p>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Form Input Jadwal -->
        <?php if ($ranting_id > 0 && !$is_readonly): ?>
        <div class="card">
            <h3>➕ Tambah Jadwal Baru</h3>
            
            <form method="POST" style="margin-bottom: 25px;">
                <input type="hidden" name="action" value="add">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="hari">Hari</label>
                        <select name="hari" id="hari" required>
                            <option value="">-- Pilih Hari --</option>
                            <?php foreach ($hari_options as $h): ?>
                                <option value="<?php echo $h; ?>"><?php echo $h; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="jam_mulai">Jam Mulai</label>
                        <input type="time" name="jam_mulai" id="jam_mulai" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="jam_selesai">Jam Selesai</label>
                        <input type="time" name="jam_selesai" id="jam_selesai" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary" style="margin-top: 10px;">➕ Tambah Jadwal</button>
            </form>
        </div>
        <?php endif; ?>        
    </div>

    <script>
        // Function untuk update dropdown Provinsi via AJAX
        function updateProvinsi() {
            const negaraSelect = document.getElementById('filter_negara');
            const provinsiSelect = document.getElementById('filter_provinsi');
            const kotaSelect = document.getElementById('filter_kota');
            const rantingSelect = document.getElementById('ranting_id');
            
            const negaraId = negaraSelect.value;
            
            if (negaraId === '') {
                // Jika tidak ada negara yang dipilih, disable semua dropdown
                provinsiSelect.disabled = true;
                kotaSelect.disabled = true;
                rantingSelect.disabled = true;
                
                provinsiSelect.innerHTML = '<option value="">-- Pilih Provinsi --</option>';
                kotaSelect.innerHTML = '<option value="">-- Pilih Kota --</option>';
                rantingSelect.innerHTML = '<option value="">-- Pilih Ranting --</option>';
                return;
            }
            
            provinsiSelect.disabled = false;
            
            // Fetch provinsi yang ada di bawah negara ini
            fetch('../../api/manage_provinsi.php?action=get_by_negara&id_negara=' + negaraId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let html = '<option value="">-- Pilih Provinsi --</option>';
                        data.data.forEach(provinsi => {
                            html += '<option value="' + provinsi.id + '">' + provinsi.nama + '</option>';
                        });
                        provinsiSelect.innerHTML = html;
                        
                        // Reset kota dan ranting dropdown
                        kotaSelect.innerHTML = '<option value="">-- Pilih Kota --</option>';
                        kotaSelect.disabled = true;
                        rantingSelect.innerHTML = '<option value="">-- Pilih Ranting --</option>';
                        rantingSelect.disabled = true;
                    }
                })
                .catch(error => console.error('Error:', error));
        }
        
        // Function untuk update dropdown Kota via AJAX
        function updateKota() {
            const provinsiSelect = document.getElementById('filter_provinsi');
            const kotaSelect = document.getElementById('filter_kota');
            const rantingSelect = document.getElementById('ranting_id');
            
            const provinsiId = provinsiSelect.value;
            
            if (provinsiId === '') {
                // Jika tidak ada provinsi yang dipilih, disable kota dan ranting
                kotaSelect.disabled = true;
                rantingSelect.disabled = true;
                kotaSelect.innerHTML = '<option value="">-- Pilih Kota --</option>';
                rantingSelect.innerHTML = '<option value="">-- Pilih Ranting --</option>';
                return;
            }
            
            kotaSelect.disabled = false;
            
            // Fetch kota yang ada di bawah provinsi ini
            fetch('../../api/manage_kota.php?action=get_by_provinsi&provinsi_id=' + provinsiId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let html = '<option value="">-- Pilih Kota --</option>';
                        data.data.forEach(kota => {
                            html += '<option value="' + kota.id + '">' + kota.nama + '</option>';
                        });
                        kotaSelect.innerHTML = html;
                        
                        // Reset ranting dropdown
                        rantingSelect.innerHTML = '<option value="">-- Pilih Ranting --</option>';
                        rantingSelect.disabled = true;
                    }
                })
                .catch(error => console.error('Error:', error));
        }
        
        // Function untuk update dropdown Ranting via AJAX
        function updateRanting() {
            const kotaSelect = document.getElementById('filter_kota');
            const rantingSelect = document.getElementById('ranting_id');
            
            const kotaId = kotaSelect.value;
            
            if (kotaId === '') {
                // Jika tidak ada kota yang dipilih, disable ranting
                rantingSelect.disabled = true;
                rantingSelect.innerHTML = '<option value="">-- Pilih Ranting --</option>';
                return;
            }
            
            rantingSelect.disabled = false;
            
            // Fetch ranting yang ada di bawah kota ini
            fetch('../../api/get_ranting.php?pengkot_id=' + kotaId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let html = '<option value="">-- Pilih Ranting --</option>';
                        data.data.forEach(ranting => {
                            html += '<option value="' + ranting.id + '">' + ranting.nama_ranting + '</option>';
                        });
                        rantingSelect.innerHTML = html;
                    }
                })
                .catch(error => console.error('Error:', error));
        }
    </script>
</body>
</html>