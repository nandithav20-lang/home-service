<?php
$pageTitle = "Customer Dashboard";
require_once __DIR__ . '/../config/db.php';
requireRole('customer');

$pdo = getDB();
$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';

// Dynamic greeting based on time of day
$hour = (int)date('H');
if ($hour < 12) {
    $timeGreeting = "Good Morning";
} elseif ($hour < 17) {
    $timeGreeting = "Good Afternoon";
} else {
    $timeGreeting = "Good Evening";
}
$pageGreeting = "{$timeGreeting}, " . htmlspecialchars($userName) . " 👋";
$pageSubtitle = "Here's what happening with your services today.";

// Fetch live user stats
$stats = [
    'total' => 0,
    'pending' => 0,
    'confirmed' => 0,
    'completed' => 0
];

try {
    $stmt = $pdo->prepare("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status IN ('Accepted', 'In Progress') THEN 1 ELSE 0 END) as confirmed,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed
        FROM bookings WHERE customer_id = ?");
    $stmt->execute([$userId]);
    $dbStats = $stmt->fetch();
    if ($dbStats) {
        $stats['total'] = (int)($dbStats['total'] ?? 0);
        $stats['pending'] = (int)($dbStats['pending'] ?? 0);
        $stats['confirmed'] = (int)($dbStats['confirmed'] ?? 0);
        $stats['completed'] = (int)($dbStats['completed'] ?? 0);
    }
} catch (Exception $e) {}

// Fetch upcoming & recent bookings for this customer
$upcomingBookings = [];
try {
    $stmt = $pdo->prepare("SELECT b.*, s.name as service_name, c.name as category_name, c.icon as category_icon,
                           u.name as provider_name, u.phone as provider_phone
                           FROM bookings b
                           JOIN services s ON b.service_id = s.id
                           JOIN service_categories c ON s.category_id = c.id
                           LEFT JOIN providers p ON b.provider_id = p.id
                           LEFT JOIN users u ON p.user_id = u.id
                           WHERE b.customer_id = ?
                           ORDER BY 
                             CASE WHEN b.status = 'Pending' THEN 1 
                                  WHEN b.status IN ('Accepted', 'In Progress') THEN 2 
                                  ELSE 3 END ASC,
                             b.booking_date ASC 
                           LIMIT 5");
    $stmt->execute([$userId]);
    $upcomingBookings = $stmt->fetchAll();
} catch (Exception $e) {}

// Fetch categories for popular services section
$categories = [];
try {
    $categories = $pdo->query("SELECT * FROM service_categories ORDER BY id ASC LIMIT 6")->fetchAll();
} catch (Exception $e) {}

require_once __DIR__ . '/../includes/header.php';
?>

<!-- 1. Row of 4 Compact KPI Stat Cards -->
<div class="grid grid-4" style="margin-bottom: 1.6rem;">
    <!-- Total Bookings -->
    <div class="kpi-card">
        <div class="kpi-content">
            <span class="kpi-label">Total Bookings</span>
            <span class="kpi-value"><?php echo $stats['total']; ?></span>
            <span class="kpi-subtext neutral"><i class="fa-solid fa-layer-group"></i> All Time</span>
        </div>
        <div class="kpi-icon blue">
            <i class="fa-solid fa-calendar-check"></i>
        </div>
    </div>

    <!-- Pending -->
    <div class="kpi-card">
        <div class="kpi-content">
            <span class="kpi-label">Pending</span>
            <span class="kpi-value"><?php echo $stats['pending']; ?></span>
            <span class="kpi-subtext neutral" style="color:var(--warning);"><i class="fa-solid fa-clock"></i> Awaiting Action</span>
        </div>
        <div class="kpi-icon amber">
            <i class="fa-solid fa-hourglass-half"></i>
        </div>
    </div>

    <!-- Confirmed / In Progress -->
    <div class="kpi-card">
        <div class="kpi-content">
            <span class="kpi-label">Confirmed</span>
            <span class="kpi-value"><?php echo $stats['confirmed']; ?></span>
            <span class="kpi-subtext positive"><i class="fa-solid fa-circle-check"></i> Scheduled</span>
        </div>
        <div class="kpi-icon teal">
            <i class="fa-solid fa-screwdriver-wrench"></i>
        </div>
    </div>

    <!-- Completed -->
    <div class="kpi-card">
        <div class="kpi-content">
            <span class="kpi-label">Completed</span>
            <span class="kpi-value"><?php echo $stats['completed']; ?></span>
            <span class="kpi-subtext positive"><i class="fa-solid fa-check-double"></i> Finished</span>
        </div>
        <div class="kpi-icon green">
            <i class="fa-solid fa-circle-check"></i>
        </div>
    </div>
</div>

<!-- Smart Customer Safety & Emergency Protection Bar -->
<div style="background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border: 1px solid #bae6fd; border-radius: 12px; padding: 1rem 1.25rem; margin-bottom: 1.6rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
    <div style="display: flex; align-items: center; gap: 12px;">
        <div style="width: 40px; height: 40px; border-radius: 8px; background: #0284c7; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 1.2rem;">
            <i class="fa-solid fa-shield-halved"></i>
        </div>
        <div>
            <strong style="color: #0369a1; font-size: 0.98rem; display: block;">SafeVisit & Emergency Protection Center</strong>
            <span style="color: #0284c7; font-size: 0.82rem;">Arrival OTP verification, automated check-ins, silent SOS, and trusted emergency contacts.</span>
        </div>
    </div>
    <div style="display: flex; gap: 8px;">
        <a href="safety.php" class="btn btn-primary btn-sm" style="background: #0284c7; border-color: #0284c7;">
            <i class="fa-solid fa-shield-heart"></i> Open SafeVisit
        </a>
        <a href="trusted_contacts.php" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-user-shield"></i> Contacts
        </a>
        <a href="tel:112" class="btn btn-sm" style="background: #fee2e2; color: #dc2626; border: 1px solid #fecaca;" title="Call 112">
            <i class="fa-solid fa-phone"></i> 112
        </a>
    </div>
</div>

<!-- 2. Need a service? Hero Search Banner -->
<div class="hero-search-banner">
    <div class="hero-search-content">
        <h2>Need a service?</h2>
        <p>Book trusted professionals for your home services.</p>
        <form action="services.php" method="GET" class="hero-search-form">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="q" placeholder="Search for services..." required>
            <button type="submit">Search</button>
        </form>
    </div>
    <div class="hero-search-image">
        <img src="<?php echo $pathPrefix; ?>assets/images/hero-interior.jpg" alt="Home Interior">
    </div>
</div>

<!-- 3. Middle Section: Upcoming Bookings (Left) & Quick Actions (Right) -->
<div class="dashboard-split">
    <!-- Left: Upcoming Bookings -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fa-solid fa-calendar-days"></i> Upcoming Bookings
            </h3>
            <a href="my-bookings.php" style="font-size: 0.85rem; font-weight: 600; color: var(--primary);">
                View All <i class="fa-solid fa-arrow-right" style="font-size: 11px;"></i>
            </a>
        </div>

        <?php if (empty($upcomingBookings)): ?>
            <div style="text-align: center; padding: 2.5rem 1rem;">
                <div style="width: 50px; height: 50px; border-radius: 50%; background: #eff6ff; color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin: 0 auto 1rem;">
                    <i class="fa-solid fa-calendar-plus"></i>
                </div>
                <h4 style="font-size: 1.05rem; margin-bottom: 4px;">No upcoming service appointments</h4>
                <p style="color: var(--text-muted); font-size: 0.88rem; margin-bottom: 1.25rem;">Explore top-rated professionals for plumbing, electrical, cleaning and repair.</p>
                <a href="services.php" class="btn btn-primary btn-sm">
                    <i class="fa-solid fa-plus"></i> Browse Services
                </a>
            </div>
        <?php else: ?>
            <div>
                <?php foreach ($upcomingBookings as $b): 
                    // Determine category icon & colors
                    $cat = strtolower($b['category_name']);
                    $iconClass = 'fa-solid fa-screwdriver-wrench';
                    $bgIcon = '#eff6ff';
                    $colorIcon = '#2563eb';

                    if (strpos($cat, 'plumb') !== false) {
                        $iconClass = 'fa-solid fa-faucet-drip';
                        $bgIcon = '#eff6ff';
                        $colorIcon = '#2563eb';
                    } elseif (strpos($cat, 'elect') !== false) {
                        $iconClass = 'fa-solid fa-bolt';
                        $bgIcon = '#fffbeb';
                        $colorIcon = '#d97706';
                    } elseif (strpos($cat, 'clean') !== false) {
                        $iconClass = 'fa-solid fa-broom';
                        $bgIcon = '#f0fdfa';
                        $colorIcon = '#0d9488';
                    } elseif (strpos($cat, 'carp') !== false) {
                        $iconClass = 'fa-solid fa-hammer';
                        $bgIcon = '#fff7ed';
                        $colorIcon = '#ea580c';
                    } elseif (strpos($cat, 'paint') !== false) {
                        $iconClass = 'fa-solid fa-paint-roller';
                        $bgIcon = '#fdf2f8';
                        $colorIcon = '#db2777';
                    }
                ?>
                    <div class="booking-item-card">
                        <div class="booking-item-left">
                            <div class="booking-service-icon" style="background: <?php echo $bgIcon; ?>; color: <?php echo $colorIcon; ?>;">
                                <i class="<?php echo $iconClass; ?>"></i>
                            </div>
                            <div class="booking-item-details">
                                <h4><?php echo htmlspecialchars($b['service_name']); ?></h4>
                                <p>
                                    <i class="fa-regular fa-clock" style="font-size:11px;margin-right:4px;"></i>
                                    <?php echo date('d M Y', strtotime($b['booking_date'])); ?> &bull; <?php echo date('h:i A', strtotime($b['booking_time'])); ?>
                                    <?php if (!empty($b['provider_name'])): ?>
                                        &bull; <span style="color:var(--text-dark);font-weight:600;"><?php echo htmlspecialchars($b['provider_name']); ?></span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        <div class="booking-item-right">
                            <?php 
                            if ($b['status'] === 'Accepted' || $b['status'] === 'In Progress') {
                                echo '<span class="badge badge-completed"><i class="fa-solid fa-circle-check" style="font-size:9px;"></i> Confirmed</span>';
                            } else {
                                echo getStatusBadge($b['status']); 
                            }
                            ?>
                            <a href="my-bookings.php" class="btn btn-outline btn-sm" style="padding: 0.28rem 0.65rem; font-size: 0.8rem;">
                                Details
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Right: Quick Actions -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fa-solid fa-bolt"></i> Quick Actions
            </h3>
        </div>

        <div style="display: flex; flex-direction: column;">
            <a href="services.php" class="quick-action-item">
                <div class="quick-action-left">
                    <div class="quick-action-icon"><i class="fa-solid fa-plus"></i></div>
                    <span>Book a Service</span>
                </div>
                <i class="fa-solid fa-chevron-right" style="font-size: 0.8rem; color: var(--text-muted);"></i>
            </a>

            <a href="my-bookings.php" class="quick-action-item">
                <div class="quick-action-left">
                    <div class="quick-action-icon" style="background:#f0fdfa;color:#0d9488;"><i class="fa-solid fa-calendar-check"></i></div>
                    <span>View Bookings</span>
                </div>
                <i class="fa-solid fa-chevron-right" style="font-size: 0.8rem; color: var(--text-muted);"></i>
            </a>

            <a href="profile.php" class="quick-action-item">
                <div class="quick-action-left">
                    <div class="quick-action-icon" style="background:#f5f3ff;color:#7c3aed;"><i class="fa-solid fa-user-pen"></i></div>
                    <span>Edit Profile</span>
                </div>
                <i class="fa-solid fa-chevron-right" style="font-size: 0.8rem; color: var(--text-muted);"></i>
            </a>

            <a href="my-bookings.php#reviews" class="quick-action-item">
                <div class="quick-action-left">
                    <div class="quick-action-icon" style="background:#fffbeb;color:#d97706;"><i class="fa-solid fa-star"></i></div>
                    <span>Write a Review</span>
                </div>
                <i class="fa-solid fa-chevron-right" style="font-size: 0.8rem; color: var(--text-muted);"></i>
            </a>
        </div>
    </div>
</div>

<!-- 4. Bottom Section: Popular Services Category Cards -->
<div style="margin-top: 1.5rem;">
    <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--text-dark); margin-bottom: 0.85rem;">
        Popular Services
    </h3>

    <div class="popular-services-grid">
        <!-- 1. Plumbing -->
        <a href="services.php?category=1" class="popular-service-item">
            <div class="popular-service-icon" style="background:#eff6ff;color:#2563eb;">
                <i class="fa-solid fa-faucet-drip"></i>
            </div>
            <span class="popular-service-name">Plumbing</span>
        </a>

        <!-- 2. Electrical -->
        <a href="services.php?category=2" class="popular-service-item">
            <div class="popular-service-icon" style="background:#fffbeb;color:#d97706;">
                <i class="fa-solid fa-bolt"></i>
            </div>
            <span class="popular-service-name">Electrical</span>
        </a>

        <!-- 3. Cleaning -->
        <a href="services.php?category=3" class="popular-service-item">
            <div class="popular-service-icon" style="background:#f0fdfa;color:#0d9488;">
                <i class="fa-solid fa-broom"></i>
            </div>
            <span class="popular-service-name">Cleaning</span>
        </a>

        <!-- 4. Carpentry -->
        <a href="services.php?category=4" class="popular-service-item">
            <div class="popular-service-icon" style="background:#fff7ed;color:#ea580c;">
                <i class="fa-solid fa-hammer"></i>
            </div>
            <span class="popular-service-name">Carpentry</span>
        </a>

        <!-- 5. Painting -->
        <a href="services.php?category=5" class="popular-service-item">
            <div class="popular-service-icon" style="background:#fdf2f8;color:#db2777;">
                <i class="fa-solid fa-paint-roller"></i>
            </div>
            <span class="popular-service-name">Painting</span>
        </a>

        <!-- 6. Appliance Repair -->
        <a href="services.php?category=6" class="popular-service-item">
            <div class="popular-service-icon" style="background:#f5f3ff;color:#7c3aed;">
                <i class="fa-solid fa-screwdriver-wrench"></i>
            </div>
            <span class="popular-service-name">Appliance Repair</span>
        </a>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
