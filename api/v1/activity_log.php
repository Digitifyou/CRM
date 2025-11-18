<?php
// /api/v1/activity_log.php

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php'; // $pdo connection

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        // --- READ (Get all activities for one student) ---
        case 'GET':
            if (!isset($_GET['student_id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Student ID is required']);
                exit;
            }

            $student_id = $_GET['student_id'];
            
            // We join with users to show who logged the activity
            $stmt = $pdo->prepare("
                SELECT 
                    a.*, 
                    u.full_name AS user_name 
                FROM activity_log a
                LEFT JOIN users u ON a.user_id = u.user_id
                WHERE a.student_id = ?
                ORDER BY a.timestamp DESC
            ");
            $stmt->execute([$student_id]);
            $activities = $stmt->fetchAll();
            
            echo json_encode($activities);
            break;

        // --- CREATE A NEW ACTIVITY ---
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);

            // Basic validation
            if (empty($data['student_id']) || empty($data['activity_type']) || empty($data['content'])) {
                 http_response_code(400); // Bad Request
                 echo json_encode(['error' => 'Student ID, Type, and Content are required']);
                 exit;
            }
            
            // Placeholder: Use the logged-in user ID from session
            $user_id = $_SESSION['user_id'] ?? 1; 

            $sql = "INSERT INTO activity_log 
                        (student_id, user_id, activity_type, content) 
                    VALUES 
                        (?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                (int)$data['student_id'],
                $user_id,
                $data['activity_type'],
                $data['content']
            ]);
            
            http_response_code(201); // Created
            echo json_encode(['message' => 'Activity logged successfully', 'log_id' => $pdo->lastInsertId()]);
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