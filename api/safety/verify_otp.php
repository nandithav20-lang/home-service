<?php
// ========================================================
// API: Verify Arrival / Completion OTP
// File: api/safety/verify_otp.php
// ========================================================

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/safety_functions.php';

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$csrfToken = $input['csrf_token'] ?? '';

if (!verifyCsrfToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit;
}

$sessionId = (int)($input['session_id'] ?? 0);
$otp = trim($input['otp'] ?? '');
$type = $input['type'] ?? 'arrival'; // 'arrival' or 'completion'
$userId = (int)$_SESSION['user_id'];

if (!$sessionId || empty($otp)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Session ID and OTP are required']);
    exit;
}

if ($type === 'completion') {
    $res = verifyCompletionOtpSession($sessionId, $otp, $userId);
} else {
    $res = verifyArrivalOtpSession($sessionId, $otp, $userId);
}

echo json_encode($res);
?>
