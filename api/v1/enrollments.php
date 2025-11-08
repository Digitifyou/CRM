<?php
// /api/v1/enrollments.php

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php'; // $pdo connection

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        // --- READ (Get all "open" enrollments for the Kanban) ---
        case 'GET':
            // We join students and courses to get their names for the cards
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
                WHERE e.status = 'open' 
                ORDER BY e.created_at DESC
            ";
            
            $stmt = $pdo->query($query);
            $enrollments = $stmt->fetchAll();
            echo json_encode($enrollments);
            break;

        // --- UPDATE (Handle drag-and-drop AND Win/Loss AND Reopen) ---
        case 'PUT':
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['enrollment_id'])) {
                 http_response_code(400); // Bad Request
                 echo json_encode(['error' => 'Enrollment ID is required']);
                 exit;
            }
            
            $enrollment_id = (int)$data['enrollment_id'];
            $params = [];
            $updates = [];

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
                    // Update fees paid (assuming full payment on enrollment for MVP)
                    $updates[] = 'total_fee_paid = ?';
                    $params[] = $enroll_details['total_fee_agreed'];
                    
                    // Update student status to 'active_student'
                    $student_stmt = $pdo->prepare("UPDATE students SET status = 'active_student' WHERE student_id = ?");
                    $student_stmt->execute([(int)$enroll_details['student_id']]);
                    
                    // Find and update the corresponding batch seat count
                    if ($enroll_details['course_id']) {
                        $batch_stmt = $pdo->prepare("
                            SELECT batch_id 
                            FROM batches 
                            WHERE course_id = ? 
                            AND start_date >= CURDATE() 
                            AND filled_seats < total_seats
                            ORDER BY start_date ASC 
                            LIMIT 1
                        ");
                        $batch_stmt->execute([(int)$enroll_details['course_id']]);
                        $batch_id = $batch_stmt->fetchColumn();
                        
                        if ($batch_id) {
                            $pdo->prepare("UPDATE batches SET filled_seats = filled_seats + 1 WHERE batch_id = ?")
                                ->execute([$batch_id]);
                            $message = 'Enrollment WON and Batch seat updated.';
                        } else {
                            $message = 'Enrollment WON, but no open batch was found to update.';
                        }
                    }
                }
                $message = $message ?? 'Enrollment marked as ENROLLED (Won)'; // Fallback message

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
                // Action: REOPEN DEAL (Setting status to open AND moving stage)
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

            $sql = "UPDATE enrollments SET " . implode(', ', $updates) . " WHERE enrollment_id = ?";
            $params[] = $enrollment_id;
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            echo json_encode(['message' => $message]);
            break;
        
        // POST (Create) is handled by students.php
        // DELETE (Lost/Won) would be a PUT/DELETE here

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