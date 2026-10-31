<?php
// ========================================================
// API: Update SafeVisit Session Location
// File: api/safety/update_location.php
// ========================================================

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/safety_functions.php';

if (!isLoggedIn() || ($_SESSION['user_role'] ?? '') !== 'customer') {
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

$customerId = (int)$_SESSION['user_id'];
$sessionId = (int)($input['session_id'] ?? 0);
$enabled = isset($input['enabled']) ? (int)$input['enabled'] : 1;
$lat = isset($input['lat']) && is_numeric($input['lat']) ? (float)$input['lat'] : null;
$lng = isset($input['lng']) && is_numeric($input['lng']) ? (float)$input['lng'] : null;

if (!$sessionId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Session ID required']);
    exit;
}

updateSessionLocation($sessionId, $customerId, $lat, $lng, $enabled);
echo json_encode(['success' => true, 'message' => 'Location settings updated']);
?>
