<?php
$pageTitle = "My Profile - Edit Personal Details";
require_once __DIR__ . '/../config/db.php';
requireRole('customer');

$pdo = getDB();
$userId = $_SESSION['user_id'];

// Fetch current user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update_profile';

    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $pincode = trim($_POST['pincode'] ?? '');

        if (empty($name)) $errors[] = "Full Name cannot be empty.";
        if (!preg_match('/^[0-9]{10}$/', $phone)) $errors[] = "Phone number must be exactly 10 digits.";
        if (empty($city)) $errors[] = "City cannot be empty.";
        if (!preg_match('/^[0-9]{6}$/', $pincode)) $errors[] = "Pincode must be exactly 6 digits.";

        if (empty($errors)) {
            $uStmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, city = ?, pincode = ? WHERE id = ?");
            $uStmt->execute([$name, $phone, $city, $pincode, $userId]);
            
            $_SESSION['user_name'] = $name;
            $_SESSION['user_city'] = $city;
            $user['name'] = $name;
            $user['phone'] = $phone;
            $user['city'] = $city;
            $user['pincode'] = $pincode;

            setFlash('success', 'Your personal profile details have been updated successfully!');
            header("Location: profile.php");
            exit;
        }
    } elseif ($action === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (!verifyUserPassword($currentPass, $user['password'])) {
            $errors[] = "Current password is incorrect.";
        } elseif (strlen($newPass) < 6) {
            $errors[] = "New password must be at least 6 characters long.";
        } elseif ($newPass !== $confirmPass) {
            $errors[] = "New password confirmation does not match.";
        } else {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $pStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $pStmt->execute([$hash, $userId]);
            setFlash('success', 'Password updated successfully!');
            header("Location: profile.php");
            exit;
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 680px; margin: 2rem auto;">
    <div class="card" style="padding: 2.25rem; margin-bottom: 2rem;">
        <div style="margin-bottom: 2rem;">
            <h2>Edit Customer Profile</h2>
            <p style="color: var(--gray-500);">Update your name, contact phone number, default city, and pincode</p>
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

        <form method="POST" action="profile.php" data-validate="true">
            <input type="hidden" name="action" value="update_profile">

            <div class="form-group">
                <label class="form-label" for="name">Full Name *</label>
                <input type="text" id="name" name="name" class="form-control" required value="<?php echo htmlspecialchars($user['name']); ?>">
            </div>

            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input type="email" id="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled style="background:#f1f5f9; cursor:not-allowed;">
                    <small style="color: var(--gray-500);">Email cannot be changed directly.</small>
                </div>
                <div class="form-group">
                    <label class="form-label" for="phone">Phone Number (10 Digits) *</label>
                    <input type="tel" id="phone" name="phone" class="form-control" maxlength="10" required value="<?php echo htmlspecialchars($user['phone']); ?>">
                </div>
            </div>

            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label" for="city">Default City *</label>
                    <input type="text" id="city" name="city" class="form-control" required value="<?php echo htmlspecialchars($user['city']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="pincode">Pincode (6 Digits) *</label>
                    <input type="text" id="pincode" name="pincode" class="form-control" maxlength="6" required value="<?php echo htmlspecialchars($user['pincode']); ?>">
                </div>
            </div>

            <button type="submit" class="btn btn-primary" style="padding: 0.75rem 1.5rem;">
                <i class="fa-solid fa-floppy-disk"></i> Save Profile Details
            </button>
        </form>
    </div>

    <!-- Password Change Section -->
    <div class="card" style="padding: 2.25rem;">
        <h3 style="font-size: 1.3rem; margin-bottom: 0.5rem;"><i class="fa-solid fa-key" style="color:var(--primary);margin-right:8px;"></i> Change Password</h3>
        <p style="color: var(--gray-500); font-size: 0.9rem; margin-bottom: 1.5rem;">Secure your customer account with a new password</p>

        <form method="POST" action="profile.php">
            <input type="hidden" name="action" value="change_password">

            <div class="form-group">
                <label class="form-label" for="current_password">Current Password *</label>
                <input type="password" id="current_password" name="current_password" class="form-control" required placeholder="Enter existing password">
            </div>

            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label" for="new_password">New Password *</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" required placeholder="Min 6 characters">
                </div>
                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm New Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required placeholder="Re-type new password">
                </div>
            </div>

            <button type="submit" class="btn btn-secondary" style="padding: 0.75rem 1.5rem;">
                <i class="fa-solid fa-lock"></i> Update Password
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
