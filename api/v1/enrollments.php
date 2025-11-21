<?php
// /api/v1/enrollments.php

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php'; // $pdo connection

$method = $_SERVER['REQUEST_METHOD'];


$academy_id = ACADEMY_ID;
// --- MULTI-TENANCY CHECK ---
if (!defined('ACADEMY_ID') || ACADEMY_ID === 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden: No Academy Context.']);
    exit;
}
// ---------------------------

try {
    // --- GET CURRENT USER PERMISSIONS ---
    $current_user_id = $_SESSION['user_id'] ?? 0;
    $current_user_role = $_SESSION['role'] ?? 'counselor';

    switch ($method) {
        // --- READ (Get all "open" enrollments for the Kanban) ---
        case 'GET':
            // Base query SCOPED by joining student table
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
                WHERE e.status = 'open' AND s.academy_id = ? 
            ";
            
            $params = [$academy_id]; 

            // --- PERMISSION CHECK ---
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

        // --- UPDATE (Handle drag-and-drop AND Win/Loss AND Reopen) ---
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['enrollment_id'])) {
                 http_response_code(400); 
                 echo json_encode(['error' => 'Enrollment ID is required']);
                 exit;
            }
            
            $enrollment_id = (int)$data['enrollment_id'];
            $params = [];
            $updates = [];

            // --- SECURITY CHECK: VERIFY ENROLLMENT BELONGS TO ACADEMY (SCOPED) ---
            $check_stmt = $pdo->prepare("
                SELECT e.student_id, e.course_id 
                FROM enrollments e 
                JOIN students s ON e.student_id = s.student_id
                WHERE e.enrollment_id = ? AND s.academy_id = ?
            ");
            $check_stmt->execute([$enrollment_id, $academy_id]);
            $enroll_security = $check_stmt->fetch();

            if (!$enroll_security) {
                http_response_code(403);
                echo json_encode(['error' => 'Enrollment not found or does not belong to this academy.']);
                exit;
            }
            // --- END SECURITY CHECK ---

            if (isset($data['new_stage_id']) && !isset($data['status'])) {
                // Action: Pipeline Stage Change (Drag-and-Drop)
                $updates[] = 'pipeline_stage_id = ?';
                $params[] = (int)$data['new_stage_id'];
                $message = 'Enrollment stage updated';

            } else if (isset($data['status']) && $data['status'] === 'enrolled') {
                // Action: Mark as ENROLLED (WON)
                $updates[] = 'status = ?';
                $params[] = 'enrolled';
                
                // Get agreed fee, student ID, and course ID
                $fee_stmt = $pdo->prepare("SELECT total_fee_agreed, student_id, course_id FROM enrollments WHERE enrollment_id = ?");
                $fee_stmt->execute([$enrollment_id]);
                $enroll_details = $fee_stmt->fetch();

                if ($enroll_details) {
                    // Update fees paid
                    $updates[] = 'total_fee_paid = ?';
                    $params[] = $enroll_details['total_fee_agreed'];
                    
                    // Update student status (SCOPED)
                    $student_stmt = $pdo->prepare("UPDATE students SET status = 'active_student' WHERE student_id = ? AND academy_id = ?");
                    $student_stmt->execute([(int)$enroll_details['student_id'], $academy_id]); 
                    
                    // Find and update the corresponding batch seat count (SCOPED)
                    if ($enroll_details['course_id']) {
                        $batch_stmt = $pdo->prepare("
                            SELECT batch_id 
                            FROM batches 
                            WHERE course_id = ? 
                            AND start_date >= CURDATE() 
                            AND filled_seats < total_seats
                            AND academy_id = ? 
                            ORDER BY start_date ASC 
                            LIMIT 1
                        ");
                        $batch_stmt->execute([(int)$enroll_details['course_id'], $academy_id]); 
                        $batch_id = $batch_stmt->fetchColumn();
                        
                        if ($batch_id) {
                            $pdo->prepare("UPDATE batches SET filled_seats = filled_seats + 1 WHERE batch_id = ? AND academy_id = ?")
                                ->execute([$batch_id, $academy_id]); 
                            $message = 'Enrollment WON and Batch seat updated.';
                        } else {
                            $message = 'Enrollment WON, but no open batch was found to update.';
                        }
                    }
                }
                $message = $message ?? 'Enrollment marked as ENROLLED (Won)'; 

            } else if (isset($data['status']) && $data['status'] === 'lost') {
                // Action: Mark as LOST
                $updates[] = 'status = ?';
                $params[] = 'lost';
                
                if (isset($data['lost_reason'])) {
                    $updates[] = 'lost_reason = ?';
                    $params[] = $data['lost_reason'];
                }
                $message = 'Enrollment marked as LOST';
            } else if (isset($data['status']) && $data['status'] === 'open' && isset($data['new_stage_id'])) {
                // Action: REOPEN DEAL
                $updates[] = 'status = ?';
                $params[] = 'open';
                $updates[] = 'pipeline_stage_id = ?';
                $params[] = (int)$data['new_stage_id'];
                
                $message = 'Enrollment deal reopened and stage reset.';
            } else {
                 http_response_code(400);
                 echo json_encode(['error' => 'No valid update action provided.']);
                 exit;
            }

            // SCOPED: Final Update query
            $sql = "UPDATE enrollments SET " . implode(', ', $updates) . " WHERE enrollment_id = ? AND academy_id = ?";
            $params[] = $enrollment_id;
            $params[] = $academy_id;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode(['message' => $message]);
            break;

        default:
            http_response_code(405); 
            echo json_encode(['error' => 'Method Not Allowed']);
            break;
    }

} catch (\PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>