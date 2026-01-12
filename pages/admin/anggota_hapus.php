<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../../login.php");
    exit();
}

include '../../config/database.php';

$id = (int)$_GET['id'];

$result = $conn->query("SELECT nama_lengkap FROM anggota WHERE id = $id");
if ($result->num_rows == 0) {
    die("Anggota tidak ditemukan!");
}

$conn->query("DELETE FROM anggota WHERE id = $id");

header("Location: anggota.php?msg=deleted");
exit();
?>