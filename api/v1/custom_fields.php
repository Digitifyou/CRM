<?php
// /api/v1/custom_fields.php

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php'; // $pdo connection

$method = $_SERVER['REQUEST_METHOD'];

// --- MULTI-TENANCY CHECK ---
if (!defined('ACADEMY_ID') || ACADEMY_ID === 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden: No Academy Context.']);
    exit;
}
$academy_id = ACADEMY_ID;
// ---------------------------

try {
    switch ($method) {
        // --- READ (Get all custom fields) ---
        case 'GET':
            // SCOPED: Filter by academy_id
            $stmt = $pdo->prepare("SELECT * FROM custom_fields WHERE academy_id = ? ORDER BY field_id ASC");
            $stmt->execute([$academy_id]);
            $fields = $stmt->fetchAll();
            echo json_encode($fields);
            break;

        // --- CREATE A NEW CUSTOM FIELD ---
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['field_name']) || empty($data['field_key']) || empty($data['field_type'])) {
                 http_response_code(400); 
                 echo json_encode(['error' => 'Name, Key, and Type are required']);
                 exit;
            }
            
            $field_key = strtolower(preg_replace('/[^A-Za-z0-9\_]/', '_', $data['field_key']));
            
            $options_json = null;
            if ($data['field_type'] === 'select' && !empty($data['options'])) {
                $options_array = array_map('trim', explode(',', $data['options']));
                $options_json = json_encode($options_array);
            }

            $scoring_rules_json = $data['scoring_rules'] ?? null;
            
            // SCOPED: Insert with academy_id
            $sql = "INSERT INTO custom_fields (field_name, field_key, field_type, options, is_required, is_score_field, scoring_rules, academy_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $data['field_name'],
                $field_key,
                $data['field_type'],
                $options_json,
                (bool)($data['is_required'] ?? false),
                (bool)($data['is_score_field'] ?? false),
                $scoring_rules_json,
                $academy_id
            ]);
            
            http_response_code(201); // Created
            echo json_encode(['message' => 'Custom field created', 'field_id' => $pdo->lastInsertId()]);
            break;

        // --- UPDATE AN EXISTING CUSTOM FIELD ---
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['field_id']) || empty($data['field_name']) || empty($data['field_type'])) {
                 http_response_code(400); 
                 echo json_encode(['error' => 'Field ID, Name, and Type are required for update']);
                 exit;
            }

            // Options handling (logic remains the same)
            $options_json = null;
            if ($data['field_type'] === 'select' && !empty($data['options'])) {
                $options_array = array_map('trim', explode(',', $data['options']));
                $options_json = json_encode($options_array);
            } else if ($data['field_type'] !== 'select') {
                $options_json = null;
            } else if (isset($data['options_null'])) {
                 $options_json = null;
            } else {
                 $stmt = $pdo->prepare("SELECT options FROM custom_fields WHERE field_id = ?");
                 $stmt->execute([$data['field_id']]);
                 $options_json = $stmt->fetchColumn();
            }

            $scoring_rules_json = $data['scoring_rules'] ?? null;

            // SCOPED: Update check
            $sql = "UPDATE custom_fields SET 
                        field_name = ?, 
                        field_type = ?, 
                        options = ?, 
                        is_required = ?, 
                        is_score_field = ?,
                        scoring_rules = ?
                    WHERE field_id = ? AND academy_id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $data['field_name'],
                $data['field_type'],
                $options_json,
                (bool)($data['is_required'] ?? false),
                (bool)($data['is_score_field'] ?? false),
                $scoring_rules_json,
                (int)$data['field_id'],
                $academy_id
            ]);
            
            echo json_encode(['message' => 'Custom field updated']);
            break;

        // --- DELETE A CUSTOM FIELD ---
        case 'DELETE':
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Field ID is required']);
                exit;
            }
            
            $id = $_GET['id'];
            
            // SCOPED: Delete check
            $stmt = $pdo->prepare("DELETE FROM custom_fields WHERE field_id = ? AND academy_id = ?");
            $stmt->execute([$id, $academy_id]);
            
            echo json_encode(['message' => 'Custom field deleted']);
            break;

        default:
            http_response_code(405); // Method Not Allowed
            echo json_encode(['error' => 'Method Not Allowed']);
            break;
    }

} catch (\PDOException $e) {
    if ($e->getCode() == 23000) { 
        http_response_code(409); // Conflict
        echo json_encode(['error' => 'Field key already exists. Please choose a unique name.']);
        exit;
    }
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>