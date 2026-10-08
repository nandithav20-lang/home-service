<?php
$pageTitle = "Edit / Reschedule Booking";
require_once __DIR__ . '/../config/db.php';
requireRole('customer');

$pdo = getDB();
$userId = $_SESSION['user_id'];
$bookingId = (int)($_GET['id'] ?? 0);

// Fetch booking
$stmt = $pdo->prepare("SELECT b.*, s.name as service_name, c.name as category_name 
                       FROM bookings b 
                       JOIN services s ON b.service_id = s.id 
                       JOIN service_categories c ON s.category_id = c.id 
                       WHERE b.id = ? AND b.customer_id = ?");
$stmt->execute([$bookingId, $userId]);
$booking = $stmt->fetch();

if (!$booking) {
    setFlash('danger', 'Booking not found.');
    header("Location: my-bookings.php");
    exit;
}

if ($booking['status'] !== 'Pending') {
    setFlash('warning', 'Only Pending bookings can be modified or rescheduled. This booking is already: ' . $booking['status']);
    header("Location: my-bookings.php");
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_date = trim($_POST['booking_date'] ?? '');
    $booking_time = trim($_POST['booking_time'] ?? '');
    $service_address = trim($_POST['service_address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');
    $special_notes = trim($_POST['special_notes'] ?? '');

    if (empty($booking_date)) $errors[] = "Please select a date.";
    if (empty($booking_time)) $errors[] = "Please select a time slot.";
    if (empty($service_address)) $errors[] = "Service address cannot be empty.";
    if (empty($city)) $errors[] = "City cannot be empty.";
    if (!preg_match('/^[0-9]{6}$/', $pincode)) $errors[] = "Pincode must be 6 digits.";
    if (!empty($booking_date) && strtotime($booking_date) < strtotime(date('Y-m-d'))) {
        $errors[] = "Booking date cannot be in the past.";
    }

    if (empty($errors)) {
        $uStmt = $pdo->prepare("UPDATE bookings SET booking_date = ?, booking_time = ?, service_address = ?, city = ?, pincode = ?, special_notes = ? WHERE id = ? AND customer_id = ?");
        $uStmt->execute([$booking_date, $booking_time, $service_address, $city, $pincode, $special_notes, $bookingId, $userId]);

        setFlash('success', "Booking #BK-" . str_pad($bookingId, 4, '0', STR_PAD_LEFT) . " has been updated successfully!");
        header("Location: my-bookings.php");
        exit;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 650px; margin: 2rem auto;">
    <div class="card" style="padding: 2.25rem;">
        <div style="margin-bottom: 2rem;">
            <a href="my-bookings.php" style="font-size: 0.9rem; color: var(--gray-500); display: inline-flex; align-items: center; gap: 6px; margin-bottom: 0.75rem;">
                <i class="fa-solid fa-arrow-left"></i> Back to My Bookings
            </a>
            <h2>Edit / Reschedule Booking #BK-<?php echo str_pad($booking['id'], 4, '0', STR_PAD_LEFT); ?></h2>
            <p style="color: var(--gray-500);">
                Service: <strong><?php echo htmlspecialchars($booking['service_name']); ?></strong> (<?php echo htmlspecialchars($booking['category_name']); ?>)
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

        <form method="POST" action="edit-booking.php?id=<?php echo $bookingId; ?>" data-validate="true">
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label" for="booking_date">New Appointment Date *</label>
                    <input type="date" id="booking_date" name="booking_date" class="form-control" required value="<?php echo htmlspecialchars($booking['booking_date']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="booking_time">Preferred Time Slot *</label>
                    <select id="booking_time" name="booking_time" class="form-control" required>
                        <option value="09:00:00" <?php echo ($booking['booking_time'] === '09:00:00') ? 'selected' : ''; ?>>Morning (09:00 AM - 11:00 AM)</option>
                        <option value="11:30:00" <?php echo ($booking['booking_time'] === '11:30:00') ? 'selected' : ''; ?>>Mid-day (11:30 AM - 01:30 PM)</option>
                        <option value="14:00:00" <?php echo ($booking['booking_time'] === '14:00:00') ? 'selected' : ''; ?>>Afternoon (02:00 PM - 04:00 PM)</option>
                        <option value="16:30:00" <?php echo ($booking['booking_time'] === '16:30:00') ? 'selected' : ''; ?>>Evening (04:30 PM - 06:30 PM)</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="service_address">Service Address *</label>
                <textarea id="service_address" name="service_address" class="form-control" required rows="3"><?php echo htmlspecialchars($booking['service_address']); ?></textarea>
            </div>

            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label" for="city">City *</label>
                    <input type="text" id="city" name="city" class="form-control" required value="<?php echo htmlspecialchars($booking['city']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="pincode">Pincode (6 Digits) *</label>
                    <input type="text" id="pincode" name="pincode" class="form-control" maxlength="6" required value="<?php echo htmlspecialchars($booking['pincode']); ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="special_notes">Special Instructions or Notes</label>
                <textarea id="special_notes" name="special_notes" class="form-control" rows="3"><?php echo htmlspecialchars($booking['special_notes']); ?></textarea>
            </div>

            <div style="display:flex; gap: 1rem;">
                <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.5rem;">
                    <i class="fa-solid fa-floppy-disk"></i> Update Appointment
                </button>
                <a href="my-bookings.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
