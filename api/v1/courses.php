<?php
// /api/v1/courses.php

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php'; 

// --- MULTI-TENANCY CHECK ---
if (!defined('ACADEMY_ID') || ACADEMY_ID === 0) {
    http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit;
}
$academy_id = ACADEMY_ID;

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                // SCOPED
                $stmt = $pdo->prepare("SELECT * FROM courses WHERE course_id = ? AND academy_id = ?");
                $stmt->execute([$_GET['id'], $academy_id]);
                $course = $stmt->fetch();
                echo json_encode($course ?: []);
            } else {
                // SCOPED
                $stmt = $pdo->prepare("SELECT * FROM courses WHERE academy_id = ? ORDER BY course_name ASC");
                $stmt->execute([$academy_id]);
                echo json_encode($stmt->fetchAll());
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['course_name']) || empty($data['standard_fee'])) {
                 http_response_code(400); echo json_encode(['error' => 'Missing fields']); exit;
            }

            // INSERT with academy_id
            $sql = "INSERT INTO courses (course_name, standard_fee, duration, academy_id) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$data['course_name'], $data['standard_fee'], $data['duration'] ?? null, $academy_id]);
            
            http_response_code(201);
            echo json_encode(['message' => 'Course created', 'course_id' => $pdo->lastInsertId()]);
            break;

        case 'PUT':
            if (!isset($_GET['id'])) { http_response_code(400); echo json_encode(['error' => 'ID required']); exit; }
            $data = json_decode(file_get_contents('php://input'), true);

            // SCOPED Update
            $sql = "UPDATE courses SET course_name = ?, standard_fee = ?, duration = ? WHERE course_id = ? AND academy_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$data['course_name'], $data['standard_fee'], $data['duration'], $_GET['id'], $academy_id]);
            
            echo json_encode(['message' => 'Course updated']);
            break;

        case 'DELETE':
            if (!isset($_GET['id'])) { http_response_code(400); echo json_encode(['error' => 'ID required']); exit; }
            
            // SCOPED Delete
            $stmt = $pdo->prepare("DELETE FROM courses WHERE course_id = ? AND academy_id = ?");
            $stmt->execute([$_GET['id'], $academy_id]);
            echo json_encode(['message' => 'Course deleted']);
            break;

        default:
            http_response_code(405); echo json_encode(['error' => 'Method Not Allowed']); break;
    }
} catch (\PDOException $e) {
    http_response_code(500); echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>