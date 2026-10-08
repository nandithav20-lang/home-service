<?php
$pageTitle = "Book a Home Service";
require_once __DIR__ . '/../config/db.php';
requireLogin();

// Non-customer redirect
if ($_SESSION['user_role'] !== 'customer') {
    setFlash('warning', 'Only customer accounts can book services.');
    header("Location: ../index.php");
    exit;
}

$pdo = getDB();
$userId = $_SESSION['user_id'];

// Get user profile details for pre-filling address fields
$stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmtUser->execute([$userId]);
$userData = $stmtUser->fetch();

// Fetch available services
$services = [];
try {
    $services = $pdo->query("SELECT s.*, c.name as category_name 
                             FROM services s 
                             JOIN service_categories c ON s.category_id = c.id 
                             WHERE s.status = 'active' 
                             ORDER BY c.name ASC, s.name ASC")->fetchAll();
} catch (Exception $e) {}

$selectedServiceId = (int)($_GET['service_id'] ?? 0);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_id = (int)($_POST['service_id'] ?? 0);
    $booking_date = trim($_POST['booking_date'] ?? '');
    $booking_time = trim($_POST['booking_time'] ?? '');
    $service_address = trim($_POST['service_address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');
    $special_notes = trim($_POST['special_notes'] ?? '');

    // Validation
    if ($service_id <= 0) $errors[] = "Please select a service.";
    if (empty($booking_date)) $errors[] = "Please select a preferred date.";
    if (empty($booking_time)) $errors[] = "Please select a preferred time slot.";
    if (empty($service_address)) $errors[] = "Service address is required.";
    if (empty($city)) $errors[] = "City is required.";
    if (!preg_match('/^[0-9]{6}$/', $pincode)) $errors[] = "Valid 6-digit pincode is required.";

    // Date cannot be in past
    if (!empty($booking_date) && strtotime($booking_date) < strtotime(date('Y-m-d'))) {
        $errors[] = "Booking date cannot be in the past.";
    }

    if (empty($errors)) {
        // Fetch selected service price
        $srvStmt = $pdo->prepare("SELECT price FROM services WHERE id = ? LIMIT 1");
        $srvStmt->execute([$service_id]);
        $serviceInfo = $srvStmt->fetch();

        if (!$serviceInfo) {
            $errors[] = "Selected service is invalid or unavailable.";
        } else {
            $total_price = $serviceInfo['price'];

            $insertStmt = $pdo->prepare("INSERT INTO bookings 
                (customer_id, service_id, booking_date, booking_time, service_address, city, pincode, special_notes, total_price, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");
            
            $success = $insertStmt->execute([
                $userId,
                $service_id,
                $booking_date,
                $booking_time,
                $service_address,
                $city,
                $pincode,
                $special_notes,
                $total_price
            ]);

            if ($success) {
                $newBookingId = $pdo->lastInsertId();
                setFlash('success', "Service booking placed successfully! Request ID: #BK-" . str_pad($newBookingId, 4, '0', STR_PAD_LEFT) . ". Admin is reviewing your request.");
                header("Location: my-bookings.php");
                exit;
            } else {
                $errors[] = "Failed to submit booking request. Please try again.";
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 700px; margin: 1.5rem auto;">
    <div class="card" style="padding: 2.25rem;">
        <div style="margin-bottom: 2rem;">
            <a href="services.php" style="font-size: 0.9rem; color: var(--gray-500); display: inline-flex; align-items: center; gap: 6px; margin-bottom: 0.75rem;">
                <i class="fa-solid fa-arrow-left"></i> Back to Services Catalog
            </a>
            <h2 style="font-size: 1.75rem;">Schedule Your Service Appointment</h2>
            <p style="color: var(--gray-500);">Provide details regarding the service location and preferred time slot</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul style="margin-left: 1.25rem;">
                    <?php foreach ($errors as $err): ?>
                        <li><?php echo htmlspecialchars($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="book.php" data-validate="true">
            <div class="form-group">
                <label class="form-label" for="service_id">Choose Required Service *</label>
                <select id="service_id" name="service_id" class="form-control" required>
                    <option value="">-- Select Service --</option>
                    <?php foreach ($services as $srv): ?>
                        <option value="<?php echo $srv['id']; ?>" 
                                <?php echo ($selectedServiceId === (int)$srv['id']) ? 'selected' : ''; ?>>
                            [<?php echo htmlspecialchars($srv['category_name']); ?>] <?php echo htmlspecialchars($srv['name']); ?> &mdash; <?php echo formatPrice($srv['price']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label" for="booking_date">Appointment Date *</label>
                    <input type="date" id="booking_date" name="booking_date" class="form-control" required value="<?php echo htmlspecialchars($_POST['booking_date'] ?? date('Y-m-d', strtotime('+1 day'))); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="booking_time">Preferred Time Slot *</label>
                    <select id="booking_time" name="booking_time" class="form-control" required>
                        <option value="09:00:00">Morning (09:00 AM - 11:00 AM)</option>
                        <option value="11:30:00">Mid-day (11:30 AM - 01:30 PM)</option>
                        <option value="14:00:00" selected>Afternoon (02:00 PM - 04:00 PM)</option>
                        <option value="16:30:00">Evening (04:30 PM - 06:30 PM)</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="service_address">House Address / Street Details *</label>
                <textarea id="service_address" name="service_address" class="form-control" required placeholder="Apartment / Villa Number, Landmark, Locality..."><?php echo htmlspecialchars($_POST['service_address'] ?? ''); ?></textarea>
            </div>

            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label" for="city">City *</label>
                    <input type="text" id="city" name="city" class="form-control" required value="<?php echo htmlspecialchars($_POST['city'] ?? $userData['city'] ?? 'Bengaluru'); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="pincode">Pincode (6 Digits) *</label>
                    <input type="text" id="pincode" name="pincode" class="form-control" maxlength="6" required value="<?php echo htmlspecialchars($_POST['pincode'] ?? $userData['pincode'] ?? ''); ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="special_notes">Special Instructions or Notes for Technician (Optional)</label>
                <textarea id="special_notes" name="special_notes" class="form-control" placeholder="E.g. Please bring a high ladder, tap has rusted thread, call before arriving..."><?php echo htmlspecialchars($_POST['special_notes'] ?? ''); ?></textarea>
            </div>

            <div style="background: var(--light); padding: 1.25rem; border-radius: var(--radius-sm); margin-bottom: 1.5rem; border: 1px solid var(--gray-200);">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <div style="font-weight: 700; color: var(--gray-900);">Cash / UPI on Service Completion</div>
                        <small style="color: var(--gray-500);">Pay directly to the professional after satisfaction of work.</small>
                    </div>
                    <span class="badge badge-accepted" style="font-size: 0.8rem;">Pay on Delivery</span>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="padding: 0.85rem; font-size: 1rem;">
                <i class="fa-solid fa-paper-plane"></i> Confirm & Submit Booking Request
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
