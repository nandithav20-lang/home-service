<?php
$pageTitle = "Admin - Manage Bookings";
require_once __DIR__ . '/../config/db.php';
requireRole('admin');

$pdo = getDB();

// Handle Status or Assignment Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $bookingId = (int)($_POST['booking_id'] ?? 0);

    if ($action === 'assign_provider') {
        $providerId = (int)($_POST['provider_id'] ?? 0);
        $adminNotes = trim($_POST['admin_notes'] ?? '');

        if ($providerId > 0) {
            $uStmt = $pdo->prepare("UPDATE bookings SET provider_id = ?, status = 'Accepted', admin_notes = ? WHERE id = ?");
            $uStmt->execute([$providerId, $adminNotes, $bookingId]);
            setFlash('success', "Assigned provider successfully to Booking #BK-" . str_pad($bookingId, 4, '0', STR_PAD_LEFT) . "!");
        } else {
            setFlash('danger', "Please choose a valid service provider.");
        }
    } elseif ($action === 'change_status') {
        $newStatus = $_POST['new_status'] ?? '';
        if (in_array($newStatus, ['Pending', 'Accepted', 'In Progress', 'Completed', 'Cancelled'])) {
            $uStmt = $pdo->prepare("UPDATE bookings SET status = ? WHERE id = ?");
            $uStmt->execute([$newStatus, $bookingId]);
            setFlash('success', "Booking #BK-" . str_pad($bookingId, 4, '0', STR_PAD_LEFT) . " status updated to '{$newStatus}'.");
        }
    }
    header("Location: bookings.php");
    exit;
}

