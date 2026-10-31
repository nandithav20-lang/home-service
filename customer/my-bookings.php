<?php
$pageTitle = "My Service Bookings";
require_once __DIR__ . '/../config/db.php';
requireRole('customer');

$pdo = getDB();
$userId = $_SESSION['user_id'];

// Handle Customer Cancellation (allowed only if status is 'Pending')
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel') {
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $check = $pdo->prepare("SELECT id, status FROM bookings WHERE id = ? AND customer_id = ?");
    $check->execute([$bookingId, $userId]);
    $booking = $check->fetch();

    if ($booking && $booking['status'] === 'Pending') {
        $update = $pdo->prepare("UPDATE bookings SET status = 'Cancelled', admin_notes = 'Cancelled by customer' WHERE id = ?");
        $update->execute([$bookingId]);
        setFlash('info', "Booking #BK-" . str_pad($bookingId, 4, '0', STR_PAD_LEFT) . " has been cancelled.");
    } else {
        setFlash('danger', "This booking cannot be cancelled because it is already processed or assigned.");
    }
    header("Location: my-bookings.php");
    exit;
}

// Fetch all bookings for this customer
$filterStatus = $_GET['status'] ?? 'all';
$query = "SELECT b.*, s.name as service_name, c.name as category_name, 
                 u.name as provider_name, u.phone as provider_phone, p.status as provider_status,
                 r.id as review_id, r.rating, r.comment as review_comment
          FROM bookings b
          JOIN services s ON b.service_id = s.id
          JOIN service_categories c ON s.category_id = c.id
          LEFT JOIN providers p ON b.provider_id = p.id
          LEFT JOIN users u ON p.user_id = u.id
          LEFT JOIN reviews r ON b.id = r.booking_id
          WHERE b.customer_id = ?";

