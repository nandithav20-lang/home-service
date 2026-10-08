<?php
$pageTitle = "My Assigned Job Requests";
require_once __DIR__ . '/../config/db.php';
requireRole('provider');

$pdo = getDB();
$userId = $_SESSION['user_id'];

// Get provider ID
$pStmt = $pdo->prepare("SELECT id FROM providers WHERE user_id = ?");
$pStmt->execute([$userId]);
$prov = $pStmt->fetch();
$providerId = $prov['id'] ?? 0;

$filter = $_GET['status'] ?? 'all';
$query = "SELECT b.*, s.name as service_name, c.name as category_name, 
                 u.name as customer_name, u.phone as customer_phone, u.email as customer_email,
                 r.rating, r.comment as review_comment
          FROM bookings b
          JOIN services s ON b.service_id = s.id
          JOIN service_categories c ON s.category_id = c.id
          JOIN users u ON b.customer_id = u.id
          LEFT JOIN reviews r ON b.id = r.booking_id
          WHERE b.provider_id = ?";

$params = [$providerId];
if ($filter !== 'all' && in_array($filter, ['Accepted', 'In Progress', 'Completed'])) {
    $query .= " AND b.status = ?";
    $params[] = $filter;
}
$query .= " ORDER BY b.booking_date DESC, b.booking_time DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$requests = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h2>Assigned Customer Requests</h2>
        <p style="color: var(--gray-500);">All customer jobs dispatched and assigned to you</p>
    </div>
    <a href="dashboard.php" class="btn btn-secondary">
        <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
    </a>
</div>

<!-- Filter Tabs -->
<div style="display:flex; gap: 0.5rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
    <a href="requests.php" class="btn <?php echo ($filter === 'all') ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">All Jobs</a>
    <a href="requests.php?status=Accepted" class="btn <?php echo ($filter === 'Accepted') ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">Accepted</a>
    <a href="requests.php?status=In Progress" class="btn <?php echo ($filter === 'In Progress') ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">In Progress</a>
    <a href="requests.php?status=Completed" class="btn <?php echo ($filter === 'Completed') ? 'btn-primary' : 'btn-secondary'; ?> btn-sm">Completed</a>
</div>

<div class="card" style="padding: 1rem;">
    <?php if (empty($requests)): ?>
        <div style="text-align: center; padding: 3rem 1rem;">
            <i class="fa-regular fa-folder-open" style="font-size: 3rem; color: var(--gray-300); margin-bottom: 1rem;"></i>
            <h4>No jobs found in this category</h4>
            <p style="color: var(--gray-500);">Keep your availability status online to receive incoming requests.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Job ID</th>
                        <th>Customer Contact</th>
                        <th>Service Requested</th>
                        <th>Date & Time</th>
                        <th>Service Location</th>
                        <th>Total Fee</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $r): ?>
                        <tr>
                            <td><strong>#BK-<?php echo str_pad($r['id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                            <td>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($r['customer_name']); ?></div>
                                <div><a href="tel:<?php echo htmlspecialchars($r['customer_phone']); ?>" style="color: var(--primary); font-size: 0.85rem;"><i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($r['customer_phone']); ?></a></div>
                            </td>
                            <td>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($r['service_name']); ?></div>
                                <small style="color: var(--gray-500);"><?php echo htmlspecialchars($r['category_name']); ?></small>
                            </td>
                            <td>
                                <div><?php echo date('d M Y', strtotime($r['booking_date'])); ?></div>
                                <small style="color: var(--gray-500);"><?php echo date('h:i A', strtotime($r['booking_time'])); ?></small>
                            </td>
                            <td style="max-width: 220px; font-size: 0.85rem;">
                                <div><?php echo htmlspecialchars($r['service_address']); ?></div>
                                <div style="color: var(--gray-500);"><?php echo htmlspecialchars($r['city']); ?> - <?php echo htmlspecialchars($r['pincode']); ?></div>
                                <?php if (!empty($r['special_notes'])): ?>
                                    <div style="color: #b45309; margin-top: 4px; font-size: 0.8rem;">Note: <?php echo htmlspecialchars($r['special_notes']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color: var(--primary); font-size: 1rem;"><?php echo formatPrice($r['total_price']); ?></strong>
                            </td>
                            <td><?php echo getStatusBadge($r['status']); ?></td>
                            <td>
                                <?php if ($r['status'] !== 'Completed'): ?>
                                    <a href="update-status.php?booking_id=<?php echo $r['id']; ?>" class="btn btn-primary btn-sm">
                                        Update
                                    </a>
                                <?php else: ?>
                                    <?php if (!empty($r['rating'])): ?>
                                        <div style="font-size: 0.85rem; color: #f59e0b;">
                                            <i class="fa-solid fa-star"></i> <?php echo $r['rating']; ?>/5
                                        </div>
                                    <?php else: ?>
                                        <span style="font-size: 0.8rem; color: var(--gray-500);">Completed</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
