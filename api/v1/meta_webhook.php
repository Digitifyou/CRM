<?php
// /api/v1/meta_webhook.php
// This endpoint receives real-time lead data from Facebook/Instagram Lead Ads.

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php'; // Database connection ($pdo)
require_once __DIR__ . '/students.php'; // Contains lead creation and scoring functions

// --- CONFIGURATION ---
// This is the string you provide to Meta/Facebook for verification purposes.
const META_VERIFY_TOKEN = 'EAA1OKJP7WowBP2fPE1rZAbEjZBsbhY67LmrDjAL0vwJqNkwU8a3XJY1n8ZAC3uppVj90F1ZCRZCGz1B0U8p3bhIqXMoZBvLVIAR1TMdZBoYEs8bn5NMwZCnJH3tWZBU0KVt4B9Y88r6yztkIhpv8fXp2uok1UTtv58VlfDo3rnHt60nED86fKxP4HrlfD1ZB7ZA'; 
const META_API_VERSION = 'v19.0';
const DEFAULT_LEAD_SOURCE = 'Meta Lead Ad';

$method = $_SERVER['REQUEST_METHOD'];

/**
 * Retrieves the Meta account configuration (Access Token/Form ID) from the database.
 * NOTE: We now fetch the token from the dedicated meta_accounts table.
 * @param PDO $pdo
 * @return array|null { access_token, ad_account_id, account_name }
 */
