<?php
$pageTitle = "Explore Home Services";
require_once __DIR__ . '/../config/db.php';

$pdo = getDB();
$selectedCatId = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$searchQuery = trim($_GET['q'] ?? '');

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
    $params = [];

    if ($selectedCatId > 0) {
        $sql .= " AND s.category_id = ?";
        $params[] = $selectedCatId;
    }

    if (!empty($searchQuery)) {
        $sql .= " AND (s.name LIKE ? OR s.description LIKE ?)";
        $params[] = "%$searchQuery%";
        $params[] = "%$searchQuery%";
    }

    $sql .= " ORDER BY c.name ASC, s.name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $services = $stmt->fetchAll();
} catch (Exception $e) {}

$pageGreeting = "Book a Service";
$pageSubtitle = "Choose verified technicians and upfront pricing for home maintenance";

require_once __DIR__ . '/../includes/header.php';
?>

<!-- Search & Category Filter Controls -->
<div class="card" style="margin-bottom: 2rem;">
    <div class="grid grid-2" style="align-items: center;">
        <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label" for="service-search"><i class="fa-solid fa-magnifying-glass" style="color:var(--primary);margin-right:6px;"></i> Search by Service Keyword</label>
            <input type="text" id="service-search" class="form-control" placeholder="Search e.g. leak, tap, fan, wiring, cleaning, paint..." value="<?php echo htmlspecialchars($searchQuery); ?>">
        </div>
        <div class="form-group" style="margin-bottom: 0;">
            <label class="form-label" for="category-filter"><i class="fa-solid fa-filter" style="color:var(--primary);margin-right:6px;"></i> Filter by Trade / Category</label>
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
                <a href="book.php?service_id=<?php echo $srv['id']; ?>" class="btn btn-primary btn-block">
                    <i class="fa-solid fa-calendar-plus"></i> Book Service
                </a>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div id="no-services-found" style="<?php echo empty($services) ? 'display:block;' : 'display:none;'; ?> text-align: center; padding: 4rem 1rem;">
    <div style="width: 50px; height: 50px; border-radius: 50%; background: #eff6ff; color: var(--primary); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin: 0 auto 1rem;">
        <i class="fa-solid fa-magnifying-glass"></i>
    </div>
    <h3 style="font-size: 1.1rem; margin-bottom: 4px;">No matching services found</h3>
    <p style="color: var(--text-muted); font-size: 0.88rem; margin-bottom: 1.5rem;">Try searching with different keywords or clear your filter.</p>
    <a href="services.php" class="btn btn-secondary btn-sm">Clear Filter</a>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
