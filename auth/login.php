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

<div style="max-width: 480px; margin: 2.5rem auto;">
    <div class="card" style="padding: 2.25rem;">
        <div style="text-align: center; margin-bottom: 2rem;">
            <div style="margin: 0 auto 1rem; width: 48px; height: 48px; background: #eff6ff; color: var(--primary); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
                <i class="fa-solid fa-lock"></i>
            </div>
            <h2 style="font-size: 1.6rem; margin-bottom: 0.35rem; color: var(--text-dark);">Sign In to Your Account</h2>
            <p style="color: var(--text-muted); font-size: 0.9rem;">Access Customer, Service Provider, or Admin Portal</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php" id="loginForm">
            <div class="form-group">
                <label class="form-label" for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" required placeholder="e.g. nanditha@gmail.com" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required placeholder="Enter password">
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="padding: 0.75rem; margin-top: 0.5rem;">
                <i class="fa-solid fa-right-to-bracket"></i> Sign In
            </button>
        </form>

        <div style="margin-top: 1.75rem; text-align: center; font-size: 0.9rem; color: var(--text-muted);">
            Don't have an account? <br>
            <a href="register.php" style="font-weight: 600;">Register as Customer</a> &bull; 
            <a href="provider-register.php" style="font-weight: 600;">Register as Service Pro</a>
        </div>

        <!-- Quick Demo Credentials Card -->
        <div style="margin-top: 2rem; padding: 1.1rem; background: #f8fafc; border: 1px solid var(--border-light); border-radius: var(--radius-md); font-size: 0.85rem;">
            <div style="font-weight: 700; margin-bottom: 0.75rem; color: var(--text-dark); display: flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-bolt" style="color: var(--primary);"></i> One-Click Demo Logins:
            </div>
            <div style="display: flex; flex-direction: column; gap: 6px;">
                <button type="button" class="btn btn-secondary btn-sm" style="justify-content: flex-start; text-align: left; padding: 6px 10px;" onclick="fillLogin('nanditha@gmail.com', 'password123')">
                    <i class="fa-solid fa-user" style="color:var(--primary);width:16px;"></i>
                    <span><strong>Customer:</strong> Nanditha (nanditha@gmail.com)</span>
                </button>
                <button type="button" class="btn btn-secondary btn-sm" style="justify-content: flex-start; text-align: left; padding: 6px 10px;" onclick="fillLogin('ramesh.plumber@email.com', 'password123')">
                    <i class="fa-solid fa-user-gear" style="color:var(--warning);width:16px;"></i>
                    <span><strong>Provider:</strong> Ramesh Babu (ramesh.plumber@email.com)</span>
                </button>
                <button type="button" class="btn btn-secondary btn-sm" style="justify-content: flex-start; text-align: left; padding: 6px 10px;" onclick="fillLogin('admin@homeservice.com', 'admin123')">
                    <i class="fa-solid fa-shield-halved" style="color:var(--success);width:16px;"></i>
                    <span><strong>Admin:</strong> System Administrator (admin@homeservice.com)</span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function fillLogin(email, password) {
    document.getElementById('email').value = email;
    document.getElementById('password').value = password;
    document.getElementById('loginForm').submit();
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
