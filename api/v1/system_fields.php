<?php
// /api/v1/system_fields.php

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
        // --- READ (Get all configured system fields) ---
        case 'GET':
            // SCOPED: Filter by academy_id
            $stmt = $pdo->prepare("SELECT * FROM system_field_config WHERE academy_id = ?");
            $stmt->execute([$academy_id]);
            $config = $stmt->fetchAll();
            
            // Return an associative array keyed by field_key for easy lookup in JS
            $response = [];
            foreach ($config as $field) {
                $response[$field['field_key']] = $field;
            }
            echo json_encode($response);
            break;

        // --- CREATE/UPDATE (UPSERT - Save Configuration) ---
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['field_key'])) {
                 http_response_code(400); 
                 echo json_encode(['error' => 'Field Key is required']);
                 exit;
            }
            
            // Check if the field already exists for THIS academy (SCOPED)
            $check_stmt = $pdo->prepare("SELECT id FROM system_field_config WHERE field_key = ? AND academy_id = ?");
            $check_stmt->execute([$data['field_key'], $academy_id]);
            $existing_id = $check_stmt->fetchColumn();

            $is_required = (bool)($data['is_required'] ?? false);
            $is_score_field = (bool)($data['is_score_field'] ?? false);

            $scoring_rules = $data['scoring_rules'] ?? null;
            $display_name = $data['field_name'] ?? null;

            if ($existing_id) {
                // UPDATE (SCOPED)
                $sql = "UPDATE system_field_config SET 
                            display_name = ?, 
                            is_required = ?, 
                            is_score_field = ?, 
                            scoring_rules = ?
                        WHERE id = ? AND academy_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $display_name,
                    $is_required,
                    $is_score_field,
                    $scoring_rules,
                    $existing_id,
                    $academy_id
                ]);
            } else {
                // INSERT (Add academy_id)
                $sql = "INSERT INTO system_field_config 
                            (field_key, display_name, is_required, is_score_field, scoring_rules, academy_id) 
                        VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $data['field_key'],
                    $display_name,
                    $is_required,
                    $is_score_field,
                    $scoring_rules,
                    $academy_id
                ]);
            }
            
            echo json_encode(['message' => 'System field config saved']);
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