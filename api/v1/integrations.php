<?php
// /api/v1/integrations.php

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php'; // $pdo connection

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        // --- READ (Get all integrations) ---
        case 'GET':
            $stmt = $pdo->query("SELECT * FROM integrations");
            $integrations = $stmt->fetchAll();
            echo json_encode($integrations);
            break;

        // --- CREATE OR UPDATE (Upsert) ---
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['platform'])) {
                 http_response_code(400); 
                 echo json_encode(['error' => 'Platform is required']);
                 exit;
            }
            
            // Check if this platform already exists to decide whether to INSERT or UPDATE
            $check_stmt = $pdo->prepare("SELECT integration_id FROM integrations WHERE platform = ?");
            $check_stmt->execute([$data['platform']]);
            $existing_id = $check_stmt->fetchColumn();

            if ($existing_id) {
                // UPDATE existing record
                $sql = "UPDATE integrations SET api_key = ?, app_secret = ?, form_id = ?, is_active = ? WHERE integration_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $data['api_key'] ?? null,
                    $data['app_secret'] ?? null,
                    $data['form_id'] ?? null,
                    (bool)($data['is_active'] ?? true),
                    $existing_id
                ]);
                http_response_code(200);
                echo json_encode(['message' => $data['platform'] . ' integration updated']);

            } else {
                // INSERT new record
                $sql = "INSERT INTO integrations (platform, api_key, app_secret, form_id, is_active) VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $data['platform'],
                    $data['api_key'] ?? null,
                    $data['app_secret'] ?? null,
                    $data['form_id'] ?? null,
                    (bool)($data['is_active'] ?? true)
                ]);
                http_response_code(201); // Created
                echo json_encode(['message' => $data['platform'] . ' integration created', 'integration_id' => $pdo->lastInsertId()]);
            }
            break;
            
        // DELETE is not typically needed for integrations; deactivation is preferred (handled by POST/is_active)

        default:
            http_response_code(405); // Method Not Allowed
            echo json_encode(['error' => 'Method Not Allowed']);
            break;
    }

} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>