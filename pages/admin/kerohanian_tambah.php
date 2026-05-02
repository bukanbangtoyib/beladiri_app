<?php
session_start();

// Block tamu role, allow admin, negara, pengprov, pengkot, unit
if (!isset($_SESSION['user_id']) || $_SESSION['role'] == 'tamu') {
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
    $_SESSION['ranting_id'] ?? null, 
    $_SESSION['no_anggota'] ?? null
);

$GLOBALS['permission_manager'] = $permission_manager;

if (!$permission_manager->can('anggota_read')) {
    die("❌ Akses ditolak!");
}

$user_role = $_SESSION['role'] ?? '';
$user_pengurus_id = $_SESSION['pengurus_id'] ?? 0;
$user_ranting_id = $_SESSION['ranting_id'] ?? 0;

// Get organization name for non-admin users
$user_org_name = '';
if ($user_role != 'admin' && $user_role != 'superadmin') {
    if ($user_role == 'negara' && $user_pengurus_id) {
        $org_result = $conn->query("SELECT nama FROM negara WHERE id = $user_pengurus_id");
        if ($org_result && $org_result->num_rows > 0) {
            $user_org_name = $org_result->fetch_assoc()['nama'];
        }
    } elseif ($user_role == 'pengprov' && $user_pengurus_id) {
        $org_result = $conn->query("SELECT nama FROM provinsi WHERE id = $user_pengurus_id");
        if ($org_result && $org_result->num_rows > 0) {
            $user_org_name = $org_result->fetch_assoc()['nama'];
        }
    } elseif ($user_role == 'pengkot' && $user_pengurus_id) {
        $org_result = $conn->query("SELECT nama FROM kota WHERE id = $user_pengurus_id");
        if ($org_result && $org_result->num_rows > 0) {
            $user_org_name = $org_result->fetch_assoc()['nama'];
        }
    } elseif ($user_role == 'unit' && $user_ranting_id) {
        $org_result = $conn->query("SELECT nama_ranting as nama FROM ranting WHERE id = $user_ranting_id");
        if ($org_result && $org_result->num_rows > 0) {
            $user_org_name = $org_result->fetch_assoc()['nama'];
        }
    }
}

