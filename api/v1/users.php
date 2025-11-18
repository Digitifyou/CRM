<?php
// /api/v1/users.php

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php'; // $pdo connection

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        // --- READ (Get all users) ---
        case 'GET':
            // Only fetch active users for management list
            $stmt = $pdo->query("
                SELECT 
                    user_id, 
                    username, 
                    full_name, 
                    role, 
                    is_active, 
                    created_at 
                FROM users
                ORDER BY full_name ASC
            ");
            $users = $stmt->fetchAll();
            echo json_encode($users);
            break;

        // --- CREATE A NEW USER (Simplified placeholder for invitation) ---
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['username']) || empty($data['full_name']) || empty($data['role']) || empty($data['password'])) {
                 http_response_code(400); // Bad Request
                 echo json_encode(['error' => 'Username, Full Name, Role, and Password are required']);
                 exit;
            }
            
            // Hash the password for security
            $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);

            $sql = "INSERT INTO users (username, password_hash, full_name, role) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $data['username'],
                $password_hash,
                $data['full_name'],
                $data['role']
            ]);
            
            http_response_code(201); // Created
            echo json_encode(['message' => 'User invited', 'user_id' => $pdo->lastInsertId()]);
            break;

        // --- UPDATE USER (e.g., Change Role or Active Status) ---
        case 'PUT':
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'User ID is required']);
                exit;
            }
            
            $id = $_GET['id'];
            $data = json_decode(file_get_contents('php://input'), true);
            $updates = [];
            $params = [];

            if (isset($data['role'])) {
                $updates[] = 'role = ?';
                $params[] = $data['role'];
            }
            if (isset($data['is_active'])) {
                $updates[] = 'is_active = ?';
                $params[] = (bool)$data['is_active'];
            }
            // Add other updatable fields as needed (e.g., full_name)

            if (empty($updates)) {
                http_response_code(400);
                echo json_encode(['error' => 'No valid fields provided for update']);
                exit;
            }

            $sql = "UPDATE users SET " . implode(', ', $updates) . " WHERE user_id = ?";
            $params[] = $id;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode(['message' => 'User updated']);
            break;

        // --- DELETE USER (Disable/Deactivate) ---
        case 'DELETE':
            // For security and historical data integrity, we prefer deactivation over deletion.
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'User ID is required']);
                exit;
            }
            
            $id = $_GET['id'];
            
            // Deactivate the user by setting is_active to FALSE
            $stmt = $pdo->prepare("UPDATE users SET is_active = FALSE WHERE user_id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(['message' => 'User deactivated']);
            break;

        default:
            http_response_code(405); // Method Not Allowed
            echo json_encode(['error' => 'Method Not Allowed']);
            break;
    }

} catch (\PDOException $e) {
    // Check for duplicate username error
    if ($e->getCode() == 23000) { 
        http_response_code(409); // Conflict
        echo json_encode(['error' => 'Username already exists.']);
        exit;
    }
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>