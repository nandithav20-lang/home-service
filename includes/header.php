<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/safety_functions.php';
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

$isPortal = (strpos($_SERVER['PHP_SELF'], '/customer/') !== false || 
             strpos($_SERVER['PHP_SELF'], '/provider/') !== false || 
             strpos($_SERVER['PHP_SELF'], '/admin/') !== false);

$currentScript = basename($_SERVER['PHP_SELF']);

// Avatar selection helper
$avatarSrc = '';
if ($currentUser) {
    if (strtolower($currentUser['name']) === 'nanditha' && file_exists(__DIR__ . '/../assets/images/customer-avatar.jpg')) {
        $avatarSrc = $pathPrefix . 'assets/images/customer-avatar.jpg';
    } elseif ($role === 'provider' && file_exists(__DIR__ . '/../assets/images/provider-pro.jpg')) {
        $avatarSrc = $pathPrefix . 'assets/images/provider-pro.jpg';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | HomeService' : 'HomeService - Professional On-Demand Household Services'; ?></title>
    <!-- Modern Font Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Stylesheets -->
    <link rel="stylesheet" href="<?php echo $pathPrefix; ?>assets/css/style.css">
    <link rel="stylesheet" href="<?php echo $pathPrefix; ?>assets/css/safety.css">
    <!-- FontAwesome 6 Free CDN -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>

<?php if ($isPortal && $currentUser): ?>
<!-- ========================================================
     AUTHENTICATED DASHBOARD PORTAL LAYOUT (Sidebar + Topbar)
     ======================================================== -->
<div class="app-layout">
    <!-- Left Sidebar -->
    <aside class="app-sidebar">
        <div class="sidebar-header">
            <a href="<?php echo $pathPrefix . $role . '/dashboard.php'; ?>" class="sidebar-brand">
                <div class="sidebar-logo-icon">
                    <i class="fa-solid fa-house-chimney"></i>
                </div>
                <span>HomeService</span>
            </a>
        </div>

        <ul class="sidebar-nav">
            <?php if ($role === 'customer'): ?>
                <li class="sidebar-item">
                    <a href="<?php echo $pathPrefix; ?>customer/dashboard.php" class="sidebar-link <?php echo ($currentScript === 'dashboard.php') ? 'active' : ''; ?>">
                        <i class="fa-solid fa-table-cells-large"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="<?php echo $pathPrefix; ?>customer/services.php" class="sidebar-link <?php echo ($currentScript === 'services.php' || $currentScript === 'book.php') ? 'active' : ''; ?>">
                        <i class="fa-solid fa-calendar-plus"></i>
                        <span>Book a Service</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="<?php echo $pathPrefix; ?>customer/my-bookings.php" class="sidebar-link <?php echo ($currentScript === 'my-bookings.php' || $currentScript === 'edit-booking.php') ? 'active' : ''; ?>">
                        <i class="fa-solid fa-calendar-check"></i>
                        <span>My Bookings</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="<?php echo $pathPrefix; ?>customer/safety.php" class="sidebar-link <?php echo ($currentScript === 'safety.php') ? 'active' : ''; ?>" style="color: #0284c7;">
                        <i class="fa-solid fa-shield-halved" style="color: #0284c7;"></i>
                        <span>SafeVisit & SOS</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="<?php echo $pathPrefix; ?>customer/trusted_contacts.php" class="sidebar-link <?php echo ($currentScript === 'trusted_contacts.php') ? 'active' : ''; ?>">
                        <i class="fa-solid fa-user-shield"></i>
                        <span>Trusted Contacts</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="<?php echo $pathPrefix; ?>customer/my-bookings.php#reviews" class="sidebar-link <?php echo ($currentScript === 'add-review.php') ? 'active' : ''; ?>">
                        <i class="fa-solid fa-star"></i>
                        <span>Reviews</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="<?php echo $pathPrefix; ?>customer/profile.php" class="sidebar-link <?php echo ($currentScript === 'profile.php') ? 'active' : ''; ?>">
                        <i class="fa-solid fa-user"></i>
                        <span>Profile</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="<?php echo $pathPrefix; ?>customer/report_provider.php" class="sidebar-link <?php echo ($currentScript === 'report_provider.php') ? 'active' : ''; ?>" style="color: var(--text-muted); font-size: 0.85rem;">
                        <i class="fa-regular fa-flag"></i>
                        <span>Report Provider</span>
                    </a>
                </li>
                <li class="sidebar-item" style="margin-top: auto;">
                    <a href="<?php echo $pathPrefix; ?>auth/logout.php" class="sidebar-link">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i>
                        <span>Logout</span>
                    </a>
                </li>

            <?php elseif ($role === 'provider'): ?>
                <li class="sidebar-item">
                    <a href="<?php echo $pathPrefix; ?>provider/dashboard.php" class="sidebar-link <?php echo ($currentScript === 'dashboard.php') ? 'active' : ''; ?>">
                        <i class="fa-solid fa-table-cells-large"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="<?php echo $pathPrefix; ?>provider/requests.php" class="sidebar-link <?php echo ($currentScript === 'requests.php' || $currentScript === 'update-status.php') ? 'active' : ''; ?>">
                        <i class="fa-solid fa-clipboard-list"></i>
                        <span>My Requests</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="<?php echo $pathPrefix; ?>provider/dashboard.php#schedule" class="sidebar-link">
                        <i class="fa-solid fa-calendar-days"></i>
                        <span>Bookings</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="<?php echo $pathPrefix; ?>provider/dashboard.php#earnings" class="sidebar-link">
                        <i class="fa-solid fa-chart-column"></i>
                        <span>Earnings</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="<?php echo $pathPrefix; ?>provider/verification.php" class="sidebar-link <?php echo ($currentScript === 'verification.php') ? 'active' : ''; ?>" style="color: #059669;">
                        <i class="fa-solid fa-id-card" style="color: #059669;"></i>
                        <span>Verification Status</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="<?php echo $pathPrefix; ?>provider/profile.php" class="sidebar-link <?php echo ($currentScript === 'profile.php') ? 'active' : ''; ?>">
                        <i class="fa-solid fa-user"></i>
                        <span>Profile</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="<?php echo $pathPrefix; ?>provider/dashboard.php#availability" class="sidebar-link">
                        <i class="fa-solid fa-clock"></i>
                        <span>Availability</span>
                    </a>
                </li>
                <li class="sidebar-item" style="margin-top: auto;">
                    <a href="<?php echo $pathPrefix; ?>auth/logout.php" class="sidebar-link">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i>
                        <span>Logout</span>
                    </a>
                </li>

            <?php elseif ($role === 'admin'): ?>
                <li class="sidebar-item">
                    <a href="<?php echo $pathPrefix; ?>admin/dashboard.php" class="sidebar-link <?php echo ($currentScript === 'dashboard.php') ? 'active' : ''; ?>">
                        <i class="fa-solid fa-table-cells-large"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="<?php echo $pathPrefix; ?>admin/safety.php" class="sidebar-link <?php echo ($currentScript === 'safety.php') ? 'active' : ''; ?>" style="color: #dc2626;">
                        <i class="fa-solid fa-shield-halved" style="color: #dc2626;"></i>
                        <span>Safety & SOS Center</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="<?php echo $pathPrefix; ?>admin/users.php" class="sidebar-link <?php echo ($currentScript === 'users.php' || $currentScript === 'edit-user.php') ? 'active' : ''; ?>">
                        <i class="fa-solid fa-users"></i>
                        <span>Users</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="<?php echo $pathPrefix; ?>admin/providers.php" class="sidebar-link <?php echo ($currentScript === 'providers.php' || $currentScript === 'edit-provider.php') ? 'active' : ''; ?>">
                        <i class="fa-solid fa-user-gear"></i>
                        <span>Providers</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="<?php echo $pathPrefix; ?>admin/bookings.php" class="sidebar-link <?php echo ($currentScript === 'bookings.php' || $currentScript === 'edit-booking.php') ? 'active' : ''; ?>">
                        <i class="fa-solid fa-calendar-check"></i>
                        <span>Bookings</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="<?php echo $pathPrefix; ?>admin/services.php" class="sidebar-link <?php echo ($currentScript === 'services.php' || $currentScript === 'edit-service.php') ? 'active' : ''; ?>">
                        <i class="fa-solid fa-screwdriver-wrench"></i>
                        <span>Services</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="<?php echo $pathPrefix; ?>admin/categories.php" class="sidebar-link <?php echo ($currentScript === 'categories.php' || $currentScript === 'edit-category.php') ? 'active' : ''; ?>">
                        <i class="fa-solid fa-layer-group"></i>
                        <span>Categories</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="<?php echo $pathPrefix; ?>admin/dashboard.php#reviews" class="sidebar-link">
                        <i class="fa-solid fa-star"></i>
                        <span>Reviews</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="<?php echo $pathPrefix; ?>admin/dashboard.php#analytics" class="sidebar-link">
                        <i class="fa-solid fa-chart-line"></i>
                        <span>Reports</span>
                    </a>
                </li>
                <li class="sidebar-item">
                    <a href="<?php echo $pathPrefix; ?>admin/dashboard.php#settings" class="sidebar-link">
                        <i class="fa-solid fa-gear"></i>
                        <span>Settings</span>
                    </a>
                </li>
                <li class="sidebar-item" style="margin-top: auto;">
                    <a href="<?php echo $pathPrefix; ?>auth/logout.php" class="sidebar-link">
                        <i class="fa-solid fa-arrow-right-from-bracket"></i>
                        <span>Logout</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>

        <div class="sidebar-footer">
            <div class="sidebar-badge-card">
                <div class="sidebar-badge-icon">
                    <i class="fa-solid fa-house-chimney-window"></i>
                </div>
                <div class="sidebar-badge-title">Your home, our priority</div>
                <div class="sidebar-badge-desc">Quality service guaranteed</div>
            </div>
        </div>
    </aside>

    <!-- Main Content Wrapper -->
    <div class="app-main">
        <!-- Topbar -->
        <header class="app-topbar">
            <div class="topbar-left">
                <button type="button" class="topbar-toggle" id="sidebar-toggle" aria-label="Toggle navigation menu">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="topbar-greeting">
                    <h2><?php echo isset($pageGreeting) ? $pageGreeting : 'Welcome back, ' . htmlspecialchars($currentUser['name']) . '!'; ?></h2>
                    <p><?php echo isset($pageSubtitle) ? $pageSubtitle : "Here's what is happening with your account today."; ?></p>
                </div>
            </div>

            <div class="topbar-right">
                <!-- Topbar Search for quick service lookup -->
                <form action="<?php echo $pathPrefix . ($role === 'customer' ? 'customer/services.php' : ($role === 'provider' ? 'provider/requests.php' : 'admin/bookings.php')); ?>" method="GET" class="topbar-search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" name="q" placeholder="Search...">
                </form>

                <a href="<?php echo $pathPrefix . $role . '/dashboard.php'; ?>" class="topbar-icon-btn" title="Notifications">
                    <i class="fa-regular fa-bell"></i>
                    <span class="topbar-icon-badge"></span>
                </a>

                <!-- Profile Dropdown -->
                <div class="user-profile-menu">
                    <button type="button" class="user-profile-btn" id="user-profile-btn">
                        <?php if (!empty($avatarSrc)): ?>
                            <img src="<?php echo $avatarSrc; ?>" alt="<?php echo htmlspecialchars($currentUser['name']); ?>" class="user-avatar-circle">
                        <?php else: ?>
                            <div class="user-avatar-circle">
                                <?php echo strtoupper(substr($currentUser['name'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                        <div class="user-profile-info">
                            <div class="user-profile-name"><?php echo htmlspecialchars($currentUser['name']); ?></div>
                            <div class="user-profile-role"><?php echo ucfirst($role); ?></div>
                        </div>
                        <i class="fa-solid fa-chevron-down" style="font-size:0.75rem;color:var(--text-muted);margin-left:4px;"></i>
                    </button>

                    <div class="user-dropdown" id="user-dropdown">
                        <a href="<?php echo $pathPrefix . ($role === 'customer' ? 'customer/profile.php' : ($role === 'provider' ? 'provider/profile.php' : 'admin/dashboard.php')); ?>" class="user-dropdown-item">
                            <i class="fa-regular fa-user"></i> My Profile
                        </a>
                        <a href="<?php echo $pathPrefix . ($role === 'customer' ? 'customer/my-bookings.php' : ($role === 'provider' ? 'provider/requests.php' : 'admin/bookings.php')); ?>" class="user-dropdown-item">
                            <i class="fa-solid fa-calendar-check"></i> Bookings
                        </a>
                        <div class="user-dropdown-divider"></div>
                        <a href="<?php echo $pathPrefix; ?>auth/logout.php" class="user-dropdown-item" style="color:var(--danger);">
                            <i class="fa-solid fa-arrow-right-from-bracket"></i> Sign Out
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- Body Area -->
        <main class="app-body">
            <?php displayFlash(); ?>

<?php else: ?>
<!-- ========================================================
     PUBLIC WEBSITE LAYOUT (Landing / Auth Pages)
     ======================================================== -->
<header class="navbar">
    <div class="container nav-container">
        <a href="<?php echo $pathPrefix; ?>index.php" class="brand-logo">
            <span class="brand-icon"><i class="fa-solid fa-house-chimney"></i></span>
            <span>Home<span style="color:var(--primary);">Service</span></span>
        </a>

        <ul class="nav-links">
            <li><a href="<?php echo $pathPrefix; ?>index.php" class="nav-link">Home</a></li>
            <li><a href="<?php echo $pathPrefix; ?>customer/services.php" class="nav-link">Services</a></li>

            <?php if ($currentUser): ?>
                <li><a href="<?php echo $pathPrefix . $role . '/dashboard.php'; ?>" class="btn btn-primary btn-sm">Go to Dashboard</a></li>
                <li><a href="<?php echo $pathPrefix; ?>auth/logout.php" class="btn btn-secondary btn-sm">Logout</a></li>
            <?php else: ?>
                <li><a href="<?php echo $pathPrefix; ?>auth/login.php" class="btn btn-secondary btn-sm">Sign In</a></li>
                <li><a href="<?php echo $pathPrefix; ?>auth/register.php" class="btn btn-primary btn-sm">Book a Service</a></li>
                <li><a href="<?php echo $pathPrefix; ?>auth/provider-register.php" class="btn btn-outline btn-sm">Join as Pro</a></li>
            <?php endif; ?>
        </ul>
    </div>
</header>

<main class="main-content">
    <div class="container">
        <?php displayFlash(); ?>
<?php endif; ?>
