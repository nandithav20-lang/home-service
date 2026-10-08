<?php
$pageTitle = "Admin - Manage Services";
require_once __DIR__ . '/../config/db.php';
requireRole('admin');

$pdo = getDB();

// Handle Add / Delete Service
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $duration = (int)($_POST['duration_mins'] ?? 60);

        if ($categoryId > 0 && !empty($name) && $price > 0) {
            $stmt = $pdo->prepare("INSERT INTO services (category_id, name, description, price, duration_mins, status) VALUES (?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$categoryId, $name, $description, $price, $duration]);
            setFlash('success', "New service '{$name}' added successfully!");
        } else {
            setFlash('danger', "Please provide all required service fields.");
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['service_id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM services WHERE id = ?");
        $stmt->execute([$id]);
        setFlash('info', "Service deleted successfully.");
    } elseif ($action === 'toggle_status') {
        $id = (int)($_POST['service_id'] ?? 0);
        $curStatus = $_POST['current_status'] ?? 'active';
        $newStatus = ($curStatus === 'active') ? 'inactive' : 'active';
        $stmt = $pdo->prepare("UPDATE services SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $id]);
        setFlash('success', "Service status updated to {$newStatus}.");
    }
    header("Location: services.php");
    exit;
}

// Fetch categories & services
$categories = $pdo->query("SELECT * FROM service_categories ORDER BY name ASC")->fetchAll();
$services = $pdo->query("SELECT s.*, c.name as category_name 
                         FROM services s 
                         JOIN service_categories c ON s.category_id = c.id 
                         ORDER BY c.name ASC, s.name ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h2>Manage Services Catalog</h2>
        <p style="color: var(--gray-500);">Add, edit, and adjust pricing for available household trades and services</p>
    </div>
    <a href="#add-service-form" class="btn btn-primary">
        <i class="fa-solid fa-plus"></i> Add New Service
    </a>
</div>

<div class="grid grid-3" style="align-items: flex-start; margin-bottom: 2rem;">
    <!-- Services List (2 cols) -->
    <div class="card" style="grid-column: span 2; padding: 1rem;">
        <div class="card-header">
            <h3 style="font-size: 1.25rem;">Active Services (<?php echo count($services); ?>)</h3>
        </div>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Trade</th>
                        <th>Service Name</th>
                        <th>Price</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($services as $srv): ?>
                        <tr>
                            <td><span class="badge badge-accepted"><?php echo htmlspecialchars($srv['category_name']); ?></span></td>
                            <td>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($srv['name']); ?></div>
                                <small style="color: var(--gray-500);"><?php echo htmlspecialchars($srv['description']); ?></small>
                            </td>
                            <td><strong style="color: var(--primary);"><?php echo formatPrice($srv['price']); ?></strong></td>
                            <td>~<?php echo $srv['duration_mins']; ?>m</td>
                            <td>
                                <form method="POST" action="services.php" style="display:inline;">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="service_id" value="<?php echo $srv['id']; ?>">
                                    <input type="hidden" name="current_status" value="<?php echo $srv['status']; ?>">
                                    <button type="submit" class="badge <?php echo ($srv['status'] === 'active') ? 'badge-completed' : 'badge-cancelled'; ?>" style="border:none; cursor:pointer;">
                                        <?php echo ucfirst($srv['status']); ?>
                                    </button>
                                </form>
                            </td>
                            <td>
                                <a href="edit-service.php?id=<?php echo $srv['id']; ?>" class="btn btn-secondary btn-sm" style="padding: 2px 8px;" title="Edit Service">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>
                                <form method="POST" action="services.php" style="display:inline;" onsubmit="return confirmAction('Delete this service permanently?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="service_id" value="<?php echo $srv['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" style="padding: 2px 8px;"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Add Service Form (1 col) -->
    <div class="card" id="add-service-form" style="padding: 1.75rem;">
        <h3 style="font-size: 1.25rem; margin-bottom: 1.25rem;"><i class="fa-solid fa-circle-plus" style="color:var(--primary);margin-right:6px;"></i> Add New Service</h3>
        
        <form method="POST" action="services.php">
            <input type="hidden" name="action" value="add">

            <div class="form-group">
                <label class="form-label" for="category_id">Service Category *</label>
                <select id="category_id" name="category_id" class="form-control" required>
                    <option value="">-- Select Category --</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label" for="name">Service Name *</label>
                <input type="text" id="name" name="name" class="form-control" required placeholder="e.g. Geyser Installation & Check">
            </div>

            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label" for="price">Standard Price (₹) *</label>
                    <input type="number" step="0.5" id="price" name="price" class="form-control" required placeholder="499.00">
                </div>
                <div class="form-group">
                    <label class="form-label" for="duration_mins">Duration (Mins)</label>
                    <input type="number" id="duration_mins" name="duration_mins" class="form-control" value="60">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Service Description</label>
                <textarea id="description" name="description" class="form-control" rows="3" placeholder="Explain what is included in this service..."></textarea>
            </div>

            <button type="submit" class="btn btn-primary btn-block">
                <i class="fa-solid fa-plus"></i> Add Service
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
