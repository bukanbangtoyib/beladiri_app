<?php
// Konfigurasi database
$servername = "localhost";
$username = "root";
$password = ""; // Default XAMPP kosong
$database = "beladiri_db";

// Buat koneksi
$conn = new mysqli($servername, $username, $password, $database);

// Cek koneksi
if ($conn->connect_error) {
    die("Koneksi gagal: " . $conn->connect_error);
}

// Set charset ke UTF-8 untuk mendukung bahasa Indonesia
$conn->set_charset("utf8");
?>