<?php
$pageTitle = "Update Job Status";
require_once __DIR__ . '/../config/db.php';
requireRole('provider');

$pdo = getDB();
$userId = $_SESSION['user_id'];
$bookingId = (int)($_GET['booking_id'] ?? 0);

// Fetch provider profile ID
$pStmt = $pdo->prepare("SELECT id FROM providers WHERE user_id = ?");
$pStmt->execute([$userId]);
$prov = $pStmt->fetch();
$providerId = $prov['id'] ?? 0;

// Fetch booking assigned to this provider
$stmt = $pdo->prepare("SELECT b.*, s.name as service_name, c.name as category_name, 
                              u.name as customer_name, u.phone as customer_phone, u.city as customer_city
                       FROM bookings b 
                       JOIN services s ON b.service_id = s.id 
                       JOIN service_categories c ON s.category_id = c.id 
                       JOIN users u ON b.customer_id = u.id 
                       WHERE b.id = ? AND b.provider_id = ?");
$stmt->execute([$bookingId, $providerId]);
$booking = $stmt->fetch();

if (!$booking) {
    setFlash('danger', 'Booking not found or not assigned to you.');
    header("Location: requests.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newStatus = $_POST['status'] ?? '';
    $workNotes = trim($_POST['work_notes'] ?? '');

    // Allowable provider transitions
    if (in_array($newStatus, ['Accepted', 'In Progress', 'Completed'])) {
        $updateSql = "UPDATE bookings SET status = ?, admin_notes = CONCAT(IFNULL(admin_notes,''), ' | [Pro Note: ', ?, ']') WHERE id = ?";
        $uStmt = $pdo->prepare($updateSql);
        $uStmt->execute([$newStatus, $workNotes, $bookingId]);

        setFlash('success', "Booking #BK-" . str_pad($bookingId, 4, '0', STR_PAD_LEFT) . " status updated to '{$newStatus}'!");
        header("Location: requests.php");
        exit;
    } else {
        setFlash('danger', 'Invalid status selection.');
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 600px; margin: 2rem auto;">
    <div class="card" style="padding: 2.25rem;">
        <div style="margin-bottom: 1.5rem;">
            <a href="requests.php" style="font-size: 0.9rem; color: var(--gray-500); display: inline-flex; align-items: center; gap: 6px; margin-bottom: 0.75rem;">
                <i class="fa-solid fa-arrow-left"></i> Back to Requests
            </a>
            <h2 style="font-size: 1.6rem;">Update Work Progress</h2>
            <p style="color: var(--gray-500); font-size: 0.9rem;">
                Booking ID: <strong>#BK-<?php echo str_pad($booking['id'], 4, '0', STR_PAD_LEFT); ?></strong> &bull; 
                Service: <strong><?php echo htmlspecialchars($booking['service_name']); ?></strong>
            </p>
        </div>

        <div style="background: var(--light); padding: 1rem 1.25rem; border-radius: var(--radius-sm); margin-bottom: 1.5rem; font-size: 0.9rem; border: 1px solid var(--gray-200);">
            <div style="margin-bottom: 4px;"><strong>Customer:</strong> <?php echo htmlspecialchars($booking['customer_name']); ?> (<?php echo htmlspecialchars($booking['customer_phone']); ?>)</div>
            <div style="margin-bottom: 4px;"><strong>Location:</strong> <?php echo htmlspecialchars($booking['service_address']); ?>, <?php echo htmlspecialchars($booking['customer_city']); ?></div>
            <div><strong>Scheduled:</strong> <?php echo date('d M Y', strtotime($booking['booking_date'])); ?> at <?php echo date('h:i A', strtotime($booking['booking_time'])); ?></div>
        </div>

        <form method="POST" action="update-status.php?booking_id=<?php echo $bookingId; ?>">
            <div class="form-group">
                <label class="form-label" for="status">Job Status *</label>
                <select id="status" name="status" class="form-control" required>
                    <option value="Accepted" <?php echo ($booking['status'] === 'Accepted') ? 'selected' : ''; ?>>
                        🔵 Accepted (Confirmed and on schedule)
                    </option>
                    <option value="In Progress" <?php echo ($booking['status'] === 'In Progress') ? 'selected' : ''; ?>>
                        🟣 In Progress (Arrived on site / working)
                    </option>
                    <option value="Completed" <?php echo ($booking['status'] === 'Completed') ? 'selected' : ''; ?>>
                        🟢 Completed (Job successfully finished)
                    </option>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="work_notes">Technician Completion / Progress Notes</label>
                <textarea id="work_notes" name="work_notes" class="form-control" rows="3" placeholder="e.g. Replaced 1/2 inch brass tap valve, tested water pressure, customer verified satisfactory."></textarea>
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="padding: 0.75rem;">
                <i class="fa-solid fa-floppy-disk"></i> Save & Update Status
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
