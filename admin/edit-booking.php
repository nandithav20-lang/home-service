<?php
$pageTitle = "Admin - Edit Booking";
require_once __DIR__ . '/../config/db.php';
requireRole('admin');

$pdo = getDB();
$bookingId = (int)($_GET['id'] ?? 0);

// Fetch booking with service and customer details
$stmt = $pdo->prepare("SELECT b.*, s.name as service_name, c.name as category_name, 
                              u.name as customer_name, u.phone as customer_phone, u.email as customer_email
                       FROM bookings b
                       JOIN services s ON b.service_id = s.id
                       JOIN service_categories c ON s.category_id = c.id
                       JOIN users u ON b.customer_id = u.id
                       WHERE b.id = ?");
$stmt->execute([$bookingId]);
$booking = $stmt->fetch();

if (!$booking) {
    setFlash('danger', 'Booking not found.');
    header("Location: bookings.php");
    exit;
}

// Fetch all providers
$providers = $pdo->query("SELECT p.id as provider_id, u.name as provider_name, c.name as category_name 
                          FROM providers p 
                          JOIN users u ON p.user_id = u.id 
                          JOIN service_categories c ON p.category_id = c.id
                          WHERE p.status = 'approved'
                          ORDER BY u.name ASC")->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $provider_id = !empty($_POST['provider_id']) ? (int)$_POST['provider_id'] : null;
    $status = $_POST['status'] ?? 'Pending';
    $booking_date = trim($_POST['booking_date'] ?? '');
    $booking_time = trim($_POST['booking_time'] ?? '');
    $service_address = trim($_POST['service_address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');
    $total_price = (float)($_POST['total_price'] ?? 0);
    $special_notes = trim($_POST['special_notes'] ?? '');
    $admin_notes = trim($_POST['admin_notes'] ?? '');

    if (empty($booking_date)) $errors[] = "Booking date is required.";
    if (empty($booking_time)) $errors[] = "Booking time is required.";
    if (empty($service_address)) $errors[] = "Service address is required.";
    if ($total_price <= 0) $errors[] = "Total price must be greater than 0.";

    if (empty($errors)) {
        $uStmt = $pdo->prepare("UPDATE bookings SET 
            provider_id = ?, 
            status = ?, 
            booking_date = ?, 
            booking_time = ?, 
            service_address = ?, 
            city = ?, 
            pincode = ?, 
            total_price = ?, 
            special_notes = ?, 
            admin_notes = ? 
            WHERE id = ?");

        $uStmt->execute([
            $provider_id,
            $status,
            $booking_date,
            $booking_time,
            $service_address,
            $city,
            $pincode,
            $total_price,
            $special_notes,
            $admin_notes,
            $bookingId
        ]);

        setFlash('success', "Booking #BK-" . str_pad($bookingId, 4, '0', STR_PAD_LEFT) . " has been updated successfully!");
        header("Location: bookings.php");
        exit;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 750px; margin: 2rem auto;">
    <div class="card" style="padding: 2.25rem;">
        <div style="margin-bottom: 2rem;">
            <a href="bookings.php" style="font-size: 0.9rem; color: var(--gray-500); display: inline-flex; align-items: center; gap: 6px; margin-bottom: 0.75rem;">
                <i class="fa-solid fa-arrow-left"></i> Back to All Bookings
            </a>
            <h2>Edit Booking Record #BK-<?php echo str_pad($booking['id'], 4, '0', STR_PAD_LEFT); ?></h2>
            <p style="color: var(--gray-500);">
                Customer: <strong><?php echo htmlspecialchars($booking['customer_name']); ?></strong> (<?php echo htmlspecialchars($booking['customer_phone']); ?>) &bull; 
                Service: <strong><?php echo htmlspecialchars($booking['service_name']); ?></strong>
            </p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul style="margin-left: 1.25rem;">
                    <?php foreach ($errors as $e): ?>
                        <li><?php echo htmlspecialchars($e); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="edit-booking.php?id=<?php echo $bookingId; ?>">
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label" for="status">Booking Status *</label>
                    <select id="status" name="status" class="form-control" required>
                        <?php foreach (['Pending', 'Accepted', 'In Progress', 'Completed', 'Cancelled'] as $st): ?>
                            <option value="<?php echo $st; ?>" <?php echo ($booking['status'] === $st) ? 'selected' : ''; ?>><?php echo $st; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="provider_id">Assigned Service Provider</label>
                    <select id="provider_id" name="provider_id" class="form-control">
                        <option value="">-- No Provider Assigned (Unassigned) --</option>
                        <?php foreach ($providers as $p): ?>
                            <option value="<?php echo $p['provider_id']; ?>" <?php echo ($booking['provider_id'] == $p['provider_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($p['provider_name']); ?> (<?php echo htmlspecialchars($p['category_name']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="grid grid-3">
                <div class="form-group">
                    <label class="form-label" for="booking_date">Scheduled Date *</label>
                    <input type="date" id="booking_date" name="booking_date" class="form-control" required value="<?php echo htmlspecialchars($booking['booking_date']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="booking_time">Scheduled Time *</label>
                    <input type="time" id="booking_time" name="booking_time" class="form-control" required value="<?php echo htmlspecialchars($booking['booking_time']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="total_price">Total Charge (₹) *</label>
                    <input type="number" step="0.5" id="total_price" name="total_price" class="form-control" required value="<?php echo htmlspecialchars($booking['total_price']); ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="service_address">Service Address *</label>
                <textarea id="service_address" name="service_address" class="form-control" required rows="2"><?php echo htmlspecialchars($booking['service_address']); ?></textarea>
            </div>

            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label" for="city">City *</label>
                    <input type="text" id="city" name="city" class="form-control" required value="<?php echo htmlspecialchars($booking['city']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="pincode">Pincode *</label>
                    <input type="text" id="pincode" name="pincode" class="form-control" maxlength="6" required value="<?php echo htmlspecialchars($booking['pincode']); ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="special_notes">Customer Notes</label>
                <textarea id="special_notes" name="special_notes" class="form-control" rows="2"><?php echo htmlspecialchars($booking['special_notes']); ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label" for="admin_notes">Admin / Dispatcher Notes</label>
                <textarea id="admin_notes" name="admin_notes" class="form-control" rows="2" placeholder="Internal dispatch notes, follow-up comments..."><?php echo htmlspecialchars($booking['admin_notes']); ?></textarea>
            </div>

            <div style="display:flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.5rem;">
                    <i class="fa-solid fa-floppy-disk"></i> Update Booking Details
                </button>
                <a href="bookings.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
