<?php
// /api/v1/students.php

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php'; // $pdo connection

$method = $_SERVER['REQUEST_METHOD'];

// Standard Score Points Mapping for dynamic weights
const SCORE_POINTS = [
    'Low' => 10,
    'Medium' => 25,
    'High' => 50
];

/**
 * Calculates the total score value for a given score level (Low, Medium, High).
 * @param string $level
 * @return int
 */
function getScoreValue($level) {
    return SCORE_POINTS[$level] ?? 0;
}

/**
 * Fetches all scoring configuration (built-in and custom) from the database
 * and combines them into a single configuration array.
 * @param PDO $pdo The database connection object.
 * @return array The configuration array { field_key: {is_score_field, scoring_rules, ...} }
 */
function getScoringConfiguration($pdo) {
    $config = [];

    // 1. Fetch Custom Field scoring rules
    $custom_fields_stmt = $pdo->query("
        SELECT field_key, is_score_field, scoring_rules 
        FROM custom_fields 
        WHERE is_score_field = TRUE
    ");
    while ($row = $custom_fields_stmt->fetch()) {
        $config[$row['field_key']] = $row;
    }

    // 2. Fetch System Field configuration (Built-in fields)
    $system_fields_stmt = $pdo->query("
        SELECT field_key, is_score_field, scoring_rules 
        FROM system_field_config
        WHERE is_score_field = TRUE
    ");
    while ($row = $system_fields_stmt->fetch()) {
        $config[$row['field_key']] = $row;
    }
    
    // Safety Net: Add hardcoded defaults for keys if missing config (to prevent 0 scores)
    $default_scoring_rules = '{"High": "ANY"}'; 
    $default_keys = ['course_interested_id', 'lead_source', 'qualification', 'work_experience'];
    foreach ($default_keys as $key) {
        if (!isset($config[$key])) {
             $config[$key] = [
                 'field_key' => $key, 
                 'is_score_field' => 1, 
                 'scoring_rules' => $default_scoring_rules // Simple score if set to true
             ];
        }
    }
    
    return $config;
}


/**
 * Calculates a lead score based on student data and configuration.
 * @param PDO $pdo The database connection object.
 * @param int $student_id The ID of the student to score.
 * @param array $data The submitted data array (used for newly inserted data/updates).
 * @return int The calculated score.
 */
function calculateAndUpdateLeadScoreInline($pdo, $student_id, $data = []) {
    
    // 1. Fetch Student Data (with course fee and custom data)
    $stmt = $pdo->prepare("
        SELECT 
            s.course_interested_id, 
            s.lead_source, 
            s.qualification, 
            s.work_experience,
            s.custom_data,
            c.standard_fee
        FROM students s
        LEFT JOIN courses c ON s.course_interested_id = c.course_id
        WHERE s.student_id = ?
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    if (!$student) return 0;
    
    // Use data passed in PUT/POST, falling back to fetched data
    $fields_to_check = [
        'course_interested_id' => $data['course_interested_id'] ?? $student['course_interested_id'],
        'lead_source' => $data['lead_source'] ?? $student['lead_source'],
        'qualification' => $data['qualification'] ?? $student['qualification'],
        'work_experience' => $data['work_experience'] ?? $student['work_experience'],
    ];
    $standard_fee = $data['standard_fee'] ?? $student['standard_fee'];

    $custom_data = json_decode($student['custom_data'] ?? '{}', true);

    // 2. Get Configuration
    $scoring_config = getScoringConfiguration($pdo);

    $score = 0;

    // --- 3. Dynamic Scoring Logic ---
    
    // Combine built-in fields and custom fields for scoring loop
    $all_scoring_fields = $fields_to_check + $custom_data;

    foreach ($scoring_config as $config) {
        $field_key = $config['field_key'];
        
        // Find the student's value for the current scoring key
        $student_value = $all_scoring_fields[$field_key] ?? null;
        
        if ($config['is_score_field'] == 1 && !empty($student_value)) {
            
            // CRITICAL: Scoring Logic Here
            $rules_json = $config['scoring_rules'] ?? null;
            $rules = $rules_json ? json_decode($rules_json, true) : [];
            $student_value_lower = strtolower((string)$student_value);
            
            $matched_level = null;
            $base_score_applied = false;

            // A. Check for specific value match in rules (High, Medium, Low)
            // Iterate in order (High > Medium > Low) to apply the highest score first
            foreach (['High', 'Medium', 'Low'] as $level) {
                if (isset($rules[$level])) {
                    // Split, trim, and lowercase configured rules string:
                    $configured_values = array_map('strtolower', array_map('trim', explode(',', $rules[$level])));
                    
                    if (in_array($student_value_lower, $configured_values) || in_array('any', $configured_values)) {
                         $matched_level = $level;
                         break; // Apply highest matching score and exit loop
                    }
                }
            }
            
            // B. Apply Score based on matched level (or default if available)
            if ($matched_level) {
                $score += getScoreValue($matched_level);
                $base_score_applied = true;
            } else if (isset($rules['default'])) {
                 // Fallback score if a value exists but doesn't match a specific rule
                 $score += getScoreValue($rules['default']);
                 $base_score_applied = true;
            }

            // C. Special Rule for Course Fee Bonus (Hardcoded for high value built-in course field)
            if ($field_key === 'course_interested_id' && $student['standard_fee'] > 30000 && $base_score_applied) {
                 $score += 10;
            }
        }
    }

    $final_score = min(100, max(0, $score)); 

    // 4. Update student lead_score in DB
    $update_stmt = $pdo->prepare("UPDATE students SET lead_score = ? WHERE student_id = ?");
    $update_stmt->execute([$final_score, $student_id]);

    return $final_score;
}

try {
    switch ($method) {
        // --- READ (Get all or one) ---
        case 'GET':
            if (isset($_GET['id'])) {
                $id = $_GET['id'];
                
                // 1. Fetch Student's Main Data (now includes custom_data)
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
                
                // 3. Decode custom_data for the front-end
                $student['custom_data'] = json_decode($student['custom_data'] ?? '{}', true);
                
                echo json_encode($student);

            } else {
                // Get all students for the main list
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
            $data = json_decode(file_get_contents('php://input'), true);

            // Basic validation
            if (empty($data['full_name']) || empty($data['phone'])) {
                 http_response_code(400); // Bad Request
                 echo json_encode(['error' => 'Full Name and Phone are required']);
                 exit;
            }
            
            // Extract custom field data if present
            $custom_data_json = json_encode($data['custom_data'] ?? []);

            $sql = "INSERT INTO students 
                        (full_name, email, phone, status, course_interested_id, lead_source, qualification, work_experience, custom_data) 
                    VALUES 
                        (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $data['full_name'],
                $data['email'] ?? null,
                $data['phone'],
                $data['status'] ?? 'inquiry',
                $data['course_interested_id'] ? (int)$data['course_interested_id'] : null,
                $data['lead_source'] ?? null,
                $data['qualification'] ?? null,
                $data['work_experience'] ?? null,
                $custom_data_json
            ]);
            
            $new_id = $pdo->lastInsertId();
            
            // ** LEAD SCORING **
            calculateAndUpdateLeadScoreInline($pdo, $new_id, $data);
            
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
            if (empty($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Student ID is required for update']);
                exit;
            }

            $id = $_GET['id'];
            $data = json_decode(file_get_contents('php://input'), true);

            $updates = [];
            $params = [];
            $run_score_update = false; // Flag to check if we need to rescore
            $custom_data_to_save = [];

            // 1. Define updates for Built-in fields
            if (isset($data['full_name'])) { $updates[] = 'full_name = ?'; $params[] = $data['full_name']; }
            if (isset($data['email'])) { $updates[] = 'email = ?'; $params[] = $data['email']; }
            if (isset($data['phone'])) { $updates[] = 'phone = ?'; $params[] = $data['phone']; }
            if (isset($data['status'])) { $updates[] = 'status = ?'; $params[] = $data['status']; }
            
            // 2. Define updates for Scoring/Standard fields
            if (isset($data['course_interested_id'])) { 
                 $updates[] = 'course_interested_id = ?'; $params[] = $data['course_interested_id'] ? (int)$data['course_interested_id'] : null;
                 $run_score_update = true; 
            }
            if (isset($data['lead_source'])) { 
                 $updates[] = 'lead_source = ?'; $params[] = $data['lead_source']; 
                 $run_score_update = true; 
            }
            if (isset($data['qualification'])) { 
                 $updates[] = 'qualification = ?'; $params[] = $data['qualification']; 
                 $run_score_update = true; 
            }
            if (isset($data['work_experience'])) { 
                 $updates[] = 'work_experience = ?'; $params[] = $data['work_experience']; 
                 $run_score_update = true; 
            }
            
            // 3. Handle Custom Data (merge and update)
            if (isset($data['custom_data'])) {
                $custom_data_to_save = $data['custom_data'];
                $updates[] = 'custom_data = ?';
                $params[] = json_encode($custom_data_to_save);
                $run_score_update = true;
            }

            if (empty($updates)) {
                http_response_code(400);
                echo json_encode(['error' => 'No valid fields provided for update']);
                exit;
            }

            $sql = "UPDATE students SET " . implode(', ', $updates) . " WHERE student_id = ?";
            $params[] = $id;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            // ** RESCORE LOGIC **
            if ($run_score_update) {
                calculateAndUpdateLeadScoreInline($pdo, (int)$id, $data);
            }
            
            echo json_encode(['message' => 'Student profile updated']);
            break;

        // --- DELETE ---
        case 'DELETE':
            // Logic for deleting a student (still not implemented)
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