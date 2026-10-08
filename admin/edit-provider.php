<?php
$pageTitle = "Admin - Edit Service Provider";
require_once __DIR__ . '/../config/db.php';
requireRole('admin');

$pdo = getDB();
$providerId = (int)($_GET['id'] ?? 0);

// Fetch provider
$stmt = $pdo->prepare("SELECT p.*, u.name, u.email, u.phone, u.city, u.pincode 
                       FROM providers p 
                       JOIN users u ON p.user_id = u.id 
                       WHERE p.id = ?");
$stmt->execute([$providerId]);
$provider = $stmt->fetch();

if (!$provider) {
    setFlash('danger', 'Provider record not found.');
    header("Location: providers.php");
    exit;
}

$categories = $pdo->query("SELECT * FROM service_categories ORDER BY name ASC")->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $experience_years = (int)($_POST['experience_years'] ?? 0);
    $hourly_rate = (float)($_POST['hourly_rate'] ?? 0);
    $status = $_POST['status'] ?? 'approved';
    $availability = $_POST['availability'] ?? 'available';
    $bio = trim($_POST['bio'] ?? '');

    if (!preg_match('/^[0-9]{10}$/', $phone)) $errors[] = "Phone number must be 10 digits.";
    if (empty($city)) $errors[] = "City is required.";
    if ($hourly_rate <= 0) $errors[] = "Hourly rate must be greater than 0.";

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $uStmt = $pdo->prepare("UPDATE users SET phone = ?, city = ?, pincode = ? WHERE id = ?");
            $uStmt->execute([$phone, $city, $pincode, $provider['user_id']]);

            $pStmt = $pdo->prepare("UPDATE providers SET category_id = ?, experience_years = ?, hourly_rate = ?, status = ?, availability = ?, bio = ? WHERE id = ?");
            $pStmt->execute([$category_id, $experience_years, $hourly_rate, $status, $availability, $bio, $providerId]);

            $pdo->commit();

            setFlash('success', "Service Provider details for '{$provider['name']}' have been updated!");
            header("Location: providers.php");
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Error saving changes: " . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 680px; margin: 2rem auto;">
    <div class="card" style="padding: 2.25rem;">
        <div style="margin-bottom: 2rem;">
            <a href="providers.php" style="font-size: 0.9rem; color: var(--gray-500); display: inline-flex; align-items: center; gap: 6px; margin-bottom: 0.75rem;">
                <i class="fa-solid fa-arrow-left"></i> Back to Providers Directory
            </a>
            <h2>Edit Service Provider #PR-<?php echo str_pad($provider['id'], 3, '0', STR_PAD_LEFT); ?></h2>
            <p style="color: var(--gray-500);">
                Professional: <strong><?php echo htmlspecialchars($provider['name']); ?></strong> (<?php echo htmlspecialchars($provider['email']); ?>)
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

        <form method="POST" action="edit-provider.php?id=<?php echo $providerId; ?>">
            <div class="grid grid-3">
                <div class="form-group">
                    <label class="form-label" for="phone">Phone Number *</label>
                    <input type="tel" id="phone" name="phone" class="form-control" maxlength="10" required value="<?php echo htmlspecialchars($provider['phone']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="city">Base City *</label>
                    <input type="text" id="city" name="city" class="form-control" required value="<?php echo htmlspecialchars($provider['city']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="pincode">Pincode *</label>
                    <input type="text" id="pincode" name="pincode" class="form-control" maxlength="6" required value="<?php echo htmlspecialchars($provider['pincode']); ?>">
                </div>
            </div>

            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label" for="category_id">Trade Category *</label>
                    <select id="category_id" name="category_id" class="form-control" required>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo ($provider['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="hourly_rate">Visiting / Hourly Rate (₹) *</label>
                    <input type="number" step="10" id="hourly_rate" name="hourly_rate" class="form-control" required value="<?php echo htmlspecialchars($provider['hourly_rate']); ?>">
                </div>
            </div>

            <div class="grid grid-3">
                <div class="form-group">
                    <label class="form-label" for="experience_years">Experience (Years)</label>
                    <input type="number" id="experience_years" name="experience_years" min="0" max="40" class="form-control" required value="<?php echo htmlspecialchars($provider['experience_years']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="status">Verification Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="approved" <?php echo ($provider['status'] === 'approved') ? 'selected' : ''; ?>>Approved</option>
                        <option value="pending" <?php echo ($provider['status'] === 'pending') ? 'selected' : ''; ?>>Pending</option>
                        <option value="rejected" <?php echo ($provider['status'] === 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label" for="availability">Availability</label>
                    <select id="availability" name="availability" class="form-control">
                        <option value="available" <?php echo ($provider['availability'] === 'available') ? 'selected' : ''; ?>>Available</option>
                        <option value="busy" <?php echo ($provider['availability'] === 'busy') ? 'selected' : ''; ?>>Busy</option>
                        <option value="offline" <?php echo ($provider['availability'] === 'offline') ? 'selected' : ''; ?>>Offline</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="bio">Professional Bio & Notes</label>
                <textarea id="bio" name="bio" class="form-control" rows="3"><?php echo htmlspecialchars($provider['bio']); ?></textarea>
            </div>

            <div style="display:flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.5rem;">
                    <i class="fa-solid fa-floppy-disk"></i> Save Provider Details
                </button>
                <a href="providers.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
