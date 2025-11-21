<?php
// /api/v1/batches.php

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
        // --- READ (Get all batches, joined with course name) ---
        case 'GET':
            // SCOPED: Filter by academy_id on batches table
            $stmt = $pdo->prepare("
                SELECT 
                    b.batch_id, 
                    b.batch_name, 
                    b.start_date, 
                    b.total_seats, 
                    b.filled_seats, 
                    c.course_name
                FROM batches b
                JOIN courses c ON b.course_id = c.course_id
                WHERE b.academy_id = ?
                ORDER BY b.start_date DESC
            ");
            $stmt->execute([$academy_id]);
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

            // SCOPED: Insert with academy_id
            $sql = "INSERT INTO batches (course_id, batch_name, start_date, total_seats, academy_id) VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                (int)$data['course_id'],
                $data['batch_name'],
                $data['start_date'],
                (int)$data['total_seats'],
                $academy_id
            ]);
            
            http_response_code(201); // Created
            echo json_encode(['message' => 'Batch created', 'batch_id' => $pdo->lastInsertId()]);
            break;

        // --- UPDATE ---
        case 'PUT':
            // SCOPED: Update check
            http_response_code(501); 
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
            
            // SCOPED: Delete check
            $stmt = $pdo->prepare("DELETE FROM batches WHERE batch_id = ? AND academy_id = ?");
            $stmt->execute([$id, $academy_id]);
            
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