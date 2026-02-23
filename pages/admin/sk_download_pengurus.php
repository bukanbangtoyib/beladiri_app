<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

include '../../config/database.php';

// Validate parameters
if (!isset($_GET['file']) || !isset($_GET['id'])) {
    die("Parameter tidak lengkap!");
}

$filename = basename($_GET['file']); // Security: remove any path traversal
$pengurus_id = (int)$_GET['id'];

// Verify exists in negara/provinsi/kota
$check = $conn->query("SELECT id FROM kota WHERE id = $pengurus_id");
if ($check->num_rows == 0) {
    $check = $conn->query("SELECT id FROM provinsi WHERE id = $pengurus_id");
}
if ($check->num_rows == 0) {
    $check = $conn->query("SELECT id FROM negara WHERE id = $pengurus_id");
}
if ($check->num_rows == 0) {
    die("Data tidak ditemukan!");
}

$upload_dir = '../../uploads/sk_pengurus/';
$file_path = $upload_dir . $filename;

// Security checks
if (!file_exists($file_path) || !is_file($file_path)) {
    die("File tidak ditemukan!");
}

// Verify file is in correct directory (prevent directory traversal)
$real_path = realpath($file_path);
$real_dir = realpath($upload_dir);

if ($real_path === false || strpos($real_path, $real_dir) !== 0) {
    die("File tidak valid!");
}

// Verify file extension is PDF
if (strtolower(pathinfo($file_path, PATHINFO_EXTENSION)) !== 'pdf') {
    die("Hanya file PDF yang dapat didownload!");
}

// Set headers for file download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($file_path));
header('Pragma: public');
header('Cache-Control: public, must-revalidate, max-age=0');

// Read and output file
readfile($file_path);
exit;
?>