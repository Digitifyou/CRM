<?php
// /api/v1/import_leads.php

// CRITICAL: Turn off display_errors so warnings don't break JSON
ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php'; 
require_once __DIR__ . '/students.php'; // Functions available, main logic skipped

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (empty($data['import_data']) || empty($data['field_mapping'])) {
         http_response_code(400); 
         echo json_encode(['error' => 'Import data and field mapping are required']);
         exit;
    }

    $import_data = $data['import_data'];
    $field_mapping = $data['field_mapping'];
    $success_count = 0;
    $error_details = [];
    
    // Default assignment (Logged in user or Admin ID 1)
    $assigned_user_id = $_SESSION['user_id'] ?? 1; 
    
    // FIXED: Hardcode Academy ID as requested
    $academy_id = 12;

    $stage_stmt = $pdo->query("SELECT stage_id FROM pipeline_stages ORDER BY stage_order ASC LIMIT 1");
    $first_stage_id = $stage_stmt->fetchColumn();

    if (!$first_stage_id) {
         throw new Exception('Pipeline stages are not defined. Cannot create enrollments.');
    }

    $pdo->beginTransaction();

    foreach ($import_data as $row_index => $row) {
        $lead = [
            'full_name' => null, 'email' => null, 'phone' => null, 
            'course_interested_id' => null, 'lead_source' => 'Bulk Import',
            'qualification' => null, 'work_experience' => null, 'custom_data' => []
        ];
        $custom_data = [];

        // 1. Map data
        foreach ($row as $source_column => $value) {
            $crm_field_key = $field_mapping[$source_column] ?? null;
            if ($crm_field_key) {
                if (in_array($crm_field_key, ['full_name', 'email', 'phone', 'course_interested_id', 'lead_source', 'qualification', 'work_experience'])) {
                    $lead[$crm_field_key] = $value;
                } else {
                    $custom_data[$crm_field_key] = $value;
                }
            }
        }
        $lead['custom_data'] = $custom_data;
        
        // 2. Validation
        if (empty($lead['full_name']) || empty($lead['phone'])) {
            $error_details[] = ['row' => $row_index + 1, 'error' => 'Missing required Name or Phone.'];
            continue;
        }

        // 3. Insertion
        try {
            $course_id = $lead['course_interested_id'] ? (int)$lead['course_interested_id'] : null;
            $course_fee = 0;
            
            if ($course_id) {
                 $fee_stmt = $pdo->prepare("SELECT standard_fee FROM courses WHERE course_id = ?");
                 $fee_stmt->execute([$course_id]);
                 $course_fee = $fee_stmt->fetchColumn() ?: 0;
            }

            // FIXED: Added academy_id to INSERT
            $sql = "INSERT INTO students (full_name, email, phone, status, course_interested_id, lead_source, qualification, work_experience, custom_data, academy_id) VALUES (?, ?, ?, 'inquiry', ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $lead['full_name'], $lead['email'], $lead['phone'],
                $course_id, $lead['lead_source'], $lead['qualification'],
                $lead['work_experience'], json_encode($lead['custom_data']),
                $academy_id // Passing 12 here
            ]);
            
            $new_id = $pdo->lastInsertId();
            
            // Score lead
            calculateAndUpdateLeadScoreInline($pdo, $new_id, $lead);
            
            // Create Enrollment
            $enroll_sql = "INSERT INTO enrollments (student_id, course_id, assigned_to_user_id, pipeline_stage_id, total_fee_agreed) VALUES (?, ?, ?, ?, ?)";
            $enroll_stmt = $pdo->prepare($enroll_sql);
            $enroll_stmt->execute([$new_id, $course_id, $assigned_user_id, $first_stage_id, $course_fee]);

            $success_count++;

        } catch (\PDOException $e) {
            if ($e->getCode() == 23000) { 
                $error_details[] = ['row' => $row_index + 1, 'error' => 'Duplicate phone/email.'];
            } else {
                 $error_details[] = ['row' => $row_index + 1, 'error' => 'DB Error: ' . $e->getMessage()];
            }
        }
    }
    
    $pdo->commit();

    echo json_encode([
        'message' => 'Import complete.', 
        'success_count' => $success_count, 
        'error_count' => count($error_details),
        'errors' => $error_details
    ]);

} catch (\Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}
?>