<?php
// /api/v1/courses.php

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php'; // $pdo connection

// --- Security Check (Placeholder) ---
// In a real app, you'd check if the user is an admin:
// if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
//     http_response_code(403);
//     echo json_encode(['error' => 'Forbidden']);
//     exit;
// }

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        // --- READ (Get all or one) ---
        case 'GET':
            if (isset($_GET['id'])) {
                // Get single course
                $id = $_GET['id'];
                $stmt = $pdo->prepare("SELECT * FROM courses WHERE course_id = ?");
                $stmt->execute([$id]);
                $course = $stmt->fetch();
                echo json_encode($course);
            } else {
                // Get all courses
                $stmt = $pdo->query("SELECT * FROM courses ORDER BY course_name ASC");
                $courses = $stmt->fetchAll();
                echo json_encode($courses);
            }
            break;

        // --- CREATE ---
        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['course_name']) || empty($data['standard_fee'])) {
                 http_response_code(400); // Bad Request
                 echo json_encode(['error' => 'Course Name and Fee are required']);
                 exit;
            }

            $sql = "INSERT INTO courses (course_name, standard_fee, duration) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $data['course_name'],
                $data['standard_fee'],
                $data['duration'] ?? null
            ]);
            
            http_response_code(201); // Created
            $new_id = $pdo->lastInsertId();
            echo json_encode(['message' => 'Course created', 'course_id' => $new_id]);
            break;

        // --- UPDATE ---
        case 'PUT':
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Course ID is required']);
                exit;
            }
            
            $id = $_GET['id'];
            $data = json_decode(file_get_contents('php://input'), true);

            $sql = "UPDATE courses SET course_name = ?, standard_fee = ?, duration = ? WHERE course_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $data['course_name'],
                $data['standard_fee'],
                $data['duration'],
                $id
            ]);
            
            echo json_encode(['message' => 'Course updated']);
            break;

        // --- DELETE ---
        case 'DELETE':
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Course ID is required']);
                exit;
            }
            
            $id = $_GET['id'];
            $stmt = $pdo->prepare("DELETE FROM courses WHERE course_id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(['message' => 'Course deleted']);
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

