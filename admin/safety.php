<?php
$pageTitle = "Admin Safety, Incident & Verification Center";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/safety_functions.php';
requireRole('admin');

$pdo = getDB();
ensureSafetyTablesExist($pdo);
$adminId = (int)$_SESSION['user_id'];
$csrfToken = getCsrfToken();

// Handle Admin Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($submittedToken)) {
        setFlash('danger', 'Security token invalid.');
        header("Location: safety.php");
        exit;
    }

    $action = $_POST['action'] ?? '';

    // 1. Provider Verification State Update
    if ($action === 'update_provider_status') {
        $providerId = (int)($_POST['provider_id'] ?? 0);
        $newStatus = $_POST['new_status'] ?? '';
        $adminNotes = trim($_POST['admin_notes'] ?? '');

        if ($providerId > 0 && in_array($newStatus, ['approved', 'pending', 'rejected', 'suspended'])) {
            // Update providers table
            $pdo->prepare("UPDATE providers SET status = ? WHERE id = ?")->execute([$newStatus, $providerId]);

            // Update or create provider_verifications table entry
            $vCheck = $pdo->prepare("SELECT id FROM provider_verifications WHERE provider_id = ?");
            $vCheck->execute([$providerId]);
            if ($vCheck->fetch()) {
                $pdo->prepare("UPDATE provider_verifications SET status = ?, reviewed_by = ?, reviewed_at = NOW(), verification_notes = CONCAT(IFNULL(verification_notes,''), '\n[Admin: ', ?, ']') WHERE provider_id = ?")
                    ->execute([$newStatus, $adminId, $adminNotes, $providerId]);
            } else {
                $pdo->prepare("INSERT INTO provider_verifications (provider_id, status, reviewed_by, reviewed_at, verification_notes) VALUES (?, ?, ?, NOW(), ?)")
                    ->execute([$providerId, $newStatus, $adminId, $adminNotes]);
            }

            logSafetyAudit($adminId, 'admin_update_provider_verification', 'provider', $providerId, "Admin updated provider verification status to '{$newStatus}'. Notes: {$adminNotes}");
            setFlash('success', "Provider #PR-{$providerId} status changed to " . ucfirst($newStatus) . ".");
        }
        header("Location: safety.php#providers");
        exit;
    }

    // 2. Resolve Incident
    if ($action === 'resolve_incident') {
        $incidentId = (int)($_POST['incident_id'] ?? 0);
        $newStatus = $_POST['incident_status'] ?? 'resolved';
        $resNotes = trim($_POST['resolution_notes'] ?? '');

        if ($incidentId > 0 && in_array($newStatus, ['active', 'investigating', 'resolved', 'false_alarm'])) {
            $pdo->prepare("UPDATE safety_incidents SET status = ?, resolution_notes = ?, resolved_by = ?, resolved_at = NOW() WHERE id = ?")
                ->execute([$newStatus, $resNotes, $adminId, $incidentId]);

            logSafetyAudit($adminId, 'resolve_safety_incident', 'safety_incident', $incidentId, "Incident marked as {$newStatus}. Resolution: {$resNotes}");
            setFlash('success', "Safety Incident #{$incidentId} updated to " . ucfirst(str_replace('_', ' ', $newStatus)) . ".");
        }
        header("Location: safety.php#incidents");
        exit;
    }

    // 3. Update Report Status & Optional Immediate Suspension
    if ($action === 'update_report') {
        $reportId = (int)($_POST['report_id'] ?? 0);
        $providerId = (int)($_POST['provider_id'] ?? 0);
        $reportStatus = $_POST['report_status'] ?? 'action_taken';
        $adminNotes = trim($_POST['admin_notes'] ?? '');
        $suspendProvider = isset($_POST['suspend_provider']) ? true : false;

        if ($reportId > 0) {
            $pdo->prepare("UPDATE customer_reports SET admin_status = ?, admin_notes = ? WHERE id = ?")
                ->execute([$reportStatus, $adminNotes, $reportId]);

            if ($suspendProvider && $providerId > 0) {
                $pdo->prepare("UPDATE providers SET status = 'suspended' WHERE id = ?")->execute([$providerId]);
                $pdo->prepare("UPDATE provider_verifications SET status = 'suspended' WHERE provider_id = ?")->execute([$providerId]);
                logSafetyAudit($adminId, 'suspend_provider_from_report', 'provider', $providerId, "Immediate suspension triggered from Report #{$reportId}");
            }

            logSafetyAudit($adminId, 'update_customer_report', 'customer_report', $reportId, "Admin updated report status to {$reportStatus}. Suspend: " . ($suspendProvider ? 'Yes' : 'No'));
            setFlash('success', "Report #{$reportId} updated." . ($suspendProvider ? " Provider #PR-{$providerId} suspended." : ""));
        }
        header("Location: safety.php#reports");
        exit;
    }
}

