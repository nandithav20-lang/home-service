<?php
$pageTitle = "Admin - Customers Directory";
require_once __DIR__ . '/../config/db.php';
requireRole('admin');

$pdo = getDB();

// Handle Customer Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $userId = (int)($_POST['user_id'] ?? 0);
    if ($userId > 0) {
        $del = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'customer'");
        $del->execute([$userId]);
        setFlash('info', "Customer record deleted.");
    }
    header("Location: users.php");
    exit;
}

$search = trim($_GET['search'] ?? '');
$sql = "SELECT u.id as customer_id, u.name, u.email, u.phone, u.city, u.pincode, u.created_at,
               COUNT(b.id) as total_bookings
        FROM users u
        LEFT JOIN bookings b ON u.id = b.customer_id
        WHERE u.role = 'customer'";

$params = [];
if (!empty($search)) {
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ? OR u.city LIKE ? OR u.pincode LIKE ?)";
    $params = ["%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%", "%{$search}%"];
}
$sql .= " GROUP BY u.id ORDER BY u.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h2>Registered Customer Directory</h2>
        <p style="color: var(--gray-500);">Complete list of platform customer accounts and booking engagement</p>
    </div>
    <div style="font-weight: 700; color: var(--primary);">
        Total Customers: <?php echo count($customers); ?>
    </div>
</div>

<!-- Search Bar -->
<div class="card" style="padding: 1.25rem; margin-bottom: 1.5rem;">
    <form method="GET" action="users.php" style="display: flex; gap: 1rem; align-items: center;">
        <div style="flex: 1;">
            <input type="text" name="search" class="form-control" placeholder="Search by name, email, phone, city (Bengaluru, Mysuru...), or pincode" value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
        <?php if (!empty($search)): ?>
            <a href="users.php" class="btn btn-secondary">Reset</a>
        <?php endif; ?>
    </form>
</div>

<!-- Customers Table (Exact Schema from Excel Input) -->
<div class="card" style="padding: 1rem;">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>customer_id</th>
                    <th>name</th>
                    <th>email</th>
                    <th>phone</th>
                    <th>city</th>
                    <th>pincode</th>
                    <th>total_bookings</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($customers)): ?>
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 2rem; color: var(--gray-500);">No customer records found.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($customers as $c): ?>
                        <tr>
                            <td><strong><?php echo $c['customer_id']; ?></strong></td>
                            <td style="font-weight: 600; color: var(--dark);"><?php echo htmlspecialchars($c['name']); ?></td>
                            <td><a href="mailto:<?php echo htmlspecialchars($c['email']); ?>"><?php echo htmlspecialchars($c['email']); ?></a></td>
                            <td><a href="tel:<?php echo htmlspecialchars($c['phone']); ?>" style="color: var(--gray-700);"><i class="fa-solid fa-phone" style="font-size:11px;margin-right:4px;"></i><?php echo htmlspecialchars($c['phone']); ?></a></td>
                            <td><?php echo htmlspecialchars($c['city']); ?></td>
                            <td><code><?php echo htmlspecialchars($c['pincode']); ?></code></td>
                            <td><span class="badge badge-accepted"><?php echo $c['total_bookings']; ?> Bookings</span></td>
                            <td>
                                <a href="bookings.php?search=<?php echo urlencode($c['name']); ?>" class="btn btn-secondary btn-sm" title="View Bookings">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                                <a href="edit-user.php?id=<?php echo $c['customer_id']; ?>" class="btn btn-primary btn-sm" title="Edit Customer Details">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>
                                <form method="POST" action="users.php" style="display:inline;" onsubmit="return confirmAction('Delete this customer account?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?php echo $c['customer_id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
