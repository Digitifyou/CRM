<?php
// /api/v1/setup.php
// Handles the creation of a new Academy (Tenant) and bootstrapping of initial data.

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php'; 

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);

    // CRITICAL VALIDATION
    if (empty($data['username']) || empty($data['full_name']) || empty($data['password']) || empty($data['academy_name']) || empty($data['academy_phone'])) {
         http_response_code(400); 
         echo json_encode(['error' => 'Academy Name, Academy Phone, Username, Owner Name, and Password are required']);
         exit;
    }
    
    $pdo->beginTransaction();

    // 1. Create the Owner User
    $password_hash = password_hash($data['password'], PASSWORD_DEFAULT);
    
    // Insert with temporary academy_id = 0
    $user_sql = "INSERT INTO users (username, password_hash, full_name, role, academy_id) VALUES (?, ?, ?, 'owner', 0)";
    $stmt = $pdo->prepare($user_sql);
    $stmt->execute([
        $data['username'],
        $password_hash,
        $data['full_name'],
    ]);
    
    $new_user_id = $pdo->lastInsertId();
    $new_academy_id = $new_user_id; // Use the owner's ID as the Academy ID

    // 2. Update the user with their new academy_id
    $pdo->prepare("UPDATE users SET academy_id = ? WHERE user_id = ?")
        ->execute([$new_academy_id, $new_user_id]);

    // 3. Store Academy Configuration (Conceptual INSERT into the new table)
    $config_sql = "INSERT INTO academy_config (academy_id, academy_name, academy_phone, academy_website) VALUES (?, ?, ?, ?)";
    $config_stmt = $pdo->prepare($config_sql);
    $config_stmt->execute([
        $new_academy_id,
        $data['academy_name'],
        $data['academy_phone'],
        $data['academy_website'] ?? null
    ]);


    // 4. Bootstrap Default Pipeline Stages for this new Academy ID
    $default_stages = [
        ['New Inquiry', 1],
        ['Contacted', 2],
        ['Counseled', 3],
        ['Demo Attended', 4],
        ['Payment Pending', 5],
    ];

    $pipeline_sql = "INSERT INTO pipeline_stages (stage_name, stage_order, academy_id) VALUES (?, ?, ?)";
    $pipeline_stmt = $pdo->prepare($pipeline_sql);

    foreach ($default_stages as $stage) {
        $pipeline_stmt->execute([$stage[0], $stage[1], $new_academy_id]);
    }
    
    $pdo->commit();
    
    http_response_code(201); 
    echo json_encode(['message' => 'Academy setup complete.', 'academy_id' => $new_academy_id]);

} catch (\PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($e->getCode() == 23000) { 
        http_response_code(409);
        echo json_encode(['error' => 'Username already exists.']);
        exit;
    }
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>