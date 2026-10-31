<?php
$pageTitle = "SafeVisit & Customer Protection";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/safety_functions.php';
require_once __DIR__ . '/../includes/safety_notifications.php';
requireRole('customer');

$pdo = getDB();
$userId = (int)$_SESSION['user_id'];
$csrfToken = getCsrfToken();

// Handle POST actions for Start / End session / OTP Verification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($submittedToken)) {
        setFlash('danger', 'Security validation failed.');
        header("Location: safety.php");
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'start_session') {
        $bookingId = (int)($_POST['booking_id'] ?? 0);
        $interval = (int)($_POST['interval_mins'] ?? 30);
        $grace = (int)($_POST['grace_mins'] ?? 5);

        try {
            startSafeVisitSession($bookingId, $userId, $interval, $grace);
            setFlash('success', 'SafeVisit session initiated! Arrival OTP is ready.');
        } catch (Exception $e) {
            setFlash('danger', $e->getMessage());
        }
        header("Location: safety.php");
        exit;
    } elseif ($action === 'end_session') {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        if ($sessionId > 0) {
            endSafeVisitSession($sessionId, $userId, 'completed');
            setFlash('success', 'SafeVisit session ended successfully.');
        }
        header("Location: safety.php");
        exit;
    } elseif ($action === 'verify_arrival') {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        $otp = trim($_POST['otp'] ?? '');
        $res = verifyArrivalOtpSession($sessionId, $otp, $userId);
        setFlash($res['success'] ? 'success' : 'danger', $res['message']);
        header("Location: safety.php");
        exit;
    } elseif ($action === 'verify_completion') {
        $sessionId = (int)($_POST['session_id'] ?? 0);
        $otp = trim($_POST['otp'] ?? '');
        $res = verifyCompletionOtpSession($sessionId, $otp, $userId);
        setFlash($res['success'] ? 'success' : 'danger', $res['message']);
        header("Location: safety.php");
        exit;
    }
}

// Fetch Active SafeVisit Session for Customer
$activeSession = getActiveSafeVisitSession($userId);

