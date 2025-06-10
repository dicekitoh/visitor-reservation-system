<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONSリクエストへの対応
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// POSTリクエストのみ受け付け
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// JSON入力を取得
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON']);
    exit;
}

// 必須フィールドのチェック
$required = ['userName', 'visitorName', 'date', 'time'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Missing field: $field"]);
        exit;
    }
}

// LINEWORKS API設定
$serviceAccount = '***REMOVED***';
$clientId = '***REMOVED***';
$clientSecret = '***REMOVED***';
$privateKeyPath = './private_20250529134836.key';
$userId = '38067785-e626-4e0c-18d6-05d56a82ed44';

try {
    // JWTライブラリの読み込み（簡易版）
    function base64url_encode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    // JWT作成（Node.js版と同じ形式）
    $header = json_encode(['typ' => 'JWT', 'alg' => 'RS256']);
    $now = time();
    $payload = json_encode([
        'iss' => $clientId,  // Node.js版と同じ：CLIENT_ID
        'sub' => $serviceAccount,  // Node.js版と同じ：SERVICE_ACCOUNT 
        'iat' => $now,
        'exp' => $now + 3600,
        'aud' => 'https://auth.worksmobile.com/oauth2/v2.0/token'
    ]);
    
    $base64Header = base64url_encode($header);
    $base64Payload = base64url_encode($payload);
    $signature_input = $base64Header . '.' . $base64Payload;
    
    // 秘密鍵でJWT署名
    if (!file_exists($privateKeyPath)) {
        throw new Exception('Private key file not found');
    }
    
    $privateKey = file_get_contents($privateKeyPath);
    if (!$privateKey) {
        throw new Exception('Failed to read private key');
    }
    
    $key = openssl_pkey_get_private($privateKey);
    if (!$key) {
        throw new Exception('Invalid private key');
    }
    
    openssl_sign($signature_input, $signature, $key, OPENSSL_ALGO_SHA256);
    $base64Signature = base64url_encode($signature);
    $jwt = $signature_input . '.' . $base64Signature;
    
    // アクセストークン取得
    $tokenUrl = 'https://auth.worksmobile.com/oauth2/v2.0/token';
    $tokenData = [
        'assertion' => $jwt,
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
        'scope' => 'calendar calendar.read'
    ];
    
    $tokenOptions = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($tokenData)
        ]
    ];
    
    $tokenContext = stream_context_create($tokenOptions);
    $tokenResponse = file_get_contents($tokenUrl, false, $tokenContext);
    
    if (!$tokenResponse) {
        throw new Exception('Failed to get access token');
    }
    
    $tokenResult = json_decode($tokenResponse, true);
    if (!isset($tokenResult['access_token'])) {
        throw new Exception('Invalid token response: ' . $tokenResponse);
    }
    
    $accessToken = $tokenResult['access_token'];
    
    // カレンダーイベント作成（LINEWORKS API公式形式）
    // 日時フォーマット（ISO 8601形式、秒あり）
    $startDateTime = $data['date'] . 'T' . $data['time'] . ':00';
    $endTime = date('H:i:s', strtotime($data['time'] . ' +30 minutes'));
    $endDateTime = $data['date'] . 'T' . $endTime;
    
    $eventData = [
        'eventComponents' => [
            [
                'summary' => '面会予約：' . $data['userName'] . ' ← ' . $data['visitorName'],
                'description' => "利用者：{$data['userName']}\n面会者：{$data['visitorName']}\n予約システムより自動登録",
                'start' => [
                    'dateTime' => $startDateTime,
                    'timeZone' => 'Asia/Tokyo'
                ],
                'end' => [
                    'dateTime' => $endDateTime,
                    'timeZone' => 'Asia/Tokyo'
                ],
                'visibility' => 'PUBLIC',
                'transparency' => 'OPAQUE'
            ]
        ]
    ];
    
    $apiUrl = "https://www.worksapis.com/v1.0/users/{$userId}/calendar/events";
    $apiOptions = [
        'http' => [
            'header' => "Authorization: Bearer {$accessToken}\r\n" .
                       "Content-Type: application/json\r\n",
            'method' => 'POST',
            'content' => json_encode($eventData),
            'ignore_errors' => true
        ]
    ];
    
    $apiContext = stream_context_create($apiOptions);
    $apiResponse = file_get_contents($apiUrl, false, $apiContext);
    
    // HTTPレスポンスヘッダーから状態確認
    $httpCode = 500;
    if (isset($http_response_header[0])) {
        preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $matches);
        $httpCode = isset($matches[1]) ? intval($matches[1]) : 500;
    }
    
    if (!$apiResponse || ($httpCode < 200 || $httpCode >= 300)) {
        $errorDetail = $apiResponse ? $apiResponse : 'No response from API';
        throw new Exception("Calendar API Error (HTTP {$httpCode}): " . $errorDetail);
    }
    
    $apiResult = json_decode($apiResponse, true);
    if (!$apiResult) {
        throw new Exception('Invalid API response format');
    }
    
    // イベントIDを取得
    $eventId = null;
    if (isset($apiResult['eventComponents']) && isset($apiResult['eventComponents'][0]['eventId'])) {
        $eventId = $apiResult['eventComponents'][0]['eventId'];
    }
    
    // 成功レスポンス
    echo json_encode([
        'success' => true,
        'message' => 'カレンダーに予約が正常に登録されました',
        'eventId' => $eventId,
        'eventData' => $eventData
    ]);
    
} catch (Exception $e) {
    // エラーレスポンス
    error_log("LINEWORKS API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'details' => 'LINEWORKS API連携でエラーが発生しました'
    ]);
}
?>