<?php
// /api/v1/meta_accounts.php
// Manages the Meta Ad Account configuration (Access Token, Ad Account ID).

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
        
        // --- READ (Get Connection Status) ---
        case 'GET':
            // SCOPED: Filter by academy_id
            $stmt = $pdo->prepare("
                SELECT access_token, ad_account_id, account_name 
                FROM meta_accounts 
                WHERE academy_id = ? AND is_active = TRUE LIMIT 1
            ");
            $stmt->execute([$academy_id]);
            $config = $stmt->fetch();

            if ($config) {
                echo json_encode([
                    'connected' => true,
                    'account_name' => $config['account_name'],
                    'ad_account_id' => $config['ad_account_id'],
                ]);
            } else {
                http_response_code(404);
                echo json_encode(['connected' => false, 'error' => 'No active Meta account found.']);
            }
            break;

        // --- CREATE/UPDATE (Save Token/Account ID) ---
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['access_token']) || empty($data['ad_account_id']) || empty($data['account_name'])) {
                 http_response_code(400); 
                 echo json_encode(['error' => 'Access Token, Ad Account ID, and Name are required.']);
                 exit;
            }
            
            // 1. Check if an existing account exists for THIS academy (SCOPED)
            $check_stmt = $pdo->prepare("SELECT id FROM meta_accounts WHERE academy_id = ? LIMIT 1");
            $check_stmt->execute([$academy_id]);
            $existing_id = $check_stmt->fetchColumn();

            if ($existing_id) {
                // UPDATE (SCOPED)
                $sql = "UPDATE meta_accounts SET access_token = ?, ad_account_id = ?, account_name = ?, is_active = TRUE WHERE id = ? AND academy_id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $data['access_token'],
                    $data['ad_account_id'],
                    $data['account_name'],
                    $existing_id,
                    $academy_id
                ]);
            } else {
                // INSERT (Add academy_id)
                $sql = "INSERT INTO meta_accounts (user_id, access_token, ad_account_id, account_name, academy_id) VALUES (?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $_SESSION['user_id'] ?? 1, // Use current user ID for reference
                    $data['access_token'],
                    $data['ad_account_id'],
                    $data['account_name'],
                    $academy_id
                ]);
            }
            
            http_response_code(200); 
            echo json_encode(['message' => 'Meta Account configured successfully.']);
            break;

        default:
            http_response_code(405); // Method Not Allowed
            echo json_encode(['error' => 'Method Not Allowed']);
            break;
    }

} catch (\PDOException $e) {
    // CRITICAL: Handle 500 error and prevent HTML output
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>