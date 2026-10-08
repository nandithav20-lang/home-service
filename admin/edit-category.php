<?php
$pageTitle = "Admin - Edit Service Category";
require_once __DIR__ . '/../config/db.php';
requireRole('admin');

$pdo = getDB();
$catId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM service_categories WHERE id = ?");
$stmt->execute([$catId]);
$category = $stmt->fetch();

if (!$category) {
    setFlash('danger', 'Category not found.');
    header("Location: categories.php");
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (empty($name)) {
        $errors[] = "Category name cannot be empty.";
    } else {
        $uStmt = $pdo->prepare("UPDATE service_categories SET name = ?, description = ? WHERE id = ?");
        $uStmt->execute([$name, $description, $catId]);

        setFlash('success', "Category '{$name}' updated successfully!");
        header("Location: categories.php");
        exit;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 580px; margin: 2rem auto;">
    <div class="card" style="padding: 2.25rem;">
        <div style="margin-bottom: 2rem;">
            <a href="categories.php" style="font-size: 0.9rem; color: var(--gray-500); display: inline-flex; align-items: center; gap: 6px; margin-bottom: 0.75rem;">
                <i class="fa-solid fa-arrow-left"></i> Back to Categories
            </a>
            <h2>Edit Service Category</h2>
            <p style="color: var(--gray-500);">Update trade title and customer-facing description</p>
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

        <form method="POST" action="edit-category.php?id=<?php echo $catId; ?>">
            <div class="form-group">
                <label class="form-label" for="name">Category Name *</label>
                <input type="text" id="name" name="name" class="form-control" required value="<?php echo htmlspecialchars($category['name']); ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Short Description</label>
                <textarea id="description" name="description" class="form-control" rows="4"><?php echo htmlspecialchars($category['description']); ?></textarea>
            </div>

            <div style="display:flex; gap: 1rem;">
                <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.5rem;">
                    <i class="fa-solid fa-floppy-disk"></i> Save Category
                </button>
                <a href="categories.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
