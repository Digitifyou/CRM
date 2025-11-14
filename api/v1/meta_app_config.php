<?php
// /api/v1/meta_app_config.php
// Securely returns required Meta configuration IDs to the frontend.

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php'; 

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

try {
    if (!defined('META_APP_ID')) {
         http_response_code(500);
         echo json_encode(['error' => 'META_APP_ID is not defined in config.php.']);
         exit;
    }
    
    echo json_encode([
        'app_id' => META_APP_ID
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error.']);
}
?>