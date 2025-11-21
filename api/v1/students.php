<?php
// /api/v1/students.php

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php'; // $pdo connection

// --- DEFINITIONS (Available to other files) ---

// Standard Score Points Mapping for dynamic weights
const SCORE_POINTS = [
    'Low' => 25,
    'Medium' => 50,
    'High' => 100
];
const MAX_SCORE_PER_FIELD = 100; 

function getScoreValue($level) {
    return SCORE_POINTS[$level] ?? 0;
}

function getScoringConfiguration($pdo) {
    $config = [];

    // 1. Fetch Custom Field scoring rules
    try {
        $custom_fields_stmt = $pdo->query("SELECT field_key, is_score_field, scoring_rules FROM custom_fields WHERE is_score_field = TRUE");
        while ($row = $custom_fields_stmt->fetch()) {
            $config[$row['field_key']] = $row;
        }

        // 2. Fetch System Field configuration
        $system_fields_stmt = $pdo->query("SELECT field_key, is_score_field, scoring_rules FROM system_field_config WHERE is_score_field = TRUE");
        while ($row = $system_fields_stmt->fetch()) {
            $config[$row['field_key']] = $row;
        }
    } catch (PDOException $e) {
        // Fail silently if tables don't exist yet (bootstrapping)
    }
    
    $default_scoring_rules = '{"High": "ANY"}'; 
    $default_keys = ['course_interested_id', 'lead_source', 'qualification', 'work_experience'];
    foreach ($default_keys as $key) {
        if (!isset($config[$key])) {
             $config[$key] = ['field_key' => $key, 'is_score_field' => 1, 'scoring_rules' => $default_scoring_rules];
        }
    }
    return $config;
}