$params = [$userId];
if ($filterStatus !== 'all' && in_array($filterStatus, ['Pending', 'Accepted', 'In Progress', 'Completed', 'Cancelled'])) {
    $query .= " AND b.status = ?";
    $params[] = $filterStatus;
}
$query .= " ORDER BY b.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// Fetch customer's reviews
$userReviews = $pdo->prepare("SELECT r.*, s.name as service_name, u.name as provider_name 
                             FROM reviews r
                             JOIN bookings b ON r.booking_id = b.id
                             JOIN services s ON b.service_id = s.id
                             LEFT JOIN providers p ON r.provider_id = p.id
                             LEFT JOIN users u ON p.user_id = u.id
                             WHERE r.customer_id = ?
                             ORDER BY r.created_at DESC");
$userReviews->execute([$userId]);
$reviewsList = $userReviews->fetchAll();

$pageGreeting = "My Bookings";
$pageSubtitle = "Track appointment status, technician contact info & history";

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Action Bar: Filters + Book Service Button -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.4rem; flex-wrap: wrap; gap: 1rem;">
    <!-- Status Filter Pills -->
    <div style="display: flex; gap: 6px; flex-wrap: wrap;">
        <?php 
        $filters = ['all' => 'All Bookings', 'Pending' => 'Pending', 'Accepted' => 'Confirmed', 'Completed' => 'Completed', 'Cancelled' => 'Cancelled'];
        foreach ($filters as $key => $lbl):
            $isActive = ($filterStatus === $key);
            $cls = $isActive ? 'btn-primary' : 'btn-secondary';
        ?>
            <a href="my-bookings.php<?php echo $key !== 'all' ? '?status=' . urlencode($key) : ''; ?>" class="btn <?php echo $cls; ?> btn-sm">
                <?php echo $lbl; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <a href="services.php" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-plus"></i> Book Another Service
    </a>
</div>

<!-- Bookings Table Card -->
<div class="card" style="margin-bottom: 2rem;">
    <?php if (empty($bookings)): ?>
        <div style="text-align: center; padding: 3rem 1rem;">
            <div style="width: 50px; height: 50px; border-radius: 50%; background: #eff6ff; color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin: 0 auto 1rem;">
                <i class="fa-regular fa-clipboard"></i>
            </div>
            <h4 style="font-size: 1.05rem; margin-bottom: 4px;">No bookings found in this view</h4>
            <p style="color: var(--text-muted); font-size: 0.88rem; margin-bottom: 1.25rem;">Ready to schedule a service visit?</p>
            <a href="services.php" class="btn btn-primary btn-sm"><i class="fa-solid fa-plus"></i> Browse Services</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Service / Category</th>
                        <th>Schedule</th>
                        <th>Service Address</th>
                        <th>Assigned Pro</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $b): ?>
                        <tr>
                            <td><strong>#BK-<?php echo str_pad($b['id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                            <td>
                                <div style="font-weight: 600; color: var(--text-dark);"><?php echo htmlspecialchars($b['service_name']); ?></div>
                                <span class="badge badge-default" style="font-size: 0.72rem;"><?php echo htmlspecialchars($b['category_name']); ?></span>
                            </td>
                            <td>
                                <div><i class="fa-regular fa-calendar" style="font-size:11px;margin-right:4px;"></i> <?php echo date('d M Y', strtotime($b['booking_date'])); ?></div>
                                <small style="color: var(--text-muted);"><i class="fa-regular fa-clock" style="font-size:11px;margin-right:4px;"></i> <?php echo date('h:i A', strtotime($b['booking_time'])); ?></small>
                            </td>
                            <td style="max-width: 200px;">
                                <div style="font-size: 0.85rem; line-height: 1.4; color: var(--text-dark);">
                                    <?php echo htmlspecialchars($b['service_address']); ?>, <?php echo htmlspecialchars($b['city']); ?> - <?php echo htmlspecialchars($b['pincode']); ?>
                                </div>
                                <?php if (!empty($b['special_notes'])): ?>
                                    <small style="color: var(--text-muted); display: block; margin-top: 3px;">
                                        <em>"<?php echo htmlspecialchars($b['special_notes']); ?>"</em>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($b['provider_name'])): ?>
                                    <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                        <div style="font-weight: 600; color: var(--text-dark);"><?php echo htmlspecialchars($b['provider_name']); ?></div>
                                        <?php echo renderVerifiedBadge($b['provider_status'] ?? 'pending', true); ?>
                                    </div>
                                    <small style="color: var(--primary);">
                                        <i class="fa-solid fa-phone" style="font-size:10px;"></i> <?php echo htmlspecialchars($b['provider_phone']); ?>
                                    </small>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-style: italic; font-size: 0.82rem;">Pending assignment</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color: var(--primary); font-size: 0.95rem;"><?php echo formatPrice($b['total_price']); ?></strong>
                            </td>
                            <td><?php echo getStatusBadge($b['status']); ?></td>
                            <td style="text-align: right;">
                                <?php if ($b['status'] === 'Pending'): ?>
                                    <div style="display: inline-flex; gap: 4px;">
                                        <a href="edit-booking.php?id=<?php echo $b['id']; ?>" class="btn btn-secondary btn-sm" title="Reschedule">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <form method="POST" action="my-bookings.php" onsubmit="return confirmAction('Are you sure you want to cancel this booking?');" style="display:inline;">
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                            <button type="submit" class="btn btn-outline btn-sm" style="color:var(--danger);border-color:#fecaca;" title="Cancel Booking">
                                                <i class="fa-solid fa-ban"></i>
                                            </button>
                                        </form>
                                    </div>
                                <?php elseif ($b['status'] === 'Completed'): ?>
                                    <?php if (!empty($b['review_id'])): ?>
                                        <div style="font-size: 0.82rem; text-align: right;">
                                            <div style="color: #f59e0b;">
                                                <?php for($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fa-solid fa-star" style="color: <?php echo ($i <= $b['rating']) ? '#f59e0b' : '#cbd5e1'; ?>; font-size: 11px;"></i>
                                                <?php endfor; ?>
                                            </div>
                                            <small style="color: var(--text-muted);">Reviewed</small>
                                        </div>
                                    <?php else: ?>
                                        <a href="add-review.php?booking_id=<?php echo $b['id']; ?>" class="btn btn-sm" style="background:#fffbeb;color:#d97706;border:1px solid #fde68a;">
                                            <i class="fa-solid fa-star"></i> Rate Pro
                                        </a>
                                    <?php endif; ?>
                                <?php elseif (in_array($b['status'], ['Accepted', 'In Progress'])): ?>
                                    <div style="display: flex; flex-direction: column; gap: 4px; align-items: flex-end;">
                                        <a href="safety.php" class="btn btn-primary btn-sm" style="background:#0284c7;border-color:#0284c7;padding:3px 8px;font-size:0.78rem;">
                                            <i class="fa-solid fa-shield-halved"></i> SafeVisit & SOS
                                        </a>
                                        <?php if (!empty($b['provider_id'])): ?>
                                            <a href="report_provider.php?provider_id=<?php echo $b['provider_id']; ?>&booking_id=<?php echo $b['id']; ?>" style="font-size: 0.72rem; color: var(--danger);">
                                                <i class="fa-regular fa-flag"></i> Report
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <span style="color: var(--text-muted); font-size: 0.82rem; font-weight:600;">Cancelled</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Customer Past Reviews Section (Anchor target for Sidebar Reviews) -->
<div class="card" id="reviews">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fa-solid fa-star" style="color:#f59e0b;"></i> My Reviews & Ratings
        </h3>
    </div>

    <?php if (empty($reviewsList)): ?>
        <p style="text-align: center; color: var(--text-muted); padding: 1.5rem 0; font-size: 0.9rem;">
            You have not submitted any service reviews yet. Complete a booking to rate your technician!
        </p>
    <?php else: ?>
        <div class="grid grid-2">
            <?php foreach ($reviewsList as $rev): ?>
                <div style="background: #f8fafc; border: 1px solid var(--border-light); border-radius: var(--radius-md); padding: 1rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                        <strong style="color: var(--text-dark); font-size: 0.95rem;"><?php echo htmlspecialchars($rev['service_name']); ?></strong>
                        <div style="color: #f59e0b; font-size: 0.85rem;">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fa-solid fa-star" style="color: <?php echo ($i <= $rev['rating']) ? '#f59e0b' : '#cbd5e1'; ?>;"></i>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <p style="font-size: 0.88rem; color: var(--text-body); font-style: italic; margin-bottom: 6px;">
                        "<?php echo htmlspecialchars($rev['comment']); ?>"
                    </p>
                    <small style="color: var(--text-muted); font-size: 0.78rem;">
                        Assigned Professional: <strong><?php echo htmlspecialchars($rev['provider_name'] ?? 'Technician'); ?></strong> &bull; <?php echo date('d M Y', strtotime($rev['created_at'])); ?>
                    </small>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
