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
                 u.name as provider_name, u.phone as provider_phone,
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

require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h2>My Service Bookings</h2>
        <p style="color: var(--gray-500);">Track status, technician contact info, and manage scheduled work</p>
    </div>
    <a href="services.php" class="btn btn-primary">
        <i class="fa-solid fa-plus"></i> Book Another Service
    </a>
</div>

<!-- Status Filter Pills -->
<div style="display:flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1.5rem;">
    <?php 
    $filters = ['all' => 'All Bookings', 'Pending' => 'Pending', 'Accepted' => 'Accepted', 'In Progress' => 'In Progress', 'Completed' => 'Completed', 'Cancelled' => 'Cancelled'];
    foreach ($filters as $key => $lbl):
        $isActive = ($filterStatus === $key);
        $cls = $isActive ? 'btn-primary' : 'btn-secondary';
    ?>
        <a href="my-bookings.php<?php echo $key !== 'all' ? '?status=' . urlencode($key) : ''; ?>" class="btn <?php echo $cls; ?> btn-sm">
            <?php echo $lbl; ?>
        </a>
    <?php endforeach; ?>
</div>

<div class="card" style="padding: 1rem;">
    <?php if (empty($bookings)): ?>
        <div style="text-align: center; padding: 3rem 1rem;">
            <i class="fa-regular fa-clipboard" style="font-size: 3rem; color: var(--gray-300); margin-bottom: 1rem;"></i>
            <h4>No bookings found in this filter</h4>
            <p style="color: var(--gray-500); margin-bottom: 1.5rem;">Ready to schedule a service visit?</p>
            <a href="services.php" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Browse Services</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Service / Trade</th>
                        <th>Schedule</th>
                        <th>Address</th>
                        <th>Assigned Pro</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $b): ?>
                        <tr>
                            <td><strong>#BK-<?php echo str_pad($b['id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                            <td>
                                <div style="font-weight: 600; color: var(--dark);"><?php echo htmlspecialchars($b['service_name']); ?></div>
                                <span class="badge badge-default" style="font-size: 0.75rem;"><?php echo htmlspecialchars($b['category_name']); ?></span>
                            </td>
                            <td>
                                <div><i class="fa-regular fa-calendar"></i> <?php echo date('d M Y', strtotime($b['booking_date'])); ?></div>
                                <small style="color: var(--gray-500);"><i class="fa-regular fa-clock"></i> <?php echo date('h:i A', strtotime($b['booking_time'])); ?></small>
                            </td>
                            <td style="max-width: 200px;">
                                <div style="font-size: 0.85rem; line-height: 1.4; color: var(--gray-700);">
                                    <?php echo htmlspecialchars($b['service_address']); ?>, <?php echo htmlspecialchars($b['city']); ?> - <?php echo htmlspecialchars($b['pincode']); ?>
                                </div>
                                <?php if (!empty($b['special_notes'])): ?>
                                    <small style="color: var(--gray-500); display: block; margin-top: 4px;">
                                        <em>"<?php echo htmlspecialchars($b['special_notes']); ?>"</em>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($b['provider_name'])): ?>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($b['provider_name']); ?></div>
                                    <small style="color: var(--primary);">
                                        <i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($b['provider_phone']); ?>
                                    </small>
                                <?php else: ?>
                                    <span style="color: var(--gray-500); font-style: italic; font-size: 0.85rem;">Pending assignment</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color: var(--primary); font-size: 1rem;"><?php echo formatPrice($b['total_price']); ?></strong>
                            </td>
                            <td><?php echo getStatusBadge($b['status']); ?></td>
                            <td>
                                <?php if ($b['status'] === 'Pending'): ?>
                                    <div style="display:flex; flex-direction:column; gap:4px;">
                                        <a href="edit-booking.php?id=<?php echo $b['id']; ?>" class="btn btn-secondary btn-sm">
                                            <i class="fa-solid fa-pen-to-square"></i> Reschedule
                                        </a>
                                        <form method="POST" action="my-bookings.php" onsubmit="return confirmAction('Are you sure you want to cancel this booking?');">
                                            <input type="hidden" name="action" value="cancel">
                                            <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" style="width:100%;">
                                                <i class="fa-solid fa-ban"></i> Cancel
                                            </button>
                                        </form>
                                    </div>
                                <?php elseif ($b['status'] === 'Completed'): ?>
                                    <?php if (!empty($b['review_id'])): ?>
                                        <div style="font-size: 0.85rem;">
                                            <div class="star-rating">
                                                <?php for($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fa-solid fa-star" style="color: <?php echo $i <= $b['rating'] ? '#f59e0b' : '#cbd5e1'; ?>; font-size: 12px;"></i>
                                                <?php endfor; ?>
                                            </div>
                                            <small style="color: var(--gray-500); display: block;">Reviewed</small>
                                        </div>
                                    <?php else: ?>
                                        <a href="add-review.php?booking_id=<?php echo $b['id']; ?>" class="btn btn-warning btn-sm" style="color: #000 !important; background: #fbbf24; border-color: #f59e0b;">
                                            <i class="fa-solid fa-star"></i> Rate Pro
                                        </a>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: var(--gray-500); font-size: 0.85rem;">Active</span>
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
