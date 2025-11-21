<?php
// /api/v1/google_sync.php

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

// --- 1. Simple Google Auth Helper Class (No Composer needed) ---
class SimpleGoogleAuth {
    private $credentials;
    private $token;

    public function __construct($credentialsPath) {
        if (!file_exists($credentialsPath)) {
            throw new Exception("Credentials file not found.");
        }
        $this->credentials = json_decode(file_get_contents($credentialsPath), true);
    }

    public function getAccessToken() {
        $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $now = time();
        $payload = json_encode([
            'iss' => $this->credentials['client_email'],
            'sub' => $this->credentials['client_email'],
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
            'scope' => 'https://www.googleapis.com/auth/spreadsheets.readonly'
        ]);

        $base64UrlHeader = $this->base64UrlEncode($header);
        $base64UrlPayload = $this->base64UrlEncode($payload);
        $signatureInput = $base64UrlHeader . "." . $base64UrlPayload;

        $signature = '';
        if (!openssl_sign($signatureInput, $signature, $this->credentials['private_key'], 'SHA256')) {
            throw new Exception("Failed to sign JWT.");
        }
        $base64UrlSignature = $this->base64UrlEncode($signature);
        $jwt = $signatureInput . "." . $base64UrlSignature;

        // Exchange JWT for Access Token
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ]));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        if (isset($data['error'])) {
            throw new Exception("Google Auth Error: " . $data['error_description']);
        }

        return $data['access_token'];
    }

    private function base64UrlEncode($data) {
        return str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($data));
    }
}

// --- 2. Handle the Request ---
$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $sheetUrl = $input['sheet_url'] ?? '';

    if (empty($sheetUrl)) {
        throw new Exception("Spreadsheet URL is required.");
    }

    // Extract Spreadsheet ID from URL
    preg_match('/\/d\/([a-zA-Z0-9-_]+)/', $sheetUrl, $matches);
    if (!isset($matches[1])) {
        throw new Exception("Invalid Google Sheet URL.");
    }
    $spreadsheetId = $matches[1];

    // Get Token
    $auth = new SimpleGoogleAuth(__DIR__ . '/../credentials.json');
    $accessToken = $auth->getAccessToken();

    // Fetch Sheet Data (Assuming Sheet1 or first sheet, range A1:Z1000)
    $range = 'A1:Z1000'; 
    $url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/{$range}?access_token={$accessToken}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $sheetData = json_decode($response, true);

    if (isset($sheetData['error'])) {
        throw new Exception("Sheets API Error: " . $sheetData['error']['message']);
    }

    if (empty($sheetData['values'])) {
        throw new Exception("Sheet is empty.");
    }

    // --- 3. Process Data for Frontend (Same format as CSV parser) ---
    $rows = $sheetData['values'];
    $headers = array_shift($rows); // First row is headers
    $formattedData = [];

    foreach ($rows as $row) {
        // Skip empty rows
        if (empty($row)) continue;

        $item = [];
        foreach ($headers as $index => $header) {
            // Google API omits trailing empty cells, so use isset
            $item[$header] = isset($row[$index]) ? $row[$index] : ''; 
        }
        $formattedData[] = $item;
    }

    echo json_encode([
        'headers' => $headers,
        'data' => $formattedData
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>