<?php
// /api/v1/enrollments.php

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php'; 

if (!defined('ACADEMY_ID')) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden: No Academy Context.']);
    exit;
}
$academy_id = ACADEMY_ID;

$method = $_SERVER['REQUEST_METHOD'];

try {
    $current_user_id = $_SESSION['user_id'] ?? 0;
    $current_user_role = $_SESSION['role'] ?? 'counselor';

    switch ($method) {
        case 'GET':
            // SCOPED: Filtering by enrollments.academy_id
            $query = "
                SELECT 
                    e.enrollment_id,
                    e.pipeline_stage_id,
                    e.next_follow_up_date,
                    e.total_fee_agreed,
                    s.full_name AS student_name,
                    c.course_name
                FROM enrollments e
                JOIN students s ON e.student_id = s.student_id
                LEFT JOIN courses c ON e.course_id = c.course_id
                WHERE e.status = 'open' AND e.academy_id = ? 
            ";
            
            $params = [$academy_id]; 

            if ($current_user_role === 'counselor') {
                $query .= " AND e.assigned_to_user_id = ?";
                $params[] = $current_user_id;
            }

            $query .= " ORDER BY e.created_at DESC";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $enrollments = $stmt->fetchAll();
            echo json_encode($enrollments);
            break;

        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['enrollment_id'])) {
                 http_response_code(400); echo json_encode(['error' => 'Enrollment ID required']); exit;
            }
            
            $enrollment_id = (int)$data['enrollment_id'];
            $params = [];
            $updates = [];

            // SCOPED Check
            $check_stmt = $pdo->prepare("SELECT student_id, course_id FROM enrollments WHERE enrollment_id = ? AND academy_id = ?");
            $check_stmt->execute([$enrollment_id, $academy_id]);
            $enroll_details = $check_stmt->fetch();

            if (!$enroll_details) {
                http_response_code(403); echo json_encode(['error' => 'Enrollment not found/access denied.']); exit;
            }

            if (isset($data['new_stage_id']) && !isset($data['status'])) {
                $updates[] = 'pipeline_stage_id = ?';
                $params[] = (int)$data['new_stage_id'];
                $message = 'Stage updated';

            } else if (isset($data['status']) && $data['status'] === 'enrolled') {
                $updates[] = 'status = ?'; $params[] = 'enrolled';
                
                // Calculate fees logic here if needed
                // Update student status (SCOPED)
                $pdo->prepare("UPDATE students SET status = 'active_student' WHERE student_id = ? AND academy_id = ?")
                    ->execute([$enroll_details['student_id'], $academy_id]);
                
                // Update Batch seats (SCOPED)
                if ($enroll_details['course_id']) {
                    $batch_stmt = $pdo->prepare("SELECT batch_id FROM batches WHERE course_id = ? AND start_date >= CURDATE() AND filled_seats < total_seats AND academy_id = ? ORDER BY start_date ASC LIMIT 1");
                    $batch_stmt->execute([$enroll_details['course_id'], $academy_id]);
                    $batch_id = $batch_stmt->fetchColumn();
                    if ($batch_id) {
                        $pdo->prepare("UPDATE batches SET filled_seats = filled_seats + 1 WHERE batch_id = ?")->execute([$batch_id]);
                    }
                }
                $message = 'Enrollment Won';

            } else if (isset($data['status']) && $data['status'] === 'lost') {
                $updates[] = 'status = ?'; $params[] = 'lost';
                if (isset($data['lost_reason'])) { $updates[] = 'lost_reason = ?'; $params[] = $data['lost_reason']; }
                $message = 'Enrollment Lost';
            }

            if (!empty($updates)) {
                $sql = "UPDATE enrollments SET " . implode(', ', $updates) . " WHERE enrollment_id = ? AND academy_id = ?";
                $params[] = $enrollment_id;
                $params[] = $academy_id;
                $pdo->prepare($sql)->execute($params);
            }
            
            echo json_encode(['message' => $message ?? 'Updated']);
            break;

        default:
            http_response_code(405); echo json_encode(['error' => 'Method Not Allowed']); break;
    }

} catch (\PDOException $e) {
    http_response_code(500); echo json_encode(['error' => $e->getMessage()]);
}
?>