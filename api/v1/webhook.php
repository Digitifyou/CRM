<?php
// /api/v1/webhook.php

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php'; // $pdo connection

$method = $_SERVER['REQUEST_METHOD'];

// --- SECURITY CHECK (Placeholder) ---
// In a real app, you would validate the 'key' against the database
if (empty($_GET['key']) || $_GET['key'] !== 'SECURE-AUTO-GENERATED-KEY') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden: Invalid or missing security key.']);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Only POST requests are supported for lead capture.']);
    exit;
}

try {
    // 1. Get raw input data (for webhooks, this can be JSON or form-encoded data)
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Fallback for form-encoded or other non-JSON POST data
    if (empty($data)) {
        $data = $_POST;
    }

    // 2. Basic Validation (Name or Phone is required to create a student)
    if (empty($data['full_name']) && empty($data['phone'])) {
         http_response_code(400); // Bad Request
         echo json_encode(['error' => 'Full Name or Phone number is required for lead creation']);
         exit;
    }

    // 3. Data Mapping and Cleaning (Normalize fields for the students table)
    $full_name = $data['full_name'] ?? ($data['name'] ?? 'Web Lead');
    $phone = $data['phone'] ?? ($data['mobile'] ?? null);
    $email = $data['email'] ?? null;
    
    // Default to the simplest lead source if not specified
    $lead_source = $data['lead_source'] ?? 'Website Form'; 

    // Find first pipeline stage (New Inquiry)
    $stage_stmt = $pdo->query("SELECT stage_id FROM pipeline_stages ORDER BY stage_order ASC LIMIT 1");
    $first_stage_id = $stage_stmt->fetchColumn();
    
    // Default assigned user
    $admin_user_id = 1; 

    // 4. Create Student/Lead Record
    $sql = "INSERT INTO students 
                (full_name, email, phone, status, lead_source) 
            VALUES 
                (?, ?, ?, 'inquiry', ?)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $full_name,
        $email,
        $phone,
        $lead_source
    ]);
    
    $new_id = $pdo->lastInsertId();
    
    // 5. Create Default Enrollment Record (if first stage exists)
    if ($first_stage_id) {
         $enroll_sql = "INSERT INTO enrollments 
                            (student_id, course_id, assigned_to_user_id, pipeline_stage_id, total_fee_agreed) 
                        VALUES 
                            (?, NULL, ?, ?, 0.00)";
         $enroll_stmt = $pdo->prepare($enroll_sql);
         $enroll_stmt->execute([
            $new_id,
            $admin_user_id,
            $first_stage_id,
         ]);
    }
    
    http_response_code(201); // Created
    echo json_encode([
        'success' => true, 
        'message' => 'Lead created successfully.', 
        'student_id' => $new_id
    ]);

} catch (\PDOException $e) {
    // Check for duplicate phone/email error
    if ($e->getCode() == 23000) { 
        http_response_code(409); // Conflict
        echo json_encode(['error' => 'Lead already exists (Duplicate phone or email).']);
        exit;
    }
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>