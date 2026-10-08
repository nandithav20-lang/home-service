<?php
$pageTitle = "Admin - Edit Customer";
require_once __DIR__ . '/../config/db.php';
requireRole('admin');

$pdo = getDB();
$userId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'customer' LIMIT 1");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    setFlash('danger', 'Customer record not found.');
    header("Location: users.php");
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');
    $newPassword = trim($_POST['new_password'] ?? '');

    if (empty($name)) $errors[] = "Full Name cannot be empty.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
    if (!preg_match('/^[0-9]{10}$/', $phone)) $errors[] = "Phone number must be 10 digits.";
    if (empty($city)) $errors[] = "City cannot be empty.";
    if (!preg_match('/^[0-9]{6}$/', $pincode)) $errors[] = "Pincode must be 6 digits.";

    // Check email uniqueness if changed
    if ($email !== $user['email']) {
        $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $chk->execute([$email, $userId]);
        if ($chk->fetch()) {
            $errors[] = "Another account already uses this email address.";
        }
    }

    if (empty($errors)) {
        if (!empty($newPassword)) {
            $hash = password_hash($newPassword, PASSWORD_DEFAULT);
            $uStmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, city = ?, pincode = ?, password = ? WHERE id = ?");
            $uStmt->execute([$name, $email, $phone, $city, $pincode, $hash, $userId]);
        } else {
            $uStmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, city = ?, pincode = ? WHERE id = ?");
            $uStmt->execute([$name, $email, $phone, $city, $pincode, $userId]);
        }

        setFlash('success', "Customer details for '{$name}' have been updated successfully!");
        header("Location: users.php");
        exit;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 650px; margin: 2rem auto;">
    <div class="card" style="padding: 2.25rem;">
        <div style="margin-bottom: 2rem;">
            <a href="users.php" style="font-size: 0.9rem; color: var(--gray-500); display: inline-flex; align-items: center; gap: 6px; margin-bottom: 0.75rem;">
                <i class="fa-solid fa-arrow-left"></i> Back to Customers Directory
            </a>
            <h2>Edit Customer #<?php echo $user['id']; ?> &mdash; <?php echo htmlspecialchars($user['name']); ?></h2>
            <p style="color: var(--gray-500);">Modify name, registered email, phone, city, or pincode</p>
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

        <form method="POST" action="edit-user.php?id=<?php echo $userId; ?>" data-validate="true">
            <div class="form-group">
                <label class="form-label" for="name">Full Name *</label>
                <input type="text" id="name" name="name" class="form-control" required value="<?php echo htmlspecialchars($user['name']); ?>">
            </div>

            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label" for="email">Email Address *</label>
                    <input type="email" id="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($user['email']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="phone">Phone Number (10 Digits) *</label>
                    <input type="tel" id="phone" name="phone" class="form-control" maxlength="10" required value="<?php echo htmlspecialchars($user['phone']); ?>">
                </div>
            </div>

            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label" for="city">City *</label>
                    <input type="text" id="city" name="city" class="form-control" required value="<?php echo htmlspecialchars($user['city']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="pincode">Pincode (6 Digits) *</label>
                    <input type="text" id="pincode" name="pincode" class="form-control" maxlength="6" required value="<?php echo htmlspecialchars($user['pincode']); ?>">
                </div>
            </div>

            <div class="form-group" style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--gray-200);">
                <label class="form-label" for="new_password">Reset Password (Optional)</label>
                <input type="password" id="new_password" name="new_password" class="form-control" placeholder="Leave blank to keep existing password">
            </div>

            <div style="display:flex; gap: 1rem; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.5rem;">
                    <i class="fa-solid fa-floppy-disk"></i> Save Customer Changes
                </button>
                <a href="users.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
