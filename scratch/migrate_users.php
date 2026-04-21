<?php
include 'config/database.php';

// 1. Add no_anggota column if not exists
$check = $conn->query("SHOW COLUMNS FROM users LIKE 'no_anggota'");
if ($check->num_rows == 0) {
    if ($conn->query("ALTER TABLE users ADD COLUMN no_anggota VARCHAR(50) NULL AFTER ranting_id")) {
        echo "Added no_anggota column to users table.\n";
    } else {
        echo "Error adding column: " . $conn->error . "\n";
    }
} else {
    echo "no_anggota column already exists.\n";
}

// 2. We will handle migration in a separate step after updating roles.
echo "Migration script finished.\n";
