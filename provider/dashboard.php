<?php
$pageTitle = "Provider Dashboard";
require_once __DIR__ . '/../config/db.php';
requireRole('provider');

$pdo = getDB();
$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'Provider';

// Get provider record
$stmtProv = $pdo->prepare("SELECT p.*, c.name as category_name, u.name, u.phone, u.city 
                           FROM providers p 
                           JOIN users u ON p.user_id = u.id 
                           JOIN service_categories c ON p.category_id = c.id 
                           WHERE p.user_id = ? LIMIT 1");
$stmtProv->execute([$userId]);
$provider = $stmtProv->fetch();
$providerId = $provider['id'] ?? 0;

// Handle availability update
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['availability'])) {
    $newAvail = $_POST['availability'];
    if (in_array($newAvail, ['available', 'busy', 'offline']) && $providerId) {
        $uAvail = $pdo->prepare("UPDATE providers SET availability = ? WHERE id = ?");
        $uAvail->execute([$newAvail, $providerId]);
        $provider['availability'] = $newAvail;
        setFlash('success', "Your availability is now: " . ucfirst($newAvail));
        header("Location: dashboard.php");
        exit;
    }
}

// Handle Quick Accept/Reject from dashboard table
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['quick_action'])) {
    $action = $_POST['quick_action'];
    $bId = (int)($_POST['booking_id'] ?? 0);
    if ($bId > 0 && $providerId && in_array($action, ['accept', 'reject'])) {
        if ($action === 'accept') {
            if (($provider['status'] ?? '') !== 'approved') {
                setFlash('danger', "Verification Required: Your account is currently " . ucfirst($provider['status'] ?? 'pending') . ". Only verified and approved providers can accept bookings. Please visit 'Verification Status' to submit your ID.");
                header("Location: dashboard.php");
                exit;
            }
            $up = $pdo->prepare("UPDATE bookings SET status = 'Accepted' WHERE id = ? AND provider_id = ?");
            $up->execute([$bId, $providerId]);
            setFlash('success', "Booking #BK-" . str_pad($bId, 4, '0', STR_PAD_LEFT) . " accepted!");
        } elseif ($action === 'reject') {
            $up = $pdo->prepare("UPDATE bookings SET status = 'Pending', provider_id = NULL WHERE id = ? AND provider_id = ?");
            $up->execute([$bId, $providerId]);
            setFlash('warning', "Booking #BK-" . str_pad($bId, 4, '0', STR_PAD_LEFT) . " declined.");
        }
        header("Location: dashboard.php");
        exit;
    }
}

// Stats Calculation
$stats = [
    'total_requests' => 0,
    'pending' => 0,
    'accepted' => 0,
    'completed' => 0,
    'total_earnings' => 0.00,
    'avg_rating' => 5.0,
    'review_count' => 0
];

if ($providerId) {
    $stmtCounts = $pdo->prepare("SELECT 
        COUNT(*) as total_requests,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status IN ('Accepted', 'In Progress') THEN 1 ELSE 0 END) as accepted,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status = 'Completed' THEN total_price ELSE 0 END) as total_earnings
        FROM bookings WHERE provider_id = ?");
    $stmtCounts->execute([$providerId]);
    $counts = $stmtCounts->fetch();
    if ($counts) {
        $stats['total_requests'] = (int)($counts['total_requests'] ?? 0);
        $stats['pending'] = (int)($counts['pending'] ?? 0);
        $stats['accepted'] = (int)($counts['accepted'] ?? 0);
        $stats['completed'] = (int)($counts['completed'] ?? 0);
        $stats['total_earnings'] = (float)($counts['total_earnings'] ?? 0);
    }

    // Ratings
    $stmtRating = $pdo->prepare("SELECT AVG(rating) as avg_r, COUNT(*) as cnt FROM reviews WHERE provider_id = ?");
    $stmtRating->execute([$providerId]);
    $rData = $stmtRating->fetch();
    if ($rData && $rData['cnt'] > 0) {
        $stats['avg_rating'] = round((float)$rData['avg_r'], 1);
        $stats['review_count'] = (int)$rData['cnt'];
    }
}

