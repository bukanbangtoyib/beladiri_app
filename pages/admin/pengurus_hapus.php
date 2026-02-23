<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../../login.php");
    exit();
}

include '../../config/database.php';

$id = (int)$_GET['id'];
$jenis = $_GET['jenis'] ?? 'pusat';

if (!in_array($jenis, ['pusat', 'provinsi', 'kota'])) {
    $jenis = 'pusat';
}

// Map jenis to table names
$table_map = [
    'pusat' => 'negara',
    'provinsi' => 'provinsi',
    'kota' => 'kota'
];

$table = $table_map[$jenis];

// Delete from new tables
$conn->query("DELETE FROM $table WHERE id = $id");

header("Location: pengurus_list.php?jenis=$jenis");
exit();
?>
