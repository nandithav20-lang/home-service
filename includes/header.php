<?php
require_once __DIR__ . '/../config/db.php';
$currentUser = getCurrentUser();
$role = $currentUser['role'] ?? null;

// Determine current relative path for links
$pathPrefix = '';
if (strpos($_SERVER['PHP_SELF'], '/customer/') !== false || 
    strpos($_SERVER['PHP_SELF'], '/provider/') !== false || 
    strpos($_SERVER['PHP_SELF'], '/admin/') !== false ||
    strpos($_SERVER['PHP_SELF'], '/auth/') !== false) {
    $pathPrefix = '../';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | ' . APP_NAME : APP_NAME . ' - On-Demand Household Services'; ?></title>
    <!-- Stylesheets -->
    <link rel="stylesheet" href="<?php echo $pathPrefix; ?>assets/css/style.css">
    <!-- FontAwesome 6 Free CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<header class="navbar">
    <div class="container nav-container">
        <a href="<?php echo $pathPrefix; ?>index.php" class="brand-logo">
            <span class="brand-icon"><i class="fa-solid fa-screwdriver-wrench"></i></span>
            <span>Home<span style="color:var(--primary);">Serve</span></span>
        </a>

        <ul class="nav-links">
            <li><a href="<?php echo $pathPrefix; ?>index.php" class="nav-link">Home</a></li>
            <li><a href="<?php echo $pathPrefix; ?>customer/services.php" class="nav-link">Services</a></li>

            <?php if ($role === 'customer'): ?>
                <li><a href="<?php echo $pathPrefix; ?>customer/dashboard.php" class="nav-link">Dashboard</a></li>
                <li><a href="<?php echo $pathPrefix; ?>customer/my-bookings.php" class="nav-link">My Bookings</a></li>
                <li><a href="<?php echo $pathPrefix; ?>customer/profile.php" class="nav-link">My Profile</a></li>
            <?php elseif ($role === 'provider'): ?>
                <li><a href="<?php echo $pathPrefix; ?>provider/dashboard.php" class="nav-link">Provider Dashboard</a></li>
                <li><a href="<?php echo $pathPrefix; ?>provider/requests.php" class="nav-link">Job Requests</a></li>
                <li><a href="<?php echo $pathPrefix; ?>provider/profile.php" class="nav-link">My Profile</a></li>
            <?php elseif ($role === 'admin'): ?>
                <li><a href="<?php echo $pathPrefix; ?>admin/dashboard.php" class="nav-link">Admin Dashboard</a></li>
                <li><a href="<?php echo $pathPrefix; ?>admin/bookings.php" class="nav-link">Bookings</a></li>
                <li><a href="<?php echo $pathPrefix; ?>admin/services.php" class="nav-link">Services</a></li>
                <li><a href="<?php echo $pathPrefix; ?>admin/users.php" class="nav-link">Customers</a></li>
                <li><a href="<?php echo $pathPrefix; ?>admin/providers.php" class="nav-link">Providers</a></li>
            <?php endif; ?>

            <?php if ($currentUser): ?>
                <li class="nav-user-tag">
                    <i class="fa-regular fa-user"></i>
                    <span><?php echo htmlspecialchars($currentUser['name']); ?></span>
                    <small style="color:var(--gray-500);text-transform:capitalize;">(<?php echo $role; ?>)</small>
                </li>
                <li>
                    <a href="<?php echo $pathPrefix; ?>auth/logout.php" class="btn btn-secondary btn-sm">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i> Logout
                    </a>
                </li>
            <?php else: ?>
                <li><a href="<?php echo $pathPrefix; ?>auth/login.php" class="btn btn-secondary btn-sm">Sign In</a></li>
                <li><a href="<?php echo $pathPrefix; ?>auth/register.php" class="btn btn-primary btn-sm">Register</a></li>
                <li><a href="<?php echo $pathPrefix; ?>auth/provider-register.php" class="btn btn-outline btn-sm">Join as Pro</a></li>
            <?php endif; ?>
        </ul>
    </div>
</header>

<main class="main-content">
    <div class="container">
        <?php displayFlash(); ?>