// Fetch Bookings eligible for SafeVisit (Accepted or In Progress with an assigned provider)
$eligibleBookings = [];
try {
    $bStmt = $pdo->prepare("SELECT b.*, s.name as service_name, c.name as category_name, 
                                   u.name as provider_name, u.phone as provider_phone,
                                   p.status as provider_status
                            FROM bookings b
                            JOIN services s ON b.service_id = s.id
                            JOIN service_categories c ON s.category_id = c.id
                            JOIN providers p ON b.provider_id = p.id
                            JOIN users u ON p.user_id = u.id
                            WHERE b.customer_id = ? 
                              AND b.status IN ('Accepted', 'In Progress')
                            ORDER BY b.booking_date ASC, b.booking_time ASC");
    $bStmt->execute([$userId]);
    $eligibleBookings = $bStmt->fetchAll();
} catch (Exception $e) {
    $eligibleBookings = [];
}

// Trusted contacts count
$trustedContacts = getTrustedContacts($userId);

// Recent Notifications
$recentNotifications = getSafetyNotificationsForCustomer($userId, 5);

$pageGreeting = "SafeVisit Protection Hub";
$pageSubtitle = "Real-time OTP verification, safety check-ins, and emergency protection";

require_once __DIR__ . '/../includes/header.php';
?>
<!-- Safety Styles and Script -->
<link rel="stylesheet" href="../assets/css/safety.css">
<script src="../assets/js/safety.js"></script>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
        <a href="trusted_contacts.php" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-users-shield"></i> Trusted Contacts (<?php echo count($trustedContacts); ?>)
        </a>
        <a href="report_provider.php" class="btn btn-outline btn-sm" style="color: var(--text-dark);">
            <i class="fa-solid fa-flag"></i> Report Provider
        </a>
    </div>
    <div>
        <a href="tel:112" class="btn-call-112">
            <i class="fa-solid fa-phone-volume"></i> Direct Call 112
        </a>
    </div>
</div>

<?php if ($activeSession): ?>
    <!-- ========================================================
         ACTIVE SAFEVISIT SESSION WORKSPACE
         ======================================================== -->
    <div class="safevisit-hero">
        <div class="safevisit-hero-header">
            <div class="safevisit-title">
                <div class="safevisit-icon-shield">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <div>
                    <h3 style="margin: 0; font-size: 1.35rem; color: #0369a1;">
                        SafeVisit Active &bull; Booking #BK-<?php echo str_pad($activeSession['booking_id'], 4, '0', STR_PAD_LEFT); ?>
                    </h3>
                    <p style="margin: 4px 0 0; color: #0284c7; font-size: 0.88rem;">
                        Service: <strong><?php echo htmlspecialchars($activeSession['service_name']); ?></strong> &bull; Started: <?php echo date('h:i A', strtotime($activeSession['started_at'])); ?>
                    </p>
                </div>
            </div>
            <div>
                <form method="POST" action="safety.php" onsubmit="return confirm('Are you sure you want to conclude this SafeVisit session?');">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="action" value="end_session">
                    <input type="hidden" name="session_id" value="<?php echo $activeSession['id']; ?>">
                    <button type="submit" class="btn btn-secondary btn-sm">
                        <i class="fa-solid fa-circle-stop"></i> End Safety Session
                    </button>
                </form>
            </div>
        </div>

        <!-- Provider Profile Card -->
        <div style="background: #ffffff; border-radius: 10px; padding: 1.1rem; margin-bottom: 1.25rem; border: 1px solid var(--safety-blue-border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 48px; height: 48px; border-radius: 50%; background: #e0f2fe; color: #0284c7; font-weight: 700; display: flex; align-items: center; justify-content: center; font-size: 1.25rem;">
                    <?php echo strtoupper(substr($activeSession['provider_name'], 0, 1)); ?>
                </div>
                <div>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <h4 style="margin: 0; font-size: 1.05rem; color: var(--text-dark);"><?php echo htmlspecialchars($activeSession['provider_name']); ?></h4>
                        <?php echo renderVerifiedBadge($activeSession['provider_verification_status'] ?? 'approved'); ?>
                    </div>
                    <div style="font-size: 0.88rem; color: var(--text-muted); margin-top: 2px;">
                        <i class="fa-solid fa-phone" style="font-size: 0.8rem; margin-right: 4px;"></i>
                        <?php echo htmlspecialchars($activeSession['provider_phone']); ?> &bull; 
                        <i class="fa-solid fa-location-dot" style="font-size: 0.8rem; margin-right: 4px;"></i>
                        <?php echo htmlspecialchars($activeSession['service_address']); ?>
                    </div>
                </div>
            </div>
            <div>
                <a href="report_provider.php?provider_id=<?php echo $activeSession['provider_id']; ?>&booking_id=<?php echo $activeSession['booking_id']; ?>" class="btn btn-outline btn-sm" style="color: var(--danger); border-color: var(--danger-border);">
                    <i class="fa-regular fa-flag"></i> Report Concerns
                </a>
            </div>
        </div>

        <!-- Emergency SOS Actions -->
        <div class="sos-container">
            <div>
                <button type="button" id="btn-emergency-sos" class="btn-sos-emergency" style="width: 100%;" onclick="triggerSafetySOS('sos', <?php echo $activeSession['id']; ?>, '<?php echo $csrfToken; ?>');">
                    <i class="fa-solid fa-triangle-exclamation" style="font-size: 1.25rem;"></i>
                    <span>EMERGENCY SOS (Alert Contacts & Ops)</span>
                </button>
                <small style="color: var(--text-muted); display: block; margin-top: 5px; text-align: center; font-size: 0.78rem;">
                    Immediately alerts all <?php echo count($trustedContacts); ?> trusted emergency contacts and platform security desk.
                </small>
            </div>
            <div>
                <button type="button" id="btn-silent-sos" class="btn-sos-silent" style="width: 100%;" onclick="triggerSafetySOS('silent_sos', <?php echo $activeSession['id']; ?>, '<?php echo $csrfToken; ?>');">
                    <i class="fa-solid fa-bell-slash" style="font-size: 1.25rem;"></i>
                    <span>SILENT SOS (Discreet No-Sound Alert)</span>
                </button>
                <small id="sos-silent-status" style="color: var(--text-muted); display: block; margin-top: 5px; text-align: center; font-size: 0.78rem;">
                    Sends silent high-priority alert without any screen sirens or audible alarm sounds.
                </small>
            </div>
        </div>

        <!-- Check-in & Location Sharing Bar -->
        <div class="checkin-widget">
            <div class="checkin-status-info">
                <h4><i class="fa-solid fa-stopwatch" style="color: var(--safety-blue); margin-right: 6px;"></i> Safety Check-In Timer</h4>
                <p id="checkin-timer-display">
                    Interval: <?php echo $activeSession['checkin_interval_mins']; ?> mins &bull; Grace Period: <?php echo $activeSession['grace_period_mins']; ?> mins
                </p>
                <div id="checkin-feedback" style="display: none; margin-top: 6px; padding: 6px 12px; font-size: 0.85rem;"></div>
            </div>
            <div>
                <button type="button" id="btn-im-safe" class="btn-safe-checkin" onclick="performSafetyCheckin(<?php echo $activeSession['id']; ?>, '<?php echo $csrfToken; ?>');">
                    <i class="fa-solid fa-shield-heart"></i> I'm Safe (Check In Now)
                </button>
            </div>
        </div>

        <!-- Location Sharing Switch -->
        <div class="location-sharing-panel">
            <div>
                <strong style="color: var(--text-dark); display: block; font-size: 0.95rem;">
                    <i class="fa-solid fa-location-crosshairs" style="color: var(--safety-blue); margin-right: 6px;"></i>
                    Live GPS Location Sharing
                </strong>
                <span id="location-sharing-status" class="location-indicator <?php echo $activeSession['location_sharing_enabled'] ? 'active' : 'inactive'; ?>">
                    <?php if ($activeSession['location_sharing_enabled']): ?>
                        <span class="pulse-dot"></span> GPS Sharing Active (Coordinates: <?php echo number_format($activeSession['last_latitude'], 4); ?>, <?php echo number_format($activeSession['last_longitude'], 4); ?>)
                    <?php else: ?>
                        <i class="fa-solid fa-location-slash"></i> Location sharing is currently disabled
                    <?php endif; ?>
                </span>
            </div>
            <div>
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.88rem; font-weight: 600;">
                    <input type="checkbox" id="location-toggle-checkbox" <?php echo $activeSession['location_sharing_enabled'] ? 'checked' : ''; ?> 
                           onchange="toggleLocationSharing(<?php echo $activeSession['id']; ?>, '<?php echo $csrfToken; ?>', this.checked);" 
                           style="width: 18px; height: 18px;">
                    <span>Share Location</span>
                </label>
            </div>
        </div>

        <!-- Arrival & Completion OTP Verification Cards -->
        <div class="otp-card-grid">
            <!-- Arrival OTP Card -->
            <div class="otp-box <?php echo $activeSession['arrival_verified_at'] ? 'verified' : ''; ?>">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <strong style="font-size: 0.95rem; color: var(--text-dark);">
                        <i class="fa-solid fa-key" style="color: var(--safety-blue); margin-right: 6px;"></i>
                        Arrival Verification OTP
                    </strong>
                    <?php if ($activeSession['arrival_verified_at']): ?>
                        <span class="badge badge-completed"><i class="fa-solid fa-check"></i> Verified</span>
                    <?php else: ?>
                        <span class="badge badge-pending">Pending Arrival</span>
                    <?php endif; ?>
                </div>
                <p style="font-size: 0.82rem; color: var(--text-muted); margin: 6px 0 8px;">
                    Share this 6-digit code with the technician upon their arrival to confirm their identity:
                </p>
                
                <div class="otp-digits"><?php echo htmlspecialchars($activeSession['arrival_otp_plain'] ?? '******'); ?></div>

                <?php if (!$activeSession['arrival_verified_at']): ?>
                    <form method="POST" action="safety.php" style="margin-top: 8px; display: flex; gap: 6px;">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <input type="hidden" name="action" value="verify_arrival">
                        <input type="hidden" name="session_id" value="<?php echo $activeSession['id']; ?>">
                        <input type="text" name="otp" placeholder="Enter Arrival Code" class="form-control" style="font-size: 0.85rem; padding: 6px 10px; width: 140px;" maxlength="6" required>
                        <button type="submit" class="btn btn-primary btn-sm">Confirm Arrival</button>
                    </form>
                <?php else: ?>
                    <div style="font-size: 0.8rem; color: #059669; margin-top: 4px;">
                        <i class="fa-solid fa-circle-check"></i> Arrived at <?php echo date('h:i A', strtotime($activeSession['arrival_verified_at'])); ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Completion OTP Card -->
            <div class="otp-box <?php echo $activeSession['completion_verified_at'] ? 'verified' : ''; ?>">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <strong style="font-size: 0.95rem; color: var(--text-dark);">
                        <i class="fa-solid fa-circle-check" style="color: #059669; margin-right: 6px;"></i>
                        Completion Verification OTP
                    </strong>
                    <?php if ($activeSession['completion_verified_at']): ?>
                        <span class="badge badge-completed"><i class="fa-solid fa-check"></i> Completed</span>
                    <?php else: ?>
                        <span class="badge badge-default">Job In Progress</span>
                    <?php endif; ?>
                </div>
                <p style="font-size: 0.82rem; color: var(--text-muted); margin: 6px 0 8px;">
                    Share this code ONLY when the service work is finished to your satisfaction:
                </p>

                <div class="otp-digits" style="color: #059669; background: #ecfdf5; border-color: #a7f3d0;">
                    <?php echo htmlspecialchars($activeSession['completion_otp_plain'] ?? '******'); ?>
                </div>

                <?php if (!$activeSession['completion_verified_at']): ?>
                    <form method="POST" action="safety.php" style="margin-top: 8px; display: flex; gap: 6px;">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <input type="hidden" name="action" value="verify_completion">
                        <input type="hidden" name="session_id" value="<?php echo $activeSession['id']; ?>">
                        <input type="text" name="otp" placeholder="Enter Completion Code" class="form-control" style="font-size: 0.85rem; padding: 6px 10px; width: 140px;" maxlength="6" required>
                        <button type="submit" class="btn btn-secondary btn-sm" style="background: #059669; color: #fff;">Complete Job</button>
                    </form>
                <?php else: ?>
                    <div style="font-size: 0.8rem; color: #059669; margin-top: 4px;">
                        <i class="fa-solid fa-circle-check"></i> Completed at <?php echo date('h:i A', strtotime($activeSession['completion_verified_at'])); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!empty($activeSession['next_checkin_due'])): ?>
        <script>
            window.locationSharingEnabled = <?php echo $activeSession['location_sharing_enabled'] ? 'true' : 'false'; ?>;
            window.addEventListener('DOMContentLoaded', function() {
                initCheckinCountdown('<?php echo $activeSession['next_checkin_due']; ?>');
            });
        </script>
    <?php endif; ?>

