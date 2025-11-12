<?php
// /api/v1/meta_ads.php
// SIMULATES API calls to fetch Ads data.

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php'; 

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

/**
 * Retrieves the Meta account configuration from the database.
 * @param PDO $pdo
 * @return array|null
 */
function getMetaAccountConfig($pdo) {
    // In a real system, you would use the logged-in user's ID here. 
    // For MVP, we assume user_id 1 is the admin setting up the account.
    $stmt = $pdo->prepare("SELECT * FROM meta_accounts WHERE user_id = 1 AND is_active = TRUE LIMIT 1");
    $stmt->execute();
    return $stmt->fetch();
}


/**
 * SIMULATES fetching real-time data from Meta's Graph API.
 * This is where the cURL/Guzzle calls to Facebook would happen.
 * @param string $accessToken
 * @param string $adAccountId
 * @return array
 */
function simulateMetaAdsData($adAccountId) {
    // --- SIMULATED DATA ---
    return [
        'account_name' => 'Training Academy Ad Account',
        'kpis' => [
            'spend' => 15432,
            'leads' => 250,
            'cpl' => 61.73,
            'enrollment_rate' => 3.2
        ],
        'campaigns' => [
            [
                'name' => 'Jan - Full Stack Lead Gen',
                'status' => 'ACTIVE',
                'spend' => 10000,
                'leads' => 150,
                'cpl' => 66.67,
                'adsets' => [
                    ['name' => 'Remarketing - Website', 'status' => 'ACTIVE', 'spend' => 3000, 'leads' => 50, 'cpl' => 60.00],
                    ['name' => 'Lookalike - Top 5%', 'status' => 'ACTIVE', 'spend' => 7000, 'leads' => 100, 'cpl' => 70.00]
                ]
            ],
            [
                'name' => 'Q4 - Digital Marketing',
                'status' => 'PAUSED',
                'spend' => 5432,
                'leads' => 100,
                'cpl' => 54.32,
                'adsets' => [
                    ['name' => 'Interest - Gen Z', 'status' => 'PAUSED', 'spend' => 5432, 'leads' => 100, 'cpl' => 54.32]
                ]
            ]
        ]
    ];
}


try {
    $config = getMetaAccountConfig($pdo);

    if (!$config) {
        http_response_code(403);
        echo json_encode(['error' => 'No active Meta account connected in database.']);
        exit;
    }

    // Simulate successful retrieval
    // In a real app, you would pass $config['access_token'] and $config['ad_account_id']
    $data = simulateMetaAdsData($config['ad_account_id']);

    echo json_encode($data);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Integration error: ' . $e->getMessage()]);
}