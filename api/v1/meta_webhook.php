<?php
// /api/v1/meta_webhook.php
// This endpoint receives real-time lead data from Facebook/Instagram Lead Ads.

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php'; // Database connection ($pdo)
require_once __DIR__ . '/students.php'; // Contains lead creation and scoring functions

// --- CONFIGURATION ---
// This is the string you provide to Meta/Facebook for verification purposes.
const META_VERIFY_TOKEN = 'YOUR_SECRET_VERIFICATION_TOKEN_123'; 
const DEFAULT_LEAD_SOURCE = 'Meta';

$method = $_SERVER['REQUEST_METHOD'];

/**
 * Retrieves the Meta configuration (Access Token/Form ID) from the database.
 * @param PDO $pdo
 * @return array|null
 */
function getMetaConfig($pdo) {
    $stmt = $pdo->prepare("SELECT * FROM integrations WHERE platform = 'meta' AND is_active = TRUE");
    $stmt->execute();
    return $stmt->fetch();
}

/**
 * Finds the correct course ID based on the course name provided by Meta.
 * (This is a simplified lookup and should be improved in a full system).
 * @param PDO $pdo
 * @param string $courseName
 * @return int|null
 */
function findCourseIdByApproximateName($pdo, $courseName) {
    // Attempt a basic case-insensitive match
    $stmt = $pdo->prepare("SELECT course_id FROM courses WHERE LOWER(course_name) LIKE LOWER(?) LIMIT 1");
    $stmt->execute(["%$courseName%"]);
    return $stmt->fetchColumn() ?: null;
}

/**
 * Handles the Lead Processing and Creation logic (similar to students.php POST).
 * @param PDO $pdo
 * @param array $leadData The processed and flattened lead data.
 */
function processAndCreateLead($pdo, $leadData) {
    
    // Basic validation
    if (empty($leadData['full_name']) && empty($leadData['phone'])) {
         http_response_code(400); 
         echo json_encode(['error' => 'Lead missing essential contact fields.']);
         exit;
    }
    
    // --- Determine Course ID ---
    $course_id = null;
    $course_fee = 0;

    if (!empty($leadData['course_interested'])) {
        $course_id = findCourseIdByApproximateName($pdo, $leadData['course_interested']);
        
        if ($course_id) {
             $fee_stmt = $pdo->prepare("SELECT standard_fee FROM courses WHERE course_id = ?");
             $fee_stmt->execute([(int)$course_id]);
             $course_fee = $fee_stmt->fetchColumn() ?: 0;
        }
    }
    
    // --- Create Student Record ---
    $sql = "INSERT INTO students 
                (full_name, email, phone, status, course_interested_id, lead_source, qualification, work_experience) 
            VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $leadData['full_name'],
        $leadData['email'] ?? null,
        $leadData['phone'] ?? null,
        'inquiry',
        $course_id,
        $leadData['lead_source'] ?? DEFAULT_LEAD_SOURCE,
        $leadData['qualification'] ?? null,
        $leadData['work_experience'] ?? null
    ]);
    
    $new_id = $pdo->lastInsertId();
    
    // --- Trigger Scoring ---
    // Pass the newly created data structure to the inline scorer (defined in students.php)
    // We pass a minimal data array for the scorer to use instantly.
    $scoring_data = [
        'course_interested_id' => $course_id,
        'lead_source' => $leadData['lead_source'] ?? DEFAULT_LEAD_SOURCE,
        'qualification' => $leadData['qualification'] ?? null,
        'work_experience' => $leadData['work_experience'] ?? null
    ];
    calculateAndUpdateLeadScoreInline($pdo, $new_id, $scoring_data);
    
    // --- Create Enrollment Record ---
    $stage_stmt = $pdo->query("SELECT stage_id FROM pipeline_stages ORDER BY stage_order ASC LIMIT 1");
    $first_stage_id = $stage_stmt->fetchColumn();
    $admin_user_id = 1; 

    if ($first_stage_id) {
         $enroll_sql = "INSERT INTO enrollments 
                            (student_id, course_id, assigned_to_user_id, pipeline_stage_id, total_fee_agreed) 
                        VALUES 
                            (?, ?, ?, ?, ?)";
         $enroll_stmt = $pdo->prepare($enroll_sql);
         $enroll_stmt->execute([
            $new_id,
            $course_id,
            $admin_user_id,
            $first_stage_id,
            $course_fee
         ]);
    }
    
    http_response_code(201);
    echo json_encode(['success' => true, 'message' => 'Lead created and scored.', 'student_id' => $new_id]);

} catch (\PDOException $e) {
    if ($e->getCode() == 23000) { 
        http_response_code(409);
        echo json_encode(['error' => 'Lead already exists (Duplicate phone or email).']);
        exit;
    }
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

// --- MAIN WEBHOOK ROUTER ---

if ($method === 'GET') {
    // 1. Handle Verification (Meta sends a GET request with specific params)
    if (isset($_GET['hub_mode']) && $_GET['hub_mode'] === 'subscribe' && isset($_GET['hub_verify_token'])) {
        if ($_GET['hub_verify_token'] === META_VERIFY_TOKEN) {
            http_response_code(200);
            echo $_GET['hub_challenge'];
            exit;
        } else {
            http_response_code(403);
            echo json_encode(['error' => 'Verification token mismatch.']);
            exit;
        }
    }
    http_response_code(400);
    echo json_encode(['error' => 'Invalid GET request.']);
    exit;
}


if ($method === 'POST') {
    // 2. Handle Lead Intake (Meta sends a POST request)
    $payload = json_decode(file_get_contents('php://input'), true);

    if (empty($payload) || !isset($payload['entry'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid POST payload.']);
        exit;
    }

    $config = getMetaConfig($pdo);

    if (!$config || $config['is_active'] != 1) {
        http_response_code(403);
        echo json_encode(['error' => 'Meta integration is not configured or inactive.']);
        exit;
    }

    // Since Meta payloads are complex, we need a function to fetch the lead data.
    // This requires another API call to Facebook's Graph API, which is outside of this MVP scope.
    // We will simulate the data flattening based on expected form fields.

    // A REAL IMPLEMENTATION requires calling the Graph API to get the lead details.
    // For this MVP, we SIMULATE receiving the flattened form data.

    $flattenedLeadData = [
        'full_name' => 'Meta Lead Example',
        'phone' => '9999912345',
        'email' => 'meta_lead@example.com',
        'course_interested' => 'Python Full Stack',
        'qualification' => 'BSc',
        'work_experience' => 'Fresher',
        'lead_source' => DEFAULT_LEAD_SOURCE
    ];

    // Assuming the processing is successful:
    processAndCreateLead($pdo, $flattenedLeadData);
}

?>