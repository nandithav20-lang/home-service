<?php
// ========================================================
// API: Trigger Safety SOS (Emergency / Silent)
// File: api/safety/trigger_sos.php
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
$type = in_array($input['type'] ?? '', ['silent_sos', 'sos']) ? $input['type'] : 'sos';
$sessionId = !empty($input['session_id']) ? (int)$input['session_id'] : null;
$lat = isset($input['lat']) && is_numeric($input['lat']) ? (float)$input['lat'] : null;
$lng = isset($input['lng']) && is_numeric($input['lng']) ? (float)$input['lng'] : null;
$notes = sanitize($input['notes'] ?? 'SOS button triggered from customer interface');

try {
    $result = triggerSafetyIncident($customerId, $type, $sessionId, $lat, $lng, $notes);
    echo json_encode([
        'success' => true,
        'incident_id' => $result['incident_id'],
        'type' => $type,
        'contacts_notified' => $result['contacts_notified'],
        'message' => 'Emergency dispatch logged. Trusted contacts notified.'
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
