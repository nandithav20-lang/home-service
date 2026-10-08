<?php
$pageTitle = "Customer Dashboard";
require_once __DIR__ . '/../config/db.php';
requireRole('customer');

$pdo = getDB();
$userId = $_SESSION['user_id'];

// Fetch Stats
$stats = [
    'total' => 0,
    'pending' => 0,
    'in_progress' => 0,
    'completed' => 0
];

try {
    $stmt = $pdo->prepare("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status IN ('Accepted', 'In Progress') THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed
        FROM bookings WHERE customer_id = ?");
    $stmt->execute([$userId]);
    $stats = $stmt->fetch() ?: $stats;
} catch (Exception $e) {}

// Fetch recent bookings
$recentBookings = [];
try {
    $stmt = $pdo->prepare("SELECT b.*, s.name as service_name, c.name as category_name, 
                           u.name as provider_name, u.phone as provider_phone
                           FROM bookings b
                           JOIN services s ON b.service_id = s.id
                           JOIN service_categories c ON s.category_id = c.id
                           LEFT JOIN providers p ON b.provider_id = p.id
                           LEFT JOIN users u ON p.user_id = u.id
                           WHERE b.customer_id = ?
                           ORDER BY b.created_at DESC LIMIT 5");
    $stmt->execute([$userId]);
    $recentBookings = $stmt->fetchAll();
} catch (Exception $e) {}

require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>! 👋</h2>
        <p style="color: var(--gray-500);">Here is a summary of your household service appointments.</p>
    </div>
    <div style="display:flex; gap: 0.75rem;">
        <a href="services.php" class="btn btn-primary">
            <i class="fa-solid fa-plus"></i> Book a Service
        </a>
        <a href="my-bookings.php" class="btn btn-secondary">
            <i class="fa-solid fa-clock-rotate-left"></i> View All Bookings
        </a>
    </div>
</div>

<!-- Stats Row -->
<div class="grid grid-4" style="margin-bottom: 2.5rem;">
    <div class="stat-card">
        <div class="stat-icon primary"><i class="fa-solid fa-calendar-check"></i></div>
        <div>
            <div class="stat-num"><?php echo $stats['total'] ?? 0; ?></div>
            <div class="stat-label">Total Bookings</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon warning"><i class="fa-solid fa-hourglass-half"></i></div>
        <div>
            <div class="stat-num"><?php echo $stats['pending'] ?? 0; ?></div>
            <div class="stat-label">Pending Approval</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon primary" style="background:#f3e8ff;color:#7e22ce;"><i class="fa-solid fa-screwdriver-wrench"></i></div>
        <div>
            <div class="stat-num"><?php echo $stats['in_progress'] ?? 0; ?></div>
            <div class="stat-label">Active / Dispatched</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success"><i class="fa-solid fa-circle-check"></i></div>
        <div>
            <div class="stat-num"><?php echo $stats['completed'] ?? 0; ?></div>
            <div class="stat-label">Completed Work</div>
        </div>
    </div>
</div>

<!-- Recent Appointments Card -->
<div class="card">
    <div class="card-header">
        <h3 style="font-size: 1.25rem;"><i class="fa-solid fa-list-check" style="color:var(--primary);margin-right:8px;"></i> Recent Service Bookings</h3>
        <a href="my-bookings.php" class="btn btn-outline btn-sm">See Full History</a>
    </div>

    <?php if (empty($recentBookings)): ?>
        <div style="text-align: center; padding: 3rem 1rem;">
            <div style="font-size: 3rem; color: var(--gray-300); margin-bottom: 1rem;"><i class="fa-regular fa-calendar-xmark"></i></div>
            <h4>No bookings yet</h4>
            <p style="color: var(--gray-500); margin-bottom: 1.5rem;">Need an electrician, plumber or cleaner? Book your first service now!</p>
            <a href="services.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Browse Services</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Booking ID</th>
                        <th>Service</th>
                        <th>Scheduled For</th>
                        <th>Assigned Pro</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentBookings as $b): ?>
                        <tr>
                            <td><strong>#BK-<?php echo str_pad($b['id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                            <td>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($b['service_name']); ?></div>
                                <small style="color: var(--gray-500);"><?php echo htmlspecialchars($b['category_name']); ?></small>
                            </td>
                            <td>
                                <div><?php echo date('d M Y', strtotime($b['booking_date'])); ?></div>
                                <small style="color: var(--gray-500);"><?php echo date('h:i A', strtotime($b['booking_time'])); ?></small>
                            </td>
                            <td>
                                <?php if (!empty($b['provider_name'])): ?>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($b['provider_name']); ?></div>
                                    <small style="color: var(--gray-500);"><i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($b['provider_phone']); ?></small>
                                <?php else: ?>
                                    <span style="color: var(--gray-500); font-style: italic;">Awaiting assignment</span>
                                <?php endif; ?>
                            </td>
                            <td><strong style="color: var(--primary);"><?php echo formatPrice($b['total_price']); ?></strong></td>
                            <td><?php echo getStatusBadge($b['status']); ?></td>
                            <td>
                                <a href="my-bookings.php" class="btn btn-secondary btn-sm">Details</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
