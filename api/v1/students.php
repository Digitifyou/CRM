<?php
// /api/v1/students.php

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php'; 
// FIXED: Use helper
require_once __DIR__ . '/helpers/scoring_helper.php'; 

if (!defined('ACADEMY_ID')) {
    http_response_code(403); echo json_encode(['error' => 'Forbidden']); exit;
}
$academy_id = ACADEMY_ID;

$method = $_SERVER['REQUEST_METHOD'];
$current_user_id = $_SESSION['user_id'] ?? 0;
$current_user_role = $_SESSION['role'] ?? 'counselor';

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                $stmt = $pdo->prepare("SELECT s.*, c.course_name FROM students s LEFT JOIN courses c ON s.course_interested_id = c.course_id WHERE s.student_id = ? AND s.academy_id = ?");
                $stmt->execute([$_GET['id'], $academy_id]);
                $student = $stmt->fetch();
                if (!$student) { http_response_code(404); echo json_encode(['error' => 'Not found']); exit; }
                
                $enroll_stmt = $pdo->prepare("SELECT e.*, c.course_name, p.stage_name FROM enrollments e LEFT JOIN courses c ON e.course_id = c.course_id LEFT JOIN pipeline_stages p ON e.pipeline_stage_id = p.stage_id WHERE e.student_id = ? ORDER BY e.created_at DESC");
                $enroll_stmt->execute([$_GET['id']]);
                $student['enrollments'] = $enroll_stmt->fetchAll();
                $student['custom_data'] = json_decode($student['custom_data'] ?? '{}', true);
                echo json_encode($student);
            } else {
                $sql = "SELECT s.student_id, s.full_name, s.phone, s.status, s.lead_score, s.created_at, c.course_name FROM students s LEFT JOIN courses c ON s.course_interested_id = c.course_id LEFT JOIN enrollments e ON s.student_id = e.student_id WHERE s.academy_id = ?";
                $params = [$academy_id];
                if ($current_user_role === 'counselor') {
                    $sql .= " AND e.assigned_to_user_id = ?";
                    $params[] = $current_user_id;
                }
                $sql .= " GROUP BY s.student_id ORDER BY s.created_at DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                echo json_encode($stmt->fetchAll());
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            if (empty($data['full_name']) || empty($data['phone'])) { http_response_code(400); echo json_encode(['error' => 'Name/Phone required']); exit; }
            
            $sql = "INSERT INTO students (full_name, email, phone, status, course_interested_id, lead_source, qualification, work_experience, custom_data, academy_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $data['full_name'], $data['email'] ?? null, $data['phone'], 'inquiry',
                $data['course_interested_id'] ?: null, $data['lead_source'], 
                $data['qualification'], $data['work_experience'], 
                json_encode($data['custom_data'] ?? []), $academy_id
            ]);
            $new_id = $pdo->lastInsertId();
            
            // FIXED: Use helper function
            calculateLeadScore($pdo, $new_id, $academy_id, $data);
            
            // Auto Enrollment
            $stage_stmt = $pdo->prepare("SELECT stage_id FROM pipeline_stages WHERE academy_id = ? ORDER BY stage_order ASC LIMIT 1");
            $stage_stmt->execute([$academy_id]);
            $first_stage_id = $stage_stmt->fetchColumn();
            
            if (!$first_stage_id) {
                 $stage_stmt = $pdo->prepare("SELECT stage_id FROM pipeline_stages WHERE academy_id = 0 ORDER BY stage_order ASC LIMIT 1");
                 $stage_stmt->execute();
                 $first_stage_id = $stage_stmt->fetchColumn();
            }

            $fee = 0;
            if (!empty($data['course_interested_id'])) {
                 $fee_stmt = $pdo->prepare("SELECT standard_fee FROM courses WHERE course_id = ?");
                 $fee_stmt->execute([(int)$data['course_interested_id']]);
                 $fee = $fee_stmt->fetchColumn();
            }

            if ($first_stage_id) {
                 $enroll_stmt = $pdo->prepare("INSERT INTO enrollments (student_id, course_id, assigned_to_user_id, pipeline_stage_id, total_fee_agreed, academy_id) VALUES (?, ?, ?, ?, ?, ?)");
                 $enroll_stmt->execute([$new_id, $data['course_interested_id'] ?: null, $current_user_id, $first_stage_id, $fee ?: 0, $academy_id]);
            }
            
            http_response_code(201); echo json_encode(['message' => 'Created', 'student_id' => $new_id]);
            break;

        case 'PUT':
            if (empty($_GET['id'])) { http_response_code(400); echo json_encode(['error' => 'ID required']); exit; }
            $id = $_GET['id'];
            $data = json_decode(file_get_contents('php://input'), true);
            
            $updates = []; $params = [];
            if (isset($data['full_name'])) { $updates[] = 'full_name = ?'; $params[] = $data['full_name']; }
            if (isset($data['email'])) { $updates[] = 'email = ?'; $params[] = $data['email']; }
            if (isset($data['phone'])) { $updates[] = 'phone = ?'; $params[] = $data['phone']; }
            if (isset($data['status'])) { $updates[] = 'status = ?'; $params[] = $data['status']; }
            if (isset($data['course_interested_id'])) { $updates[] = 'course_interested_id = ?'; $params[] = $data['course_interested_id'] ?: null; }
            if (isset($data['lead_source'])) { $updates[] = 'lead_source = ?'; $params[] = $data['lead_source']; }
            if (isset($data['qualification'])) { $updates[] = 'qualification = ?'; $params[] = $data['qualification']; }
            if (isset($data['work_experience'])) { $updates[] = 'work_experience = ?'; $params[] = $data['work_experience']; }
            if (isset($data['custom_data'])) { $updates[] = 'custom_data = ?'; $params[] = json_encode($data['custom_data']); }

            if (empty($updates)) { http_response_code(400); echo json_encode(['error' => 'No fields']); exit; }
            
            $sql = "UPDATE students SET " . implode(', ', $updates) . " WHERE student_id = ? AND academy_id = ?";
            $params[] = $id;
            $params[] = $academy_id;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            // FIXED: Use helper function
            calculateLeadScore($pdo, $id, $academy_id, $data);
            echo json_encode(['message' => 'Updated']);
            break;

        case 'DELETE':
            if (!in_array($current_user_role, ['admin', 'owner'])) { http_response_code(403); exit; }
            $stmt = $pdo->prepare("DELETE FROM students WHERE student_id = ? AND academy_id = ?");
            $stmt->execute([$_GET['id'], $academy_id]);
            echo json_encode(['message' => 'Deleted']);
            break;
            
        default:
            http_response_code(405); echo json_encode(['error' => 'Method Not Allowed']); break;
    }
} catch (Exception $e) {
    http_response_code(500); echo json_encode(['error' => $e->getMessage()]);
}
?>