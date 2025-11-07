<?php
// /api/v1/students.php

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php'; // $pdo connection

$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        // --- READ (Get all or one) ---
        case 'GET':
            if (isset($_GET['id'])) {
                // Get single student's full profile
                $id = $_GET['id'];
                
                // 1. Fetch Student's Main Data
                $stmt = $pdo->prepare("
                    SELECT 
                        s.*, 
                        c.course_name 
                    FROM students s
                    LEFT JOIN courses c ON s.course_interested_id = c.course_id
                    WHERE s.student_id = ?
                ");
                $stmt->execute([$id]);
                $student = $stmt->fetch();
                
                if (!$student) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Student not found']);
                    exit;
                }

                // 2. Fetch Enrollment History (Open/Won/Lost)
                $enroll_stmt = $pdo->prepare("
                    SELECT 
                        e.enrollment_id, 
                        e.total_fee_agreed,
                        e.total_fee_paid,
                        e.balance_due,
                        e.status,
                        e.created_at,
                        c.course_name,
                        p.stage_name AS pipeline_stage
                    FROM enrollments e
                    LEFT JOIN courses c ON e.course_id = c.course_id
                    LEFT JOIN pipeline_stages p ON e.pipeline_stage_id = p.stage_id
                    WHERE e.student_id = ?
                    ORDER BY e.created_at DESC
                ");
                $enroll_stmt->execute([$id]);
                $student['enrollments'] = $enroll_stmt->fetchAll();
                
                // Note: Activity log is fetched via a separate API call in JS for better modularity
                
                echo json_encode($student);

            } else {
                // Get all students for the main list
                // ... (rest of the code is unchanged)
                $query = "
                    SELECT 
                        s.student_id, 
                        s.full_name, 
                        s.phone, 
                        s.status, 
                        s.lead_score, 
                        s.created_at,
                        c.course_name AS course_interested 
                    FROM students s
                    LEFT JOIN courses c ON s.course_interested_id = c.course_id
                    ORDER BY s.created_at DESC
                ";
                
                $stmt = $pdo->query($query);
                $students = $stmt->fetchAll();
                echo json_encode($students);
            }
            break;

        // --- CREATE A NEW STUDENT/LEAD ---
        case 'POST':
            // ... (rest of the code is unchanged)
            $data = json_decode(file_get_contents('php://input'), true);

            // Basic validation
            if (empty($data['full_name']) || empty($data['phone'])) {
                 http_response_code(400); // Bad Request
                 echo json_encode(['error' => 'Full Name and Phone are required']);
                 exit;
            }

            $sql = "INSERT INTO students 
                        (full_name, email, phone, status, course_interested_id, lead_source, qualification, work_experience) 
                    VALUES 
                        (?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $data['full_name'],
                $data['email'] ?? null,
                $data['phone'],
                $data['status'] ?? 'inquiry',
                $data['course_interested_id'] ? (int)$data['course_interested_id'] : null,
                $data['lead_source'] ?? null,
                $data['qualification'] ?? null,
                $data['work_experience'] ?? null
            ]);
            
            $new_id = $pdo->lastInsertId();
            
            // ** AI TRIGGER (Placeholder) **
            // This is where you would call the AI to score the lead
            // file_get_contents('http://localhost/crm/api/v1/ai.php?action=score&id=' . $new_id);
            
            // Create a new enrollment record for this inquiry
            // Find the first pipeline stage (e.g., "New Inquiry")
            $stage_stmt = $pdo->query("SELECT stage_id FROM pipeline_stages ORDER BY stage_order ASC LIMIT 1");
            $first_stage_id = $stage_stmt->fetchColumn();
            
            // Find a user to assign it to (e.g., admin user ID 1 for now)
            $admin_user_id = 1; 

            // Get course fee
            $fee = 0;
            if (!empty($data['course_interested_id'])) {
                 $fee_stmt = $pdo->prepare("SELECT standard_fee FROM courses WHERE course_id = ?");
                 $fee_stmt->execute([(int)$data['course_interested_id']]);
                 $fee = $fee_stmt->fetchColumn();
            }

            if ($first_stage_id) {
                 $enroll_sql = "INSERT INTO enrollments 
                                    (student_id, course_id, assigned_to_user_id, pipeline_stage_id, total_fee_agreed) 
                                VALUES 
                                    (?, ?, ?, ?, ?)";
                 $enroll_stmt = $pdo->prepare($enroll_sql);
                 $enroll_stmt->execute([
                    $new_id,
                    $data['course_interested_id'] ? (int)$data['course_interested_id'] : null,
                    $admin_user_id,
                    $first_stage_id,
                    $fee ?: 0
                 ]);
            }
            
            http_response_code(201); // Created
            echo json_encode(['message' => 'Student and enrollment created', 'student_id' => $new_id]);
            break;


        // --- UPDATE ---
        case 'PUT':
            // Logic for updating a student (to be implemented later)
            http_response_code(501); // Not Implemented
            echo json_encode(['error' => 'Update not yet implemented']);
            break;

        // --- DELETE ---
        case 'DELETE':
            // Logic for deleting a student (to be implemented later)
            http_response_code(501); // Not Implemented
            echo json_encode(['error' => 'Delete not yet implemented']);
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