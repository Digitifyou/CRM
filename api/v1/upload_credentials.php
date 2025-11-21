<?php
// /api/v1/upload_credentials.php

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php'; 

// 1. Security: Auth & Role Check
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!in_array($_SESSION['role'], ['admin', 'owner'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden: Only Admins can update credentials.']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

try {
    // 2. Check File Upload
    if (!isset($_FILES['credentials_file']) || $_FILES['credentials_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('No file uploaded or upload error occurred.');
    }

    $fileTmpPath = $_FILES['credentials_file']['tmp_name'];
    $fileType = $_FILES['credentials_file']['type'];
    $fileSize = $_FILES['credentials_file']['size'];

    // 3. Validate JSON Content
    $jsonContent = file_get_contents($fileTmpPath);
    $data = json_decode($jsonContent, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON file. Please upload the correct key file from Google Cloud.');
    }

    // Verify key fields exist to ensure it's the right kind of JSON
    if (!isset($data['type']) || $data['type'] !== 'service_account' || !isset($data['private_key'])) {
        throw new Exception('Invalid Service Account Key. Missing required fields.');
    }

    // 4. Save File Securely
    // We save it one level up in /api/ folder, so it's not directly accessible via /api/v1/ URL if configured correctly,
    // or at least separated from public assets.
    $destination = __DIR__ . '/../credentials.json';

    if (move_uploaded_file($fileTmpPath, $destination)) {
        echo json_encode(['message' => 'Google Credentials uploaded and saved successfully.']);
    } else {
        throw new Exception('Failed to save file to server. Check folder permissions.');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>