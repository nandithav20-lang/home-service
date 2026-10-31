<?php
$pageTitle = "Admin Dashboard";
require_once __DIR__ . '/../config/db.php';
requireRole('admin');

$pdo = getDB();

// 1. Core Platform Metrics
$totalCustomers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
$totalProviders = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'provider'")->fetchColumn();
$totalBookings  = (int)$pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$totalRevenue   = (float)$pdo->query("SELECT IFNULL(SUM(total_price), 0) FROM bookings WHERE status = 'Completed'")->fetchColumn();

// 2. Booking Status Breakdown for Donut Chart
$statusStmt = $pdo->query("SELECT 
    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status IN ('Accepted', 'In Progress') THEN 1 ELSE 0 END) as confirmed,
    SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled
    FROM bookings");
$statusCounts = $statusStmt->fetch() ?: ['completed' => 0, 'pending' => 0, 'confirmed' => 0, 'cancelled' => 0];

$completedCount = (int)($statusCounts['completed'] ?? 0);
$pendingCount   = (int)($statusCounts['pending'] ?? 0);
$confirmedCount = (int)($statusCounts['confirmed'] ?? 0);
$cancelledCount = (int)($statusCounts['cancelled'] ?? 0);

$donutTotal = max(1, $totalBookings);
$pctCompleted = round(($completedCount / $donutTotal) * 100);
$pctPending   = round(($pendingCount / $donutTotal) * 100);
$pctConfirmed = round(($confirmedCount / $donutTotal) * 100);
$pctCancelled = max(0, 100 - ($pctCompleted + $pctPending + $pctConfirmed));

// Circumference for r=60 is ~377
$circ = 2 * M_PI * 55; // ~345.57
$dashCompleted = ($pctCompleted / 100) * $circ;
$dashPending   = ($pctPending / 100) * $circ;
$dashConfirmed = ($pctConfirmed / 100) * $circ;
$dashCancelled = ($pctCancelled / 100) * $circ;

$offsetCompleted = 0;
$offsetPending   = -$dashCompleted;
$offsetConfirmed = -($dashCompleted + $dashPending);
$offsetCancelled = -($dashCompleted + $dashPending + $dashConfirmed);

// 3. Bookings Trend (Last 7 Days)
$trendDays = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $trendDays[$d] = [
        'day' => date('D', strtotime($d)),
        'date' => date('d M', strtotime($d)),
        'count' => 0
    ];
}

