<?php
$pageTitle = "Service Provider Registration";
require_once __DIR__ . '/../config/db.php';

if (isLoggedIn()) {
    header("Location: ../provider/dashboard.php");
    exit;
}

$pdo = getDB();
$categories = [];
try {
    $categories = $pdo->query("SELECT * FROM service_categories ORDER BY name ASC")->fetchAll();
} catch (Exception $e) {}

$errors = [];
$formData = [
    'name' => '',
    'email' => '',
    'phone' => '',
    'city' => '',
    'pincode' => '',
    'category_id' => '',
    'experience_years' => '2',
    'hourly_rate' => '300.00',
    'bio' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData['name'] = trim($_POST['name'] ?? '');
    $formData['email'] = trim($_POST['email'] ?? '');
    $formData['phone'] = trim($_POST['phone'] ?? '');
    $formData['city'] = trim($_POST['city'] ?? '');
    $formData['pincode'] = trim($_POST['pincode'] ?? '');
    $formData['category_id'] = (int)($_POST['category_id'] ?? 0);
    $formData['experience_years'] = (int)($_POST['experience_years'] ?? 1);
    $formData['hourly_rate'] = (float)($_POST['hourly_rate'] ?? 250);
    $formData['bio'] = trim($_POST['bio'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($formData['name'])) $errors[] = "Full Name is required.";
    if (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email is required.";
    if (!preg_match('/^[0-9]{10}$/', $formData['phone'])) $errors[] = "Phone must be a valid 10-digit number.";
    if (empty($formData['city'])) $errors[] = "City is required.";
    if (!preg_match('/^[0-9]{6}$/', $formData['pincode'])) $errors[] = "Pincode must be 6 digits.";
    if (empty($formData['category_id'])) $errors[] = "Please select your primary service trade.";
    if (strlen($password) < 6) $errors[] = "Password must be at least 6 characters.";
    if ($password !== $confirm_password) $errors[] = "Passwords do not match.";

    if (empty($errors)) {
        // Check email uniqueness
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$formData['email']]);
        if ($stmt->fetch()) {
            $errors[] = "An account with this email address already exists.";
        } else {
            try {
                $pdo->beginTransaction();

                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $insUser = $pdo->prepare("INSERT INTO users (name, email, password, phone, city, pincode, role) VALUES (?, ?, ?, ?, ?, ?, 'provider')");
                $insUser->execute([
                    $formData['name'],
                    $formData['email'],
                    $hashed,
                    $formData['phone'],
                    $formData['city'],
                    $formData['pincode']
                ]);
                $userId = $pdo->lastInsertId();

                $insProv = $pdo->prepare("INSERT INTO providers (user_id, category_id, experience_years, hourly_rate, bio, availability, status) VALUES (?, ?, ?, ?, ?, 'available', 'approved')");
                $insProv->execute([
                    $userId,
                    $formData['category_id'],
                    $formData['experience_years'],
                    $formData['hourly_rate'],
                    $formData['bio']
                ]);

                $pdo->commit();

                // Auto sign-in
                $_SESSION['user_id'] = $userId;
                $_SESSION['user_name'] = $formData['name'];
                $_SESSION['user_email'] = $formData['email'];
                $_SESSION['user_role'] = 'provider';
                $_SESSION['user_city'] = $formData['city'];

                setFlash('success', "Welcome to the HomeServe Pro network! Your provider profile is ready.");
                header("Location: ../provider/dashboard.php");
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "Failed to register provider: " . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 650px; margin: 2rem auto;">
    <div class="card" style="padding: 2.25rem;">
        <div style="text-align: center; margin-bottom: 2rem;">
            <div class="brand-icon" style="margin: 0 auto 1rem; width: 48px; height: 48px; font-size: 1.3rem; background: linear-gradient(135deg, #059669, #10b981);">
                <i class="fa-solid fa-briefcase"></i>
            </div>
            <h2 style="font-size: 1.6rem; margin-bottom: 0.35rem;">Service Provider Registration</h2>
            <p style="color: var(--gray-500); font-size: 0.9rem;">Join as a technician and get direct service booking requests</p>
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

        <form method="POST" action="provider-register.php" data-validate="true">
            <h4 style="margin-bottom: 1rem; color: var(--primary); font-size: 1.05rem;">1. Personal & Contact Details</h4>
            
            <div class="form-group">
                <label class="form-label" for="name">Full Name *</label>
                <input type="text" id="name" name="name" class="form-control" required placeholder="e.g. Ramesh Babu" value="<?php echo htmlspecialchars($formData['name']); ?>">
            </div>

            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label" for="email">Email Address *</label>
                    <input type="email" id="email" name="email" class="form-control" required placeholder="ramesh@example.com" value="<?php echo htmlspecialchars($formData['email']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="phone">Phone Number (10 Digits) *</label>
                    <input type="tel" id="phone" name="phone" class="form-control" required placeholder="9811223344" maxlength="10" value="<?php echo htmlspecialchars($formData['phone']); ?>">
                </div>
            </div>

            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label" for="city">Base City / Operating Town *</label>
                    <input type="text" id="city" name="city" class="form-control" required placeholder="e.g. Bengaluru" value="<?php echo htmlspecialchars($formData['city']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="pincode">Area Pincode *</label>
                    <input type="text" id="pincode" name="pincode" class="form-control" required placeholder="e.g. 560001" maxlength="6" value="<?php echo htmlspecialchars($formData['pincode']); ?>">
                </div>
            </div>

            <h4 style="margin: 1.5rem 0 1rem; color: var(--primary); font-size: 1.05rem;">2. Trade & Professional Details</h4>

            <div class="form-group">
                <label class="form-label" for="category_id">Primary Trade / Service Category *</label>
                <select id="category_id" name="category_id" class="form-control" required>
                    <option value="">-- Choose Category --</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo ($formData['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label" for="experience_years">Years of Experience</label>
                    <input type="number" id="experience_years" name="experience_years" min="0" max="40" class="form-control" required value="<?php echo htmlspecialchars($formData['experience_years']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="hourly_rate">Visiting / Hourly Rate (₹)</label>
                    <input type="number" step="10" id="hourly_rate" name="hourly_rate" min="50" class="form-control" required value="<?php echo htmlspecialchars($formData['hourly_rate']); ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="bio">Professional Bio & Experience Summary</label>
                <textarea id="bio" name="bio" class="form-control" placeholder="Mention certifications, types of fixtures handled, tools owned..."><?php echo htmlspecialchars($formData['bio']); ?></textarea>
            </div>

            <h4 style="margin: 1.5rem 0 1rem; color: var(--primary); font-size: 1.05rem;">3. Security Credentials</h4>

            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label" for="password">Password *</label>
                    <input type="password" id="password" name="password" class="form-control" required placeholder="Min 6 characters">
                </div>
                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required placeholder="Re-type password">
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="padding: 0.75rem; margin-top: 0.5rem; background:#059669; border-color:#059669;">
                <i class="fa-solid fa-user-check"></i> Register as Service Provider
            </button>
        </form>

        <div style="margin-top: 1.75rem; text-align: center; font-size: 0.9rem; color: var(--gray-500);">
            Already have an account? <a href="login.php" style="font-weight: 600;">Sign in here</a> &bull; 
            <a href="register.php" style="font-weight: 600;">Register as Customer</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
