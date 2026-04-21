<?php
include 'config/database.php';
include 'helpers/user_auto_creation.php';

echo "Starting migration...\n";

// 1. Migrate Negara
echo "Migrating Negara...\n";
$res = $conn->query("SELECT * FROM negara");
while ($row = $res->fetch_assoc()) {
    $nama = $row['nama'];
    createOrUpdateUser($conn, [
        'username' => $nama,
        'password' => formatPwd($nama) . '1955',
        'nama_lengkap' => "Pengurus Negara $nama",
        'role' => 'negara',
        'pengurus_id' => $row['id']
    ]);
}

// 2. Migrate Provinsi
echo "Migrating Provinsi...\n";
$res = $conn->query("SELECT * FROM provinsi");
while ($row = $res->fetch_assoc()) {
    $nama = $row['nama'];
    createOrUpdateUser($conn, [
        'username' => $nama,
        'password' => formatPwd($nama) . '1955',
        'nama_lengkap' => "Pengurus Provinsi $nama",
        'role' => 'pengprov',
        'pengurus_id' => $row['id']
    ]);
}

// 3. Migrate Kota
echo "Migrating Kota...\n";
$res = $conn->query("SELECT * FROM kota");
while ($row = $res->fetch_assoc()) {
    $nama = $row['nama'];
    createOrUpdateUser($conn, [
        'username' => $nama,
        'password' => formatPwd($nama) . '1955',
        'nama_lengkap' => "Pengurus Kota / Kabupaten $nama",
        'role' => 'pengkot',
        'pengurus_id' => $row['id']
    ]);
}

// 4. Migrate Ranting
echo "Migrating Ranting...\n";
$res = $conn->query("SELECT * FROM ranting WHERE jenis = 'unit'");
while ($row = $res->fetch_assoc()) {
    $nama = $row['nama_ranting'] ?? $row['nama']; // Try both just in case
    createOrUpdateUser($conn, [
        'username' => $nama,
        'password' => formatPwd($nama) . '1955',
        'nama_lengkap' => "Pengurus Unit/Ranting $nama",
        'role' => 'unit',
        'ranting_id' => $row['id']
    ]);
}

// 5. Migrate Anggota
echo "Migrating Anggota...\n";
$res = $conn->query("SELECT * FROM anggota");
while ($row = $res->fetch_assoc()) {
    $nama = $row['nama_lengkap'];
    $no_anggota = $row['no_anggota'];
    $tgl_lahir = $row['tanggal_lahir'];
    $pwd_tgl = date('dmY', strtotime($tgl_lahir));
    
    createOrUpdateUser($conn, [
        'username' => $no_anggota,
        'password' => formatPwd($nama) . $pwd_tgl,
        'nama_lengkap' => $nama,
        'role' => 'anggota',
        'no_anggota' => $no_anggota
    ]);
}

echo "Migration finished.\n";
