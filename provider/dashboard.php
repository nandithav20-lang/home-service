<?php
$pageTitle = "Service Provider Dashboard";
require_once __DIR__ . '/../config/db.php';
requireRole('provider');

$pdo = getDB();
$userId = $_SESSION['user_id'];

// Get provider record
$stmtProv = $pdo->prepare("SELECT p.*, c.name as category_name, u.name, u.phone, u.city 
                           FROM providers p 
                           JOIN users u ON p.user_id = u.id 
                           JOIN service_categories c ON p.category_id = c.id 
                           WHERE p.user_id = ? LIMIT 1");
$stmtProv->execute([$userId]);
$provider = $stmtProv->fetch();

$providerId = $provider['id'] ?? 0;

// Handle availability toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['availability'])) {
    $newAvail = $_POST['availability'];
    if (in_array($newAvail, ['available', 'busy', 'offline'])) {
        $uAvail = $pdo->prepare("UPDATE providers SET availability = ? WHERE id = ?");
        $uAvail->execute([$newAvail, $providerId]);
        $provider['availability'] = $newAvail;
        setFlash('success', "Your availability status is now set to: " . ucfirst($newAvail));
    }
}

// Stats
$stats = [
    'active_jobs' => 0,
    'completed_jobs' => 0,
    'total_earnings' => 0.00,
    'avg_rating' => 5.0,
    'review_count' => 0
];

