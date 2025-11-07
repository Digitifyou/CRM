<?php
// /api/v1/batches.php

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php'; // $pdo connection

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        // --- READ (Get all batches, joined with course name) ---
        case 'GET':
            $stmt = $pdo->query("
                SELECT 
                    b.batch_id, 
                    b.batch_name, 
                    b.start_date, 
                    b.total_seats, 
                    b.filled_seats, 
                    c.course_name
                FROM batches b
                JOIN courses c ON b.course_id = c.course_id
                ORDER BY b.start_date DESC
            ");
            $batches = $stmt->fetchAll();
            echo json_encode($batches);
            break;

        // --- CREATE A NEW BATCH ---
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['course_id']) || empty($data['batch_name']) || empty($data['total_seats']) || empty($data['start_date'])) {
                 http_response_code(400); 
                 echo json_encode(['error' => 'Course, Name, Total Seats, and Start Date are required']);
                 exit;
            }

            $sql = "INSERT INTO batches (course_id, batch_name, start_date, total_seats) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                (int)$data['course_id'],
                $data['batch_name'],
                $data['start_date'],
                (int)$data['total_seats']
            ]);
            
            http_response_code(201); // Created
            echo json_encode(['message' => 'Batch created', 'batch_id' => $pdo->lastInsertId()]);
            break;

        // --- UPDATE ---
        case 'PUT':
            // Update logic for editing batch details (e.g., total_seats, start_date)
            // Note: filled_seats would be updated automatically by Enrollment logic
            http_response_code(501); // Not Implemented
            echo json_encode(['error' => 'Batch update not yet implemented']);
            break;

        // --- DELETE A BATCH ---
        case 'DELETE':
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Batch ID is required']);
                exit;
            }
            
            $id = $_GET['id'];
            
            // Note: The 'batches' table has ON DELETE CASCADE on 'course_id', 
            // but we should check if any students are enrolled in this batch
            // before allowing deletion (this check is skipped for MVP simplicity).
            
            $stmt = $pdo->prepare("DELETE FROM batches WHERE batch_id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(['message' => 'Batch deleted']);
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