<?php
$pageTitle = "Home - On-Demand Household Services";
require_once __DIR__ . '/includes/header.php';

$pdo = getDB();

// Fetch categories
$categories = [];
try {
    $stmt = $pdo->query("SELECT * FROM service_categories ORDER BY id ASC");
    $categories = $stmt->fetchAll();
} catch (Exception $e) {}

// Fetch popular services
$services = [];
try {
    $stmt = $pdo->query("SELECT s.*, c.name as category_name, c.icon as category_icon 
                         FROM services s 
                         JOIN service_categories c ON s.category_id = c.id 
                         WHERE s.status = 'active' 
                         LIMIT 6");
    $services = $stmt->fetchAll();
} catch (Exception $e) {}
?>

<!-- Light Hero Banner matching reference style -->
<div style="background: linear-gradient(135deg, #e8f2fe 0%, #edf5fc 100%); border: 1px solid #dce8f5; padding: 3.5rem 2.5rem; border-radius: var(--radius-lg); margin-bottom: 3rem; text-align: center; box-shadow: var(--shadow-xs);">
    <div style="max-width: 720px; margin: 0 auto;">
        <span style="display:inline-flex; align-items:center; gap:6px; padding: 5px 14px; background: #ffffff; border: 1px solid #cfe0f2; border-radius: var(--radius-full); font-size: 0.82rem; font-weight: 700; color: var(--primary); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 1.25rem; box-shadow: 0 1px 3px rgba(15,23,42,0.04);">
            <i class="fa-solid fa-shield-halved"></i> Verified Local Experts At Your Doorstep
        </span>
        <h1 style="color: var(--text-dark); font-size: 2.6rem; font-weight: 800; margin-bottom: 0.85rem; line-height: 1.2; letter-spacing: -0.5px;">
            Reliable Home Services, Booked In Minutes
        </h1>
        <p style="color: var(--text-body); font-size: 1.05rem; margin-bottom: 2rem; line-height: 1.6;">
            From certified electricians and plumbers to deep cleaners and carpenters. Transparent pricing, verified technicians, and guaranteed satisfaction.
        </p>
        <div style="display: flex; gap: 0.85rem; justify-content: center; flex-wrap: wrap;">
            <a href="customer/services.php" class="btn btn-primary" style="padding: 0.75rem 1.75rem; font-size: 0.95rem;">
                <i class="fa-solid fa-magnifying-glass"></i> Explore All Services
            </a>
            <?php if (!$currentUser): ?>
                <a href="auth/register.php" class="btn btn-secondary" style="padding: 0.75rem 1.75rem; font-size: 0.95rem;">
                    <i class="fa-solid fa-user-plus"></i> Customer Sign Up
                </a>
                <a href="auth/provider-register.php" class="btn btn-outline" style="padding: 0.75rem 1.75rem; font-size: 0.95rem;">
                    <i class="fa-solid fa-briefcase"></i> Join as Service Pro
                </a>
            <?php else: ?>
                <a href="<?php echo $role; ?>/dashboard.php" class="btn btn-secondary" style="padding: 0.75rem 1.75rem; font-size: 0.95rem;">
                    <i class="fa-solid fa-table-cells-large"></i> Go to Dashboard
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Service Categories Section -->
<section style="margin-bottom: 3.5rem;">
    <div style="text-align: center; margin-bottom: 2rem;">
        <h2 style="font-size: 1.85rem; margin-bottom: 0.4rem; color: var(--text-dark);">Browse By Trade Category</h2>
        <p style="color: var(--text-muted); font-size: 0.95rem;">Select a specialized trade to find certified technicians</p>
    </div>

    <div class="grid grid-3">
        <?php 
        $iconMap = [
            'Plumbing' => ['icon' => 'fa-faucet-drip', 'color' => '#2563eb', 'bg' => '#eff6ff'],
            'Electrical' => ['icon' => 'fa-bolt', 'color' => '#d97706', 'bg' => '#fffbeb'],
            'Cleaning' => ['icon' => 'fa-broom', 'color' => '#0d9488', 'bg' => '#f0fdfa'],
            'Carpentry' => ['icon' => 'fa-hammer', 'color' => '#ea580c', 'bg' => '#fff7ed'],
            'Painting' => ['icon' => 'fa-paint-roller', 'color' => '#db2777', 'bg' => '#fdf2f8'],
            'Appliance Repair' => ['icon' => 'fa-screwdriver-wrench', 'color' => '#7c3aed', 'bg' => '#f5f3ff']
        ];
        foreach ($categories as $cat): 
            $meta = $iconMap[$cat['name']] ?? ['icon' => 'fa-screwdriver-wrench', 'color' => '#2563eb', 'bg' => '#eff6ff'];
        ?>
            <a href="customer/services.php?category=<?php echo $cat['id']; ?>" class="card" style="text-decoration:none; display:flex; flex-direction:column; gap:8px;">
                <div style="width: 44px; height: 44px; border-radius: var(--radius-md); background: <?php echo $meta['bg']; ?>; color: <?php echo $meta['color']; ?>; display:flex; align-items:center; justify-content:center; font-size: 1.25rem;">
                    <i class="fa-solid <?php echo $meta['icon']; ?>"></i>
                </div>
                <h3 style="font-size: 1.15rem; color: var(--text-dark);"><?php echo htmlspecialchars($cat['name']); ?></h3>
                <p style="color: var(--text-muted); font-size: 0.88rem; line-height: 1.5;"><?php echo htmlspecialchars($cat['description']); ?></p>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- Popular Services Grid -->
<section style="margin-bottom: 3.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 1.75rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 style="font-size: 1.85rem; margin-bottom: 0.35rem; color: var(--text-dark);">Trending Household Services</h2>
            <p style="color: var(--text-muted); font-size: 0.95rem;">Popular home repairs booked by residents across Karnataka</p>
        </div>
        <a href="customer/services.php" class="btn btn-outline btn-sm">View All Services <i class="fa-solid fa-arrow-right" style="font-size: 11px;"></i></a>
    </div>

    <div class="grid grid-3">
        <?php foreach ($services as $srv): ?>
            <div class="service-card">
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                        <span class="badge badge-accepted"><?php echo htmlspecialchars($srv['category_name']); ?></span>
                        <span style="font-size: 0.82rem; color: var(--text-muted);">
                            <i class="fa-regular fa-clock"></i> ~<?php echo $srv['duration_mins']; ?> mins
                        </span>
                    </div>
                    <h3 style="font-size: 1.15rem; margin-bottom: 0.4rem; color: var(--text-dark);">
                        <?php echo htmlspecialchars($srv['name']); ?>
                    </h3>
                    <p style="color: var(--text-muted); font-size: 0.88rem; margin-bottom: 1.25rem; line-height: 1.5;">
                        <?php echo htmlspecialchars($srv['description']); ?>
                    </p>
                </div>
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 1rem; border-top: 1px dashed var(--border-light); padding-top: 0.75rem;">
                        <span style="color: var(--text-muted); font-size: 0.85rem;">Standard Rate:</span>
                        <span class="service-card-price"><?php echo formatPrice($srv['price']); ?></span>
                    </div>
                    <a href="customer/book.php?service_id=<?php echo $srv['id']; ?>" class="btn btn-primary btn-block">
                        <i class="fa-solid fa-calendar-plus"></i> Book Service
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- Trust & Verification Highlights -->
<section style="background: #ffffff; border: 1px solid var(--border-card); border-radius: var(--radius-lg); padding: 2.5rem; margin-bottom: 2rem;">
    <div class="grid grid-3" style="text-align: center;">
        <div>
            <div style="width: 50px; height: 50px; border-radius: 50%; background: #eff6ff; color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.35rem; margin: 0 auto 0.85rem;">
                <i class="fa-solid fa-user-check"></i>
            </div>
            <h4 style="font-size: 1.05rem; margin-bottom: 4px; color: var(--text-dark);">Verified Professionals</h4>
            <p style="font-size: 0.88rem; color: var(--text-muted);">Background-checked and certified technicians for safety and peace of mind.</p>
        </div>
        <div>
            <div style="width: 50px; height: 50px; border-radius: 50%; background: #ecfdf5; color: #16a34a; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; margin: 0 auto 0.85rem;">
                <i class="fa-solid fa-indian-rupee-sign"></i>
            </div>
            <h4 style="font-size: 1.05rem; margin-bottom: 4px; color: var(--text-dark);">Transparent Pricing</h4>
            <p style="font-size: 0.88rem; color: var(--text-muted);">Upfront service rates with zero hidden inspection surprises.</p>
        </div>
        <div>
            <div style="width: 50px; height: 50px; border-radius: 50%; background: #fffbeb; color: #d97706; display: flex; align-items: center; justify-content: center; font-size: 1.35rem; margin: 0 auto 0.85rem;">
                <i class="fa-solid fa-thumbs-up"></i>
            </div>
            <h4 style="font-size: 1.05rem; margin-bottom: 4px; color: var(--text-dark);">Quality Guaranteed</h4>
            <p style="font-size: 0.88rem; color: var(--text-muted);">30-day service warranty on all plumbing, electrical and repairs.</p>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
