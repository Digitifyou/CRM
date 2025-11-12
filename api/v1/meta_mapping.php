<?php
// /api/v1/meta_mapping.php
// Handles CRUD for saving Meta Form Field mappings to CRM fields.

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php'; 

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        // --- READ (Get all saved mappings) ---
        case 'GET':
            $stmt = $pdo->query("SELECT meta_field_name, crm_field_key, is_built_in FROM meta_field_mapping");
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

            // Clear all existing mappings first (simplified bulk update method)
            $pdo->exec("TRUNCATE TABLE meta_field_mapping"); 

            $sql = "INSERT INTO meta_field_mapping (meta_field_name, crm_field_key, is_built_in) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql);

            foreach ($mappingData as $metaField => $crmFieldConfig) {
                // Ensure a target key exists before inserting (user might have selected "-- Ignore")
                if (!empty($crmFieldConfig['crm_field_key'])) {
                    $stmt->execute([
                        $metaField,
                        $crmFieldConfig['crm_field_key'],
                        (bool)$crmFieldConfig['is_built_in']
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