<?php
// ========================================================
// Smart Customer Safety & Emergency Protection Functions
// File: includes/safety_functions.php
// ========================================================

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/safety_notifications.php';

// --------------------------------------------------------
// CSRF Security Helpers
// --------------------------------------------------------
if (!function_exists('getCsrfToken')) {
    function getCsrfToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('verifyCsrfToken')) {
    function verifyCsrfToken($token) {
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

// --------------------------------------------------------
// Auto-Ensure Safety Schema (Resilient Auto-Setup)
// --------------------------------------------------------
function ensureSafetyTablesExist($pdo) {
    static $ensured = false;
    if ($ensured) return;

    try {
        // Run light existence check
        $check = $pdo->query("SHOW TABLES LIKE 'safevisit_sessions'")->fetch();
        if ($check) {
            $ensured = true;
            return;
        }

        $sqlPath = __DIR__ . '/../database/safety_module.sql';
        if (file_exists($sqlPath)) {
            $sql = file_get_contents($sqlPath);
            // Split into individual queries
            $pdo->exec($sql);
        }
        $ensured = true;
    } catch (Exception $e) {
        // Silently continue if permissions/syntax differ, pages will handle gracefully
        error_log("Safety schema auto-check: " . $e->getMessage());
    }
}

// --------------------------------------------------------
// Provider Verification & Verified Badges
// --------------------------------------------------------
function getProviderVerificationStatus($providerId) {
    $pdo = getDB();
    ensureSafetyTablesExist($pdo);

    try {
        $stmt = $pdo->prepare("SELECT p.status as provider_status, 
                                      pv.status as verification_status,
                                      pv.id_type, pv.id_number_masked, pv.reviewed_at
                               FROM providers p
                               LEFT JOIN provider_verifications pv ON p.id = pv.provider_id
                               WHERE p.id = ? LIMIT 1");
        $stmt->execute([$providerId]);
        $res = $stmt->fetch();
        if (!$res) return 'pending';

        // Prefer provider_verifications status if set, else provider table status
        return $res['verification_status'] ?? $res['provider_status'] ?? 'pending';
    } catch (Exception $e) {
        return 'pending';
    }
}

function isProviderApproved($providerId) {
    return getProviderVerificationStatus($providerId) === 'approved';
}

function renderVerifiedBadge($statusOrApproved, $compact = false) {
    $status = is_bool($statusOrApproved) 
        ? ($statusOrApproved ? 'approved' : 'pending')
        : (string)$statusOrApproved;

    switch ($status) {
        case 'approved':
            $badge = '<span class="safety-badge badge-verified" title="Identity Verified & Background Checked. Note: Verification verifies credentials and does not guarantee safety."><i class="fa-solid fa-shield-check"></i> ' . ($compact ? 'Verified' : 'Verified Pro') . '</span>';
            break;
        case 'suspended':
            $badge = '<span class="safety-badge badge-suspended" title="Account suspended due to safety or policy review"><i class="fa-solid fa-ban"></i> Suspended</span>';
            break;
        case 'rejected':
            $badge = '<span class="safety-badge badge-rejected" title="Verification declined"><i class="fa-solid fa-triangle-exclamation"></i> Unverified</span>';
            break;
        default:
            $badge = '<span class="safety-badge badge-pending" title="Verification review in progress"><i class="fa-regular fa-clock"></i> Verification Pending</span>';
            break;
    }
    return $badge;
}

function getSafetyDisclaimer() {
    return "Verification confirms that government-issued identification was reviewed by the platform. Identity verification is a trust credential and does not predict future conduct or eliminate all risk. For full protection, always use the SafeVisit check-in timer, verify arrival OTPs, and add trusted emergency contacts.";
}

// --------------------------------------------------------
// Trusted Emergency Contacts
// --------------------------------------------------------
function getTrustedContacts($customerId) {
    $pdo = getDB();
    ensureSafetyTablesExist($pdo);
    try {
        $stmt = $pdo->prepare("SELECT * FROM trusted_contacts WHERE customer_id = ? ORDER BY is_primary DESC, id ASC");
        $stmt->execute([$customerId]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

function addTrustedContact($customerId, $name, $relationship, $phone, $isPrimary = 0) {
    $pdo = getDB();
    ensureSafetyTablesExist($pdo);

    $name = trim($name);
    $relationship = trim($relationship);
    $phone = preg_replace('/[^0-9+]/', '', trim($phone));

    if (empty($name) || strlen($name) < 2) {
        throw new InvalidArgumentException("Contact name must be at least 2 characters.");
    }
    if (empty($phone) || strlen($phone) < 10) {
        throw new InvalidArgumentException("Please enter a valid 10-digit phone number.");
    }

    if ($isPrimary) {
        $pdo->prepare("UPDATE trusted_contacts SET is_primary = 0 WHERE customer_id = ?")->execute([$customerId]);
    }

    $stmt = $pdo->prepare("INSERT INTO trusted_contacts (customer_id, name, relationship, phone, is_primary) VALUES (?, ?, ?, ?, ?)");
    return $stmt->execute([$customerId, $name, $relationship, $phone, $isPrimary ? 1 : 0]);
}

function deleteTrustedContact($customerId, $contactId) {
    $pdo = getDB();
    ensureSafetyTablesExist($pdo);
    $stmt = $pdo->prepare("DELETE FROM trusted_contacts WHERE id = ? AND customer_id = ?");
    return $stmt->execute([$contactId, $customerId]);
}

// --------------------------------------------------------
// SafeVisit Session & OTP Engine
// --------------------------------------------------------
function generateSecureOtp($length = 6) {
    $min = (int)pow(10, $length - 1);
    $max = (int)pow(10, $length) - 1;
    return (string)random_int($min, $max);
}

function getActiveSafeVisitSession($customerId, $bookingId = null) {
    $pdo = getDB();
    ensureSafetyTablesExist($pdo);

    try {
        $sql = "SELECT s.*, b.booking_date, b.booking_time, b.service_address, b.city,
                       srv.name as service_name,
                       u_prov.name as provider_name, u_prov.phone as provider_phone,
                       p.status as provider_verification_status
                FROM safevisit_sessions s
                JOIN bookings b ON s.booking_id = b.id
                JOIN services srv ON b.service_id = srv.id
                JOIN providers p ON s.provider_id = p.id
                JOIN users u_prov ON p.user_id = u_prov.id
                WHERE s.customer_id = ? AND s.session_status = 'active'";
        $params = [$customerId];
        if ($bookingId) {
            $sql .= " AND s.booking_id = ?";
            $params[] = $bookingId;
        }
        $sql .= " ORDER BY s.id DESC LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

function startSafeVisitSession($bookingId, $customerId, $intervalMins = 30, $graceMins = 5) {
    $pdo = getDB();
    ensureSafetyTablesExist($pdo);

    // Verify booking belongs to customer and has assigned provider
    $bStmt = $pdo->prepare("SELECT id, customer_id, provider_id, status FROM bookings WHERE id = ? AND customer_id = ?");
    $bStmt->execute([$bookingId, $customerId]);
    $booking = $bStmt->fetch();

    if (!$booking) {
        throw new Exception("Booking not found or access denied.");
    }
    if (empty($booking['provider_id'])) {
        throw new Exception("Cannot start SafeVisit: No service provider has been assigned to this booking yet.");
    }
    if (!in_array($booking['status'], ['Accepted', 'In Progress'])) {
        throw new Exception("SafeVisit can only be started for 'Accepted' or 'In Progress' bookings.");
    }

    // Check if there is already an active session
    $existing = getActiveSafeVisitSession($customerId, $bookingId);
    if ($existing) {
        return $existing;
    }

    // Generate cryptographically secure OTPs
    $arrivalOtp = generateSecureOtp(6);
    $completionOtp = generateSecureOtp(6);
    $arrivalHash = password_hash($arrivalOtp, PASSWORD_DEFAULT);
    $completionHash = password_hash($completionOtp, PASSWORD_DEFAULT);

    // 4 hours expiration from start
    $expiresAt = date('Y-m-d H:i:s', strtotime('+4 hours'));
    $firstDue = date('Y-m-d H:i:s', strtotime("+{$intervalMins} minutes"));

    $stmt = $pdo->prepare("INSERT INTO safevisit_sessions 
        (booking_id, customer_id, provider_id, session_status, 
         arrival_otp_hash, arrival_otp_plain, completion_otp_hash, completion_otp_plain, 
         otp_expires_at, checkin_interval_mins, grace_period_mins, last_checkin_at, next_checkin_due) 
        VALUES (?, ?, ?, 'active', ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");

    $stmt->execute([
        $bookingId,
        $customerId,
        $booking['provider_id'],
        $arrivalHash,
        $arrivalOtp, // Kept visible to customer so they can verify arrival with technician
        $completionHash,
        $completionOtp,
        $expiresAt,
        $intervalMins,
        $graceMins,
        $firstDue
    ]);

    $sessionId = $pdo->lastInsertId();
    logSafetyAudit($customerId, 'start_safevisit_session', 'safevisit_session', $sessionId, "Customer started SafeVisit for Booking #BK-{$bookingId}");
    return getActiveSafeVisitSession($customerId, $bookingId);
}

function verifyArrivalOtpSession($sessionId, $inputOtp, $verifiedByUserId) {
    $pdo = getDB();
    ensureSafetyTablesExist($pdo);

    $stmt = $pdo->prepare("SELECT * FROM safevisit_sessions WHERE id = ?");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();

    if (!$session || $session['session_status'] !== 'active') {
        return ['success' => false, 'message' => 'Active session not found.'];
    }

    if ($session['arrival_verified_at'] !== null) {
        return ['success' => true, 'message' => 'Arrival OTP already verified.'];
    }

    // Check attempt lockout
    if ($session['arrival_attempts'] >= 5) {
        return ['success' => false, 'message' => 'Too many failed attempts. Verification locked for security.'];
    }

    // Check expiration
    if (strtotime($session['otp_expires_at']) < time()) {
        return ['success' => false, 'message' => 'Arrival OTP has expired. Please refresh session.'];
    }

    $inputClean = trim($inputOtp);
    $matches = false;
    if (!empty($session['arrival_otp_hash']) && password_verify($inputClean, $session['arrival_otp_hash'])) {
        $matches = true;
    } elseif ($session['arrival_otp_plain'] === $inputClean) {
        $matches = true;
    }

    if ($matches) {
        $uStmt = $pdo->prepare("UPDATE safevisit_sessions SET arrival_verified_at = NOW(), arrival_attempts = 0 WHERE id = ?");
        $uStmt->execute([$sessionId]);

        // Also update booking status to 'In Progress' if not already
        $pdo->prepare("UPDATE bookings SET status = 'In Progress' WHERE id = ? AND status = 'Accepted'")->execute([$session['booking_id']]);

        logSafetyAudit($verifiedByUserId, 'verify_arrival_otp', 'safevisit_session', $sessionId, "Arrival OTP verified successfully.");
        return ['success' => true, 'message' => 'Provider Arrival Verified! Service is now In Progress.'];
    } else {
        $pdo->prepare("UPDATE safevisit_sessions SET arrival_attempts = arrival_attempts + 1 WHERE id = ?")->execute([$sessionId]);
        $remaining = 4 - $session['arrival_attempts'];
        return ['success' => false, 'message' => "Invalid Arrival OTP. {$remaining} attempts remaining."];
    }
}

function verifyCompletionOtpSession($sessionId, $inputOtp, $verifiedByUserId) {
    $pdo = getDB();
    ensureSafetyTablesExist($pdo);

    $stmt = $pdo->prepare("SELECT * FROM safevisit_sessions WHERE id = ?");
    $stmt->execute([$sessionId]);
    $session = $stmt->fetch();

    if (!$session || $session['session_status'] !== 'active') {
        return ['success' => false, 'message' => 'Active session not found.'];
    }

    if ($session['completion_verified_at'] !== null) {
        return ['success' => true, 'message' => 'Completion OTP already verified.'];
    }

    if ($session['completion_attempts'] >= 5) {
        return ['success' => false, 'message' => 'Too many failed attempts. Verification locked.'];
    }

    $inputClean = trim($inputOtp);
    $matches = false;
    if (!empty($session['completion_otp_hash']) && password_verify($inputClean, $session['completion_otp_hash'])) {
        $matches = true;
    } elseif ($session['completion_otp_plain'] === $inputClean) {
        $matches = true;
    }

    if ($matches) {
        $uStmt = $pdo->prepare("UPDATE safevisit_sessions SET completion_verified_at = NOW(), session_status = 'completed', ended_at = NOW() WHERE id = ?");
        $uStmt->execute([$sessionId]);

        // Complete the booking
        $pdo->prepare("UPDATE bookings SET status = 'Completed' WHERE id = ?")->execute([$session['booking_id']]);

        logSafetyAudit($verifiedByUserId, 'verify_completion_otp', 'safevisit_session', $sessionId, "Completion OTP verified. Session closed.");
        return ['success' => true, 'message' => 'Job Completion Verified! SafeVisit session successfully completed.'];
    } else {
        $pdo->prepare("UPDATE safevisit_sessions SET completion_attempts = completion_attempts + 1 WHERE id = ?")->execute([$sessionId]);
        $remaining = 4 - $session['completion_attempts'];
        return ['success' => false, 'message' => "Invalid Completion OTP. {$remaining} attempts remaining."];
    }
}

function recordSafetyCheckin($sessionId, $customerId) {
    $pdo = getDB();
    ensureSafetyTablesExist($pdo);

    $stmt = $pdo->prepare("SELECT * FROM safevisit_sessions WHERE id = ? AND customer_id = ? AND session_status = 'active'");
    $stmt->execute([$sessionId, $customerId]);
    $session = $stmt->fetch();

    if (!$session) {
        return ['success' => false, 'message' => 'Active SafeVisit session not found.'];
    }

    $interval = (int)$session['checkin_interval_mins'];
    $nextDue = date('Y-m-d H:i:s', strtotime("+{$interval} minutes"));

    $uStmt = $pdo->prepare("UPDATE safevisit_sessions SET last_checkin_at = NOW(), next_checkin_due = ? WHERE id = ?");
    $uStmt->execute([$nextDue, $sessionId]);

    logSafetyAudit($customerId, 'safety_checkin', 'safevisit_session', $sessionId, "Customer confirmed 'I am Safe'");
    return ['success' => true, 'next_due' => $nextDue, 'message' => "Safety confirmed! Next check-in in {$interval} minutes."];
}

function updateSessionLocation($sessionId, $customerId, $lat, $lng, $sharingEnabled = 1) {
    $pdo = getDB();
    ensureSafetyTablesExist($pdo);

    $stmt = $pdo->prepare("UPDATE safevisit_sessions 
        SET location_sharing_enabled = ?, last_latitude = ?, last_longitude = ?, last_location_updated = NOW() 
        WHERE id = ? AND customer_id = ? AND session_status = 'active'");
    $stmt->execute([$sharingEnabled ? 1 : 0, $lat, $lng, $sessionId, $customerId]);
    return true;
}

function endSafeVisitSession($sessionId, $customerId, $reason = 'completed') {
    $pdo = getDB();
    ensureSafetyTablesExist($pdo);

    $stmt = $pdo->prepare("UPDATE safevisit_sessions SET session_status = ?, ended_at = NOW() WHERE id = ? AND customer_id = ?");
    $stmt->execute([$reason, $sessionId, $customerId]);
    logSafetyAudit($customerId, 'end_safevisit_session', 'safevisit_session', $sessionId, "Session ended. Reason: {$reason}");
    return true;
}

// --------------------------------------------------------
// Emergency SOS & Incident Management
// --------------------------------------------------------
function triggerSafetyIncident($customerId, $incidentType, $sessionId = null, $lat = null, $lng = null, $notes = '') {
    $pdo = getDB();
    ensureSafetyTablesExist($pdo);

    $bookingId = null;
    $providerId = null;

    if ($sessionId) {
        $sStmt = $pdo->prepare("SELECT booking_id, provider_id FROM safevisit_sessions WHERE id = ?");
        $sStmt->execute([$sessionId]);
        $session = $sStmt->fetch();
        if ($session) {
            $bookingId = $session['booking_id'];
            $providerId = $session['provider_id'];
            // Update session status to emergency
            $pdo->prepare("UPDATE safevisit_sessions SET session_status = 'emergency' WHERE id = ?")->execute([$sessionId]);
        }
    }

    // Create Incident Record
    $stmt = $pdo->prepare("INSERT INTO safety_incidents 
        (session_id, booking_id, customer_id, provider_id, incident_type, status, latitude, longitude, notes) 
        VALUES (?, ?, ?, ?, ?, 'active', ?, ?, ?)");
    $stmt->execute([$sessionId, $bookingId, $customerId, $providerId, $incidentType, $lat, $lng, $notes]);
    $incidentId = $pdo->lastInsertId();

    // Fetch customer details
    $cStmt = $pdo->prepare("SELECT name, phone FROM users WHERE id = ?");
    $cStmt->execute([$customerId]);
    $customer = $cStmt->fetch();
    $custName = $customer['name'] ?? 'Customer';
    $custPhone = $customer['phone'] ?? '';

    // Provider details if present
    $provName = "Assigned Technician";
    if ($providerId) {
        $pStmt = $pdo->prepare("SELECT u.name, u.phone FROM providers p JOIN users u ON p.user_id = u.id WHERE p.id = ?");
        $pStmt->execute([$providerId]);
        $provData = $pStmt->fetch();
        if ($provData) {
            $provName = "{$provData['name']} ({$provData['phone']})";
        }
    }

    // Notify Trusted Contacts
    $contacts = getTrustedContacts($customerId);
    $locationStr = ($lat && $lng) ? " GPS Location: https://maps.google.com/?q={$lat},{$lng}" : " Location: Customer registered address";

    $isSilent = ($incidentType === 'silent_sos');
    $alertTitle = $isSilent ? "[SILENT SAFETY ALERT]" : "[EMERGENCY SOS ALERT]";
    $msgBody = "{$alertTitle} {$custName} triggered an emergency alert during an active home service visit with {$provName}.{$locationStr}. Please check on them immediately or call emergency 112.";

    $dispatchResults = [];
    foreach ($contacts as $contact) {
        $res = sendSafetyNotification(
            $incidentId,
            $customerId,
            'trusted_contact',
            $contact['name'],
            $contact['phone'],
            $msgBody,
            'sms'
        );
        $dispatchResults[] = $res;
    }

    // Also notify internal admin queue
    sendSafetyNotification(
        $incidentId,
        $customerId,
        'admin',
        'HomeService Safety Ops Desk',
        'EmergencyDesk',
        "Admin Incident #{$incidentId}: {$incidentType} by {$custName} (Phone: {$custPhone}). Booking #BK-{$bookingId}.",
        'in_app'
    );

    logSafetyAudit($customerId, 'trigger_' . $incidentType, 'safety_incident', $incidentId, "Emergency incident triggered. Contacts notified: " . count($contacts));

    return [
        'incident_id' => $incidentId,
        'type' => $incidentType,
        'contacts_notified' => count($contacts),
        'dispatches' => $dispatchResults
    ];
}

// --------------------------------------------------------
// Provider Confidential Reporting & Blocking
// --------------------------------------------------------
function reportProviderConfidential($customerId, $providerId, $bookingId, $category, $details, $incidentDate, $blockProvider = false) {
    $pdo = getDB();
    ensureSafetyTablesExist($pdo);

    $allowedCategories = ['harassment', 'threats', 'suspicious_behaviour', 'fraud', 'physical_danger', 'other'];
    if (!in_array($category, $allowedCategories)) {
        throw new InvalidArgumentException("Invalid report category.");
    }

    $details = trim($details);
    if (strlen($details) < 10) {
        throw new InvalidArgumentException("Please provide a more detailed description of the incident (at least 10 characters).");
    }

    $stmt = $pdo->prepare("INSERT INTO customer_reports 
        (customer_id, provider_id, booking_id, category, details, incident_date, block_provider, admin_status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->execute([$customerId, $providerId, $bookingId ?: null, $category, $details, $incidentDate, $blockProvider ? 1 : 0]);
    $reportId = $pdo->lastInsertId();

    if ($blockProvider) {
        blockProviderFromCustomer($customerId, $providerId, "Customer block via incident report #{$reportId}");
    }

    // If report involves physical danger or threats, escalate immediately to safety incidents
    if (in_array($category, ['physical_danger', 'threats', 'harassment'])) {
        triggerSafetyIncident($customerId, 'report', null, null, null, "Urgent Customer Report #{$reportId} ({$category}): " . substr($details, 0, 150));
    }

    logSafetyAudit($customerId, 'submit_provider_report', 'customer_report', $reportId, "Confidential report filed against Provider #PR-{$providerId}. Blocked: " . ($blockProvider ? 'Yes' : 'No'));

    return $reportId;
}

function blockProviderFromCustomer($customerId, $providerId, $reason = '') {
    $pdo = getDB();
    ensureSafetyTablesExist($pdo);
    try {
        $stmt = $pdo->prepare("INSERT IGNORE INTO customer_blocked_providers (customer_id, provider_id, reason) VALUES (?, ?, ?)");
        $stmt->execute([$customerId, $providerId, $reason]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function isProviderBlockedByCustomer($customerId, $providerId) {
    $pdo = getDB();
    ensureSafetyTablesExist($pdo);
    try {
        $stmt = $pdo->prepare("SELECT id FROM customer_blocked_providers WHERE customer_id = ? AND provider_id = ?");
        $stmt->execute([$customerId, $providerId]);
        return (bool)$stmt->fetch();
    } catch (Exception $e) {
        return false;
    }
}

// --------------------------------------------------------
// Safety Audit Logger
// --------------------------------------------------------
function logSafetyAudit($userId, $action, $targetType, $targetId, $details = '') {
    try {
        $pdo = getDB();
        ensureSafetyTablesExist($pdo);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt = $pdo->prepare("INSERT INTO safety_audit_logs (user_id, action, target_type, target_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $action, $targetType, $targetId, $details, $ip]);
    } catch (Exception $e) {
        // Logging should not break user operations
    }
}
?>
