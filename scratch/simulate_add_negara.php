<?php
include 'config/database.php';

// Simulate manage_negara.php add action
$_POST['action'] = 'add';
$_POST['nama'] = 'Test Negara 2 ' . time();
$_POST['kode'] = 'T2';

// Include the API file
// Note: we need to bypass session check or mock it
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'superadmin';

include 'api/manage_negara.php';
