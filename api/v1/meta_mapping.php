<?php
// /api/v1/meta_mapping.php
// Handles CRUD for saving Meta Form Field mappings to CRM fields.

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php'; 

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
        // --- READ (Get all saved mappings) ---
        case 'GET':
            // SCOPED: Filter by academy_id
            $stmt = $pdo->prepare("SELECT meta_field_name, crm_field_key, is_built_in FROM meta_field_mapping WHERE academy_id = ?");
            $stmt->execute([$academy_id]);
            $mappings = $stmt->fetchAll();
            
            // Reformat as an associative array for easier JS use
            $response = [];
            foreach ($mappings as $map) {
                $response[$map['meta_field_name']] = [
                    'crm_field_key' => $map['crm_field_key'],
                    'is_built_in' => (bool)$map['is_built_in']
                ];
            }
            echo json_encode($response);
            break;

        // --- CREATE/UPDATE (Save all mapping rules) ---
        case 'POST':
            $mappingData = json_decode(file_get_contents('php://input'), true);

            if (empty($mappingData)) {
                 http_response_code(400); 
                 echo json_encode(['error' => 'No mapping data provided.']);
                 exit;
            }

            $pdo->beginTransaction();

            // SCOPED: Delete only existing mappings for THIS academy
            $pdo->prepare("DELETE FROM meta_field_mapping WHERE academy_id = ?")->execute([$academy_id]); 

            $sql = "INSERT INTO meta_field_mapping (meta_field_name, crm_field_key, is_built_in, academy_id) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);

            foreach ($mappingData as $metaField => $crmFieldConfig) {
                if (!empty($crmFieldConfig['crm_field_key'])) {
                    $stmt->execute([
                        $metaField,
                        $crmFieldConfig['crm_field_key'],
                        (bool)$crmFieldConfig['is_built_in'],
                        $academy_id // SCOPED: Insert with academy_id
                    ]);
                }
            }

            $pdo->commit();
            http_response_code(200);
            echo json_encode(['message' => 'Meta field mapping saved successfully.']);
            break;

        default:
            http_response_code(405); 
            echo json_encode(['error' => 'Method Not Allowed']);
            break;
    }

} catch (\PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>