<?php
$pageTitle = "Trusted Emergency Contacts";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/safety_functions.php';
requireRole('customer');

$pdo = getDB();
$userId = (int)$_SESSION['user_id'];
$csrfToken = getCsrfToken();
$errors = [];

// Handle Form Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $submittedToken = $_POST['csrf_token'] ?? '';

    if (!verifyCsrfToken($submittedToken)) {
        setFlash('danger', 'Security validation failed. Please try again.');
        header("Location: trusted_contacts.php");
        exit;
    }

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $relationship = trim($_POST['relationship'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $isPrimary = isset($_POST['is_primary']) ? 1 : 0;

        try {
            addTrustedContact($userId, $name, $relationship, $phone, $isPrimary);
            setFlash('success', "Trusted emergency contact '{$name}' added successfully.");
            header("Location: trusted_contacts.php");
            exit;
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    } elseif ($action === 'delete') {
        $contactId = (int)($_POST['contact_id'] ?? 0);
        if ($contactId > 0) {
            deleteTrustedContact($userId, $contactId);
            setFlash('success', 'Emergency contact removed.');
            header("Location: trusted_contacts.php");
            exit;
        }
    }
}

$contacts = getTrustedContacts($userId);

$pageGreeting = "Trusted Contacts";
$pageSubtitle = "People who will be automatically notified if you trigger SOS or miss a safety check-in";

require_once __DIR__ . '/../includes/header.php';
?>
<!-- Safety Styles -->
<link rel="stylesheet" href="../assets/css/safety.css">

<div style="display: flex; justify-content: flex-end; align-items: center; margin-bottom: 1.25rem; gap: 0.75rem;">
    <a href="safety.php" class="btn btn-primary btn-sm">
        <i class="fa-solid fa-shield-halved"></i> Go to SafeVisit
    </a>
    <a href="dashboard.php" class="btn btn-secondary btn-sm">
        <i class="fa-solid fa-arrow-left"></i> Dashboard
    </a>
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

<div class="grid grid-2" style="gap: 1.5rem; align-items: start;">
    <!-- Left Column: Registered Contacts List -->
    <div class="card" style="padding: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; border-bottom: 1px solid var(--border-light); padding-bottom: 0.75rem;">
            <h3 style="font-size: 1.2rem; margin: 0; color: var(--text-dark);">
                <i class="fa-solid fa-user-shield" style="color: var(--safety-blue); margin-right: 6px;"></i>
                Your Emergency Circle
            </h3>
            <span class="badge badge-accepted"><?php echo count($contacts); ?> Saved</span>
        </div>

        <?php if (empty($contacts)): ?>
            <div style="text-align: center; padding: 2.5rem 1rem; background: var(--bg-muted); border-radius: 8px;">
                <i class="fa-solid fa-users-slash" style="font-size: 2.5rem; color: var(--text-light); margin-bottom: 0.75rem;"></i>
                <h4 style="margin-bottom: 0.35rem; color: var(--text-dark);">No Trusted Contacts Added</h4>
                <p style="font-size: 0.88rem; color: var(--text-muted); max-width: 320px; margin: 0 auto 1.25rem;">
                    Add family members, close friends, or neighbors who should receive your emergency alerts and live GPS link during home service visits.
                </p>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 0.85rem;">
                <?php foreach ($contacts as $c): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: var(--bg-muted); border: 1px solid var(--border-subtle); border-radius: 8px;">
                        <div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <strong style="color: var(--text-dark); font-size: 1rem;"><?php echo htmlspecialchars($c['name']); ?></strong>
                                <span class="badge badge-default" style="font-size: 0.72rem;"><?php echo htmlspecialchars($c['relationship']); ?></span>
                                <?php if ($c['is_primary']): ?>
                                    <span class="badge badge-completed" style="font-size: 0.72rem;"><i class="fa-solid fa-star"></i> Primary</span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size: 0.9rem; color: var(--text-muted); margin-top: 4px;">
                                <i class="fa-solid fa-phone" style="font-size: 0.8rem; margin-right: 4px;"></i>
                                <?php echo htmlspecialchars($c['phone']); ?>
                            </div>
                        </div>
                        <div>
                            <form method="POST" action="trusted_contacts.php" onsubmit="return confirm('Remove <?php echo htmlspecialchars(addslashes($c['name'])); ?> from trusted contacts?');" style="margin: 0;">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="contact_id" value="<?php echo $c['id']; ?>">
                                <button type="submit" class="btn btn-outline btn-sm" style="color: var(--danger); border-color: var(--danger-border); padding: 5px 10px;" title="Delete Contact">
                                    <i class="fa-regular fa-trash-can"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="safety-disclaimer-box" style="margin-top: 1.5rem;">
            <i class="fa-solid fa-circle-info" style="color: var(--safety-blue); margin-right: 4px;"></i>
            <strong>Emergency Dispatch Note:</strong> In case real SMS gateway API credentials are not linked in server configuration, alert dispatches are logged to the platform Safety Dispatch Console with full audit traces.
        </div>
    </div>

    <!-- Right Column: Add Contact Form -->
    <div class="card" style="padding: 1.5rem;">
        <h3 style="font-size: 1.2rem; margin-top: 0; margin-bottom: 0.5rem; color: var(--text-dark);">
            <i class="fa-solid fa-user-plus" style="color: var(--safety-blue); margin-right: 6px;"></i>
            Add Trusted Contact
        </h3>
        <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 1.25rem;">
            Fill in the emergency contact details below. Only you have access to this information.
        </p>

        <form method="POST" action="trusted_contacts.php">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <input type="hidden" name="action" value="add">

            <div class="form-group" style="margin-bottom: 1rem;">
                <label class="form-label" for="name">Full Name *</label>
                <input type="text" id="name" name="name" class="form-control" required placeholder="e.g. Priya Sharma" style="width: 100%;">
            </div>

            <div class="form-group" style="margin-bottom: 1rem;">
                <label class="form-label" for="relationship">Relationship *</label>
                <select id="relationship" name="relationship" class="form-control" required style="width: 100%;">
                    <option value="">-- Select Relationship --</option>
                    <option value="Parent">Parent</option>
                    <option value="Spouse / Partner">Spouse / Partner</option>
                    <option value="Sibling">Sibling</option>
                    <option value="Close Friend">Close Friend</option>
                    <option value="Neighbor">Neighbor</option>
                    <option value="Guardian">Guardian</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label class="form-label" for="phone">Phone Number (with Country Code) *</label>
                <input type="tel" id="phone" name="phone" class="form-control" required placeholder="+91 98765 43210" style="width: 100%;">
                <small style="color: var(--text-muted); font-size: 0.78rem;">Must be capable of receiving emergency SMS or calls.</small>
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.9rem;">
                    <input type="checkbox" name="is_primary" value="1" style="width: 16px; height: 16px;">
                    <span>Set as Primary Contact (Priority alert recipient)</span>
                </label>
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">
                <i class="fa-solid fa-shield-plus"></i> Save Emergency Contact
            </button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

