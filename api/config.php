<?php
// /config.php - CRITICAL: Session Management

// --- START SESSION FOR AUTHENTICATION ---
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// --- END SESSION ---

// --- DATABASE CONNECTION PARAMETERS ---
// $host = 'localhost';
// $db   = 'crm_academy';
// $user = 'root';    
// $pass = ''; 
// $charset = 'utf8mb4';

//  --- DATABASE CONNECTION PARAMETERS ---
$host = 'mysql.hostinger.com';
$db   = 'u230344840_crm';
$user = 'u230344840_flowsystmz';    
$pass = 'Flowsystmz@12'; 
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     // Establish the PDO connection and store it in the global $pdo variable
     $pdo = new PDO($dsn, $user, $pass, $options); 
} catch (\PDOException $e) {
     http_response_code(500);
     echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
     exit;
}

// --- META API CREDENTIALS (PRODUCTION READY) ---
define('META_APP_ID', '1170946974995892');
define('META_APP_SECRET', '377431f42d7f0e4ba17dadbe867f329b');
define('META_PAGE_ID', 'YOUR_FACEBOOK_PAGE_ID'); 
?>