// Fetch all available providers grouped by category for assignment dropdowns
$allProviders = $pdo->query("SELECT p.id as provider_id, u.name as provider_name, u.phone, p.category_id, c.name as category_name 
                             FROM providers p 
                             JOIN users u ON p.user_id = u.id 
                             JOIN service_categories c ON p.category_id = c.id
                             WHERE p.status = 'approved'
                             ORDER BY u.name ASC")->fetchAll();

// Filter & Fetch Bookings
$filterStatus = $_GET['status'] ?? 'all';
$searchQuery = trim($_GET['search'] ?? '');

$sql = "SELECT b.*, s.name as service_name, s.category_id, c.name as category_name, 
               u.name as customer_name, u.phone as customer_phone, u.email as customer_email,
               p_user.name as provider_name, p_user.phone as provider_phone
        FROM bookings b
        JOIN services s ON b.service_id = s.id
        JOIN service_categories c ON s.category_id = c.id
        JOIN users u ON b.customer_id = u.id
        LEFT JOIN providers p ON b.provider_id = p.id
        LEFT JOIN users p_user ON p.user_id = p_user.id
        WHERE 1=1";

$params = [];
if ($filterStatus !== 'all' && in_array($filterStatus, ['Pending', 'Accepted', 'In Progress', 'Completed', 'Cancelled'])) {
    $sql .= " AND b.status = ?";
    $params[] = $filterStatus;
}
if (!empty($searchQuery)) {
    $sql .= " AND (u.name LIKE ? OR u.phone LIKE ? OR b.id = ?)";
    $params[] = "%{$searchQuery}%";
    $params[] = "%{$searchQuery}%";
    $params[] = (int)str_replace(['#', 'BK-', 'bk-'], '', $searchQuery);
}
$sql .= " ORDER BY b.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$assignModalBookingId = (int)($_GET['assign'] ?? 0);

require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h2>Booking Requests & Dispatch Management</h2>
        <p style="color: var(--gray-500);">Review customer requests, dispatch field technicians, and track work lifecycles</p>
    </div>
</div>

<!-- Filters and Search Bar -->
<div class="card" style="padding: 1.25rem; margin-bottom: 1.5rem;">
    <form method="GET" action="bookings.php" style="display: flex; gap: 1rem; flex-wrap: wrap; align-items: flex-end;">
        <div style="flex: 1; min-width: 250px;">
            <label class="form-label" for="search"><i class="fa-solid fa-magnifying-glass"></i> Search Customer or Booking ID</label>
            <input type="text" id="search" name="search" class="form-control" placeholder="Search by name, phone or #BK-0001" value="<?php echo htmlspecialchars($searchQuery); ?>">
        </div>
        <div style="width: 200px;">
            <label class="form-label" for="status"><i class="fa-solid fa-filter"></i> Status Filter</label>
            <select id="status" name="status" class="form-control" onchange="this.form.submit()">
                <option value="all">All Statuses</option>
                <?php foreach (['Pending', 'Accepted', 'In Progress', 'Completed', 'Cancelled'] as $st): ?>
                    <option value="<?php echo $st; ?>" <?php echo ($filterStatus === $st) ? 'selected' : ''; ?>><?php echo $st; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter"></i> Apply</button>
            <a href="bookings.php" class="btn btn-secondary">Clear</a>
        </div>
    </form>
</div>

<!-- Master Bookings Table -->
<div class="card" style="padding: 1rem;">
    <?php if (empty($bookings)): ?>
        <div style="text-align: center; padding: 3rem 1rem;">
            <i class="fa-regular fa-calendar-xmark" style="font-size: 3rem; color: var(--gray-300); margin-bottom: 1rem;"></i>
            <h4>No bookings match your criteria</h4>
            <p style="color: var(--gray-500);">Try clearing filters or search term.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Booking ID</th>
                        <th>Customer</th>
                        <th>Service / Trade</th>
                        <th>Schedule</th>
                        <th>Address</th>
                        <th>Assigned Pro</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $b): ?>
                        <tr>
                            <td><strong>#BK-<?php echo str_pad($b['id'], 4, '0', STR_PAD_LEFT); ?></strong></td>
                            <td>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($b['customer_name']); ?></div>
                                <small style="color: var(--gray-500);"><i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($b['customer_phone']); ?></small>
                            </td>
                            <td>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($b['service_name']); ?></div>
                                <span class="badge badge-default" style="font-size: 0.7rem;"><?php echo htmlspecialchars($b['category_name']); ?></span>
                            </td>
                            <td>
                                <div><?php echo date('d M Y', strtotime($b['booking_date'])); ?></div>
                                <small style="color: var(--gray-500);"><?php echo date('h:i A', strtotime($b['booking_time'])); ?></small>
                            </td>
                            <td style="max-width: 220px; font-size: 0.85rem;">
                                <div><?php echo htmlspecialchars($b['service_address']); ?></div>
                                <div style="color: var(--gray-500);"><?php echo htmlspecialchars($b['city']); ?> - <?php echo htmlspecialchars($b['pincode']); ?></div>
                            </td>
                            <td>
                                <?php if (!empty($b['provider_name'])): ?>
                                    <div style="font-weight: 600; color: var(--gray-900);"><?php echo htmlspecialchars($b['provider_name']); ?></div>
                                    <small style="color: var(--primary);"><i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($b['provider_phone']); ?></small>
                                <?php else: ?>
                                    <span style="color: #b45309; font-weight: 600; font-size: 0.85rem;">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td><strong style="color: var(--primary);"><?php echo formatPrice($b['total_price']); ?></strong></td>
                            <td><?php echo getStatusBadge($b['status']); ?></td>
                            <td>
                                <!-- Quick Status Switch Form -->
                                <form method="POST" action="bookings.php" style="display:flex; flex-direction:column; gap: 4px; min-width: 140px;">
                                    <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                    
                                    <?php if ($b['status'] === 'Pending' || empty($b['provider_id'])): ?>
                                        <div style="display:flex; gap: 4px;">
                                            <input type="hidden" name="action" value="assign_provider">
                                            <select name="provider_id" class="form-control" style="padding: 2px 6px; font-size: 0.8rem;" required>
                                                <option value="">-- Assign Pro --</option>
                                                <?php foreach ($allProviders as $p): ?>
                                                    <?php if ($p['category_id'] == $b['category_id']): ?>
                                                        <option value="<?php echo $p['provider_id']; ?>"><?php echo htmlspecialchars($p['provider_name']); ?></option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn btn-primary btn-sm" style="padding: 2px 8px;" title="Confirm Assignment"><i class="fa-solid fa-check"></i></button>
                                        </div>
                                    <?php endif; ?>

                                    <div style="display:flex; gap: 4px; margin-top: 4px; align-items:center;">
                                        <a href="edit-booking.php?id=<?php echo $b['id']; ?>" class="btn btn-secondary btn-sm" style="padding: 2px 6px; font-size: 0.78rem;" title="Edit Full Booking">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <form method="POST" action="bookings.php" style="margin:0; flex:1;">
                                            <input type="hidden" name="action" value="change_status">
                                            <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                                            <select name="new_status" class="form-control" style="padding: 2px 6px; font-size: 0.78rem;" onchange="this.form.submit()">
                                                <option value="">Status...</option>
                                                <option value="Pending">Pending</option>
                                                <option value="Accepted">Accepted</option>
                                                <option value="In Progress">In Progress</option>
                                                <option value="Completed">Completed</option>
                                                <option value="Cancelled">Cancelled</option>
                                            </select>
                                        </form>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
