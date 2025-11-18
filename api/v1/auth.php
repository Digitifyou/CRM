<?php
// /api/v1/auth.php
// Handles user authentication (login)

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php'; // $pdo connection is available here

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

try {
    $data = json_decode(file_get_contents('php://input'), true);

    if (empty($data['username']) || empty($data['password'])) {
         http_response_code(400); 
         echo json_encode(['error' => 'Username and Password are required']);
         exit;
    }
    
    $username = $data['username'];
    $password = $data['password'];

    // 1. Fetch user data and hash
    $stmt = $pdo->prepare("
        SELECT user_id, password_hash, full_name, role, is_active 
        FROM users 
        WHERE username = ?
    ");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(401); 
        echo json_encode(['error' => 'Invalid username or password.']);
        exit;
    }
    
    // 2. Verify password hash
    // CRITICAL FIX: Trim the hash fetched from the DB to remove trailing whitespace
    $stored_hash = trim($user['password_hash']);

    if (!password_verify($password, $stored_hash)) {
        http_response_code(401); 
        echo json_encode(['error' => 'Invalid username or password.']);
        exit;
    }
    
    // 3. Check if account is active
    if (!$user['is_active']) {
        http_response_code(403); // Forbidden
        echo json_encode(['error' => 'Account is inactive.']);
        exit;
    }
    
    // 4. Authentication Successful - SET SESSION VARIABLES
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['logged_in'] = true; 

    http_response_code(200);
    echo json_encode([
        'message' => 'Login successful.',
        'user_id' => $user['user_id'],
        'full_name' => $user['full_name'],
        'role' => $user['role']
    ]);

} catch (\PDOException $e) {
    http_response_code(500);
    error_log("Auth DB Error: " . $e->getMessage()); 
    echo json_encode(['error' => 'An internal server error occurred.']);
}
?>