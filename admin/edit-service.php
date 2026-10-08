<?php
$pageTitle = "Admin - Edit Service";
require_once __DIR__ . '/../config/db.php';
requireRole('admin');

$pdo = getDB();
$serviceId = (int)($_GET['id'] ?? 0);

// Fetch existing service
$stmt = $pdo->prepare("SELECT * FROM services WHERE id = ?");
$stmt->execute([$serviceId]);
$service = $stmt->fetch();

if (!$service) {
    setFlash('danger', 'Service not found.');
    header("Location: services.php");
    exit;
}

$categories = $pdo->query("SELECT * FROM service_categories ORDER BY name ASC")->fetchAll();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $categoryId = (int)($_POST['category_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price = (float)($_POST['price'] ?? 0);
    $duration = (int)($_POST['duration_mins'] ?? 60);
    $status = $_POST['status'] ?? 'active';

    if ($categoryId <= 0) $errors[] = "Please select a category.";
    if (empty($name)) $errors[] = "Service name cannot be empty.";
    if ($price <= 0) $errors[] = "Please enter a valid price.";

    if (empty($errors)) {
        $uStmt = $pdo->prepare("UPDATE services SET category_id = ?, name = ?, description = ?, price = ?, duration_mins = ?, status = ? WHERE id = ?");
        $uStmt->execute([$categoryId, $name, $description, $price, $duration, $status, $serviceId]);

        setFlash('success', "Service '{$name}' has been updated successfully!");
        header("Location: services.php");
        exit;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 650px; margin: 2rem auto;">
    <div class="card" style="padding: 2.25rem;">
        <div style="margin-bottom: 2rem;">
            <a href="services.php" style="font-size: 0.9rem; color: var(--gray-500); display: inline-flex; align-items: center; gap: 6px; margin-bottom: 0.75rem;">
                <i class="fa-solid fa-arrow-left"></i> Back to Services List
            </a>
            <h2>Edit Service Details</h2>
            <p style="color: var(--gray-500);">Modify name, trade category, pricing, duration, and availability</p>
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

        <form method="POST" action="edit-service.php?id=<?php echo $serviceId; ?>">
            <div class="form-group">
                <label class="form-label" for="category_id">Service Category *</label>
                <select id="category_id" name="category_id" class="form-control" required>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo ($service['category_id'] == $c['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($c['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="name">Service Name *</label>
                <input type="text" id="name" name="name" class="form-control" required value="<?php echo htmlspecialchars($service['name']); ?>">
            </div>

            <div class="grid grid-3">
                <div class="form-group">
                    <label class="form-label" for="price">Price (₹) *</label>
                    <input type="number" step="0.5" id="price" name="price" class="form-control" required value="<?php echo htmlspecialchars($service['price']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="duration_mins">Duration (Mins)</label>
                    <input type="number" id="duration_mins" name="duration_mins" class="form-control" value="<?php echo htmlspecialchars($service['duration_mins']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="status">Status</label>
                    <select id="status" name="status" class="form-control">
                        <option value="active" <?php echo ($service['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($service['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Service Description</label>
                <textarea id="description" name="description" class="form-control" rows="4"><?php echo htmlspecialchars($service['description']); ?></textarea>
            </div>

            <div style="display:flex; gap: 1rem;">
                <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.5rem;">
                    <i class="fa-solid fa-floppy-disk"></i> Save Changes
                </button>
                <a href="services.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