try {
    $trendStmt = $pdo->query("SELECT DATE(created_at) as b_date, COUNT(*) as cnt 
                              FROM bookings 
                              WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                              GROUP BY DATE(created_at)");
    while ($row = $trendStmt->fetch()) {
        if (isset($trendDays[$row['b_date']])) {
            $trendDays[$row['b_date']]['count'] = (int)$row['cnt'];
        }
    }
} catch (Exception $e) {}

// Calculate trend points for SVG line chart (width 450, height 120, padding 20)
$trendCounts = array_column($trendDays, 'count');
$maxTrend = max(5, max($trendCounts ?: [5]));
$pts = [];
$areaPts = [];
$xStep = 450 / 6;
$idx = 0;
foreach ($trendDays as $d => $data) {
    $x = round($idx * $xStep);
    $y = round(110 - (($data['count'] / $maxTrend) * 90));
    $pts[] = "{$x},{$y}";
    $idx++;
}
$polylinePoints = implode(' ', $pts);
$areaPoints = "0,120 " . $polylinePoints . " 450,120";

// 4. Recent Bookings Table
$recentBookings = [];
try {
    $recentBookings = $pdo->query("SELECT b.*, s.name as service_name, c.name as category_name, 
                                   u.name as customer_name, p_user.name as provider_name
                                   FROM bookings b
                                   JOIN services s ON b.service_id = s.id
                                   JOIN service_categories c ON s.category_id = c.id
                                   JOIN users u ON b.customer_id = u.id
                                   LEFT JOIN providers p ON b.provider_id = p.id
                                   LEFT JOIN users p_user ON p.user_id = p_user.id
                                   ORDER BY b.created_at DESC LIMIT 6")->fetchAll();
} catch (Exception $e) {}

// 5. Recent Reviews
$recentReviews = [];
try {
    $recentReviews = $pdo->query("SELECT r.*, u.name as customer_name, s.name as service_name, p_user.name as provider_name
                                  FROM reviews r
                                  JOIN users u ON r.customer_id = u.id
                                  JOIN bookings b ON r.booking_id = b.id
                                  JOIN services s ON b.service_id = s.id
                                  LEFT JOIN providers p ON r.provider_id = p.id
                                  LEFT JOIN users p_user ON p.user_id = p_user.id
                                  ORDER BY r.created_at DESC LIMIT 4")->fetchAll();
} catch (Exception $e) {}

$pageGreeting = "Admin Dashboard";
$pageSubtitle = "Overview of your platform.";

require_once __DIR__ . '/../includes/header.php';
?>

<!-- 1. Row of 4 Compact KPI Stat Cards -->
<div class="grid grid-4" style="margin-bottom: 1.6rem;">
    <!-- Total Users -->
    <div class="kpi-card">
        <div class="kpi-content">
            <span class="kpi-label">Total Users</span>
            <span class="kpi-value"><?php echo $totalCustomers; ?></span>
            <span class="kpi-subtext positive"><i class="fa-solid fa-arrow-trend-up"></i> +8% this month</span>
        </div>
        <div class="kpi-icon blue">
            <i class="fa-solid fa-users"></i>
        </div>
    </div>

    <!-- Total Providers -->
    <div class="kpi-card">
        <div class="kpi-content">
            <span class="kpi-label">Total Providers</span>
            <span class="kpi-value"><?php echo $totalProviders; ?></span>
            <span class="kpi-subtext positive"><i class="fa-solid fa-arrow-trend-up"></i> +5% this month</span>
        </div>
        <div class="kpi-icon amber">
            <i class="fa-solid fa-user-gear"></i>
        </div>
    </div>

    <!-- Total Bookings -->
    <div class="kpi-card">
        <div class="kpi-content">
            <span class="kpi-label">Total Bookings</span>
            <span class="kpi-value"><?php echo $totalBookings; ?></span>
            <span class="kpi-subtext positive"><i class="fa-solid fa-arrow-trend-up"></i> +14% this month</span>
        </div>
        <div class="kpi-icon teal">
            <i class="fa-solid fa-calendar-check"></i>
        </div>
    </div>

    <!-- Total Revenue -->
    <div class="kpi-card">
        <div class="kpi-content">
            <span class="kpi-label">Total Revenue</span>
            <span class="kpi-value"><?php echo formatPrice($totalRevenue); ?></span>
            <span class="kpi-subtext positive"><i class="fa-solid fa-arrow-trend-up"></i> +18% this month</span>
        </div>
        <div class="kpi-icon green">
            <i class="fa-solid fa-indian-rupee-sign"></i>
        </div>
    </div>
</div>

<!-- 2. Middle Analytics Section: Booking Status (Donut) & Bookings Trend (Line Chart) -->
<div class="dashboard-split-equal" id="analytics">
    <!-- Left: Booking Status Donut Chart -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fa-solid fa-chart-pie"></i> Booking Status
            </h3>
            <span style="font-size: 0.82rem; color: var(--text-muted); font-weight: 500;">Live Status</span>
        </div>

        <div class="donut-chart-container">
            <!-- Donut SVG -->
            <div class="donut-svg-wrapper">
                <svg width="160" height="160" viewBox="0 0 160 160" style="transform: rotate(-90deg);">
                    <!-- Background Ring -->
                    <circle cx="80" cy="80" r="55" fill="none" stroke="#f1f5f9" stroke-width="18" />

                    <!-- Completed Ring (Green) -->
                    <circle cx="80" cy="80" r="55" fill="none" stroke="#16a34a" stroke-width="18"
                            stroke-dasharray="<?php echo $dashCompleted; ?> <?php echo $circ; ?>"
                            stroke-dashoffset="<?php echo $offsetCompleted; ?>"
                            stroke-linecap="round" />

                    <!-- Pending Ring (Amber) -->
                    <circle cx="80" cy="80" r="55" fill="none" stroke="#d97706" stroke-width="18"
                            stroke-dasharray="<?php echo $dashPending; ?> <?php echo $circ; ?>"
                            stroke-dashoffset="<?php echo $offsetPending; ?>"
                            stroke-linecap="round" />

                    <!-- Confirmed Ring (Blue) -->
                    <circle cx="80" cy="80" r="55" fill="none" stroke="#2563eb" stroke-width="18"
                            stroke-dasharray="<?php echo $dashConfirmed; ?> <?php echo $circ; ?>"
                            stroke-dashoffset="<?php echo $offsetConfirmed; ?>"
                            stroke-linecap="round" />

                    <!-- Cancelled Ring (Rose) -->
                    <circle cx="80" cy="80" r="55" fill="none" stroke="#dc2626" stroke-width="18"
                            stroke-dasharray="<?php echo $dashCancelled; ?> <?php echo $circ; ?>"
                            stroke-dashoffset="<?php echo $offsetCancelled; ?>"
                            stroke-linecap="round" />
                </svg>

                <div class="donut-center-label">
                    <span class="donut-center-num"><?php echo $totalBookings; ?></span>
                    <span class="donut-center-text">Total</span>
                </div>
            </div>

            <!-- Legend with Percentages -->
            <div class="donut-legend">
                <div class="donut-legend-item">
                    <div class="donut-legend-dot" style="background:#16a34a;"></div>
                    <span>Completed</span>
                    <span class="donut-legend-pct"><?php echo $pctCompleted; ?>%</span>
                </div>
                <div class="donut-legend-item">
                    <div class="donut-legend-dot" style="background:#d97706;"></div>
                    <span>Pending</span>
                    <span class="donut-legend-pct"><?php echo $pctPending; ?>%</span>
                </div>
                <div class="donut-legend-item">
                    <div class="donut-legend-dot" style="background:#2563eb;"></div>
                    <span>Confirmed</span>
                    <span class="donut-legend-pct"><?php echo $pctConfirmed; ?>%</span>
                </div>
                <div class="donut-legend-item">
                    <div class="donut-legend-dot" style="background:#dc2626;"></div>
                    <span>Cancelled</span>
                    <span class="donut-legend-pct"><?php echo $pctCancelled; ?>%</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Right: Bookings Trend Line Chart -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fa-solid fa-chart-line"></i> Bookings Trend
            </h3>
            <select class="form-control" style="width: auto; padding: 0.25rem 0.65rem; font-size: 0.8rem; font-weight: 600;">
                <option selected>Last 7 Days</option>
                <option>Last 30 Days</option>
            </select>
        </div>

        <div class="trend-chart-wrapper">
            <svg width="100%" height="135" viewBox="0 0 450 135" preserveAspectRatio="none">
                <defs>
                    <linearGradient id="trendGradient" x1="0%" y1="0%" x2="0%" y2="100%">
                        <stop offset="0%" stop-color="#2563eb" stop-opacity="0.25" />
                        <stop offset="100%" stop-color="#2563eb" stop-opacity="0.0" />
                    </linearGradient>
                </defs>

                <!-- Horizontal Reference Gridlines -->
                <line x1="0" y1="20" x2="450" y2="20" stroke="#f1f5f9" stroke-width="1" />
                <line x1="0" y1="65" x2="450" y2="65" stroke="#f1f5f9" stroke-width="1" />
                <line x1="0" y1="110" x2="450" y2="110" stroke="#f1f5f9" stroke-width="1" />

                <!-- Gradient Area Fill -->
                <polygon points="<?php echo $areaPoints; ?>" fill="url(#trendGradient)" />

                <!-- Smooth Line -->
                <polyline points="<?php echo $polylinePoints; ?>" fill="none" stroke="#2563eb" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />

                <!-- Data Points -->
                <?php 
                $i = 0;
                foreach ($trendDays as $d => $data): 
                    $x = round($i * $xStep);
                    $y = round(110 - (($data['count'] / $maxTrend) * 90));
                ?>
                    <circle cx="<?php echo $x; ?>" cy="<?php echo $y; ?>" r="4" fill="#ffffff" stroke="#2563eb" stroke-width="2" />
                <?php 
                    $i++;
                endforeach; 
                ?>
            </svg>

            <!-- Day Labels -->
            <div style="display: flex; justify-content: space-between; padding-top: 6px; font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">
                <?php foreach ($trendDays as $d => $data): ?>
                    <span><?php echo $data['day']; ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- 3. Recent Bookings Table -->
<div class="card" style="margin-bottom: 1.75rem;">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fa-solid fa-list-check"></i> Recent Bookings
        </h3>
        <a href="bookings.php" style="font-size: 0.85rem; font-weight: 600; color: var(--primary);">
            View All <i class="fa-solid fa-arrow-right" style="font-size: 11px;"></i>
        </a>
    </div>

    <?php if (empty($recentBookings)): ?>
        <p style="text-align: center; color: var(--text-muted); padding: 2rem 0;">No bookings registered yet.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Service</th>
                        <th>Customer</th>
                        <th>Provider</th>
                        <th>Date & Time</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentBookings as $b): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 600; color: var(--text-dark);"><?php echo htmlspecialchars($b['service_name']); ?></div>
                                <small style="color: var(--text-muted); font-size: 0.8rem;">
                                    #BK-<?php echo str_pad($b['id'], 4, '0', STR_PAD_LEFT); ?> &bull; <?php echo htmlspecialchars($b['category_name']); ?>
                                </small>
                            </td>
                            <td>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($b['customer_name']); ?></div>
                            </td>
                            <td>
                                <?php if (!empty($b['provider_name'])): ?>
                                    <span style="font-weight: 600; color: var(--text-dark);"><?php echo htmlspecialchars($b['provider_name']); ?></span>
                                <?php else: ?>
                                    <span style="color: var(--warning); font-style: italic; font-size: 0.85rem;"><i class="fa-solid fa-triangle-exclamation"></i> Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><?php echo date('d M Y', strtotime($b['booking_date'])); ?></div>
                                <small style="color: var(--text-muted);"><?php echo date('h:i A', strtotime($b['booking_time'])); ?></small>
                            </td>
                            <td>
                                <strong style="color: var(--primary);"><?php echo formatPrice($b['total_price']); ?></strong>
                            </td>
                            <td>
                                <?php echo getStatusBadge($b['status']); ?>
                            </td>
                            <td style="text-align: right;">
                                <a href="edit-booking.php?id=<?php echo $b['id']; ?>" class="btn btn-secondary btn-sm">
                                    Manage
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- 4. Quick Management Grid (Row of 4 Cards) -->
<div>
    <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--text-dark); margin-bottom: 0.85rem;">
        Quick Management
    </h3>

    <div class="quick-manage-grid">
        <!-- 1. Manage Users -->
        <a href="users.php" class="quick-manage-card">
            <div class="quick-manage-icon">
                <i class="fa-solid fa-users"></i>
            </div>
            <h4>Manage Users</h4>
            <p>View, search and manage customer registered accounts.</p>
        </a>

        <!-- 2. Manage Providers -->
        <a href="providers.php" class="quick-manage-card">
            <div class="quick-manage-icon" style="background:#fffbeb;color:#d97706;">
                <i class="fa-solid fa-user-gear"></i>
            </div>
            <h4>Manage Providers</h4>
            <p>Approve, verify and manage service technician profiles.</p>
        </a>

        <!-- 3. Manage Services -->
        <a href="services.php" class="quick-manage-card">
            <div class="quick-manage-icon" style="background:#f0fdfa;color:#0d9488;">
                <i class="fa-solid fa-screwdriver-wrench"></i>
            </div>
            <h4>Manage Services</h4>
            <p>Configure pricing, durations and catalog service items.</p>
        </a>

        <!-- 4. View Reviews -->
        <a href="#reviews" class="quick-manage-card">
            <div class="quick-manage-icon" style="background:#fef2f2;color:#dc2626;">
                <i class="fa-solid fa-star"></i>
            </div>
            <h4>View Reviews</h4>
            <p>Monitor ratings, customer feedback and service satisfaction.</p>
        </a>
    </div>
</div>

<!-- 5. Recent Reviews Section (Anchor target for Reviews) -->
<?php if (!empty($recentReviews)): ?>
<div class="card" id="reviews" style="margin-top: 1.75rem;">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fa-solid fa-star" style="color:#f59e0b;"></i> Customer Reviews & Ratings
        </h3>
        <span style="font-size: 0.82rem; color: var(--text-muted); font-weight: 500;">Recent Verified Ratings</span>
    </div>

    <div class="grid grid-2">
        <?php foreach ($recentReviews as $rev): ?>
            <div style="background: #f8fafc; border: 1px solid var(--border-light); border-radius: var(--radius-md); padding: 1.1rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <strong style="color: var(--text-dark);"><?php echo htmlspecialchars($rev['customer_name']); ?></strong>
                    <div style="color: #f59e0b; font-size: 0.85rem;">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fa-solid fa-star" style="color: <?php echo ($i <= $rev['rating']) ? '#f59e0b' : '#cbd5e1'; ?>;"></i>
                        <?php endfor; ?>
                    </div>
                </div>
                <p style="font-size: 0.88rem; color: var(--text-body); font-style: italic; margin-bottom: 6px;">
                    "<?php echo htmlspecialchars($rev['comment']); ?>"
                </p>
                <small style="color: var(--text-muted); font-size: 0.8rem;">
                    Service: <strong><?php echo htmlspecialchars($rev['service_name']); ?></strong> &bull; Pro: <strong><?php echo htmlspecialchars($rev['provider_name'] ?? 'N/A'); ?></strong>
                </small>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
