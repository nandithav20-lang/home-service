<?php
$pageTitle = "My Provider Profile";
require_once __DIR__ . '/../config/db.php';
requireRole('provider');

$pdo = getDB();
$userId = $_SESSION['user_id'];

// Fetch provider profile & user details
$stmt = $pdo->prepare("SELECT p.*, u.name, u.email, u.phone, u.city, u.pincode, c.name as category_name
                       FROM providers p
                       JOIN users u ON p.user_id = u.id
                       JOIN service_categories c ON p.category_id = c.id
                       WHERE p.user_id = ? LIMIT 1");
$stmt->execute([$userId]);
$profile = $stmt->fetch();

$categories = $pdo->query("SELECT * FROM service_categories ORDER BY name ASC")->fetchAll();
$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? $profile['category_id']);
    $experience_years = (int)($_POST['experience_years'] ?? $profile['experience_years']);
    $hourly_rate = (float)($_POST['hourly_rate'] ?? $profile['hourly_rate']);
    $availability = $_POST['availability'] ?? 'available';
    $bio = trim($_POST['bio'] ?? '');

    if (!preg_match('/^[0-9]{10}$/', $phone)) {
        $errorMsg = "Phone number must be 10 digits.";
    } elseif (empty($city)) {
        $errorMsg = "City cannot be empty.";
    } else {
        try {
            $pdo->beginTransaction();

            $uStmt = $pdo->prepare("UPDATE users SET phone = ?, city = ?, pincode = ? WHERE id = ?");
            $uStmt->execute([$phone, $city, $pincode, $userId]);

            $pStmt = $pdo->prepare("UPDATE providers SET category_id = ?, experience_years = ?, hourly_rate = ?, availability = ?, bio = ? WHERE user_id = ?");
            $pStmt->execute([$category_id, $experience_years, $hourly_rate, $availability, $bio, $userId]);

            $pdo->commit();

            setFlash('success', 'Profile updated successfully!');
            header("Location: profile.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errorMsg = "Error updating profile: " . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 680px; margin: 1.5rem auto;">
    <div class="card" style="padding: 2.25rem;">
        <div style="margin-bottom: 2rem;">
            <h2>Manage Professional Profile</h2>
            <p style="color: var(--gray-500);">Keep your operating charges, experience, and contact information up-to-date</p>
        </div>

        <?php if ($errorMsg): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($errorMsg); ?></div>
        <?php endif; ?>

        <form method="POST" action="profile.php" data-validate="true">
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($profile['name']); ?>" disabled style="background:#f1f5f9; cursor:not-allowed;">
                </div>
                <div class="form-group">
                    <label class="form-label">Registered Email</label>
                    <input type="email" class="form-control" value="<?php echo htmlspecialchars($profile['email']); ?>" disabled style="background:#f1f5f9; cursor:not-allowed;">
                </div>
            </div>

            <div class="grid grid-3">
                <div class="form-group">
                    <label class="form-label" for="phone">Phone Number *</label>
                    <input type="tel" id="phone" name="phone" class="form-control" maxlength="10" required value="<?php echo htmlspecialchars($profile['phone']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="city">Base City *</label>
                    <input type="text" id="city" name="city" class="form-control" required value="<?php echo htmlspecialchars($profile['city']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="pincode">Pincode *</label>
                    <input type="text" id="pincode" name="pincode" class="form-control" maxlength="6" required value="<?php echo htmlspecialchars($profile['pincode']); ?>">
                </div>
            </div>

            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label" for="category_id">Primary Trade / Category *</label>
                    <select id="category_id" name="category_id" class="form-control" required>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo ($profile['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="availability">Availability Status *</label>
                    <select id="availability" name="availability" class="form-control" required>
                        <option value="available" <?php echo ($profile['availability'] === 'available') ? 'selected' : ''; ?>>🟢 Available for Bookings</option>
                        <option value="busy" <?php echo ($profile['availability'] === 'busy') ? 'selected' : ''; ?>>🟡 Currently Busy</option>
                        <option value="offline" <?php echo ($profile['availability'] === 'offline') ? 'selected' : ''; ?>>🔴 Offline / On Leave</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label" for="experience_years">Experience (Years)</label>
                    <input type="number" id="experience_years" name="experience_years" min="0" max="45" class="form-control" required value="<?php echo htmlspecialchars($profile['experience_years']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="hourly_rate">Visiting Rate / Minimum Fee (₹)</label>
                    <input type="number" step="10" id="hourly_rate" name="hourly_rate" min="50" class="form-control" required value="<?php echo htmlspecialchars($profile['hourly_rate']); ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="bio">Skills & Biography</label>
                <textarea id="bio" name="bio" class="form-control" rows="4"><?php echo htmlspecialchars($profile['bio']); ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.5rem;">
                <i class="fa-solid fa-floppy-disk"></i> Save Profile Changes
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
