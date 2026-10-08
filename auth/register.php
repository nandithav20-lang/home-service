<?php
$pageTitle = "Customer Registration";
require_once __DIR__ . '/../config/db.php';

if (isLoggedIn()) {
    header("Location: ../customer/dashboard.php");
    exit;
}

$errors = [];
$formData = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'city' => '',
    'pincode' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['name'] = trim($_POST['name'] ?? '');
    $formData['email'] = trim($_POST['email'] ?? '');
    $formData['phone'] = trim($_POST['phone'] ?? '');
    $formData['city'] = trim($_POST['city'] ?? '');
    $formData['pincode'] = trim($_POST['pincode'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($formData['name'])) $errors[] = "Full Name is required.";
    if (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email address is required.";
    if (!preg_match('/^[0-9]{10}$/', $formData['phone'])) $errors[] = "Phone number must be exactly 10 digits.";
    if (empty($formData['city'])) $errors[] = "City is required.";
    if (!preg_match('/^[0-9]{6}$/', $formData['pincode'])) $errors[] = "Pincode must be a 6-digit postal code.";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters long.";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match.";

    if (empty($errors)) {
        $pdo = getDB();
        // Check email uniqueness
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$formData['email']]);
        if ($stmt->fetch()) {
            $errors[] = "An account with this email address already exists.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $insert = $pdo->prepare("INSERT INTO users (name, email, password, phone, city, pincode, role) VALUES (?, ?, ?, ?, ?, ?, 'customer')");
            $success = $insert->execute([
                $formData['name'],
                $formData['email'],
                $hashed,
                $formData['phone'],
                $formData['city'],
                $formData['pincode']
            ]);

            if ($success) {
                // Auto sign-in
                $newId = $pdo->lastInsertId();
                $_SESSION['user_id'] = $newId;
                $_SESSION['user_name'] = $formData['name'];
                $_SESSION['user_email'] = $formData['email'];
                $_SESSION['user_role'] = 'customer';
                $_SESSION['user_city'] = $formData['city'];

                setFlash('success', "Registration successful! Welcome to HomeServe Pro, " . htmlspecialchars($formData['name']) . ".");
                header("Location: ../customer/dashboard.php");
                exit;
            } else {
                $errors[] = "An unexpected error occurred while creating your account.";
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 580px; margin: 2rem auto;">
    <div class="card" style="padding: 2.25rem;">
        <div style="text-align: center; margin-bottom: 2rem;">
            <div class="brand-icon" style="margin: 0 auto 1rem; width: 48px; height: 48px; font-size: 1.3rem;">
                <i class="fa-solid fa-user-plus"></i>
            </div>
            <h2 style="font-size: 1.6rem; margin-bottom: 0.35rem;">Create Customer Account</h2>
            <p style="color: var(--gray-500); font-size: 0.9rem;">Join thousands of homeowners getting services done seamlessly</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul style="margin-left: 1.25rem;">
                    <?php foreach ($errors as $err): ?>
                        <li><?php echo htmlspecialchars($err); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php" data-validate="true">
            <div class="form-group">
                <label class="form-label" for="name">Full Name *</label>
                <input type="text" id="name" name="name" class="form-control" required placeholder="e.g. Arjun Kumar" value="<?php echo htmlspecialchars($formData['name']); ?>">
            </div>

            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label" for="email">Email Address *</label>
                    <input type="email" id="email" name="email" class="form-control" required placeholder="name@example.com" value="<?php echo htmlspecialchars($formData['email']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="phone">Phone Number (10 Digits) *</label>
                    <input type="tel" id="phone" name="phone" class="form-control" required placeholder="9876543210" maxlength="10" value="<?php echo htmlspecialchars($formData['phone']); ?>">
                </div>
            </div>

            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label" for="city">City *</label>
                    <input type="text" id="city" name="city" class="form-control" required placeholder="e.g. Bengaluru, Mysuru" value="<?php echo htmlspecialchars($formData['city']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="pincode">Pincode (6 Digits) *</label>
                    <input type="text" id="pincode" name="pincode" class="form-control" required placeholder="e.g. 560001" maxlength="6" value="<?php echo htmlspecialchars($formData['pincode']); ?>">
                </div>
            </div>

            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label" for="password">Password (min 6 chars) *</label>
                    <input type="password" id="password" name="password" class="form-control" required placeholder="Choose a password">
                </div>
                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required placeholder="Re-type password">
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="padding: 0.75rem; margin-top: 0.5rem;">
                <i class="fa-solid fa-check-circle"></i> Complete Registration
            </button>
        </form>

        <div style="margin-top: 1.75rem; text-align: center; font-size: 0.9rem; color: var(--gray-500);">
            Already have an account? <a href="login.php" style="font-weight: 600;">Sign in here</a> &bull; 
            <a href="provider-register.php" style="font-weight: 600;">Join as Service Pro</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
