<?php
// Definisi role dan permission
$ROLES_CONFIG = [
    'admin' => [
        'label' => 'Administrator',
        'description' => 'Full access to all features',
        'permissions' => [
            'view_dashboard' => true,
            'anggota_create' => true,
            'anggota_read' => true,
            'anggota_update' => true,
            'anggota_delete' => true,
            'anggota_import' => true,
            'anggota_export' => true,
            'ukt_create' => true,
            'ukt_read' => true,
            'ukt_update' => true,
            'ukt_delete' => true,
            'kerohanian_create' => true,
            'kerohanian_read' => true,
            'kerohanian_update' => true,
            'kerohanian_delete' => true,
            'pengurus_create' => true,
            'pengurus_read' => true,
            'pengurus_update' => true,
            'pengurus_delete' => true,
            'ranting_create' => true,
            'ranting_read' => true,
            'ranting_update' => true,
            'ranting_delete' => true,
            'jadwal_create' => true,
            'jadwal_read' => true,
            'jadwal_update' => true,
            'jadwal_delete' => true,
            'user_manage' => true,
            'settings' => true,
        ]
    ],
    
    'pengprov' => [
        'label' => 'Pengurus Provinsi',
        'description' => 'Manage provincial level data',
        'permissions' => [
            'view_dashboard' => true,
            'anggota_create' => 'own_hierarchy',  // Bisa create di struktur bawahnya saja
            'anggota_read' => 'all',              // Bisa lihat semua tapi edit hanya own hierarchy
            'anggota_update' => 'own_hierarchy',
            'anggota_delete' => 'own_hierarchy',
            'anggota_import' => 'own_hierarchy',
            'anggota_export' => 'all',
            'ukt_create' => 'own_hierarchy',
            'ukt_read' => 'all',
            'ukt_update' => 'own_hierarchy',
            'ukt_delete' => 'own_hierarchy',
            'kerohanian_create' => 'own_hierarchy',
            'kerohanian_read' => 'own_hierarchy',
            'kerohanian_update' => 'own_hierarchy',
            'kerohanian_delete' => 'own_hierarchy',
            'pengurus_read' => 'all',
            'pengurus_update' => 'own',           // Hanya bisa update dirinya sendiri
            'ranting_create' => 'own_hierarchy',
            'ranting_read' => 'all',
            'ranting_update' => 'own_hierarchy',
            'ranting_delete' => 'own_hierarchy',
            'jadwal_create' => 'own_hierarchy',
            'jadwal_read' => 'all',
            'jadwal_update' => 'own_hierarchy',
            'jadwal_delete' => 'own_hierarchy',
        ]
    ],
    
    'pengkot' => [
        'label' => 'Pengurus Kota/Kabupaten',
        'description' => 'Manage city level data',
        'permissions' => [
            'view_dashboard' => true,
            'anggota_create' => 'own_hierarchy',
            'anggota_read' => 'own_hierarchy_plus',  // Own hierarchy + same province
            'anggota_update' => 'own_hierarchy',
            'anggota_delete' => 'own_hierarchy',
            'anggota_import' => 'own_hierarchy',
            'anggota_export' => 'own_hierarchy_plus',
            'ukt_create' => 'own_hierarchy',
            'ukt_read' => 'own_hierarchy_plus',
            'ukt_update' => 'own_hierarchy',
            'ukt_delete' => 'own_hierarchy',
            'kerohanian_create' => 'own_hierarchy',
            'kerohanian_read' => 'own_hierarchy',
            'kerohanian_update' => 'own_hierarchy',
            'kerohanian_delete' => 'own_hierarchy',
            'pengurus_read' => 'own_hierarchy_plus',
            'pengurus_update' => 'own',
            'ranting_create' => 'own_hierarchy',
            'ranting_read' => 'own_hierarchy_plus',
            'ranting_update' => 'own_hierarchy',
            'ranting_delete' => 'own_hierarchy',
            'jadwal_create' => 'own_hierarchy',
            'jadwal_read' => 'own_hierarchy_plus',
            'jadwal_update' => 'own_hierarchy',
            'jadwal_delete' => 'own_hierarchy',
        ]
    ],
    
    'unit' => [
        'label' => 'Unit / Ranting',
        'description' => 'Manage unit/ranting level data',
        'permissions' => [
            'view_dashboard' => true,
            'anggota_create' => 'own',
            'anggota_read' => 'all',              // Bisa lihat semua anggota
            'anggota_update' => 'own',            // Edit hanya anggota di unit mereka
            'anggota_delete' => 'own',
            'anggota_import' => 'own',
            'anggota_export' => 'all',
            'ukt_read' => 'all',                  // Hanya bisa lihat UKT
            'kerohanian_read' => 'all',           // Hanya bisa lihat kerohanian
            'pengurus_read' => 'all',             // Hanya bisa lihat struktur
            'ranting_read' => 'all',              // Hanya bisa lihat ranting
            'ranting_update' => 'own',            // Bisa update jadwal ranting sendiri
            'jadwal_create' => 'own',
            'jadwal_read' => 'all',
            'jadwal_update' => 'own',
            'jadwal_delete' => 'own',
        ]
    ],
    
    'tamu' => [
        'label' => 'Tamu (Read Only)',
        'description' => 'View only access',
        'permissions' => [
            'view_dashboard' => true,
            'anggota_read' => 'all',
            'ukt_read' => 'all',
            'kerohanian_read' => 'all',
            'pengurus_read' => 'all',
            'ranting_read' => 'all',
            'jadwal_read' => 'all',
        ]
    ]
];

// Permission scope levels
$PERMISSION_SCOPES = [
    'true' => 'Full access',
    'false' => 'No access',
    'own' => 'Own data only (user profile/organization)',
    'own_hierarchy' => 'Own hierarchy (subordinate organizations)',
    'own_hierarchy_plus' => 'Own hierarchy + parent level',
    'all' => 'All data (read only)',
];
?>