<?php
$pageTitle = "Explore Home Services";
require_once __DIR__ . '/../config/db.php';

$pdo = getDB();
$selectedCatId = isset($_GET['category']) ? (int)$_GET['category'] : 0;

// Fetch categories
$categories = [];
try {
    $categories = $pdo->query("SELECT * FROM service_categories ORDER BY name ASC")->fetchAll();
} catch (Exception $e) {}

// Fetch services
$services = [];
try {
    $sql = "SELECT s.*, c.name as category_name, c.icon as category_icon 
            FROM services s 
            JOIN service_categories c ON s.category_id = c.id 
            WHERE s.status = 'active'";
    if ($selectedCatId > 0) {
        $stmt = $pdo->prepare($sql . " AND s.category_id = ? ORDER BY s.name ASC");
        $stmt->execute([$selectedCatId]);
        $services = $stmt->fetchAll();
    } else {
        $services = $pdo->query($sql . " ORDER BY c.name ASC, s.name ASC")->fetchAll();
    }
} catch (Exception $e) {}

require_once __DIR__ . '/../includes/header.php';
?>

<div style="margin-bottom: 2.5rem;">
    <div style="text-align: center; max-width: 650px; margin: 0 auto 2rem;">
        <h2 style="font-size: 2.25rem; margin-bottom: 0.5rem;">Professional Services at Your Fingertips</h2>
        <p style="color: var(--gray-500);">Choose verified technicians for all your home maintenance and repair needs</p>
    </div>

    <!-- Search & Filter Controls -->
    <div class="card" style="padding: 1.25rem; margin-bottom: 2rem;">
        <div class="grid grid-2" style="align-items: center;">
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" for="service-search"><i class="fa-solid fa-magnifying-glass"></i> Search by Service Keyword</label>
                <input type="text" id="service-search" class="form-control" placeholder="Search e.g. leak, tap, fan, wiring, cleaning...">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label" for="category-filter"><i class="fa-solid fa-filter"></i> Filter by Trade / Category</label>
                <select id="category-filter" class="form-control" onchange="window.location.href = this.value ? 'services.php?category=' + this.value : 'services.php';">
                    <option value="">All Service Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo ($selectedCatId === (int)$cat['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- Service Catalog Grid -->
    <div class="grid grid-3" id="service-list">
        <?php foreach ($services as $srv): ?>
            <div class="service-card service-item" 
                 data-name="<?php echo htmlspecialchars(strtolower($srv['name'])); ?>"
                 data-desc="<?php echo htmlspecialchars(strtolower($srv['description'])); ?>"
                 data-category="<?php echo $srv['category_id']; ?>">
                <div>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem;">
                        <span class="badge badge-accepted"><?php echo htmlspecialchars($srv['category_name']); ?></span>
                        <span style="font-size: 0.85rem; color: var(--gray-500);">
                            <i class="fa-regular fa-clock"></i> ~<?php echo $srv['duration_mins']; ?> mins
                        </span>
                    </div>
                    <h3 style="font-size: 1.2rem; margin-bottom: 0.5rem; color: var(--dark);">
                        <?php echo htmlspecialchars($srv['name']); ?>
                    </h3>
                    <p style="color: var(--gray-500); font-size: 0.9rem; margin-bottom: 1.25rem;">
                        <?php echo htmlspecialchars($srv['description']); ?>
                    </p>
                </div>
                <div>
                    <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 1rem; border-top: 1px dashed var(--gray-200); padding-top: 0.75rem;">
                        <span style="color: var(--gray-500); font-size: 0.85rem;">Standard Rate:</span>
                        <span class="service-card-price" style="margin:0;"><?php echo formatPrice($srv['price']); ?></span>
                    </div>
                    <a href="book.php?service_id=<?php echo $srv['id']; ?>" class="btn btn-primary btn-block">
                        <i class="fa-solid fa-calendar-plus"></i> Book Service
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div id="no-services-found" style="display: none; text-align: center; padding: 4rem 1rem;">
        <i class="fa-solid fa-magnifying-glass" style="font-size: 3rem; color: var(--gray-300); margin-bottom: 1rem;"></i>
        <h3>No matching services found</h3>
        <p style="color: var(--gray-500);">Try searching with different keywords or clear your filter.</p>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
