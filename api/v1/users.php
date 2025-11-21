<?php
// /api/v1/users.php

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
        // --- READ (Get all or self) ---
        case 'GET':
            $is_self_request = isset($_GET['self']) && $_GET['self'] === 'true';

            if ($is_self_request) {
                 $user_id = $_SESSION['user_id'] ?? 0;
                 if ($user_id === 0) {
                      http_response_code(403);
                      echo json_encode(['error' => 'Not logged in.']);
                      exit;
                 }
                 
                 // Fetch single user by ID, joining academy_config for details (SCOPED)
                 $stmt = $pdo->prepare("
                     SELECT 
                         u.user_id, u.username, u.full_name, u.role, u.is_active, u.created_at, u.academy_id,
                         ac.academy_name, ac.academy_phone, ac.academy_website
                     FROM users u
                     JOIN academy_config ac ON u.academy_id = ac.academy_id
                     WHERE u.user_id = ? AND u.academy_id = ?
                 ");
                 $stmt->execute([$user_id, $academy_id]);
                 $user = $stmt->fetch();
                 
                 if (!$user) {
                      http_response_code(404);
                      echo json_encode(['error' => 'User or Academy config not found.']);
                      exit;
                 }
                 echo json_encode($user);
                 break;
             }
            
            // Default: List all users (SCOPED)
            $stmt = $pdo->prepare("
                SELECT 
                    user_id, 
                    username, 
                    full_name, 
                    role, 
                    is_active, 
                    created_at 
                FROM users
                WHERE academy_id = ?
                ORDER BY full_name ASC
            ");
            $stmt->execute([$academy_id]);
            $users = $stmt->fetchAll();
            echo json_encode($users);
            break;

        // --- CREATE A NEW USER (Invite) ---
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['username']) || empty($data['full_name']) || empty($data['role']) || empty($data['password'])) {
                 http_response_code(400); // Bad Request
                 echo json_encode(['error' => 'Username, Full Name, Role, and Password are required']);
                 exit;
            }
            
            // Hash the password for security
            $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);

            // SCOPED: Insert with academy_id
            $sql = "INSERT INTO users (username, password_hash, full_name, role, academy_id) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $data['username'],
                $password_hash,
                $data['full_name'],
                $data['role'],
                $academy_id
            ]);
            
            http_response_code(201); // Created
            echo json_encode(['message' => 'User invited', 'user_id' => $pdo->lastInsertId()]);
            break;

        // --- UPDATE USER (Admin update by ID or Self-update) ---
        case 'PUT':
            $is_self_update = isset($_GET['self']) && $_GET['self'] === 'true';
            $update_id = $is_self_update ? ($_SESSION['user_id'] ?? 0) : ($_GET['id'] ?? null);

            if (!$update_id) {
                http_response_code(400);
                echo json_encode(['error' => 'User ID is required']);
                exit;
            }
            
            $id = $update_id;
            $data = json_decode(file_get_contents('php://input'), true);
            $updates = [];
            $params = [];

            // Admin-only fields (Role, is_active) - ignore if self-update
            if (isset($data['role']) && !$is_self_update) {
                $updates[] = 'role = ?';
                $params[] = $data['role'];
            }
            if (isset($data['is_active']) && !$is_self_update) {
                $updates[] = 'is_active = ?';
                $params[] = (bool)$data['is_active'];
            }
            
            // Fields allowed for self-update and admin-update
            if (isset($data['full_name'])) {
                $updates[] = 'full_name = ?';
                $params[] = $data['full_name'];
            }
            
            // Password change logic
            if (isset($data['new_password']) && !empty($data['new_password'])) {
                $password_hash = password_hash($data['new_password'], PASSWORD_DEFAULT);
                $updates[] = 'password_hash = ?';
                $params[] = $password_hash;
            }

            if (empty($updates)) {
                http_response_code(400);
                echo json_encode(['error' => 'No valid fields provided for update']);
                exit;
            }

            $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE user_id = ? AND academy_id = ?";
            $params[] = $id;
            $params[] = $academy_id;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode(['message' => 'User updated']);
            break;

        // --- DELETE USER (Disable/Deactivate) ---
        case 'DELETE':
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'User ID is required']);
                exit;
            }
            
            $id = $_GET['id'];
            
            // SCOPED: Deactivation check
            $stmt = $pdo->prepare("UPDATE users SET is_active = FALSE WHERE user_id = ? AND academy_id = ?");
            $stmt->execute([$id, $academy_id]);
            
            echo json_encode(['message' => 'User deactivated']);
            break;

        default:
            http_response_code(405); // Method Not Allowed
            echo json_encode(['error' => 'Method Not Allowed']);
            break;
    }

} catch (\PDOException $e) {
    if ($e->getCode() == 23000) { 
        http_response_code(409); // Conflict
        echo json_encode(['error' => 'Username already exists.']);
        exit;
    }
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>