// Fetch Overview Stats
$stats = [
    'active_incidents' => 0,
    'pending_verifications' => 0,
    'active_sessions' => 0,
    'pending_reports' => 0
];

try {
    $stats['active_incidents'] = (int)$pdo->query("SELECT COUNT(*) FROM safety_incidents WHERE status IN ('active', 'investigating')")->fetchColumn();
    $stats['pending_verifications'] = (int)$pdo->query("SELECT COUNT(*) FROM providers WHERE status = 'pending'")->fetchColumn();
    $stats['active_sessions'] = (int)$pdo->query("SELECT COUNT(*) FROM safevisit_sessions WHERE session_status = 'active'")->fetchColumn();
    $stats['pending_reports'] = (int)$pdo->query("SELECT COUNT(*) FROM customer_reports WHERE admin_status = 'pending'")->fetchColumn();
} catch (Exception $e) {}

// Fetch Incidents
$incidents = [];
try {
    $incStmt = $pdo->query("SELECT si.*, u_cust.name as customer_name, u_cust.phone as customer_phone,
                                   u_prov.name as provider_name, u_prov.phone as provider_phone,
                                   b.service_address
                            FROM safety_incidents si
                            JOIN users u_cust ON si.customer_id = u_cust.id
                            LEFT JOIN providers p ON si.provider_id = p.id
                            LEFT JOIN users u_prov ON p.user_id = u_prov.id
                            LEFT JOIN bookings b ON si.booking_id = b.id
                            ORDER BY (CASE WHEN si.status = 'active' THEN 1 WHEN si.status = 'investigating' THEN 2 ELSE 3 END), si.id DESC
                            LIMIT 50");
    $incidents = $incStmt->fetchAll();
} catch (Exception $e) {}

// Fetch Provider Verifications
$providersList = [];
try {
    $pQuery = $pdo->query("SELECT p.*, u.name as provider_name, u.email, u.phone, u.city, c.name as category_name,
                                  pv.id_type, pv.id_number_masked, pv.verification_notes, pv.submitted_at
                           FROM providers p
                           JOIN users u ON p.user_id = u.id
                           JOIN service_categories c ON p.category_id = c.id
                           LEFT JOIN provider_verifications pv ON p.id = pv.provider_id
                           ORDER BY (CASE WHEN p.status = 'pending' THEN 1 WHEN p.status = 'suspended' THEN 2 ELSE 3 END), p.id ASC");
    $providersList = $pQuery->fetchAll();
} catch (Exception $e) {}

// Fetch Active SafeVisit Sessions
$activeSessions = [];
try {
    $asStmt = $pdo->query("SELECT s.*, u_cust.name as customer_name, u_cust.phone as customer_phone,
                                  u_prov.name as provider_name, u_prov.phone as provider_phone,
                                  srv.name as service_name
                           FROM safevisit_sessions s
                           JOIN users u_cust ON s.customer_id = u_cust.id
                           JOIN providers p ON s.provider_id = p.id
                           JOIN users u_prov ON p.user_id = u_prov.id
                           JOIN bookings b ON s.booking_id = b.id
                           JOIN services srv ON b.service_id = srv.id
                           WHERE s.session_status = 'active'
                           ORDER BY s.id DESC");
    $activeSessions = $asStmt->fetchAll();
} catch (Exception $e) {}

// Fetch Customer Reports
$customerReports = [];
try {
    $rStmt = $pdo->query("SELECT cr.*, u_cust.name as customer_name, u_cust.phone as customer_phone,
                                 u_prov.name as provider_name, u_prov.phone as provider_phone,
                                 p.status as provider_status
                          FROM customer_reports cr
                          JOIN users u_cust ON cr.customer_id = u_cust.id
                          JOIN providers p ON cr.provider_id = p.id
                          JOIN users u_prov ON p.user_id = u_prov.id
                          ORDER BY (CASE WHEN cr.admin_status = 'pending' THEN 1 ELSE 2 END), cr.id DESC");
    $customerReports = $rStmt->fetchAll();
} catch (Exception $e) {}

// Fetch Recent Audit Logs
$auditLogs = [];
try {
    $aStmt = $pdo->query("SELECT sal.*, u.name as user_name, u.role as user_role 
                          FROM safety_audit_logs sal 
                          LEFT JOIN users u ON sal.user_id = u.id 
                          ORDER BY sal.id DESC LIMIT 25");
    $auditLogs = $aStmt->fetchAll();
} catch (Exception $e) {}

$pageGreeting = "Safety Operations & SOS Center";
$pageSubtitle = "Real-time incident response, provider credentials verification, and security controls";

require_once __DIR__ . '/../includes/header.php';
?>
<!-- Safety Styles -->
<link rel="stylesheet" href="../assets/css/safety.css">

<!-- Metric Summary Cards -->
<div class="grid grid-4" style="gap: 1rem; margin-bottom: 2rem;">
    <div class="card" style="padding: 1.25rem; border-left: 4px solid var(--danger);">
        <div style="font-size: 0.8rem; font-weight: 600; color: var(--danger); text-transform: uppercase;">Active Incidents / SOS</div>
        <div style="font-size: 2rem; font-weight: 800; color: var(--text-dark); margin: 4px 0;"><?php echo $stats['active_incidents']; ?></div>
        <small style="color: var(--text-muted);">Requires ops review</small>
    </div>

    <div class="card" style="padding: 1.25rem; border-left: 4px solid var(--warning);">
        <div style="font-size: 0.8rem; font-weight: 600; color: var(--warning); text-transform: uppercase;">Pending Verifications</div>
        <div style="font-size: 2rem; font-weight: 800; color: var(--text-dark); margin: 4px 0;"><?php echo $stats['pending_verifications']; ?></div>
        <small style="color: var(--text-muted);">Provider ID reviews</small>
    </div>

    <div class="card" style="padding: 1.25rem; border-left: 4px solid var(--safety-blue);">
        <div style="font-size: 0.8rem; font-weight: 600; color: var(--safety-blue); text-transform: uppercase;">Active SafeVisits</div>
        <div style="font-size: 2rem; font-weight: 800; color: var(--text-dark); margin: 4px 0;"><?php echo $stats['active_sessions']; ?></div>
        <small style="color: var(--text-muted);">Sessions currently running</small>
    </div>

    <div class="card" style="padding: 1.25rem; border-left: 4px solid #7c3aed;">
        <div style="font-size: 0.8rem; font-weight: 600; color: #7c3aed; text-transform: uppercase;">Pending Complaints</div>
        <div style="font-size: 2rem; font-weight: 800; color: var(--text-dark); margin: 4px 0;"><?php echo $stats['pending_reports']; ?></div>
        <small style="color: var(--text-muted);">Confidential customer reports</small>
    </div>
</div>

<!-- SECTION 1: EMERGENCY INCIDENTS & SOS LOG -->
<div class="card" id="incidents" style="padding: 1.5rem; margin-bottom: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.5rem;">
        <div>
            <h3 style="margin: 0; font-size: 1.25rem; color: var(--text-dark);">
                <i class="fa-solid fa-triangle-exclamation" style="color: var(--danger); margin-right: 6px;"></i>
                Emergency Incidents & SOS Alerts
            </h3>
            <p style="margin: 2px 0 0; color: var(--text-muted); font-size: 0.85rem;">SOS dispatches, silent alarms, and automated missed check-in alerts</p>
        </div>
        <a href="tel:112" class="btn-call-112 btn-sm">
            <i class="fa-solid fa-phone"></i> Emergency 112
        </a>
    </div>

    <?php if (empty($incidents)): ?>
        <p style="text-align: center; color: var(--text-muted); padding: 2rem 0; margin: 0;">No safety incidents recorded.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Incident ID</th>
                        <th>Alert Type</th>
                        <th>Customer Contact</th>
                        <th>Assigned Provider</th>
                        <th>GPS / Location</th>
                        <th>Timestamp</th>
                        <th>Status</th>
                        <th>Investigate / Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($incidents as $inc): ?>
                        <tr style="<?php echo ($inc['status'] === 'active') ? 'background: #fef2f2;' : ''; ?>">
                            <td><strong>#INC-<?php echo str_pad($inc['id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                            <td>
                                <?php if ($inc['incident_type'] === 'sos'): ?>
                                    <span class="badge badge-cancelled"><i class="fa-solid fa-bell"></i> SOS EMERGENCY</span>
                                <?php elseif ($inc['incident_type'] === 'silent_sos'): ?>
                                    <span class="badge" style="background: #334155; color: #fff;"><i class="fa-solid fa-bell-slash"></i> SILENT SOS</span>
                                <?php elseif ($inc['incident_type'] === 'missed_checkin'): ?>
                                    <span class="badge badge-pending"><i class="fa-solid fa-clock"></i> MISSED CHECK-IN</span>
                                <?php else: ?>
                                    <span class="badge badge-accepted"><?php echo strtoupper($inc['incident_type']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($inc['customer_name']); ?></strong><br>
                                <small><i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($inc['customer_phone']); ?></small>
                            </td>
                            <td>
                                <?php if ($inc['provider_name']): ?>
                                    <strong><?php echo htmlspecialchars($inc['provider_name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($inc['provider_phone']); ?></small>
                                <?php else: ?>
                                    <span style="color: var(--text-muted);">None assigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($inc['latitude'] && $inc['longitude']): ?>
                                    <a href="https://maps.google.com/?q=<?php echo $inc['latitude']; ?>,<?php echo $inc['longitude']; ?>" target="_blank" class="btn btn-secondary btn-sm" style="padding: 2px 8px; font-size: 0.75rem;">
                                        <i class="fa-solid fa-map-location-dot"></i> View GPS Map
                                    </a>
                                <?php else: ?>
                                    <small style="color: var(--text-muted);"><?php echo htmlspecialchars($inc['service_address'] ?? 'Customer Address'); ?></small>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 0.82rem; color: var(--text-muted);"><?php echo date('d M, h:i A', strtotime($inc['created_at'])); ?></td>
                            <td>
                                <span class="badge <?php echo ($inc['status'] === 'active') ? 'badge-cancelled' : (($inc['status'] === 'investigating') ? 'badge-pending' : 'badge-completed'); ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $inc['status'])); ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" action="safety.php" style="display: flex; gap: 4px; align-items: center; margin: 0;">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <input type="hidden" name="action" value="resolve_incident">
                                    <input type="hidden" name="incident_id" value="<?php echo $inc['id']; ?>">
                                    <select name="incident_status" class="form-control" style="font-size: 0.78rem; padding: 4px 6px; width: 110px;">
                                        <option value="investigating" <?php echo ($inc['status'] === 'investigating') ? 'selected' : ''; ?>>Investigate</option>
                                        <option value="resolved" <?php echo ($inc['status'] === 'resolved') ? 'selected' : ''; ?>>Resolved</option>
                                        <option value="false_alarm" <?php echo ($inc['status'] === 'false_alarm') ? 'selected' : ''; ?>>False Alarm</option>
                                    </select>
                                    <button type="submit" class="btn btn-primary btn-sm" style="padding: 4px 8px; font-size: 0.78rem;">Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- SECTION 2: ACTIVE SAFEVIEST SESSIONS MONITOR -->
<div class="card" style="padding: 1.5rem; margin-bottom: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
        <h3 style="margin: 0; font-size: 1.25rem; color: var(--text-dark);">
            <i class="fa-solid fa-satellite-dish" style="color: var(--safety-blue); margin-right: 6px;"></i>
            Live Active SafeVisit Sessions & Check-Ins
        </h3>
        <span class="badge badge-accepted"><?php echo count($activeSessions); ?> Live</span>
    </div>

    <?php if (empty($activeSessions)): ?>
        <p style="text-align: center; color: var(--text-muted); padding: 1.5rem 0; margin: 0;">No active SafeVisit sessions currently running.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Session ID</th>
                        <th>Booking / Service</th>
                        <th>Customer</th>
                        <th>Provider</th>
                        <th>Arrival Status</th>
                        <th>Check-in Interval</th>
                        <th>Next Due</th>
                        <th>Missed Count</th>
                        <th>GPS Sharing</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activeSessions as $as): ?>
                        <tr>
                            <td><strong>#SV-<?php echo str_pad($as['id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                            <td>
                                #BK-<?php echo str_pad($as['booking_id'], 4, '0', STR_PAD_LEFT); ?><br>
                                <small style="color: var(--text-muted);"><?php echo htmlspecialchars($as['service_name']); ?></small>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($as['customer_name']); ?><br>
                                <small style="color: var(--text-muted);"><?php echo htmlspecialchars($as['customer_phone']); ?></small>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($as['provider_name']); ?><br>
                                <small style="color: var(--text-muted);"><?php echo htmlspecialchars($as['provider_phone']); ?></small>
                            </td>
                            <td>
                                <?php if ($as['arrival_verified_at']): ?>
                                    <span class="badge badge-completed"><i class="fa-solid fa-check"></i> Arrived</span>
                                <?php else: ?>
                                    <span class="badge badge-pending">En Route</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $as['checkin_interval_mins']; ?> mins</td>
                            <td style="font-size: 0.82rem;">
                                <?php echo !empty($as['next_checkin_due']) ? date('h:i A', strtotime($as['next_checkin_due'])) : 'Pending'; ?>
                            </td>
                            <td>
                                <?php if ($as['missed_checkins_count'] > 0): ?>
                                    <span class="badge badge-cancelled"><?php echo $as['missed_checkins_count']; ?> Missed</span>
                                <?php else: ?>
                                    <span class="badge badge-completed">0</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($as['location_sharing_enabled']): ?>
                                    <span class="badge badge-completed"><i class="fa-solid fa-location-dot"></i> Active</span>
                                <?php else: ?>
                                    <span class="badge badge-default">Off</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- SECTION 3: PROVIDER VERIFICATION & SUSPENSIONS -->
<div class="card" id="providers" style="padding: 1.5rem; margin-bottom: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
        <div>
            <h3 style="margin: 0; font-size: 1.25rem; color: var(--text-dark);">
                <i class="fa-solid fa-shield-halved" style="color: #059669; margin-right: 6px;"></i>
                Provider Verification & Access Controls
            </h3>
            <p style="margin: 2px 0 0; color: var(--text-muted); font-size: 0.85rem;">Approve government IDs or suspend providers from receiving bookings</p>
        </div>
        <span class="badge badge-accepted"><?php echo count($providersList); ?> Technicians</span>
    </div>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Provider</th>
                    <th>Trade Category</th>
                    <th>Govt ID Type</th>
                    <th>ID Mask</th>
                    <th>Experience / Bio</th>
                    <th>Current Status</th>
                    <th>Administrative Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($providersList as $pv): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($pv['provider_name']); ?></strong><br>
                            <small style="color: var(--text-muted);"><?php echo htmlspecialchars($pv['phone']); ?> &bull; <?php echo htmlspecialchars($pv['city']); ?></small>
                        </td>
                        <td><span class="badge badge-accepted"><?php echo htmlspecialchars($pv['category_name']); ?></span></td>
                        <td><?php echo htmlspecialchars($pv['id_type'] ?? 'National ID'); ?></td>
                        <td><code><?php echo htmlspecialchars($pv['id_number_masked'] ?? 'Not provided'); ?></code></td>
                        <td style="font-size: 0.82rem; color: var(--text-muted); max-width: 180px;">
                            <?php echo htmlspecialchars(substr($pv['verification_notes'] ?? ($pv['bio'] ?? 'Standard provider'), 0, 50)) . '...'; ?>
                        </td>
                        <td><?php echo renderVerifiedBadge($pv['status']); ?></td>
                        <td>
                            <form method="POST" action="safety.php" style="display: flex; gap: 4px; flex-wrap: wrap; margin: 0;">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="action" value="update_provider_status">
                                <input type="hidden" name="provider_id" value="<?php echo $pv['id']; ?>">

                                <?php if ($pv['status'] !== 'approved'): ?>
                                    <button type="submit" name="new_status" value="approved" class="btn btn-sm" style="background: #059669; color: #fff; padding: 4px 8px; font-size: 0.78rem;">
                                        <i class="fa-solid fa-check"></i> Approve
                                    </button>
                                <?php endif; ?>

                                <?php if ($pv['status'] !== 'rejected'): ?>
                                    <button type="submit" name="new_status" value="rejected" class="btn btn-outline btn-sm" style="padding: 4px 8px; font-size: 0.78rem;">
                                        Reject
                                    </button>
                                <?php endif; ?>

                                <?php if ($pv['status'] !== 'suspended'): ?>
                                    <button type="submit" name="new_status" value="suspended" class="btn btn-sm" style="background: #dc2626; color: #fff; padding: 4px 8px; font-size: 0.78rem;">
                                        <i class="fa-solid fa-ban"></i> Suspend
                                    </button>
                                <?php else: ?>
                                    <button type="submit" name="new_status" value="approved" class="btn btn-secondary btn-sm" style="padding: 4px 8px; font-size: 0.78rem;">
                                        Un-suspend
                                    </button>
                                <?php endif; ?>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- SECTION 4: CONFIDENTIAL CUSTOMER COMPLAINTS & REPORTS -->
<div class="card" id="reports" style="padding: 1.5rem; margin-bottom: 2rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
        <div>
            <h3 style="margin: 0; font-size: 1.25rem; color: var(--text-dark);">
                <i class="fa-solid fa-flag" style="color: #dc2626; margin-right: 6px;"></i>
                Confidential Customer Safety Reports
            </h3>
            <p style="margin: 2px 0 0; color: var(--text-muted); font-size: 0.85rem;">Protected reports regarding harassment, threats, suspicious activity, or fraud</p>
        </div>
        <span class="badge badge-accepted"><?php echo count($customerReports); ?> Filed</span>
    </div>

    <?php if (empty($customerReports)): ?>
        <p style="text-align: center; color: var(--text-muted); padding: 1.5rem 0; margin: 0;">No safety complaints filed by customers.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Report ID</th>
                        <th>Category</th>
                        <th>Reporting Customer</th>
                        <th>Reported Provider</th>
                        <th>Incident Date</th>
                        <th>Customer Block</th>
                        <th>Report Details</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customerReports as $cr): ?>
                        <tr style="<?php echo ($cr['admin_status'] === 'pending') ? 'background: #fffbeb;' : ''; ?>">
                            <td><strong>#RPT-<?php echo str_pad($cr['id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                            <td>
                                <span class="badge badge-cancelled"><?php echo strtoupper(str_replace('_', ' ', $cr['category'])); ?></span>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($cr['customer_name']); ?></strong><br>
                                <small style="color: var(--text-muted);"><?php echo htmlspecialchars($cr['customer_phone']); ?></small>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($cr['provider_name']); ?></strong><br>
                                <small style="color: var(--text-muted);"><?php echo htmlspecialchars($cr['provider_phone']); ?> (<?php echo $cr['provider_status']; ?>)</small>
                            </td>
                            <td style="font-size: 0.82rem;"><?php echo date('d M Y', strtotime($cr['incident_date'])); ?></td>
                            <td>
                                <?php if ($cr['block_provider']): ?>
                                    <span class="badge badge-cancelled"><i class="fa-solid fa-user-slash"></i> Blocked</span>
                                <?php else: ?>
                                    <span class="badge badge-default">No</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 0.85rem; max-width: 250px;">
                                <em><?php echo htmlspecialchars($cr['details']); ?></em>
                            </td>
                            <td>
                                <form method="POST" action="safety.php" style="display: flex; flex-direction: column; gap: 4px; margin: 0;">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                    <input type="hidden" name="action" value="update_report">
                                    <input type="hidden" name="report_id" value="<?php echo $cr['id']; ?>">
                                    <input type="hidden" name="provider_id" value="<?php echo $cr['provider_id']; ?>">

                                    <select name="report_status" class="form-control" style="font-size: 0.78rem; padding: 3px 6px;">
                                        <option value="reviewing" <?php echo ($cr['admin_status'] === 'reviewing') ? 'selected' : ''; ?>>Reviewing</option>
                                        <option value="action_taken" <?php echo ($cr['admin_status'] === 'action_taken') ? 'selected' : ''; ?>>Action Taken</option>
                                        <option value="dismissed" <?php echo ($cr['admin_status'] === 'dismissed') ? 'selected' : ''; ?>>Dismissed</option>
                                    </select>

                                    <label style="font-size: 0.75rem; color: #dc2626; display: flex; align-items: center; gap: 4px; margin-top: 2px;">
                                        <input type="checkbox" name="suspend_provider" value="1"> Suspend Pro
                                    </label>

                                    <button type="submit" class="btn btn-secondary btn-sm" style="padding: 3px 6px; font-size: 0.78rem; margin-top: 2px;">
                                        Submit Action
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- SECTION 5: AUDIT TRAIL LOGS -->
<div class="card" style="padding: 1.5rem;">
    <h3 style="margin: 0 0 1rem 0; font-size: 1.25rem; color: var(--text-dark);">
        <i class="fa-solid fa-clock-rotate-left" style="color: var(--safety-blue); margin-right: 6px;"></i>
        Security & Safety Audit Log
    </h3>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Role</th>
                    <th>Action</th>
                    <th>Target</th>
                    <th>Details</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($auditLogs as $log): ?>
                    <tr>
                        <td style="font-size: 0.8rem; color: var(--text-muted);"><?php echo date('d M Y, h:i:s A', strtotime($log['created_at'])); ?></td>
                        <td><strong><?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?></strong></td>
                        <td><span class="badge badge-default" style="font-size: 0.72rem;"><?php echo htmlspecialchars($log['user_role'] ?? 'cron'); ?></span></td>
                        <td><code><?php echo htmlspecialchars($log['action']); ?></code></td>
                        <td><?php echo htmlspecialchars($log['target_type']); ?> #<?php echo $log['target_id']; ?></td>
                        <td style="font-size: 0.82rem; color: var(--text-muted);"><?php echo htmlspecialchars($log['details']); ?></td>
                        <td style="font-size: 0.78rem; color: var(--text-light);"><?php echo htmlspecialchars($log['ip_address'] ?? '127.0.0.1'); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="safety-disclaimer-box" style="margin-top: 1.5rem;">
    <strong>Platform Disclaimer:</strong> <?php echo getSafetyDisclaimer(); ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

