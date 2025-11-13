<?php
// /api/v1/import_leads.php
// Handles bulk lead import from a source like Google Sheets (CSV/TSV data payload).

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php'; // $pdo connection
// Import scoring function from students.php
require_once __DIR__ . '/students.php'; // Contains calculateAndUpdateLeadScoreInline

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['import_data']) || empty($data['field_mapping'])) {
         http_response_code(400); 
         echo json_encode(['error' => 'Import data and field mapping are required']);
         exit;
    }

    $import_data = $data['import_data'];
    $field_mapping = $data['field_mapping'];
    $success_count = 0;
    $error_details = [];
    $admin_user_id = 1; // Default assignment

    // Get first pipeline stage ID
    $stage_stmt = $pdo->query("SELECT stage_id FROM pipeline_stages ORDER BY stage_order ASC LIMIT 1");
    $first_stage_id = $stage_stmt->fetchColumn();

    if (!$first_stage_id) {
         http_response_code(500);
         echo json_encode(['error' => 'Pipeline stages are not defined. Cannot create enrollment deal.']);
         exit;
    }

    $pdo->beginTransaction();

    foreach ($import_data as $row_index => $row) {
        $lead = [
            'full_name' => null, 
            'email' => null, 
            'phone' => null, 
            'course_interested_id' => null,
            'lead_source' => 'Bulk Import', // Default source for bulk leads
            'qualification' => null, 
            'work_experience' => null, 
            'custom_data' => []
        ];
        $custom_data = [];

        // 1. Map data from source column names to CRM field keys
        foreach ($row as $source_column => $value) {
            $crm_field_key = $field_mapping[$source_column] ?? null;

            if ($crm_field_key) {
                if (in_array($crm_field_key, ['full_name', 'email', 'phone', 'course_interested_id', 'lead_source', 'qualification', 'work_experience'])) {
                    $lead[$crm_field_key] = $value;
                } else {
                    // Treat as custom field, to be stored in the custom_data JSON column
                    $custom_data[$crm_field_key] = $value;
                }
            }
        }
        $lead['custom_data'] = $custom_data;
        
        // 2. Validation (Check required fields: Full Name and Phone)
        if (empty($lead['full_name']) || empty($lead['phone'])) {
            $error_details[] = ['row' => $row_index + 1, 'error' => 'Missing required Full Name or Phone. Skipped.'];
            continue;
        }

        // --- 3. Database Insertion ---
        try {
            // Find Course Fee (assuming course_interested_id holds the actual course ID)
            $course_id = $lead['course_interested_id'] ? (int)$lead['course_interested_id'] : null;
            $course_fee = 0;
            
            if ($course_id) {
                 $fee_stmt = $pdo->prepare("SELECT standard_fee FROM courses WHERE course_id = ?");
                 $fee_stmt->execute([$course_id]);
                 $course_fee = $fee_stmt->fetchColumn() ?: 0;
            }

            // A. Insert into students table
            $sql = "INSERT INTO students 
                        (full_name, email, phone, status, course_interested_id, lead_source, qualification, work_experience, custom_data) 
                    VALUES (?, ?, ?, 'inquiry', ?, ?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $lead['full_name'],
                $lead['email'],
                $lead['phone'],
                $course_id,
                $lead['lead_source'],
                $lead['qualification'],
                $lead['work_experience'],
                json_encode($lead['custom_data'])
            ]);
            
            $new_id = $pdo->lastInsertId();
            
            // B. Score the lead (function imported from students.php)
            calculateAndUpdateLeadScoreInline($pdo, $new_id, $lead);
            
            // C. Create Enrollment Record
            $enroll_sql = "INSERT INTO enrollments 
                                (student_id, course_id, assigned_to_user_id, pipeline_stage_id, total_fee_agreed) 
                            VALUES (?, ?, ?, ?, ?)";
            $enroll_stmt = $pdo->prepare($enroll_sql);
            $enroll_stmt->execute([
                $new_id,
                $course_id,
                $admin_user_id,
                $first_stage_id,
                $course_fee
            ]);

            $success_count++;

        } catch (\PDOException $e) {
            // Check for duplicate phone/email error (code 23000)
            if ($e->getCode() == 23000) { 
                $error_details[] = ['row' => $row_index + 1, 'error' => 'Lead already exists (Duplicate phone or email). Skipped.'];
            } else {
                 $error_details[] = ['row' => $row_index + 1, 'error' => 'Database error: ' . $e->getMessage() . '. Skipped.'];
            }
        }
    }
    
    $pdo->commit();

    http_response_code(200);
    echo json_encode([
        'message' => 'Bulk import complete.', 
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