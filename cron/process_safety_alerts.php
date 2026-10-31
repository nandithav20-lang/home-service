<?php
// ========================================================
// SafeVisit Server-Side Missed Check-In Monitor
// File: cron/process_safety_alerts.php
// Suitable for Windows Task Scheduler or cron job:
// C:\xampp\php\php.exe -f C:\xampp\htdocs\home-service-app\cron\process_safety_alerts.php
// ========================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/safety_functions.php';

$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    // If run via web, restrict to admin or secret token
    if (!isLoggedIn() || ($_SESSION['user_role'] ?? '') !== 'admin') {
        $token = $_GET['token'] ?? '';
        $expectedToken = getenv('CRON_SECRET_TOKEN') ?: 'homeservice_cron_secret_2026';
        if ($token !== $expectedToken) {
            http_response_code(403);
            die("Access Denied. Run via CLI or provide valid cron token.");
        }
    }
}

$pdo = getDB();
ensureSafetyTablesExist($pdo);

$now = date('Y-m-d H:i:s');
echo "[" . date('Y-m-d H:i:s') . "] Starting SafeVisit automated check-in inspection...\n";

// Query active sessions where checkin is overdue beyond grace period
$sql = "SELECT s.*, u.name as customer_name, u.phone as customer_phone,
               p_user.name as provider_name, p_user.phone as provider_phone
        FROM safevisit_sessions s
        JOIN users u ON s.customer_id = u.id
        LEFT JOIN providers p ON s.provider_id = p.id
        LEFT JOIN users p_user ON p.user_id = p_user.id
        WHERE s.session_status = 'active'
          AND s.next_checkin_due IS NOT NULL
          AND NOW() > DATE_ADD(s.next_checkin_due, INTERVAL s.grace_period_mins MINUTE)";

$stmt = $pdo->query($sql);
$overdueSessions = $stmt->fetchAll();
$processedCount = 0;

foreach ($overdueSessions as $session) {
    $sessionId = (int)$session['id'];
    $customerId = (int)$session['customer_id'];
    $bookingId = (int)$session['booking_id'];
    $missedCount = (int)$session['missed_checkins_count'] + 1;

    echo "  -> Overdue detected: Session #{$sessionId} (Customer: {$session['customer_name']}, Booking #BK-{$bookingId})\n";

    // 1. Check if an active incident for this session was already created within last 30 mins to avoid duplicated flood
    $checkInc = $pdo->prepare("SELECT id FROM safety_incidents 
                              WHERE session_id = ? AND incident_type = 'missed_checkin' 
                              AND created_at >= DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
    $checkInc->execute([$sessionId]);
    $alreadyLogged = $checkInc->fetch();

    if (!$alreadyLogged) {
        $notes = "Automated System Alert: Customer {$session['customer_name']} missed scheduled safety check-in (Grace period: {$session['grace_period_mins']} mins elapsed). Missed count: {$missedCount}.";
        
        $incidentRes = triggerSafetyIncident(
            $customerId,
            'missed_checkin',
            $sessionId,
            $session['last_latitude'],
            $session['last_longitude'],
            $notes
        );
        echo "     Incident logged #{$incidentRes['incident_id']}. Trusted contacts alerted: {$incidentRes['contacts_notified']}.\n";
    }

    // Advance next_checkin_due by interval so it doesn't repeatedly trigger every minute
    $interval = (int)$session['checkin_interval_mins'];
    $nextDue = date('Y-m-d H:i:s', strtotime("+{$interval} minutes"));

    $uStmt = $pdo->prepare("UPDATE safevisit_sessions 
                           SET missed_checkins_count = missed_checkins_count + 1,
                               next_checkin_due = ? 
                           WHERE id = ?");
    $uStmt->execute([$nextDue, $sessionId]);
    $processedCount++;
}

echo "[" . date('Y-m-d H:i:s') . "] Processed {$processedCount} overdue SafeVisit sessions.\n";
?>
