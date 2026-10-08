<?php
$pageTitle = "Admin - Manage Categories";
require_once __DIR__ . '/../config/db.php';
requireRole('admin');

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $icon = trim($_POST['icon'] ?? 'wrench');

        if (!empty($name)) {
            $stmt = $pdo->prepare("INSERT INTO service_categories (name, description, icon) VALUES (?, ?, ?)");
            $stmt->execute([$name, $description, $icon]);
            setFlash('success', "Category '{$name}' created successfully!");
        }
    }
    header("Location: categories.php");
    exit;
}

$categories = $pdo->query("SELECT c.*, COUNT(s.id) as service_count 
                           FROM service_categories c 
                           LEFT JOIN services s ON c.id = s.category_id 
                           GROUP BY c.id ORDER BY c.name ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h2>Service Categories</h2>
        <p style="color: var(--gray-500);">Organize services into trades and business categories</p>
    </div>
    <a href="services.php" class="btn btn-secondary">
        <i class="fa-solid fa-arrow-left"></i> Back to Services
    </a>
</div>

<div class="grid grid-3" style="align-items: flex-start;">
    <div class="card" style="grid-column: span 2; padding: 1rem;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Category Name</th>
                        <th>Description</th>
                        <th>Total Services</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $c): ?>
                        <tr>
                            <td>
                                <strong style="font-size: 1.05rem;"><?php echo htmlspecialchars($c['name']); ?></strong>
                            </td>
                            <td style="color: var(--gray-500); font-size: 0.9rem;"><?php echo htmlspecialchars($c['description']); ?></td>
                            <td><span class="badge badge-accepted"><?php echo $c['service_count']; ?> Services</span></td>
                            <td>
                                <a href="edit-category.php?id=<?php echo $c['id']; ?>" class="btn btn-secondary btn-sm" title="Edit Category">
                                    <i class="fa-solid fa-pen-to-square"></i> Edit
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card" style="padding: 1.5rem;">
        <h3 style="font-size: 1.25rem; margin-bottom: 1.25rem;"><i class="fa-solid fa-folder-plus" style="color:var(--primary);margin-right:6px;"></i> New Category</h3>
        <form method="POST" action="categories.php">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label class="form-label" for="name">Category Name *</label>
                <input type="text" id="name" name="name" class="form-control" required placeholder="e.g. Pest Control">
            </div>
            <div class="form-group">
                <label class="form-label" for="description">Short Description</label>
                <textarea id="description" name="description" class="form-control" rows="3" placeholder="Description of this trade category..."></textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Create Category</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
