<?php
// /api/config.php

// 1. Start Session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 2. Error Reporting (Enable for debugging, Disable in Production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 3. Database Credentials
// DETECT ENVIRONMENT: If running on localhost (127.0.0.1 or ::1), use local creds.
$whitelist = array('127.0.0.1', '::1', 'localhost');

if (in_array($_SERVER['REMOTE_ADDR'], $whitelist)) {
    // LOCALHOST CREDENTIALS
    $host = 'localhost';
    $db   = 'crm_academy';
    $user = 'root';
    $pass = '';
} else {
    // LIVE SERVER CREDENTIALS (Hostinger)
    $host = 'mysql.hostinger.com'; 
    $db   = 'u230344840_crm';
    $user = 'u230344840_flowsystmz';
    $pass = 'Flowsystmz@12'; // Ensure this password is correct!
}

$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Return JSON error so the JS knows what happened
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
    exit;
}

// 4. Academy Config (NEW MULTI-TENANCY LOGIC)
if (isset($_SESSION['academy_id'])) {
    define('ACADEMY_ID', (int)$_SESSION['academy_id']);
} else {
    // If not set, use 0 or handle redirect logic in individual pages
    define('ACADEMY_ID', 0); 
}

// 5. User Role Constant (Owner role is used by the new setup endpoint)
define('ROLE_OWNER', 'owner');

// 6. Meta Config
define('META_APP_ID', '1170946974995892');
define('META_APP_SECRET', '377431f42d7f0e4ba17dadbe867f329b');
?>