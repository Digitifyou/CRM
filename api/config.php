<?php
// /config.php - CRITICAL FIX

// Database connection parameters
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
     // Handle connection error gracefully
     http_response_code(500);
     // Note: In production, do not expose $e->getMessage() for security
     echo json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]);
     exit;
}
?>