if ($providerId) {
    // Active & completed counts
    $stmtCounts = $pdo->prepare("SELECT 
        SUM(CASE WHEN status IN ('Accepted', 'In Progress') THEN 1 ELSE 0 END) as active_jobs,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_jobs,
        SUM(CASE WHEN status = 'Completed' THEN total_price ELSE 0 END) as total_earnings
        FROM bookings WHERE provider_id = ?");
    $stmtCounts->execute([$providerId]);
    $counts = $stmtCounts->fetch();
    if ($counts) {
        $stats['active_jobs'] = (int)($counts['active_jobs'] ?? 0);
        $stats['completed_jobs'] = (int)($counts['completed_jobs'] ?? 0);
        $stats['total_earnings'] = (float)($counts['total_earnings'] ?? 0);
    }

    // Rating stats
    $stmtRating = $pdo->prepare("SELECT AVG(rating) as avg_r, COUNT(*) as cnt FROM reviews WHERE provider_id = ?");
    $stmtRating->execute([$providerId]);
    $rData = $stmtRating->fetch();
    if ($rData && $rData['cnt'] > 0) {
        $stats['avg_rating'] = round((float)$rData['avg_r'], 1);
        $stats['review_count'] = (int)$rData['cnt'];
    }
}

// Fetch Active Jobs
$activeJobs = [];
if ($providerId) {
    $stmtJobs = $pdo->prepare("SELECT b.*, s.name as service_name, u.name as customer_name, u.phone as customer_phone
                               FROM bookings b
                               JOIN services s ON b.service_id = s.id
                               JOIN users u ON b.customer_id = u.id
                               WHERE b.provider_id = ? AND b.status IN ('Accepted', 'In Progress')
                               ORDER BY b.booking_date ASC, b.booking_time ASC");
    $stmtJobs->execute([$providerId]);
    $activeJobs = $stmtJobs->fetchAll();
}

require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h2>Provider Console &mdash; <?php echo htmlspecialchars($provider['name'] ?? $_SESSION['user_name']); ?></h2>
        <p style="color: var(--gray-500);">
            Trade: <strong><?php echo htmlspecialchars($provider['category_name'] ?? 'Professional'); ?></strong> &bull; 
            Base Rate: <strong><?php echo formatPrice($provider['hourly_rate'] ?? 0); ?></strong>
        </p>
    </div>
    
    <!-- Availability Switcher -->
    <form method="POST" action="dashboard.php" style="display:flex; align-items:center; gap: 8px;">
        <label style="font-size:0.9rem; font-weight:600; color:var(--gray-700);">Current Status:</label>
        <select name="availability" onchange="this.form.submit()" class="form-control" style="width:auto; padding: 0.4rem 0.8rem; font-weight:600;">
            <option value="available" <?php echo ($provider['availability'] === 'available') ? 'selected' : ''; ?>>🟢 Available</option>
            <option value="busy" <?php echo ($provider['availability'] === 'busy') ? 'selected' : ''; ?>>🟡 Busy</option>
            <option value="offline" <?php echo ($provider['availability'] === 'offline') ? 'selected' : ''; ?>>🔴 Offline</option>
        </select>
    </form>
</div>

<!-- Stat Cards -->
<div class="grid grid-4" style="margin-bottom: 2.5rem;">
    <div class="stat-card">
        <div class="stat-icon primary"><i class="fa-solid fa-person-digging"></i></div>
        <div>
            <div class="stat-num"><?php echo $stats['active_jobs']; ?></div>
            <div class="stat-label">Active Jobs</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success"><i class="fa-solid fa-circle-check"></i></div>
        <div>
            <div class="stat-num"><?php echo $stats['completed_jobs']; ?></div>
            <div class="stat-label">Completed Jobs</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon primary" style="background:#ecfdf5;color:#059669;"><i class="fa-solid fa-indian-rupee-sign"></i></div>
        <div>
            <div class="stat-num"><?php echo formatPrice($stats['total_earnings']); ?></div>
            <div class="stat-label">Total Earnings</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon warning"><i class="fa-solid fa-star"></i></div>
        <div>
            <div class="stat-num"><?php echo $stats['avg_rating']; ?> <small style="font-size: 1rem; color: var(--gray-500);">/ 5</small></div>
            <div class="stat-label"><?php echo $stats['review_count']; ?> Customer Reviews</div>
        </div>
    </div>
</div>

<!-- Active Jobs Queue -->
<div class="card">
    <div class="card-header">
        <h3 style="font-size: 1.25rem;"><i class="fa-solid fa-clock" style="color:var(--primary);margin-right:8px;"></i> Immediate Work Schedule</h3>
        <a href="requests.php" class="btn btn-outline btn-sm">All Assigned Requests</a>
    </div>

    <?php if (empty($activeJobs)): ?>
        <div style="text-align: center; padding: 3rem 1rem;">
            <div style="font-size: 3rem; color: var(--gray-300); margin-bottom: 1rem;"><i class="fa-solid fa-mug-hot"></i></div>
            <h4>No active appointments right now</h4>
            <p style="color: var(--gray-500);">New jobs assigned by admin will automatically appear here.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Booking ID</th>
                        <th>Customer</th>
                        <th>Service</th>
                        <th>Schedule</th>
                        <th>Location</th>
                        <th>Fee</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($activeJobs as $job): ?>
                        <tr>
                            <td><strong>#BK-<?php echo str_pad($job['id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                            <td>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($job['customer_name']); ?></div>
                                <small style="color: var(--primary);"><i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($job['customer_phone']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($job['service_name']); ?></td>
                            <td>
                                <div><?php echo date('d M Y', strtotime($job['booking_date'])); ?></div>
                                <small style="color: var(--gray-500);"><?php echo date('h:i A', strtotime($job['booking_time'])); ?></small>
                            </td>
                            <td style="max-width: 200px; font-size: 0.85rem;">
                                <?php echo htmlspecialchars($job['service_address']); ?>, <?php echo htmlspecialchars($job['city']); ?>
                            </td>
                            <td><strong style="color: var(--primary);"><?php echo formatPrice($job['total_price']); ?></strong></td>
                            <td><?php echo getStatusBadge($job['status']); ?></td>
                            <td>
                                <a href="update-status.php?booking_id=<?php echo $job['id']; ?>" class="btn btn-primary btn-sm">
                                    <i class="fa-solid fa-pen-to-square"></i> Update Status
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
