<?php
$pageTitle = "Admin Dashboard";
require_once __DIR__ . '/../config/db.php';
requireRole('admin');

$pdo = getDB();

// High Level System Metrics
$totalCustomers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
$totalProviders = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'provider'")->fetchColumn();
$totalBookings  = $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$totalRevenue   = $pdo->query("SELECT IFNULL(SUM(total_price), 0) FROM bookings WHERE status = 'Completed'")->fetchColumn();

// Pending Bookings requiring Dispatch
$pendingBookings = $pdo->query("SELECT b.*, s.name as service_name, c.name as category_name, u.name as customer_name, u.phone as customer_phone
                                FROM bookings b
                                JOIN services s ON b.service_id = s.id
                                JOIN service_categories c ON s.category_id = c.id
                                JOIN users u ON b.customer_id = u.id
                                WHERE b.status = 'Pending'
                                ORDER BY b.created_at ASC LIMIT 6")->fetchAll();

// Recent Customer Reviews
$recentReviews = $pdo->query("SELECT r.*, u.name as customer_name, s.name as service_name, p_user.name as provider_name
                              FROM reviews r
                              JOIN users u ON r.customer_id = u.id
                              JOIN bookings b ON r.booking_id = b.id
                              JOIN services s ON b.service_id = s.id
                              LEFT JOIN providers p ON r.provider_id = p.id
                              LEFT JOIN users p_user ON p.user_id = p_user.id
                              ORDER BY r.created_at DESC LIMIT 4")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h2>System Administration Control Center</h2>
        <p style="color: var(--gray-500);">Real-time monitoring of service operations, bookings, customers & providers</p>
    </div>
    <div style="display:flex; gap:0.75rem;">
        <a href="bookings.php" class="btn btn-primary">
            <i class="fa-solid fa-list-check"></i> Manage All Bookings
        </a>
        <a href="services.php" class="btn btn-secondary">
            <i class="fa-solid fa-screwdriver-wrench"></i> Manage Services
        </a>
    </div>
</div>

<!-- Stat Metric Cards -->
<div class="grid grid-4" style="margin-bottom: 2.5rem;">
    <div class="stat-card">
        <div class="stat-icon primary"><i class="fa-solid fa-users"></i></div>
        <div>
            <div class="stat-num"><?php echo $totalCustomers; ?></div>
            <div class="stat-label">Total Customers</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon success"><i class="fa-solid fa-user-gear"></i></div>
        <div>
            <div class="stat-num"><?php echo $totalProviders; ?></div>
            <div class="stat-label">Active Technicians</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon warning"><i class="fa-solid fa-calendar-days"></i></div>
        <div>
            <div class="stat-num"><?php echo $totalBookings; ?></div>
            <div class="stat-label">Total Bookings</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon primary" style="background:#ecfdf5;color:#059669;"><i class="fa-solid fa-indian-rupee-sign"></i></div>
        <div>
            <div class="stat-num"><?php echo formatPrice($totalRevenue); ?></div>
            <div class="stat-label">Completed Revenue</div>
        </div>
    </div>
</div>

<div class="grid grid-3" style="align-items: flex-start; margin-bottom: 2rem;">
    <!-- Pending Bookings (2 Columns wide) -->
    <div class="card" style="grid-column: span 2;">
        <div class="card-header">
            <h3 style="font-size: 1.25rem;"><i class="fa-solid fa-hourglass-half" style="color:var(--warning);margin-right:8px;"></i> Bookings Awaiting Provider Assignment</h3>
            <a href="bookings.php?status=Pending" class="btn btn-outline btn-sm">View All Pending</a>
        </div>

        <?php if (empty($pendingBookings)): ?>
            <div style="text-align: center; padding: 2.5rem 1rem;">
                <i class="fa-solid fa-circle-check" style="font-size: 2.5rem; color: var(--success); margin-bottom: 0.75rem;"></i>
                <h4>All Caught Up!</h4>
                <p style="color: var(--gray-500);">There are no pending booking requests awaiting assignment.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Service</th>
                            <th>Scheduled Date</th>
                            <th>Price</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingBookings as $pb): ?>
                            <tr>
                                <td><strong>#BK-<?php echo str_pad($pb['id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                                <td>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($pb['customer_name']); ?></div>
                                    <small style="color: var(--gray-500);"><?php echo htmlspecialchars($pb['customer_phone']); ?></small>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($pb['service_name']); ?></div>
                                    <small style="color: var(--gray-500);"><?php echo htmlspecialchars($pb['category_name']); ?></small>
                                </td>
                                <td><?php echo date('d M Y', strtotime($pb['booking_date'])); ?></td>
                                <td><strong style="color: var(--primary);"><?php echo formatPrice($pb['total_price']); ?></strong></td>
                                <td>
                                    <a href="bookings.php?assign=<?php echo $pb['id']; ?>" class="btn btn-primary btn-sm">
                                        <i class="fa-solid fa-user-plus"></i> Assign Pro
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Latest Reviews Column -->
    <div class="card">
        <div class="card-header">
            <h3 style="font-size: 1.25rem;"><i class="fa-solid fa-comments" style="color:var(--primary);margin-right:8px;"></i> Recent Reviews</h3>
        </div>

        <?php if (empty($recentReviews)): ?>
            <p style="color: var(--gray-500); text-align: center; padding: 2rem 0;">No customer reviews submitted yet.</p>
        <?php else: ?>
            <div style="display:flex; flex-direction:column; gap: 1rem;">
                <?php foreach ($recentReviews as $rev): ?>
                    <div style="padding-bottom: 1rem; border-bottom: 1px solid var(--gray-100);">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 4px;">
                            <strong><?php echo htmlspecialchars($rev['customer_name']); ?></strong>
                            <div class="star-rating">
                                <?php for($i=1; $i<=5; $i++): ?>
                                    <i class="fa-solid fa-star" style="color:<?php echo $i <= $rev['rating'] ? '#f59e0b' : '#cbd5e1'; ?>; font-size: 11px;"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <p style="font-size: 0.85rem; color: var(--gray-700); font-style: italic; margin-bottom: 4px;">
                            "<?php echo htmlspecialchars($rev['comment']); ?>"
                        </p>
                        <small style="color: var(--gray-500); font-size: 0.8rem;">
                            For <strong><?php echo htmlspecialchars($rev['service_name']); ?></strong> (Pro: <?php echo htmlspecialchars($rev['provider_name'] ?? 'N/A'); ?>)
                        </small>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