function getMetaAccountConfig($pdo) {
    $stmt = $pdo->prepare("
        SELECT access_token, ad_account_id, account_name 
        FROM meta_accounts 
        WHERE user_id = 1 AND is_active = TRUE LIMIT 1
    ");
    $stmt->execute();
    return $stmt->fetch();
}

/**
 * Fetches all active field mapping rules from the database.
 * @param PDO $pdo
 * @return array Array keyed by Meta field name (e.g., 'full_name') to CRM key (e.g., 'full_name').
 */
function getFieldMapping($pdo) {
    $stmt = $pdo->query("SELECT meta_field_name, crm_field_key, is_built_in FROM meta_field_mapping");
    $mapping = [];
    while ($row = $stmt->fetch()) {
        $mapping[$row['meta_field_name']] = $row['crm_field_key'];
    }
    return $mapping;
}


/**
 * Makes a live API call to Meta to retrieve the actual lead data using the leadgen_id.
 * @param string $leadgen_id The ID of the lead event from the webhook payload.
 * @param string $access_token The long-lived access token.
 * @return array|null The flattened lead data.
 */
function fetchLiveLeadData($leadgen_id, $access_token) {
    // We request the field_data and the ad_id/form_id for tracking
    $url = "https://graph.facebook.com/" . META_API_VERSION . "/$leadgen_id";
    
    // We request all fields needed for mapping
    $fields_requested = 'field_data,form_id,ad_id,created_time'; 
    
    $params = http_build_query([
        'fields' => $fields_requested,
        'access_token' => $access_token,
    ]);

    $response = @file_get_contents($url . '?' . $params);
    $data = json_decode($response, true);

    if (isset($data['error']) || $response === FALSE) {
        throw new Exception("Graph API Lead Fetch failed: " . ($data['error']['message'] ?? 'Network/Unknown Error'));
    }
    
    // This is the raw Meta data. We still need to map and flatten it below.
    return $data;
}


/**
 * Handles the Lead Processing and Creation logic (similar to students.php POST).
 * @param PDO $pdo
 * @param array $metaLeadData The raw data fetched from Meta Graph API.
 * @param array $fieldMapping The mapping rules from DB.
 */
function processAndCreateLead($pdo, $metaLeadData, $fieldMapping) {
    
    // 1. Map and Flatten Data
    $leadData = [
        'full_name' => null, 
        'email' => null, 
        'phone' => null, 
        'course_interested_id' => null,
        'lead_source' => DEFAULT_LEAD_SOURCE,
        'qualification' => null, 
        'work_experience' => null, 
        'custom_data' => []
    ];
    $custom_data = [];
    
    // Meta fields are in 'field_data'
    if (isset($metaLeadData['field_data'])) {
        foreach ($metaLeadData['field_data'] as $field) {
            $meta_field_name = $field['name'];
            $value = $field['values'][0] ?? null; // Assume single value for now
            
            $crm_field_key = $fieldMapping[$meta_field_name] ?? null;
            
            if ($crm_field_key) {
                // Check against built-in fields
                if (in_array($crm_field_key, ['full_name', 'email', 'phone', 'lead_source', 'qualification', 'work_experience'])) {
                    $leadData[$crm_field_key] = $value;
                } else if ($crm_field_key === 'course_interested_id') {
                    // Requires complex mapping/lookup, but for MVP, we assume the value is the ID.
                    $leadData[$crm_field_key] = $value; 
                } else {
                    // Custom fields stored in JSON
                    $custom_data[$crm_field_key] = $value;
                }
            }
        }
    }
    $leadData['custom_data'] = $custom_data;

    // Basic validation
    if (empty($leadData['full_name']) && empty($leadData['phone'])) {
         http_response_code(400); 
         echo json_encode(['error' => 'Lead missing essential contact fields (Full Name or Phone).']);
         exit;
    }
    
    // --- Determine Course ID and Fee (Requires clean value for 'course_interested_id') ---
    $course_id = $leadData['course_interested_id'] ? (int)$leadData['course_interested_id'] : null;
    $course_fee = 0;

    if ($course_id) {
         $fee_stmt = $pdo->prepare("SELECT standard_fee FROM courses WHERE course_id = ?");
         $fee_stmt->execute([$course_id]);
         $course_fee = $fee_stmt->fetchColumn() ?: 0;
    }
    
    // --- Create Student Record ---
    $sql = "INSERT INTO students 
                (full_name, email, phone, status, course_interested_id, lead_source, qualification, work_experience, custom_data) 
            VALUES 
                (?, ?, ?, 'inquiry', ?, ?, ?, ?, ?)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $leadData['full_name'],
        $leadData['email'] ?? null,
        $leadData['phone'] ?? null,
        $course_id,
        $leadData['lead_source'] ?? DEFAULT_LEAD_SOURCE,
        $leadData['qualification'] ?? null,
        $leadData['work_experience'] ?? null,
        json_encode($leadData['custom_data'])
    ]);
    
    $new_id = $pdo->lastInsertId();
    
    // --- Trigger Scoring ---
    calculateAndUpdateLeadScoreInline($pdo, $new_id, $leadData);
    
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
    // Note: Meta webhooks expect a 200/201 HTTP status code to acknowledge receipt.
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
    // 1. Handle Verification
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
    // 2. Handle Lead Intake
    $payload = json_decode(file_get_contents('php://input'), true);

    // Ensure payload structure is correct for a lead ad notification
    if (empty($payload) || !isset($payload['entry'][0]['changes'][0]['value']['leadgen_id'])) {
        // Acknowledge receipt, but log error (Meta requires 200 OK fast)
        http_response_code(200); 
        echo json_encode(['status' => 'acknowledged', 'message' => 'Payload received but missing leadgen_id']);
        exit;
    }
    
    // Extract leadgen_id and config
    $leadgen_id = $payload['entry'][0]['changes'][0]['value']['leadgen_id'];
    $config = getMetaAccountConfig($pdo);

    if (!$config || empty($config['access_token'])) {
        http_response_code(200); // Send OK to Meta, but fail ingestion
        echo json_encode(['status' => 'acknowledged', 'error' => 'Meta integration token missing or inactive.']);
        exit;
    }

    try {
        $access_token = $config['access_token'];
        $fieldMapping = getFieldMapping($pdo);
        
        // Fetch raw data from Meta using the token
        $metaLeadData = fetchLiveLeadData($leadgen_id, $access_token);

        // Process and create the lead
        processAndCreateLead($pdo, $metaLeadData, $fieldMapping);

    } catch (Exception $e) {
        // Log the failure but still return 200 OK to Meta to avoid re-sending webhooks
        http_response_code(200); 
        echo json_encode(['status' => 'acknowledged', 'error_ingestion' => 'Failed to process lead: ' . $e->getMessage()]);
        // For production, you would log the error to a file/service here
        error_log("Meta Webhook Ingestion Failed: " . $e->getMessage());
    }
}

?>