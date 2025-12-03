<?php
// /api/v1/import_leads.php

ini_set('display_errors', 0);
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php'; 
// FIXED: Include the helper, NOT the controller
require_once __DIR__ . '/helpers/scoring_helper.php'; 

if (!defined('ACADEMY_ID')) {
    http_response_code(403); echo json_encode(['error' => 'Forbidden: No Academy Context.']); exit;
}
$academy_id = ACADEMY_ID;

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405); echo json_encode(['error' => 'Method Not Allowed']); exit;
}

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (empty($data['import_data']) || empty($data['field_mapping'])) {
         http_response_code(400); echo json_encode(['error' => 'Import data and field mapping are required']); exit;
    }

    $import_data = $data['import_data'];
    $field_mapping = $data['field_mapping'];
    $success_count = 0;
    $error_details = [];
    
    $assigned_user_id = $_SESSION['user_id'] ?? 1; 

    // SCOPED: Fetch stage for this academy
    $stage_stmt = $pdo->prepare("SELECT stage_id FROM pipeline_stages WHERE academy_id = ? ORDER BY stage_order ASC LIMIT 1");
    $stage_stmt->execute([$academy_id]);
    $first_stage_id = $stage_stmt->fetchColumn();

    if (!$first_stage_id) {
         // Fallback to system default (0)
         $stage_stmt = $pdo->prepare("SELECT stage_id FROM pipeline_stages WHERE academy_id = 0 ORDER BY stage_order ASC LIMIT 1");
         $stage_stmt->execute();
         $first_stage_id = $stage_stmt->fetchColumn();
         if (!$first_stage_id) {
             throw new Exception('Pipeline stages are not defined.');
         }
    }

    $pdo->beginTransaction();

    foreach ($import_data as $row_index => $row) {
        $lead = [
            'full_name' => null, 'email' => null, 'phone' => null, 
            'course_interested_id' => null, 'lead_source' => 'Bulk Import',
            'qualification' => null, 'work_experience' => null, 'custom_data' => []
        ];
        $custom_data = [];

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
        
        // --- MODIFIED MANDATORY FIELD CHECK ---
        // Changed from: if (empty($lead['full_name']) || empty($lead['phone'])) 
        // To: only check for full_name as required by the user
        if (empty($lead['full_name'])) {
            $error_details[] = ['row' => $row_index + 1, 'error' => 'Missing required Name.'];
            continue;
        }

        try {
            $course_id = $lead['course_interested_id'] ? (int)$lead['course_interested_id'] : null;
            $course_fee = 0;
            
            if ($course_id) {
                 $fee_stmt = $pdo->prepare("SELECT standard_fee FROM courses WHERE course_id = ?");
                 $fee_stmt->execute([$course_id]);
                 $course_fee = $fee_stmt->fetchColumn() ?: 0;
            }

            $sql = "INSERT INTO students (full_name, email, phone, status, course_interested_id, lead_source, qualification, work_experience, custom_data, academy_id) VALUES (?, ?, ?, 'inquiry', ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $lead['full_name'], $lead['email'], $lead['phone'],
                $course_id, $lead['lead_source'], $lead['qualification'],
                $lead['work_experience'], json_encode($lead['custom_data']),
                $academy_id
            ]);
            
            $new_id = $pdo->lastInsertId();
            
            // USE HELPER FUNCTION
            calculateLeadScore($pdo, $new_id, $academy_id, $lead);
            
            $enroll_sql = "INSERT INTO enrollments (student_id, course_id, assigned_to_user_id, pipeline_stage_id, total_fee_agreed, academy_id) VALUES (?, ?, ?, ?, ?, ?)";
            $enroll_stmt = $pdo->prepare($enroll_sql);
            $enroll_stmt->execute([$new_id, $course_id, $assigned_user_id, $first_stage_id, $course_fee, $academy_id]);

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