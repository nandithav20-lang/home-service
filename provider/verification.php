<?php
$pageTitle = "Provider Identity & Trade Verification";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/safety_functions.php';
requireRole('provider');

$pdo = getDB();
$userId = (int)$_SESSION['user_id'];
$csrfToken = getCsrfToken();

// Fetch provider profile
$pStmt = $pdo->prepare("SELECT p.*, u.name, u.email, u.phone, u.city, c.name as category_name
                       FROM providers p
                       JOIN users u ON p.user_id = u.id
                       JOIN service_categories c ON p.category_id = c.id
                       WHERE p.user_id = ? LIMIT 1");
$pStmt->execute([$userId]);
$provider = $pStmt->fetch();

if (!$provider) {
    setFlash('danger', 'Provider record not found.');
    header("Location: dashboard.php");
    exit;
}

$providerId = (int)$provider['id'];

// Fetch or create verification record
$vStmt = $pdo->prepare("SELECT * FROM provider_verifications WHERE provider_id = ? LIMIT 1");
$vStmt->execute([$providerId]);
$verification = $vStmt->fetch();

// Current effective status
$status = $verification['status'] ?? $provider['status'] ?? 'pending';

// Handle submission / update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($submittedToken)) {
        setFlash('danger', 'Security validation failed.');
        header("Location: verification.php");
        exit;
    }

    $idType = trim($_POST['id_type'] ?? 'Government National ID');
    $idNumber = trim($_POST['id_number'] ?? '');
    $notes = trim($_POST['verification_notes'] ?? '');

    // Mask ID number for privacy (e.g. XXXX-XXXX-1234)
    $masked = strlen($idNumber) > 4 
        ? str_repeat('*', max(0, strlen($idNumber) - 4)) . substr($idNumber, -4) 
        : $idNumber;

    if ($verification) {
        $uStmt = $pdo->prepare("UPDATE provider_verifications 
                               SET id_type = ?, id_number_masked = ?, verification_notes = ?, status = 'pending', updated_at = NOW() 
                               WHERE provider_id = ?");
        $uStmt->execute([$idType, $masked, $notes, $providerId]);
    } else {
        $iStmt = $pdo->prepare("INSERT INTO provider_verifications 
                               (provider_id, id_type, id_number_masked, verification_notes, status) 
                               VALUES (?, ?, ?, ?, 'pending')");
        $iStmt->execute([$providerId, $idType, $masked, $notes]);
    }

    // Set provider status to pending
    $pdo->prepare("UPDATE providers SET status = 'pending' WHERE id = ?")->execute([$providerId]);

    logSafetyAudit($userId, 'submit_verification_docs', 'provider', $providerId, "Provider submitted ID credentials for review ({$idType})");

    setFlash('success', 'Verification documents submitted! Platform administrators will review your profile shortly.');
    header("Location: verification.php");
    exit;
}

$pageGreeting = "Identity & Trade Verification";
$pageSubtitle = "Review your professional badge status and submit compliance credentials";

require_once __DIR__ . '/../includes/header.php';
?>
<!-- Safety Styles -->
<link rel="stylesheet" href="../assets/css/safety.css">

<div style="max-width: 800px; margin: 1.5rem auto;">
    <div style="display: flex; justify-content: flex-end; margin-bottom: 1.25rem;">
        <a href="dashboard.php" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>

    <!-- Status Banner Card -->
    <div class="card" style="padding: 2rem; margin-bottom: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem; border-bottom: 1px solid var(--border-light); padding-bottom: 1.25rem; margin-bottom: 1.25rem;">
            <div>
                <h3 style="margin: 0 0 4px 0; font-size: 1.3rem; color: var(--text-dark);">Verification Status</h3>
                <p style="margin: 0; color: var(--text-muted); font-size: 0.88rem;">Professional ID & Background Check Status</p>
            </div>
            <div>
                <?php echo renderVerifiedBadge($status); ?>
            </div>
        </div>

        <?php if ($status === 'approved'): ?>
            <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 1.25rem; display: flex; gap: 12px; align-items: flex-start;">
                <i class="fa-solid fa-circle-check" style="color: #059669; font-size: 1.5rem; margin-top: 2px;"></i>
                <div>
                    <h4 style="margin: 0 0 4px 0; color: #065f46;">Account Fully Verified & Active</h4>
                    <p style="margin: 0; font-size: 0.88rem; color: #047857; line-height: 1.5;">
                        Your credentials have been authenticated. You are eligible to accept new customer booking dispatches, receive SafeVisit arrival codes, and display the Verified Pro badge.
                    </p>
                </div>
            </div>
        <?php elseif ($status === 'suspended'): ?>
            <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 1.25rem; display: flex; gap: 12px; align-items: flex-start;">
                <i class="fa-solid fa-ban" style="color: #dc2626; font-size: 1.5rem; margin-top: 2px;"></i>
                <div>
                    <h4 style="margin: 0 0 4px 0; color: #991b1b;">Account Temporarily Suspended</h4>
                    <p style="margin: 0; font-size: 0.88rem; color: #b91c1c; line-height: 1.5;">
                        Your provider privileges have been suspended by administration. You cannot accept new job bookings at this time. Please contact support@homeservice.com.
                    </p>
                </div>
            </div>
        <?php elseif ($status === 'rejected'): ?>
            <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 1.25rem; display: flex; gap: 12px; align-items: flex-start;">
                <i class="fa-solid fa-circle-exclamation" style="color: #d97706; font-size: 1.5rem; margin-top: 2px;"></i>
                <div>
                    <h4 style="margin: 0 0 4px 0; color: #92400e;">Verification Needs Attention</h4>
                    <p style="margin: 0; font-size: 0.88rem; color: #b45309; line-height: 1.5;">
                        Your previous verification submission could not be approved. Please review and update your official government ID details below to request re-examination.
                    </p>
                </div>
            </div>
        <?php else: ?>
            <div style="background: #f0f9ff; border: 1px solid #bae6fd; border-radius: 8px; padding: 1.25rem; display: flex; gap: 12px; align-items: flex-start;">
                <i class="fa-regular fa-clock" style="color: #0284c7; font-size: 1.5rem; margin-top: 2px;"></i>
                <div>
                    <h4 style="margin: 0 0 4px 0; color: #0369a1;">Verification In Review</h4>
                    <p style="margin: 0; font-size: 0.88rem; color: #0284c7; line-height: 1.5;">
                        Your profile is under administrative review. Once approved, you will be able to accept customer jobs and participate in SafeVisit services.
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Credentials Submission Form -->
    <div class="card" style="padding: 2rem;">
        <h3 style="font-size: 1.2rem; margin: 0 0 0.5rem 0; color: var(--text-dark);">
            <i class="fa-solid fa-id-card" style="color: var(--safety-blue); margin-right: 6px;"></i>
            Submit / Update Government ID Credentials
        </h3>
        <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 1.5rem;">
            For platform customer safety, all service technicians must present valid government credentials.
        </p>

        <form method="POST" action="verification.php">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label class="form-label" for="id_type">Document Type *</label>
                <select id="id_type" name="id_type" class="form-control" required style="width: 100%;">
                    <option value="Aadhaar Card" <?php echo (($verification['id_type'] ?? '') === 'Aadhaar Card') ? 'selected' : ''; ?>>Aadhaar National Card</option>
                    <option value="Driving License" <?php echo (($verification['id_type'] ?? '') === 'Driving License') ? 'selected' : ''; ?>>Driving License</option>
                    <option value="PAN Card" <?php echo (($verification['id_type'] ?? '') === 'PAN Card') ? 'selected' : ''; ?>>Permanent Account Number (PAN)</option>
                    <option value="Passport" <?php echo (($verification['id_type'] ?? '') === 'Passport') ? 'selected' : ''; ?>>Government Passport</option>
                    <option value="Voter ID" <?php echo (($verification['id_type'] ?? '') === 'Voter ID') ? 'selected' : ''; ?>>Voter Identity Card</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label class="form-label" for="id_number">Document Identification Number *</label>
                <input type="text" id="id_number" name="id_number" class="form-control" required 
                       placeholder="<?php echo !empty($verification['id_number_masked']) ? 'Stored as ' . htmlspecialchars($verification['id_number_masked']) : 'e.g. 5432-8765-1234'; ?>" style="width: 100%;">
                <small style="color: var(--text-muted); font-size: 0.78rem;">Number is securely masked after storage. Admin will review during approval.</small>
            </div>

            <div class="form-group" style="margin-bottom: 1.5rem;">
                <label class="form-label" for="verification_notes">Experience or Professional Certification Details</label>
                <textarea id="verification_notes" name="verification_notes" class="form-control" rows="3" 
                          placeholder="e.g. 5+ years licensed plumbing experience, government ITI certification..." style="width: 100%;"><?php echo htmlspecialchars($verification['verification_notes'] ?? ''); ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary" <?php echo ($status === 'suspended') ? 'disabled' : ''; ?>>
                <i class="fa-solid fa-cloud-arrow-up"></i> Submit Documents for Verification
            </button>
        </form>

        <div class="safety-disclaimer-box" style="margin-top: 1.5rem;">
            <strong>Compliance Notice:</strong> <?php echo getSafetyDisclaimer(); ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

