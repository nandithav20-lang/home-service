<?php
$pageTitle = "Sign In";
require_once __DIR__ . '/../config/db.php';

// Redirect if already logged in
if (isLoggedIn()) {
    $r = $_SESSION['user_role'] ?? 'customer';
    if ($r === 'admin') header("Location: ../admin/dashboard.php");
    elseif ($r === 'provider') header("Location: ../provider/dashboard.php");
    else header("Location: ../customer/dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($email) || empty($password)) {
        $error = "Please provide both email address and password.";
    } else {
        $pdo = getDB();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && verifyUserPassword($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_city'] = $user['city'];

            setFlash('success', "Welcome back, " . htmlspecialchars($user['name']) . "!");

            if ($user['role'] === 'admin') {
                header("Location: ../admin/dashboard.php");
            } elseif ($user['role'] === 'provider') {
                header("Location: ../provider/dashboard.php");
            } else {
                header("Location: ../customer/dashboard.php");
            }
            exit;
        } else {
            $error = "Invalid email or password combination.";
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div style="max-width: 480px; margin: 2rem auto;">
    <div class="card" style="padding: 2.25rem;">
        <div style="text-align: center; margin-bottom: 2rem;">
            <div class="brand-icon" style="margin: 0 auto 1rem; width: 48px; height: 48px; font-size: 1.3rem;">
                <i class="fa-solid fa-lock"></i>
            </div>
            <h2 style="font-size: 1.6rem; margin-bottom: 0.35rem;">Sign In to Your Account</h2>
            <p style="color: var(--gray-500); font-size: 0.9rem;">Access Customer, Service Provider, or Admin Portal</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" required placeholder="e.g. arjun.kumar@email.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required placeholder="Enter your password">
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="padding: 0.75rem; margin-top: 0.5rem;">
                <i class="fa-solid fa-right-to-bracket"></i> Sign In
            </button>
        </form>

        <div style="margin-top: 1.75rem; text-align: center; font-size: 0.9rem; color: var(--gray-500);">
            Don't have an account? <br>
            <a href="register.php" style="font-weight: 600;">Register as Customer</a> &bull; 
            <a href="provider-register.php" style="font-weight: 600;">Register as Service Pro</a>
        </div>

        <!-- Quick Demo Credentials Card -->
        <div style="margin-top: 2rem; padding: 1rem; background: var(--gray-100); border-radius: var(--radius-sm); font-size: 0.85rem;">
            <div style="font-weight: 700; margin-bottom: 0.5rem; color: var(--gray-900);">
                <i class="fa-solid fa-key" style="color: var(--primary);"></i> One-Click Demo Credentials:
            </div>
            <ul style="list-style: none; line-height: 1.8; color: var(--gray-700);">
                <li><strong>Admin:</strong> <code>admin@homeservice.com</code> / <code>admin123</code></li>
                <li><strong>Customer:</strong> <code>arjun.kumar@email.com</code> / <code>password123</code></li>
                <li><strong>Provider:</strong> <code>ramesh.plumber@email.com</code> / <code>password123</code></li>
            </ul>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