// Get all organizations for admin dropdown
$all_organizations = [];
if (in_array($user_role, ['admin', 'superadmin'])) {
    // Get all negara
    $negara_result = $conn->query("SELECT id, nama, 'negara' as type FROM negara ORDER BY nama");
    while ($row = $negara_result->fetch_assoc()) {
        $all_organizations[] = $row;
    }
    // Get all provinsi
    $provinsi_result = $conn->query("SELECT id, nama, 'provinsi' as type FROM provinsi ORDER BY nama");
    while ($row = $provinsi_result->fetch_assoc()) {
        $all_organizations[] = $row;
    }
    // Get all kota
    $kota_result = $conn->query("SELECT id, nama, 'kota' as type FROM kota ORDER BY nama");
    while ($row = $kota_result->fetch_assoc()) {
        $all_organizations[] = $row;
    }
    // Get all ranting
    $ranting_result = $conn->query("SELECT id, nama_ranting as nama, 'ranting' as type FROM ranting ORDER BY nama_ranting");
    while ($row = $ranting_result->fetch_assoc()) {
        $all_organizations[] = $row;
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $anggota_id = (int)$_POST['anggota_id'];
    $tanggal_pembukaan = $_POST['tanggal_pembukaan'];
    $lokasi = $conn->real_escape_string($_POST['lokasi']);
    $pembuka_nama = $conn->real_escape_string($_POST['pembuka_nama']);
    $penyelenggara = $conn->real_escape_string($_POST['penyelenggara']);
    $tingkat_pembuka_id = !empty($_POST['tingkat_pembuka_id']) ? (int)$_POST['tingkat_pembuka_id'] : NULL;
    $tingkat_id = !empty($_POST['tingkat_id']) ? (int)$_POST['tingkat_id'] : NULL;
    
    // Cek apakah sudah pernah pembukaan kerohanian
    $check = $conn->query("SELECT id FROM kerohanian WHERE anggota_id = $anggota_id");
    if ($check->num_rows > 0) {
        $error = "Anggota ini sudah memiliki catatan pembukaan kerohanian!";
    } else {
        // Cek duplikasi Nama & Tanggal Pembukaan (sesuai permintaan user)
        // Kita perlu ambil nama anggota dulu
        $anggota_res = $conn->query("SELECT nama_lengkap FROM anggota WHERE id = $anggota_id");
        $nama_anggota = ($anggota_res && $ar = $anggota_res->fetch_assoc()) ? $ar['nama_lengkap'] : '';
        
        $check_dup = $conn->query("
            SELECT k.id FROM kerohanian k 
            JOIN anggota a ON k.anggota_id = a.id 
            WHERE a.nama_lengkap = '" . $conn->real_escape_string($nama_anggota) . "' 
            AND k.tanggal_pembukaan = '$tanggal_pembukaan'
        ");
        
        if ($check_dup->num_rows > 0) {
            $error = "Data kerohanian untuk nama '$nama_anggota' pada tanggal tersebut sudah terdaftar!";
        }
    }

    if (!$error) {
        $sql = "INSERT INTO kerohanian (anggota_id, tanggal_pembukaan, lokasi, pembuka_nama, penyelenggara, tingkat_pembuka_id, tingkat_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            $stmt->bind_param("issssii", $anggota_id, $tanggal_pembukaan, $lokasi, $pembuka_nama, $penyelenggara, $tingkat_pembuka_id, $tingkat_id);
            
            if ($stmt->execute()) {
                // Update status kerohanian di anggota
                $conn->query("UPDATE anggota SET status_kerohanian = 'sudah', tanggal_pembukaan_kerohanian = '$tanggal_pembukaan' 
                              WHERE id = $anggota_id");
                
                $success = "Pembukaan kerohanian berhasil dicatat!";
                header("refresh:2;url=kerohanian.php");
            } else {
                $error = "Error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = "Error prepare: " . $conn->error;
        }
    }
}

// Ambil daftar anggota yang belum pembukaan kerohanian
$anggota_result = $conn->query("SELECT a.id, a.no_anggota, a.nama_lengkap, r.nama_ranting 
                                FROM anggota a
                                LEFT JOIN ranting r ON a.ranting_saat_ini_id = r.id
                                WHERE NOT EXISTS (SELECT 1 FROM kerohanian WHERE anggota_id = a.id)
                                ORDER BY a.nama_lengkap");

// Ambil daftar tingkat
$tingkat_result = $conn->query("SELECT id, nama_tingkat FROM tingkatan ORDER BY urutan");
$tingkat_list = $conn->query("SELECT id, nama_tingkat FROM tingkatan ORDER BY urutan");
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tambah Kerohanian - Sistem Beladiri</title>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background-color: #f5f5f5; }
        
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
        }
        
        .container { max-width: 900px; margin: 20px auto; padding: 0 20px; }
        .form-container {
            background: white;
            padding: 35px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        h1 { color: #333; margin-bottom: 30px; }
        .form-group { margin-bottom: 22px; }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }

        input[type="text"], input[type="date"], input[type="file"], input[type="tel"], input[type="time"],
        select, textarea {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            font-family: 'Segoe UI', sans-serif;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }
        
        .form-row.full { grid-template-columns: 1fr; }
        
        .required { color: #dc3545; }
        .form-hint { font-size: 12px; color: #999; margin-top: 6px; }
        h3 { color: #333; margin-bottom: 25px; font-size: 16px; padding-bottom: 12px; border-bottom: 2px solid #667eea; }
        
        /* Select2 styling to match other inputs */
        .select2-container .select2-selection--single {
            height: 42px;
            border: 1px solid #ddd;
            border-radius: 6px;
        }
        .select2-container .select2-selection--single .select2-selection__rendered {
            line-height: 42px;
            padding-left: 14px;
            font-size: 14px;
        }
        .select2-container .select2-selection--single .select2-selection__arrow {
            height: 40px;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary { background: #667eea; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        
        .button-group { display: flex; gap: 15px; margin-top: 35px; }
        
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 25px;
            border-left: 4px solid;
        }
        
        .alert-error { background: #fff5f5; color: #c00; border-left-color: #dc3545; }
        .alert-success { background: #f0fdf4; color: #060; border-left-color: #28a745; }
        
        .suggestions-box {
            position: absolute;
            background: white;
            border: 1px solid #ddd;
            border-radius: 4px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            width: 52%;
            display: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .suggestions-box.show { display: block; }
        
        .suggestion-item {
            padding: 10px 15px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
        }
        
        .suggestion-item:hover { background: #f5f5f5; }
        .suggestion-item:last-child { border-bottom: none; }
    </style>
</head>
<body>
    <?php renderNavbar('Tambah Kerohanian'); ?>

    <div style="display: flex; justify-content: center;">
        <div class="container" style="width: 100%;">
            <div class="form-container">
                <h1>Formulir Pencatatan Pembukaan Kerohanian</h1>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">⚠️ <?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">✓ <?php echo $success; ?></div>
                <?php endif; ?>
                
                <form method="POST">
                    <h3>Penyelenggara</h3>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Penyelenggara <span class="required">*</span></label>
                            <?php if (in_array($user_role, ['admin', 'superadmin'])): ?>
                                <!-- Admin: searchable dropdown with all organizations -->
                                <select name="penyelenggara" id="penyelenggara_select" required class="select2-searchable" style="width: 100%;">
                                    <option value="">-- Pilih Penyelenggara --</option>
                                    <?php foreach ($all_organizations as $org): ?>
                                        <option value="<?php echo htmlspecialchars($org['nama']); ?>">
                                            <?php echo htmlspecialchars($org['nama']) . ' (' . ucfirst($org['type']) . ')'; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-hint">Pilih organisasi penyelenggara dari dropdown</div>
                            <?php else: ?>
                                <!-- Non-admin: fixed to their organization -->
                                <input type="text" name="penyelenggara" value="<?php echo htmlspecialchars($user_org_name); ?>" readonly class="form-control-plaintext" style="background: #f8f9fa; color: #666;">
                                <input type="hidden" name="penyelenggara" value="<?php echo htmlspecialchars($user_org_name); ?>">
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label>Tanggal Pembukaan <span class="required">*</span></label>
                            <input type="date" name="tanggal_pembukaan" required>
                        </div>
                    </div>
                    
                    <div class="form-row">                    
                        <div class="form-group">
                            <label>Nama Pembuka <span class="required">*</span></label>
                            <input type="text" name="pembuka_nama" required placeholder="Nama pembuka kerohanian">
                        </div>
                    
                        <div class="form-group">
                            <label>Tingkat Pembuka <span class="required">*</span></label>
                            <select name="tingkat_pembuka_id" required>
                                <option value="">-- Pilih Tingkat --</option>
                                <?php while ($row = $tingkat_result->fetch_assoc()): ?>
                                    <option value="<?php echo $row['id']; ?>">
                                        <?php echo htmlspecialchars($row['nama_tingkat']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <div class="form-hint">Tingkat pembuka kerohanian</div>
                        </div>
                    </div>
                        
                    <div class="form-row full">
                        <div class="form-group">
                            <label>Lokasi Pembukaan <span class="required">*</span></label>
                            <textarea name="lokasi" required placeholder="Contoh: Gedung Olahraga"></textarea>
                        </div>
                    </div>

                    <h3>Data Peserta Pembukaan Kerohanian</h3>            

                    <div class="form-group">
                        <label>Anggota <span class="required">*</span></label>
                        <input type="text" id="anggota_search" placeholder="Ketik nama anggota..." autocomplete="off" required>
                        <input type="hidden" id="anggota_id" name="anggota_id">
                        <div id="anggota_suggestions" class="suggestions-box"></div>
                        <div class="form-hint">Ketik nama anggota untuk mencari (format: nama - ranting)</div>
                    </div>
                    <div class="form-group">
                            <label>Tingkat<span class="required">*</span></label>
                            <select name="tingkat_id" required>
                                <option value="">-- Pilih Tingkat --</option>
                                <?php while ($row = $tingkat_list->fetch_assoc()): ?>
                                    <option value="<?php echo $row['id']; ?>">
                                        <?php echo htmlspecialchars($row['nama_tingkat']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                            <div class="form-hint">Tingkat saat pembukaan kerohanian</div>
                        </div>
                    
                    
                    <div class="button-group">
                        <button type="submit" class="btn btn-primary">💾 Simpan</button>
                        <a href="kerohanian.php" class="btn btn-secondary">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        // Initialize Select2 for admin penyelenggara dropdown
        <?php if (in_array($user_role, ['admin', 'superadmin'])): ?>
        $(document).ready(function() {
            $('#penyelenggara_select').select2({
                placeholder: '-- Pilih Penyelenggara --',
                allowClear: false,
                width: '100%'
            }).on('select2:open', function() {
                // Robust auto-focus search field on open
                setTimeout(function() {
                    const searchField = document.querySelector('.select2-container--open .select2-search__field');
                    if (searchField) {
                        searchField.focus();
                    }
                }, 50);
            });
        });
        <?php endif; ?>
        const anggotaSearch = document.getElementById('anggota_search');
        const anggotaId = document.getElementById('anggota_id');
        const suggestionsBox = document.getElementById('anggota_suggestions');
        let timeout = null;
        
        anggotaSearch.addEventListener('input', function() {
            clearTimeout(timeout);
            const query = this.value.trim();
            
            if (query.length < 2) {
                suggestionsBox.classList.remove('show');
                suggestionsBox.innerHTML = '';
                anggotaId.value = '';
                return;
            }
            
            timeout = setTimeout(() => {
                fetch('../../api/get_anggota.php?exclude_kerohanian=1&q=' + encodeURIComponent(query))
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.data.length > 0) {
                            suggestionsBox.innerHTML = data.data.map(item => 
                                '<div class="suggestion-item" data-id="' + item.id + '" data-nama="' + item.nama_lengkap + '">' + 
                                item.display + '</div>'
                            ).join('');
                            suggestionsBox.classList.add('show');
                        } else {
                            suggestionsBox.innerHTML = '<div class="suggestion-item">Tidak ada hasil</div>';
                            suggestionsBox.classList.add('show');
                        }
                    })
                    .catch(error => console.error('Error:', error));
            }, 300);
        });
        
        suggestionsBox.addEventListener('click', function(e) {
            if (e.target.classList.contains('suggestion-item') && e.target.dataset.id) {
                anggotaSearch.value = e.target.dataset.nama;
                anggotaId.value = e.target.dataset.id;
                suggestionsBox.classList.remove('show');
            }
        });
        
        document.addEventListener('click', function(e) {
            if (!anggotaSearch.contains(e.target) && !suggestionsBox.contains(e.target)) {
                suggestionsBox.classList.remove('show');
            }
        });
        
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            if (!anggotaId.value) {
                e.preventDefault();
                alert('Silakan pilih anggota dari daftar yang muncul');
            }
        });
    </script>
</body>
</html>