function calculateAndUpdateLeadScoreInline($pdo, $student_id, $data = []) {
    $stmt = $pdo->prepare("SELECT s.course_interested_id, s.lead_source, s.qualification, s.work_experience, s.custom_data, c.standard_fee 
                           FROM students s LEFT JOIN courses c ON s.course_interested_id = c.course_id WHERE s.student_id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    if (!$student) return 0;
    
    $fields_to_check = [
        'course_interested_id' => $data['course_interested_id'] ?? $student['course_interested_id'],
        'lead_source' => $data['lead_source'] ?? $student['lead_source'],
        'qualification' => $data['qualification'] ?? $student['qualification'],
        'work_experience' => $data['work_experience'] ?? $student['work_experience'],
    ];
    
    $custom_data = json_decode($student['custom_data'] ?? '{}', true);
    if (!is_array($custom_data)) $custom_data = [];

    $scoring_config = getScoringConfiguration($pdo);
    $total_score_obtained = 0;
    $total_max_possible = 0;
    $all_scoring_fields = array_merge($fields_to_check, $custom_data);

    foreach ($scoring_config as $config) {
        $field_key = $config['field_key'];
        $student_value = $all_scoring_fields[$field_key] ?? null;
        
        if ($config['is_score_field'] == 1) {
            $total_max_possible += MAX_SCORE_PER_FIELD; 
            if (!empty($student_value)) {
                $rules_json = $config['scoring_rules'] ?? null;
                $rules = $rules_json ? json_decode($rules_json, true) : [];
                $student_value_lower = strtolower((string)$student_value);
                $matched_level = null;
                $base_score_applied = false;

                foreach (['High', 'Medium', 'Low'] as $level) {
                    if (isset($rules[$level])) {
                        $configured_values = array_map('strtolower', array_map('trim', explode(',', $rules[$level])));
                        if (in_array($student_value_lower, $configured_values) || in_array('any', $configured_values)) {
                             $matched_level = $level;
                             break; 
                        }
                    }
                }
                
                if ($matched_level) {
                    $total_score_obtained += getScoreValue($matched_level);
                    $base_score_applied = true;
                } else if (isset($rules['default'])) {
                     $total_score_obtained += getScoreValue($rules['default']);
                     $base_score_applied = true;
                }

                if ($field_key === 'course_interested_id' && isset($student['standard_fee']) && $student['standard_fee'] > 30000 && $base_score_applied) {
                     $total_score_obtained += 10;
                }
            }
        }
    }

    $final_score = ($total_max_possible === 0) ? 0 : round(($total_score_obtained / $total_max_possible) * 100);
    $final_score = min(100, max(0, $final_score)); 

    $update_stmt = $pdo->prepare("UPDATE students SET lead_score = ? WHERE student_id = ?");
    $update_stmt->execute([$final_score, $student_id]);

    return $final_score;
}

// --- EXECUTION LOGIC (Only runs if this file is called directly) ---
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    
    $method = $_SERVER['REQUEST_METHOD'];
    
    // --- PERMISSION CHECK ---
    $current_user_id = $_SESSION['user_id'] ?? 0;
    $current_user_role = $_SESSION['role'] ?? 'counselor';

    try {
        switch ($method) {
            case 'GET':
                if (isset($_GET['id'])) {
                    $id = $_GET['id'];
                    $stmt = $pdo->prepare("SELECT s.*, c.course_name FROM students s LEFT JOIN courses c ON s.course_interested_id = c.course_id WHERE s.student_id = ?");
                    $stmt->execute([$id]);
                    $student = $stmt->fetch();
                    
                    if (!$student) {
                        http_response_code(404);
                        echo json_encode(['error' => 'Student not found']);
                        exit;
                    }
                    $enroll_stmt = $pdo->prepare("SELECT e.enrollment_id, e.total_fee_agreed, e.total_fee_paid, e.balance_due, e.status, e.created_at, c.course_name, p.stage_name AS pipeline_stage FROM enrollments e LEFT JOIN courses c ON e.course_id = c.course_id LEFT JOIN pipeline_stages p ON e.pipeline_stage_id = p.stage_id WHERE e.student_id = ? ORDER BY e.created_at DESC");
                    $enroll_stmt->execute([$id]);
                    $student['enrollments'] = $enroll_stmt->fetchAll();
                    $student['custom_data'] = json_decode($student['custom_data'] ?? '{}', true);
                    
                    echo json_encode($student);
                } else {
                    $sql = "SELECT DISTINCT s.student_id, s.full_name, s.phone, s.status, s.lead_score, s.created_at, c.course_name AS course_interested FROM students s LEFT JOIN courses c ON s.course_interested_id = c.course_id LEFT JOIN enrollments e ON s.student_id = e.student_id WHERE 1=1";
                    $params = [];
                    if ($current_user_role === 'counselor') {
                        $sql .= " AND e.assigned_to_user_id = ?";
                        $params[] = $current_user_id;
                    }
                    $sql .= " ORDER BY s.created_at DESC";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $students = $stmt->fetchAll();
                    echo json_encode($students);
                }
                break;

            case 'POST':
                $data = json_decode(file_get_contents('php://input'), true);
                if (empty($data['full_name']) || empty($data['phone'])) {
                     http_response_code(400); echo json_encode(['error' => 'Full Name and Phone are required']); exit;
                }
                
                // FIXED: Added Academy ID
                $academy_id = 12;
                
                $custom_data_json = json_encode($data['custom_data'] ?? []);
                
                // FIXED: Added academy_id to INSERT
                $sql = "INSERT INTO students (full_name, email, phone, status, course_interested_id, lead_source, qualification, work_experience, custom_data, academy_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $data['full_name'], $data['email'] ?? null, $data['phone'], $data['status'] ?? 'inquiry',
                    $data['course_interested_id'] ? (int)$data['course_interested_id'] : null,
                    $data['lead_source'] ?? null, $data['qualification'] ?? null, $data['work_experience'] ?? null, 
                    $custom_data_json,
                    $academy_id // Passing 12 here
                ]);
                $new_id = $pdo->lastInsertId();
                
                calculateAndUpdateLeadScoreInline($pdo, $new_id, $data);
                
                // Auto-assign Enrollment
                $stage_stmt = $pdo->query("SELECT stage_id FROM pipeline_stages ORDER BY stage_order ASC LIMIT 1");
                $first_stage_id = $stage_stmt->fetchColumn();
                $assigned_to_user_id = $current_user_id; 
                $fee = 0;
                if (!empty($data['course_interested_id'])) {
                     $fee_stmt = $pdo->prepare("SELECT standard_fee FROM courses WHERE course_id = ?");
                     $fee_stmt->execute([(int)$data['course_interested_id']]);
                     $fee = $fee_stmt->fetchColumn();
                }
                if ($first_stage_id) {
                     $enroll_stmt = $pdo->prepare("INSERT INTO enrollments (student_id, course_id, assigned_to_user_id, pipeline_stage_id, total_fee_agreed) VALUES (?, ?, ?, ?, ?)");
                     $enroll_stmt->execute([$new_id, $data['course_interested_id'] ? (int)$data['course_interested_id'] : null, $assigned_to_user_id, $first_stage_id, $fee ?: 0]);
                }
                http_response_code(201); echo json_encode(['message' => 'Student created', 'student_id' => $new_id]);
                break;

            case 'PUT':
                if (empty($_GET['id'])) { http_response_code(400); echo json_encode(['error' => 'ID required']); exit; }
                $id = $_GET['id'];
                $data = json_decode(file_get_contents('php://input'), true);
                $updates = []; $params = []; $run_score_update = false; $custom_data_to_save = [];

                if (isset($data['full_name'])) { $updates[] = 'full_name = ?'; $params[] = $data['full_name']; }
                if (isset($data['email'])) { $updates[] = 'email = ?'; $params[] = $data['email']; }
                if (isset($data['phone'])) { $updates[] = 'phone = ?'; $params[] = $data['phone']; }
                if (isset($data['status'])) { $updates[] = 'status = ?'; $params[] = $data['status']; }
                if (isset($data['course_interested_id'])) { $updates[] = 'course_interested_id = ?'; $params[] = $data['course_interested_id'] ? (int)$data['course_interested_id'] : null; $run_score_update = true; }
                if (isset($data['lead_source'])) { $updates[] = 'lead_source = ?'; $params[] = $data['lead_source']; $run_score_update = true; }
                if (isset($data['qualification'])) { $updates[] = 'qualification = ?'; $params[] = $data['qualification']; $run_score_update = true; }
                if (isset($data['work_experience'])) { $updates[] = 'work_experience = ?'; $params[] = $data['work_experience']; $run_score_update = true; }
                if (isset($data['custom_data'])) { $updates[] = 'custom_data = ?'; $params[] = json_encode($data['custom_data']); $run_score_update = true; }

                if (empty($updates)) { http_response_code(400); echo json_encode(['error' => 'No valid fields']); exit; }
                $sql = "UPDATE students SET " . implode(', ', $updates) . " WHERE student_id = ?";
                $params[] = $id;
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                if ($run_score_update) calculateAndUpdateLeadScoreInline($pdo, (int)$id, $data);
                echo json_encode(['message' => 'Updated']);
                break;

            case 'DELETE':
                // SECURITY: Only Admins or Owners can delete leads
                if (!in_array($current_user_role, ['admin', 'owner'])) {
                    http_response_code(403);
                    echo json_encode(['error' => 'Permission denied. Only Admins can delete leads.']);
                    exit;
                }

                if (empty($_GET['id'])) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Student ID is required']);
                    exit;
                }
                
                $id = $_GET['id'];
                $stmt = $pdo->prepare("DELETE FROM students WHERE student_id = ?");
                $stmt->execute([$id]);
                echo json_encode(['message' => 'Student deleted successfully']);
                break;

            default:
                http_response_code(405); echo json_encode(['error' => 'Method Not Allowed']); break;
        }
    } catch (\PDOException $e) {
        http_response_code(500); echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
}
?>