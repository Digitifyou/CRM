<?php
// /api/v1/google_sync.php

header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';

// --- 1. Simple Google Auth Helper Class ---
class SimpleGoogleAuth {
    private $credentials;

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
        if (curl_errno($ch)) {
            throw new Exception('Curl error: ' . curl_error($ch));
        }
        curl_close($ch);

        $data = json_decode($response, true);
        if (isset($data['error'])) {
            throw new Exception("Google Auth Error: " . ($data['error_description'] ?? $data['error']));
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
    $action = $input['action'] ?? 'get_data'; // 'get_sheets' or 'get_data'
    $sheetName = $input['sheet_name'] ?? '';

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

    // --- ACTION: GET SHEET NAMES ---
    if ($action === 'get_sheets') {
        $url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}?fields=sheets.properties.title&access_token={$accessToken}";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        
        if (isset($data['error'])) {
            throw new Exception("Google API Error: " . ($data['error']['message'] ?? 'Unknown error'));
        }

        $sheets = [];
        if (!empty($data['sheets'])) {
            foreach ($data['sheets'] as $sheet) {
                $sheets[] = $sheet['properties']['title'];
            }
        }
        
        echo json_encode(['sheets' => $sheets]);
        exit;
    }

    // --- ACTION: GET DATA (Default) ---
    
    // If sheet name provided, use it. Otherwise default to first sheet (A1:Z1000)
    $range = 'A1:Z1000';
    if (!empty($sheetName)) {
        // Wrap sheet name in quotes if it contains spaces
        $range = "'" . $sheetName . "'!A1:Z1000";
    }

    $url = "https://sheets.googleapis.com/v4/spreadsheets/{$spreadsheetId}/values/" . urlencode($range) . "?access_token={$accessToken}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    $sheetData = json_decode($response, true);

    if (isset($sheetData['error'])) {
        throw new Exception("Sheets API Error: " . ($sheetData['error']['message'] ?? 'Unknown error'));
    }

    if (empty($sheetData['values'])) {
        throw new Exception("Sheet is empty or range not found.");
    }

    // Process Data
    $rows = $sheetData['values'];
    $headers = array_shift($rows); // First row is headers
    $formattedData = [];

    foreach ($rows as $row) {
        if (empty($row)) continue;
        $item = [];
        foreach ($headers as $index => $header) {
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