<?php
// /api/v1/custom_fields.php

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php'; // $pdo connection

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        // --- READ (Get all custom fields) ---
        case 'GET':
            $stmt = $pdo->query("SELECT * FROM custom_fields ORDER BY field_id ASC");
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
            
            // Normalize field key (convert spaces/special chars to underscores)
            $field_key = strtolower(preg_replace('/[^A-Za-z0-9\_]/', '_', $data['field_key']));
            
            // Convert options array/string to JSON string for storage
            $options_json = null;
            if ($data['field_type'] === 'select' && !empty($data['options'])) {
                $options_array = array_map('trim', explode(',', $data['options']));
                $options_json = json_encode($options_array);
            }

            // Scoring Rules are expected as a JSON string from the client
            $scoring_rules_json = $data['scoring_rules'] ?? null;
            
            $sql = "INSERT INTO custom_fields (field_name, field_key, field_type, options, is_required, is_score_field, scoring_rules) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $data['field_name'],
                $field_key,
                $data['field_type'],
                $options_json,
                (bool)($data['is_required'] ?? false),
                (bool)($data['is_score_field'] ?? false),
                $scoring_rules_json
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

            // Options handling (same as POST)
            $options_json = null;
            if ($data['field_type'] === 'select' && !empty($data['options'])) {
                $options_array = array_map('trim', explode(',', $data['options']));
                $options_json = json_encode($options_array);
            } else if ($data['field_type'] !== 'select') {
                $options_json = null;
            } else if (isset($data['options_null'])) {
                 // Allows clearing options on PUT request (if sent)
                 $options_json = null;
            } else {
                 // Fetch existing options if not explicitly cleared or provided
                 $stmt = $pdo->prepare("SELECT options FROM custom_fields WHERE field_id = ?");
                 $stmt->execute([$data['field_id']]);
                 $options_json = $stmt->fetchColumn();
            }

            // Scoring Rules Update (Expected as JSON string from client)
            $scoring_rules_json = $data['scoring_rules'] ?? null;

            $sql = "UPDATE custom_fields SET 
                        field_name = ?, 
                        field_type = ?, 
                        options = ?, 
                        is_required = ?, 
                        is_score_field = ?,
                        scoring_rules = ?
                    WHERE field_id = ?";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $data['field_name'],
                $data['field_type'],
                $options_json,
                (bool)($data['is_required'] ?? false),
                (bool)($data['is_score_field'] ?? false),
                $scoring_rules_json,
                (int)$data['field_id']
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
            
            $stmt = $pdo->prepare("DELETE FROM custom_fields WHERE field_id = ?");
            $stmt->execute([$id]);
            
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