// Fetch recent bookings & requests for Ramesh / this provider
$recentRequests = [];
if ($providerId) {
    $stmtReq = $pdo->prepare("SELECT b.*, s.name as service_name, c.name as category_name, 
                              u.name as customer_name, u.phone as customer_phone
                              FROM bookings b
                              JOIN services s ON b.service_id = s.id
                              JOIN service_categories c ON s.category_id = c.id
                              JOIN users u ON b.customer_id = u.id
                              WHERE b.provider_id = ?
                              ORDER BY 
                                CASE WHEN b.status = 'Pending' THEN 1 
                                     WHEN b.status = 'Accepted' THEN 2 
                                     WHEN b.status = 'In Progress' THEN 3 
                                     ELSE 4 END ASC,
                                b.booking_date DESC 
                              LIMIT 6");
    $stmtReq->execute([$providerId]);
    $recentRequests = $stmtReq->fetchAll();
}

$pageGreeting = "Welcome back, " . htmlspecialchars($provider['name'] ?? $userName) . "!";
$pageSubtitle = "Here's your service summary for today.";

require_once __DIR__ . '/../includes/header.php';
?>

<?php if (($provider['status'] ?? '') !== 'approved'): ?>
    <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <i class="fa-solid fa-shield-halved" style="color: #d97706; font-size: 1.25rem;"></i>
            <div>
                <strong style="color: #92400e;">Account Status: <?php echo ucfirst($provider['status'] ?? 'pending'); ?></strong>
                <p style="margin: 0; font-size: 0.85rem; color: #b45309;">Only approved and verified providers can accept customer bookings.</p>
            </div>
        </div>
        <a href="verification.php" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-id-card"></i> Complete Verification
        </a>
    </div>
<?php else: ?>
    <div style="display: flex; justify-content: flex-end; align-items: center; margin-bottom: 1rem;">
        <div style="font-size: 0.9rem; display: flex; align-items: center; gap: 6px;">
            <span>Verification:</span>
            <?php echo renderVerifiedBadge('approved'); ?>
        </div>
    </div>
<?php endif; ?>

<!-- 1. Row of 4 Compact KPI Stat Cards -->
<div class="grid grid-4" style="margin-bottom: 1.6rem;">
    <!-- Total Requests -->
    <div class="kpi-card">
        <div class="kpi-content">
            <span class="kpi-label">Total Requests</span>
            <span class="kpi-value"><?php echo $stats['total_requests']; ?></span>
            <span class="kpi-subtext positive"><i class="fa-solid fa-arrow-trend-up"></i> +15% this week</span>
        </div>
        <div class="kpi-icon blue">
            <i class="fa-solid fa-clipboard-list"></i>
        </div>
    </div>

    <!-- Pending -->
    <div class="kpi-card">
        <div class="kpi-content">
            <span class="kpi-label">Pending</span>
            <span class="kpi-value"><?php echo $stats['pending']; ?></span>
            <span class="kpi-subtext" style="color:var(--warning);"><i class="fa-solid fa-bell"></i> Needs attention</span>
        </div>
        <div class="kpi-icon amber">
            <i class="fa-solid fa-hourglass-half"></i>
        </div>
    </div>

    <!-- Accepted / Active -->
    <div class="kpi-card">
        <div class="kpi-content">
            <span class="kpi-label">Accepted</span>
            <span class="kpi-value"><?php echo $stats['accepted']; ?></span>
            <span class="kpi-subtext positive"><i class="fa-solid fa-clock"></i> Active jobs</span>
        </div>
        <div class="kpi-icon teal">
            <i class="fa-solid fa-person-digging"></i>
        </div>
    </div>

    <!-- Completed -->
    <div class="kpi-card">
        <div class="kpi-content">
            <span class="kpi-label">Completed</span>
            <span class="kpi-value"><?php echo $stats['completed']; ?></span>
            <span class="kpi-subtext positive"><i class="fa-solid fa-circle-check"></i> Finished</span>
        </div>
        <div class="kpi-icon green">
            <i class="fa-solid fa-circle-check"></i>
        </div>
    </div>
</div>

<!-- 2. Middle Section: Recent Booking / Requests (Left) & Earnings / Quick Info (Right) -->
<div class="dashboard-split" id="schedule">
    <!-- Left: Recent Booking / Requests Table -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fa-solid fa-list-check"></i> Recent Booking / Requests
            </h3>
            <a href="requests.php" style="font-size: 0.85rem; font-weight: 600; color: var(--primary);">
                View All <i class="fa-solid fa-arrow-right" style="font-size: 11px;"></i>
            </a>
        </div>

        <?php if (empty($recentRequests)): ?>
            <div style="text-align: center; padding: 3rem 1rem;">
                <div style="width: 50px; height: 50px; border-radius: 50%; background: #eff6ff; color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin: 0 auto 1rem;">
                    <i class="fa-solid fa-mug-hot"></i>
                </div>
                <h4 style="font-size: 1.05rem; margin-bottom: 4px;">No booking requests assigned yet</h4>
                <p style="color: var(--text-muted); font-size: 0.88rem;">New appointments scheduled by customers will appear here automatically.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Service</th>
                            <th>Customer</th>
                            <th>Date & Time</th>
                            <th>Status</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentRequests as $req): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600; color: var(--text-dark);">
                                        <?php echo htmlspecialchars($req['service_name']); ?>
                                    </div>
                                    <small style="color: var(--text-muted); font-size: 0.8rem;">
                                        #BK-<?php echo str_pad($req['id'], 4, '0', STR_PAD_LEFT); ?> &bull; <?php echo formatPrice($req['total_price']); ?>
                                    </small>
                                </td>
                                <td>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($req['customer_name']); ?></div>
                                    <small style="color: var(--text-muted);">
                                        <i class="fa-solid fa-phone" style="font-size:10px;"></i> <?php echo htmlspecialchars($req['customer_phone']); ?>
                                    </small>
                                </td>
                                <td>
                                    <div><?php echo date('d M Y', strtotime($req['booking_date'])); ?></div>
                                    <small style="color: var(--text-muted);"><?php echo date('h:i A', strtotime($req['booking_time'])); ?></small>
                                </td>
                                <td>
                                    <?php echo getStatusBadge($req['status']); ?>
                                </td>
                                <td style="text-align: right;">
                                    <?php if ($req['status'] === 'Pending'): ?>
                                        <div style="display: inline-flex; gap: 6px;">
                                            <form method="POST" action="dashboard.php" style="display:inline;">
                                                <input type="hidden" name="booking_id" value="<?php echo $req['id']; ?>">
                                                <input type="hidden" name="quick_action" value="accept">
                                                <button type="submit" class="btn btn-success btn-sm" title="Accept Request">
                                                    Accept
                                                </button>
                                            </form>
                                            <form method="POST" action="dashboard.php" style="display:inline;">
                                                <input type="hidden" name="booking_id" value="<?php echo $req['id']; ?>">
                                                <input type="hidden" name="quick_action" value="reject">
                                                <button type="submit" class="btn btn-outline btn-sm" style="color:var(--danger);border-color:#fecaca;" title="Decline">
                                                    Reject
                                                </button>
                                            </form>
                                        </div>
                                    <?php elseif ($req['status'] === 'Accepted' || $req['status'] === 'In Progress'): ?>
                                        <a href="update-status.php?booking_id=<?php echo $req['id']; ?>" class="btn btn-primary btn-sm">
                                            Update
                                        </a>
                                    <?php else: ?>
                                        <a href="requests.php" class="btn btn-secondary btn-sm">
                                            View
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right: Earnings Overview & Quick Info -->
    <div>
        <!-- Earnings Overview Card -->
        <div class="earnings-overview-card" id="earnings">
            <div style="display: flex; justify-content: space-between; align-items: baseline;">
                <h4 style="font-size: 0.95rem; font-weight: 700; color: var(--text-dark);">Earnings Overview</h4>
                <span class="earnings-badge"><i class="fa-solid fa-arrow-trend-up"></i> +12%</span>
            </div>

            <div class="earnings-amount">
                <?php echo formatPrice($stats['total_earnings']); ?>
            </div>
            <p style="font-size: 0.8rem; color: var(--text-muted);">Net earnings from completed customer jobs</p>

            <!-- Mini Visual Bar Chart (Last 7 Days) -->
            <div class="mini-bar-chart">
                <div class="bar-col" style="height: 35%;" title="Mon: ₹598"></div>
                <div class="bar-col" style="height: 55%;" title="Tue: ₹999"></div>
                <div class="bar-col" style="height: 40%;" title="Wed: ₹698"></div>
                <div class="bar-col" style="height: 80%;" title="Thu: ₹1,499"></div>
                <div class="bar-col" style="height: 65%;" title="Fri: ₹1,198"></div>
                <div class="bar-col" style="height: 95%;" title="Sat: ₹1,898"></div>
                <div class="bar-col active" style="height: 85%;" title="Sun: ₹1,598"></div>
            </div>

            <a href="requests.php" class="btn btn-primary btn-block btn-sm" style="margin-top: 0.5rem;">
                <i class="fa-solid fa-wallet"></i> View Earnings Details
            </a>
        </div>

        <!-- Quick Info / Availability Card -->
        <div class="card" id="availability">
            <h4 style="font-size: 0.95rem; font-weight: 700; color: var(--text-dark); margin-bottom: 0.85rem;">
                Quick Info
            </h4>

            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <!-- Set Availability Interactive Form -->
                <form method="POST" action="dashboard.php" style="background:#f8fafc;padding:0.75rem;border-radius:var(--radius-md);border:1px solid var(--border-light);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 0.88rem; font-weight: 600; color: var(--text-dark);">
                            <i class="fa-solid fa-clock" style="color:var(--primary);margin-right:6px;"></i> Work Status
                        </span>
                        <select name="availability" onchange="this.form.submit()" class="form-control" style="width: auto; padding: 0.25rem 0.65rem; font-size: 0.82rem; font-weight: 600;">
                            <option value="available" <?php echo (($provider['availability'] ?? '') === 'available') ? 'selected' : ''; ?>>🟢 Available</option>
                            <option value="busy" <?php echo (($provider['availability'] ?? '') === 'busy') ? 'selected' : ''; ?>>🟡 Busy</option>
                            <option value="offline" <?php echo (($provider['availability'] ?? '') === 'offline') ? 'selected' : ''; ?>>🔴 Offline</option>
                        </select>
                    </div>
                </form>

                <a href="profile.php" class="quick-action-item" style="margin-bottom: 0;">
                    <div class="quick-action-left">
                        <div class="quick-action-icon"><i class="fa-solid fa-user-pen"></i></div>
                        <span>Update Profile</span>
                    </div>
                    <i class="fa-solid fa-chevron-right" style="font-size: 0.8rem; color: var(--text-muted);"></i>
                </a>

                <div class="quick-action-item" style="margin-bottom: 0;">
                    <div class="quick-action-left">
                        <div class="quick-action-icon" style="background:#f0fdfa;color:#0d9488;"><i class="fa-solid fa-location-dot"></i></div>
                        <span>Area: <?php echo htmlspecialchars($provider['city'] ?? 'Bengaluru'); ?></span>
                    </div>
                    <span style="font-size:0.75rem;color:var(--primary);font-weight:600;">Active</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 3. Bottom Tip Banner (Matching Reference Image) -->
<div class="tip-banner-card">
    <div class="tip-banner-left">
        <img src="<?php echo $pathPrefix; ?>assets/images/provider-pro.jpg" alt="Provider Support" class="tip-avatar">
        <div class="tip-banner-text">
            <h4>Provide great service, build your reputation!</h4>
            <p>Happy customers leave 5-star ratings and bring you higher weekly job dispatch priority.</p>
        </div>
    </div>
    <a href="profile.php" class="btn btn-primary btn-sm" style="white-space: nowrap;">
        View Tips
    </a>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
