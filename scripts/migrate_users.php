<?php
/**
 * Script Migrasi User - Sistem Beladiri
 * Menghasilkan akun user untuk data anggota dan pengurus (Negara, Provinsi, Kota, Ranting) 
 * yang sudah ada di database sesuai dengan aturan yang telah ditentukan.
 */

session_start();

// Check if user is logged in and is superadmin/admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['superadmin', 'admin'])) {
    die("❌ Akses ditolak! Hanya admin yang dapat menjalankan script ini.");
}

include '../config/database.php';
include '../helpers/user_auto_creation.php';

// Set timeout lebih lama karena proses mungkin memakan waktu
set_time_limit(300);

echo "<!DOCTYPE html>
<html lang='id'>
<head>
    <meta charset='UTF-8'>
    <title>Migrasi User - Sistem Beladiri</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f7f6; padding: 40px; color: #333; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        h1 { color: #2c3e50; border-bottom: 2px solid #eee; padding-bottom: 15px; margin-top: 0; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin: 25px 0; }
        .stat-card { background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #667eea; }
        .stat-card h3 { margin: 0; font-size: 14px; color: #666; text-transform: uppercase; }
        .stat-card p { margin: 5px 0 0; font-size: 24px; font-weight: bold; color: #333; }
        .log-container { background: #1e1e1e; color: #d4d4d4; padding: 20px; border-radius: 8px; font-family: 'Consolas', monospace; font-size: 13px; max-height: 400px; overflow-y: auto; margin-top: 20px; }
        .log-entry { margin-bottom: 4px; border-bottom: 1px solid #333; padding-bottom: 4px; }
        .success { color: #4ec9b0; }
        .error { color: #f44747; }
        .info { color: #569cd6; }
        .btn { display: inline-block; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px 25px; border-radius: 6px; text-decoration: none; font-weight: 600; margin-top: 20px; transition: all 0.3s; border: none; cursor: pointer; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3); }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🚀 Proses Migrasi User</h1>";

$stats = [
    'negara' => 0,
    'provinsi' => 0,
    'kota' => 0,
    'ranting' => 0,
    'anggota' => 0,
    'errors' => 0
];

echo "<div class='log-container'>";

// 1. Migrasi Negara
$res = $conn->query("SELECT id, nama FROM negara");
while ($row = $res->fetch_assoc()) {
    $nama = $row['nama'];
    $success = createOrUpdateUser($conn, [
        'username' => $nama,
        'password' => $nama . '1955',
        'nama_lengkap' => "Pengurus Negara $nama",
        'role' => 'negara',
        'pengurus_id' => $row['id']
    ]);
    if ($success) {
        $stats['negara']++;
        echo "<div class='log-entry'><span class='success'>[SUCCESS]</span> User Negara: $nama</div>";
    } else {
        $stats['errors']++;
        echo "<div class='log-entry'><span class='error'>[ERROR]</span> Gagal migrasi Negara: $nama</div>";
    }
}

// 2. Migrasi Provinsi
$res = $conn->query("SELECT id, nama FROM provinsi");
while ($row = $res->fetch_assoc()) {
    $nama = $row['nama'];
    $success = createOrUpdateUser($conn, [
        'username' => $nama,
        'password' => $nama . '1955',
        'nama_lengkap' => "Pengurus Provinsi $nama",
        'role' => 'pengprov',
        'pengurus_id' => $row['id']
    ]);
    if ($success) {
        $stats['provinsi']++;
        echo "<div class='log-entry'><span class='success'>[SUCCESS]</span> User Provinsi: $nama</div>";
    } else {
        $stats['errors']++;
        echo "<div class='log-entry'><span class='error'>[ERROR]</span> Gagal migrasi Provinsi: $nama</div>";
    }
}

// 3. Migrasi Kota
$res = $conn->query("SELECT id, nama FROM kota");
while ($row = $res->fetch_assoc()) {
    $nama = $row['nama'];
    $success = createOrUpdateUser($conn, [
        'username' => $nama,
        'password' => $nama . '1955',
        'nama_lengkap' => "Pengurus Kota / Kabupaten $nama",
        'role' => 'pengkot',
        'pengurus_id' => $row['id']
    ]);
    if ($success) {
        $stats['kota']++;
        echo "<div class='log-entry'><span class='success'>[SUCCESS]</span> User Kota: $nama</div>";
    } else {
        $stats['errors']++;
        echo "<div class='log-entry'><span class='error'>[ERROR]</span> Gagal migrasi Kota: $nama</div>";
    }
}

// 4. Migrasi Ranting (Unit/UKM)
$res = $conn->query("SELECT id, nama_ranting FROM ranting");
while ($row = $res->fetch_assoc()) {
    $nama = $row['nama_ranting'];
    $success = createOrUpdateUser($conn, [
        'username' => $nama,
        'password' => $nama . '1955',
        'nama_lengkap' => "Pengurus Unit/Ranting $nama",
        'role' => 'unit',
        'ranting_id' => $row['id']
    ]);
    if ($success) {
        $stats['ranting']++;
        echo "<div class='log-entry'><span class='success'>[SUCCESS]</span> User Ranting: $nama</div>";
    } else {
        $stats['errors']++;
        echo "<div class='log-entry'><span class='error'>[ERROR]</span> Gagal migrasi Ranting: $nama</div>";
    }
}

// 5. Migrasi Anggota
$res = $conn->query("SELECT id, no_anggota, nama_lengkap, tanggal_lahir FROM anggota");
while ($row = $res->fetch_assoc()) {
    $no_anggota = $row['no_anggota'];
    $nama = $row['nama_lengkap'];
    $tgl_lahir = $row['tanggal_lahir'];
    
    // Skip jika no_anggota kosong
    if (empty($no_anggota)) {
        echo "<div class='log-entry'><span class='info'>[SKIP]</span> Anggota $nama dilewati karena No Anggota kosong.</div>";
        continue;
    }
    
    $pwd_tgl = (!empty($tgl_lahir) && $tgl_lahir !== '0000-00-00') ? date('dmY', strtotime($tgl_lahir)) : '';
    
    $success = createOrUpdateUser($conn, [
        'username' => $no_anggota,
        'password' => formatPwd($nama) . $pwd_tgl,
        'nama_lengkap' => $nama,
        'role' => 'anggota',
        'no_anggota' => $no_anggota
    ]);
    
    if ($success) {
        $stats['anggota']++;
        echo "<div class='log-entry'><span class='success'>[SUCCESS]</span> User Anggota: $no_anggota ($nama)</div>";
    } else {
        $stats['errors']++;
        echo "<div class='log-entry'><span class='error'>[ERROR]</span> Gagal migrasi Anggota: $nama</div>";
    }
}

echo "</div>"; // End log-container

echo "<h2>📊 Ringkasan Migrasi</h2>";
echo "<div class='stats-grid'>
    <div class='stat-card'><h3>Negara</h3><p>{$stats['negara']}</p></div>
    <div class='stat-card'><h3>Provinsi</h3><p>{$stats['provinsi']}</p></div>
    <div class='stat-card'><h3>Kota</h3><p>{$stats['kota']}</p></div>
    <div class='stat-card'><h3>Ranting</h3><p>{$stats['ranting']}</p></div>
    <div class='stat-card'><h3>Anggota</h3><p>{$stats['anggota']}</p></div>
    <div class='stat-card' style='border-left-color: #f44747;'><h3>Error</h3><p>{$stats['errors']}</p></div>
</div>";

echo "<p>Migrasi selesai! Silakan periksa log di atas jika ada error.</p>";
echo "<a href='../pages/admin/user_management.php' class='btn'>Kembali ke Manajemen User</a>";

echo "</div></body></html>";
