<?php
$dir = 'c:/xampp/htdocs/beladiri_app/pages/admin/';
$files = glob($dir . '*.php');

foreach ($files as $file) {
    $content = file_get_contents($file);
    
    // Pattern to look for:
    // new PermissionManager(
    //     $conn,
    //     $_SESSION['user_id'],
    //     $_SESSION['role'],
    //     $_SESSION['pengurus_id'] ?? null,
    //     $_SESSION['ranting_id'] ?? null
    // );
    
    $new_content = preg_replace(
        '/new\s+PermissionManager\s*\(\s*\$conn\s*,\s*\$_SESSION\[\'user_id\'\]\s*,\s*\$_SESSION\[\'role\'\]\s*,\s*\$_SESSION\[\'pengurus_id\'\]\s+\?\?\s+null\s*,\s*\$_SESSION\[\'ranting_id\'\]\s+\?\?\s+null\s*\)/s',
        'new PermissionManager($conn, $_SESSION[\'user_id\'], $_SESSION[\'role\'], $_SESSION[\'pengurus_id\'] ?? null, $_SESSION[\'ranting_id\'] ?? null, $_SESSION[\'no_anggota\'] ?? null)',
        $content
    );

    if ($new_content !== $content) {
        file_put_contents($file, $new_content);
        echo "Updated $file\n";
    }
}
