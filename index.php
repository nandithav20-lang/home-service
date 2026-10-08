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

<!-- Hero Banner -->
<div style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%); color: #fff; padding: 4rem 2rem; border-radius: var(--radius-lg); margin-bottom: 3rem; text-align: center; box-shadow: var(--shadow-lg);">
    <div style="max-width: 750px; margin: 0 auto;">
        <span style="display:inline-block; padding: 6px 14px; background: rgba(255,255,255,0.15); border-radius: 999px; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 1rem;">
            ⚡ Verified Local Experts At Your Doorstep
        </span>
        <h1 style="color: #fff; font-size: 2.75rem; font-weight: 800; margin-bottom: 1rem; line-height: 1.2;">
            Reliable Home Services, Booked In Minutes
        </h1>
        <p style="color: #c7d2fe; font-size: 1.15rem; margin-bottom: 2rem;">
            From certified electricians and plumbers to deep cleaners and carpenters. Transparent pricing, verified technicians, and hassle-free booking.
        </p>
        <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
            <a href="customer/services.php" class="btn btn-primary" style="padding: 0.85rem 1.75rem; font-size: 1rem;">
                <i class="fa-solid fa-magnifying-glass"></i> Explore All Services
            </a>
            <?php if (!$currentUser): ?>
                <a href="auth/register.php" class="btn btn-secondary" style="padding: 0.85rem 1.75rem; font-size: 1rem;">
                    <i class="fa-solid fa-user-plus"></i> Customer Sign Up
                </a>
            <?php else: ?>
                <a href="customer/dashboard.php" class="btn btn-secondary" style="padding: 0.85rem 1.75rem; font-size: 1rem;">
                    <i class="fa-solid fa-gauge-high"></i> Go to Dashboard
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Service Categories Section -->
<section style="margin-bottom: 3.5rem;">
    <div style="text-align: center; margin-bottom: 2rem;">
        <h2 style="font-size: 2rem; margin-bottom: 0.5rem;">Browse By Category</h2>
        <p style="color: var(--gray-500);">Select a specialized trade to find certified technicians</p>
    </div>

    <div class="grid grid-3">
        <?php 
        $iconMap = [
            'Plumbing' => 'fa-faucet-drip',
            'Electrical' => 'fa-bolt-lightning',
            'Cleaning' => 'fa-broom',
            'Carpentry' => 'fa-hammer',
            'Painting' => 'fa-paint-roller',
            'Appliance Repair' => 'fa-wrench'
        ];
        foreach ($categories as $cat): 
            $icon = $iconMap[$cat['name']] ?? 'fa-screwdriver-wrench';
        ?>
            <a href="customer/services.php?category=<?php echo $cat['id']; ?>" class="cat-card">
                <div class="cat-card-icon">
                    <i class="fa-solid <?php echo $icon; ?>"></i>
                </div>
                <h3 style="font-size: 1.25rem; margin-bottom: 0.5rem; color: var(--dark);"><?php echo htmlspecialchars($cat['name']); ?></h3>
                <p style="color: var(--gray-500); font-size: 0.9rem;"><?php echo htmlspecialchars($cat['description']); ?></p>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- Popular Services Grid -->
<section style="margin-bottom: 3.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem;">
        <div>
            <h2 style="font-size: 2rem; margin-bottom: 0.5rem;">Trending Household Services</h2>
            <p style="color: var(--gray-500);">Popular home repairs booked by residents in Bengaluru, Mysuru, & nearby</p>
        </div>
        <a href="customer/services.php" class="btn btn-outline btn-sm">View All Services <i class="fa-solid fa-arrow-right"></i></a>
    </div>

    <div class="grid grid-3">
        <?php foreach ($services as $srv): ?>
            <div class="service-card">
                <div>
                    <span class="badge badge-accepted" style="margin-bottom: 0.75rem;">
                        <?php echo htmlspecialchars($srv['category_name']); ?>
                    </span>
                    <h3 style="font-size: 1.2rem; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($srv['name']); ?></h3>
                    <p style="color: var(--gray-500); font-size: 0.9rem; margin-bottom: 1rem;">
                        <?php echo htmlspecialchars($srv['description']); ?>
                    </p>
                </div>
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 1rem;">
                        <span class="service-card-price"><?php echo formatPrice($srv['price']); ?></span>
                        <span style="color: var(--gray-500); font-size: 0.85rem;"><i class="fa-regular fa-clock"></i> ~<?php echo $srv['duration_mins']; ?> mins</span>
                    </div>
                    <a href="customer/book.php?service_id=<?php echo $srv['id']; ?>" class="btn btn-primary btn-block">
                        <i class="fa-solid fa-calendar-check"></i> Book Now
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<!-- How It Works Section -->
<section style="background: var(--white); padding: 3rem 2rem; border-radius: var(--radius-md); border: 1px solid var(--gray-200); margin-bottom: 2rem;">
    <div style="text-align: center; margin-bottom: 2.5rem;">
        <h2 style="font-size: 2rem; margin-bottom: 0.5rem;">How HomeServe Works</h2>
        <p style="color: var(--gray-500);">Quick, transparent, and seamless 4-step process</p>
    </div>

    <div class="grid grid-4" style="text-align: center;">
        <div>
            <div style="width: 56px; height: 56px; background: var(--primary-light); color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; font-weight: 800; margin: 0 auto 1rem;">1</div>
            <h4 style="margin-bottom: 0.5rem;">Select Service</h4>
            <p style="color: var(--gray-500); font-size: 0.88rem;">Browse plumbing, electrical, cleaning and pick what you need.</p>
        </div>
        <div>
            <div style="width: 56px; height: 56px; background: var(--primary-light); color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; font-weight: 800; margin: 0 auto 1rem;">2</div>
            <h4 style="margin-bottom: 0.5rem;">Choose Schedule</h4>
            <p style="color: var(--gray-500); font-size: 0.88rem;">Pick a convenient date, time slot, and provide your home address.</p>
        </div>
        <div>
            <div style="width: 56px; height: 56px; background: var(--primary-light); color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; font-weight: 800; margin: 0 auto 1rem;">3</div>
            <h4 style="margin-bottom: 0.5rem;">Expert Dispatched</h4>
            <p style="color: var(--gray-500); font-size: 0.88rem;">Admin assigns a verified technician who arrives on time.</p>
        </div>
        <div>
            <div style="width: 56px; height: 56px; background: var(--primary-light); color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.25rem; font-weight: 800; margin: 0 auto 1rem;">4</div>
            <h4 style="margin-bottom: 0.5rem;">Job Done & Review</h4>
            <p style="color: var(--gray-500); font-size: 0.88rem;">Inspect completed work, track status, and leave your rating.</p>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
