<?php
// /config.php - CRITICAL FIX

// --- DATABASE CONNECTION PARAMETERS ---
$host = 'localhost';
$db   = 'crm_academy';
$user = 'root';    
$pass = ''; 
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

// --- META API CREDENTIALS (NEW) ---
// Your credentials MUST be defined here.
define('META_APP_ID', '1170946974995892');
define('META_APP_SECRET', '377431f42d7f0e4ba17dadbe867f329b');
// This is the Facebook Page ID that your Lead Forms are tied to (required for Graph API calls)
define('META_PAGE_ID', 'YOUR_FACEBOOK_PAGE_ID'); 
?>