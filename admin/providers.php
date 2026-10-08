<?php
$pageTitle = "Admin - Service Providers Directory";
require_once __DIR__ . '/../config/db.php';
requireRole('admin');

$pdo = getDB();

// Handle Status Change (approve / reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $providerId = (int)($_POST['provider_id'] ?? 0);
    $newStatus  = $_POST['status'] ?? '';

    if ($providerId > 0 && in_array($newStatus, ['approved', 'pending', 'rejected'])) {
        $uStmt = $pdo->prepare("UPDATE providers SET status = ? WHERE id = ?");
        $uStmt->execute([$newStatus, $providerId]);
        setFlash('success', "Provider status updated to " . ucfirst($newStatus) . ".");
    }
    header("Location: providers.php");
    exit;
}

// Fetch Providers with category and metrics
$providers = $pdo->query("SELECT p.*, u.name, u.email, u.phone, u.city, u.pincode, c.name as category_name,
                          COUNT(b.id) as total_jobs,
                          (SELECT AVG(rating) FROM reviews r WHERE r.provider_id = p.id) as avg_rating
                          FROM providers p
                          JOIN users u ON p.user_id = u.id
                          JOIN service_categories c ON p.category_id = c.id
                          GROUP BY p.id
                          ORDER BY p.id ASC")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; flex-wrap: wrap; gap: 1rem;">
    <div>
        <h2>Service Providers Directory & Verification</h2>
        <p style="color: var(--gray-500);">Review technician applications, trade categories, rates, and approval status</p>
    </div>
    <div style="font-weight: 700; color: var(--primary);">
        Total Providers: <?php echo count($providers); ?>
    </div>
</div>

<div class="card" style="padding: 1rem;">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Professional Name</th>
                    <th>Trade Category</th>
                    <th>Contact</th>
                    <th>Exp.</th>
                    <th>Base Rate</th>
                    <th>Availability</th>
                    <th>Approval</th>
                    <th>Rating</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($providers as $pr): ?>
                    <tr>
                        <td><strong>#PR-<?php echo str_pad($pr['id'], 3, '0', STR_PAD_LEFT); ?></strong></td>
                        <td>
                            <div style="font-weight: 600; color: var(--dark);"><?php echo htmlspecialchars($pr['name']); ?></div>
                            <small style="color: var(--gray-500);"><?php echo htmlspecialchars($pr['email']); ?></small>
                        </td>
                        <td>
                            <span class="badge badge-accepted"><?php echo htmlspecialchars($pr['category_name']); ?></span>
                        </td>
                        <td>
                            <div><?php echo htmlspecialchars($pr['phone']); ?></div>
                            <small style="color: var(--gray-500);"><?php echo htmlspecialchars($pr['city']); ?></small>
                        </td>
                        <td><?php echo $pr['experience_years']; ?> yrs</td>
                        <td><strong><?php echo formatPrice($pr['hourly_rate']); ?></strong></td>
                        <td>
                            <span class="badge <?php echo $pr['availability'] === 'available' ? 'badge-completed' : ($pr['availability'] === 'busy' ? 'badge-pending' : 'badge-cancelled'); ?>">
                                <?php echo ucfirst($pr['availability']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?php echo $pr['status'] === 'approved' ? 'badge-completed' : ($pr['status'] === 'pending' ? 'badge-pending' : 'badge-cancelled'); ?>">
                                <?php echo ucfirst($pr['status']); ?>
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($pr['avg_rating'])): ?>
                                <span style="color: #f59e0b; font-weight: 600;">
                                    <i class="fa-solid fa-star"></i> <?php echo round($pr['avg_rating'], 1); ?>
                                </span>
                            <?php else: ?>
                                <span style="color: var(--gray-500); font-size: 0.8rem;">No reviews</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display:flex; gap: 4px;">
                                <a href="edit-provider.php?id=<?php echo $pr['id']; ?>" class="btn btn-secondary btn-sm" title="Edit Provider Details">
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </a>
                                <form method="POST" action="providers.php" style="display:flex; gap: 4px; margin:0;">
                                    <input type="hidden" name="provider_id" value="<?php echo $pr['id']; ?>">
                                    <?php if ($pr['status'] !== 'approved'): ?>
                                        <button type="submit" name="status" value="approved" class="btn btn-success btn-sm" title="Approve Provider">
                                            <i class="fa-solid fa-check"></i>
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" name="status" value="rejected" class="btn btn-danger btn-sm" title="Suspend / Reject Provider">
                                            <i class="fa-solid fa-ban"></i>
                                        </button>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
