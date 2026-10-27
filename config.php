<?php
// ====== Renewal Tracker — Config ======
// When installing on a server, normally install.php writes these for you.
return [
    'db' => [
        'host' => 'localhost',
        'name' => 'YOUR_DB_NAME',       // <-- Database name created in cPanel
        'user' => 'YOUR_DB_USER',       // <-- Database user
        'pass' => 'YOUR_DB_PASSWORD',   // <-- Database password
    ],
    'app_name' => 'Renewal Tracker',
    'timezone' => 'Asia/Dhaka',
    // For local testing, config.local.php (if present) overrides the values above
    'local_override' => __DIR__ . '/config.local.php',
];