<?php else: ?>
    <!-- ========================================================
         NO ACTIVE SESSION - START A SAFEVIEST SESSION
         ======================================================== -->
    <div class="card" style="padding: 2rem; margin-bottom: 2rem;">
        <div style="display: flex; gap: 16px; align-items: flex-start;">
            <div class="safevisit-icon-shield" style="flex-shrink: 0; width: 50px; height: 50px; font-size: 1.5rem;">
                <i class="fa-solid fa-shield-halved"></i>
            </div>
            <div>
                <h3 style="margin: 0 0 6px 0; font-size: 1.35rem; color: var(--text-dark);">Start SafeVisit for an Active Job</h3>
                <p style="color: var(--text-muted); font-size: 0.9rem; line-height: 1.5; margin-bottom: 1.5rem;">
                    SafeVisit provides real-time verification and protection during in-home technician appointments. You'll receive Arrival OTPs, an automated safety check-in timer, silent SOS capabilities, and instant emergency notifications.
                </p>

                <?php if (empty($eligibleBookings)): ?>
                    <div style="background: var(--bg-muted); border: 1px dashed var(--border-subtle); padding: 1.5rem; border-radius: 8px; text-align: center;">
                        <i class="fa-regular fa-calendar-xmark" style="font-size: 2rem; color: var(--text-light); margin-bottom: 0.5rem;"></i>
                        <h4 style="margin: 0 0 4px 0; color: var(--text-dark);">No Active Accepted Bookings Ready for SafeVisit</h4>
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0 0 1rem;">
                            SafeVisit activates once an admin assigns a verified technician to your booking and the job is Accepted.
                        </p>
                        <a href="my-bookings.php" class="btn btn-secondary btn-sm">View My Bookings</a>
                    </div>
                <?php else: ?>
                    <form method="POST" action="safety.php" style="background: var(--bg-muted); border: 1px solid var(--border-subtle); padding: 1.5rem; border-radius: 8px;">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                        <input type="hidden" name="action" value="start_session">

                        <div class="form-group" style="margin-bottom: 1.25rem;">
                            <label class="form-label" for="booking_id">Select Active Service Booking *</label>
                            <select id="booking_id" name="booking_id" class="form-control" required style="width: 100%;">
                                <?php foreach ($eligibleBookings as $eb): ?>
                                    <option value="<?php echo $eb['id']; ?>">
                                        #BK-<?php echo str_pad($eb['id'], 4, '0', STR_PAD_LEFT); ?>: <?php echo htmlspecialchars($eb['service_name']); ?> with <?php echo htmlspecialchars($eb['provider_name']); ?> (<?php echo date('d M', strtotime($eb['booking_date'])); ?> @ <?php echo date('h:i A', strtotime($eb['booking_time'])); ?>) - [<?php echo $eb['status']; ?>]
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="grid grid-2" style="gap: 1rem; margin-bottom: 1.5rem;">
                            <div class="form-group">
                                <label class="form-label" for="interval_mins">Check-In Interval</label>
                                <select id="interval_mins" name="interval_mins" class="form-control" style="width: 100%;">
                                    <option value="15">Every 15 Minutes</option>
                                    <option value="30" selected>Every 30 Minutes (Recommended)</option>
                                    <option value="45">Every 45 Minutes</option>
                                    <option value="60">Every 60 Minutes</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="grace_mins">Missed Check-In Grace Period</label>
                                <select id="grace_mins" name="grace_mins" class="form-control" style="width: 100%;">
                                    <option value="5" selected>5 Minutes (Recommended)</option>
                                    <option value="10">10 Minutes</option>
                                    <option value="15">15 Minutes</option>
                                </select>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-shield-check"></i> Launch SafeVisit Session
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Safety Notifications & Audit Trail Table -->
<div class="card" style="padding: 1.5rem; margin-top: 1.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
        <h4 style="margin: 0; font-size: 1.1rem; color: var(--text-dark);">
            <i class="fa-solid fa-paper-plane" style="color: var(--safety-blue); margin-right: 6px;"></i>
            Recent Emergency & Safety Alerts Log
        </h4>
        <span style="font-size: 0.8rem; color: var(--text-muted);">Accurate Delivery Auditing</span>
    </div>

    <?php if (empty($recentNotifications)): ?>
        <p style="font-size: 0.88rem; color: var(--text-muted); margin: 0; text-align: center; padding: 1.5rem 0;">
            No emergency alerts have been triggered on your account.
        </p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Alert Type</th>
                        <th>Recipient</th>
                        <th>Channel</th>
                        <th>Delivery Status</th>
                        <th>Message Preview</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentNotifications as $notif): ?>
                        <tr>
                            <td style="font-size: 0.82rem; color: var(--text-muted);">
                                <?php echo date('d M Y, h:i A', strtotime($notif['created_at'])); ?>
                            </td>
                            <td>
                                <span class="badge <?php echo ($notif['incident_type'] === 'sos') ? 'badge-cancelled' : 'badge-accepted'; ?>">
                                    <?php echo strtoupper(str_replace('_', ' ', $notif['incident_type'] ?? 'ALERT')); ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($notif['recipient_name']); ?></strong><br>
                                <small style="color: var(--text-muted);"><?php echo htmlspecialchars($notif['recipient_phone']); ?></small>
                            </td>
                            <td><span style="font-size: 0.82rem;"><?php echo strtoupper($notif['channel']); ?></span></td>
                            <td>
                                <?php if ($notif['delivery_status'] === 'simulated_dev'): ?>
                                    <span class="delivery-tag delivery-simulated" title="<?php echo htmlspecialchars($notif['status_message']); ?>">
                                        <i class="fa-solid fa-code"></i> Dev Fallback (Logged)
                                    </span>
                                <?php elseif ($notif['delivery_status'] === 'delivered'): ?>
                                    <span class="delivery-tag delivery-delivered">
                                        <i class="fa-solid fa-check-double"></i> Delivered
                                    </span>
                                <?php else: ?>
                                    <span class="delivery-tag delivery-failed">
                                        <i class="fa-solid fa-xmark"></i> Failed
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 0.8rem; color: var(--text-muted); max-width: 250px;">
                                <?php echo htmlspecialchars(substr($notif['message'], 0, 75)) . '...'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Legal & Safety Disclaimer -->
<div class="safety-disclaimer-box">
    <strong><i class="fa-solid fa-circle-exclamation"></i> Safety & Verification Advisory:</strong> 
    <?php echo getSafetyDisclaimer(); ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

