<?php
// /api/v1/pipeline.php

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php'; // $pdo connection

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        // --- READ (Get all pipeline stages) ---
        case 'GET':
            // Get all pipeline stages, ordered by their 'stage_order'
            $stmt = $pdo->query("
                SELECT stage_id, stage_name, stage_order 
                FROM pipeline_stages 
                ORDER BY stage_order ASC
            ");
            $stages = $stmt->fetchAll();
            echo json_encode($stages);
            break;

        // --- CREATE A NEW STAGE ---
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['stage_name'])) {
                 http_response_code(400);
                 echo json_encode(['error' => 'Stage Name is required']);
                 exit;
            }
            
            // Find the highest existing order and add 1
            $order_stmt = $pdo->query("SELECT MAX(stage_order) FROM pipeline_stages");
            $new_order = $order_stmt->fetchColumn() + 1;

            $sql = "INSERT INTO pipeline_stages (stage_name, stage_order) VALUES (?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $data['stage_name'],
                $new_order
            ]);
            
            http_response_code(201); // Created
            echo json_encode(['message' => 'Pipeline stage created', 'stage_id' => $pdo->lastInsertId()]);
            break;

        // --- UPDATE STAGE NAME or ORDER ---
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['stage_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Stage ID is required for update']);
                exit;
            }

            if (isset($data['stage_name'])) {
                // Update stage name
                $sql = "UPDATE pipeline_stages SET stage_name = ? WHERE stage_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$data['stage_name'], (int)$data['stage_id']]);

            } else if (isset($data['stage_order'])) {
                // Update stage order (used for drag-and-drop reordering)
                $sql = "UPDATE pipeline_stages SET stage_order = ? WHERE stage_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([(int)$data['stage_order'], (int)$data['stage_id']]);
            
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'No valid field to update (stage_name or stage_order) provided']);
                exit;
            }

            echo json_encode(['message' => 'Pipeline stage updated']);
            break;

        // --- DELETE A STAGE ---
        case 'DELETE':
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Stage ID is required']);
                exit;
            }
            
            $id = $_GET['id'];

            // CRITICAL: Check if any enrollments are linked to this stage
            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM enrollments WHERE pipeline_stage_id = ?");
            $check_stmt->execute([$id]);
            if ($check_stmt->fetchColumn() > 0) {
                http_response_code(409); // Conflict
                echo json_encode(['error' => 'Cannot delete stage. It is currently linked to one or more open enrollments. Move them first.']);
                exit;
            }
            
            $stmt = $pdo->prepare("DELETE FROM pipeline_stages WHERE stage_id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(['message' => 'Pipeline stage deleted']);
            break;

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