<?php
$pageTitle = "Rate & Review Service";
require_once __DIR__ . '/../config/db.php';
requireRole('customer');

$pdo = getDB();
$userId = $_SESSION['user_id'];
$bookingId = (int)($_GET['booking_id'] ?? 0);

// Validate booking exists, belongs to user, and is 'Completed'
$stmt = $pdo->prepare("SELECT b.*, s.name as service_name, u.name as provider_name 
                       FROM bookings b 
                       JOIN services s ON b.service_id = s.id 
                       LEFT JOIN providers p ON b.provider_id = p.id 
                       LEFT JOIN users u ON p.user_id = u.id 
                       WHERE b.id = ? AND b.customer_id = ? AND b.status = 'Completed'");
$stmt->execute([$bookingId, $userId]);
$booking = $stmt->fetch();

if (!$booking) {
    setFlash('danger', 'Invalid booking or service is not yet marked as completed.');
    header("Location: my-bookings.php");
    exit;
}

// Check if review already exists
$checkRev = $pdo->prepare("SELECT id FROM reviews WHERE booking_id = ?");
$checkRev->execute([$bookingId]);
if ($checkRev->fetch()) {
    setFlash('info', 'You have already submitted a review for this booking.');
    header("Location: my-bookings.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = (int)($_POST['rating'] ?? 5);
    $comment = trim($_POST['comment'] ?? '');

    if ($rating < 1 || $rating > 5) {
        $error = "Please select a star rating between 1 and 5.";
    } elseif (empty($comment)) {
        $error = "Please write a few words about the service experience.";
    } else {
        $ins = $pdo->prepare("INSERT INTO reviews (booking_id, customer_id, provider_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
        $ins->execute([$bookingId, $userId, $booking['provider_id'], $rating, $comment]);
        setFlash('success', 'Thank you! Your feedback and rating have been recorded.');
        header("Location: my-bookings.php");
        exit;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 550px; margin: 2rem auto;">
    <div class="card" style="padding: 2.25rem;">
        <div style="text-align: center; margin-bottom: 2rem;">
            <div class="stat-icon warning" style="margin: 0 auto 1rem; width: 50px; height: 50px;">
                <i class="fa-solid fa-star"></i>
            </div>
            <h2 style="font-size: 1.6rem;">Share Your Experience</h2>
            <p style="color: var(--gray-500); font-size: 0.9rem;">
                Service: <strong><?php echo htmlspecialchars($booking['service_name']); ?></strong><br>
                Provider: <strong><?php echo htmlspecialchars($booking['provider_name'] ?? 'Assigned Professional'); ?></strong>
            </p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="add-review.php?booking_id=<?php echo $bookingId; ?>">
            <div class="form-group" style="text-align: center;">
                <label class="form-label" style="margin-bottom: 0.75rem;">Your Rating (1 to 5 Stars) *</label>
                <div class="star-picker" style="display: flex; justify-content: center; gap: 10px; font-size: 2rem; cursor: pointer;">
                    <?php for($i = 1; $i <= 5; $i++): ?>
                        <label>
                            <input type="radio" name="rating" value="<?php echo $i; ?>" <?php echo $i === 5 ? 'checked' : ''; ?> style="display:none;">
                            <i class="fa-solid fa-star" style="color: #f59e0b;"></i>
                        </label>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="comment">Feedback & Comments *</label>
                <textarea id="comment" name="comment" class="form-control" rows="4" required placeholder="How was the technician's punctuality, skill, and cleanliness?"></textarea>
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="padding: 0.75rem;">
                <i class="fa-solid fa-check"></i> Submit Review
            </button>
            <div style="text-align: center; margin-top: 1rem;">
                <a href="my-bookings.php" style="color: var(--gray-500); font-size: 0.9rem;">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
