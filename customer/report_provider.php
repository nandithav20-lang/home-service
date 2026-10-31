<?php
$pageTitle = "Confidential Provider Incident Report";
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/safety_functions.php';
requireRole('customer');

$pdo = getDB();
$userId = (int)$_SESSION['user_id'];
$csrfToken = getCsrfToken();
$errors = [];

// Preselected provider or booking from query param
$preselectedProviderId = (int)($_GET['provider_id'] ?? 0);
$preselectedBookingId = (int)($_GET['booking_id'] ?? 0);

// Handle Report Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($submittedToken)) {
        setFlash('danger', 'Security validation failed.');
        header("Location: report_provider.php");
        exit;
    }

    $providerId = (int)($_POST['provider_id'] ?? 0);
    $bookingId = !empty($_POST['booking_id']) ? (int)$_POST['booking_id'] : null;
    $category = $_POST['category'] ?? '';
    $details = trim($_POST['details'] ?? '');
    $incidentDate = $_POST['incident_date'] ?? date('Y-m-d');
    $blockProvider = isset($_POST['block_provider']) ? true : false;

    if ($providerId <= 0) {
        $errors[] = "Please select the service provider involved.";
    }

    try {
        if (empty($errors)) {
            $reportId = reportProviderConfidential($userId, $providerId, $bookingId, $category, $details, $incidentDate, $blockProvider);
            $blockMsg = $blockProvider ? " The technician has been blocked from any future bookings on your account." : "";
            setFlash('success', "Your report (#RPT-{$reportId}) has been confidentially submitted directly to the Safety & Security Team.{$blockMsg}");
            header("Location: dashboard.php");
            exit;
        }
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

// Fetch list of providers who serviced this customer
$providersHistory = [];
try {
    $stmt = $pdo->prepare("SELECT DISTINCT p.id as provider_id, u.name as provider_name, u.phone as provider_phone,
                                  c.name as category_name, b.id as booking_id, b.booking_date, s.name as service_name
                           FROM bookings b
                           JOIN providers p ON b.provider_id = p.id
                           JOIN users u ON p.user_id = u.id
                           JOIN services s ON b.service_id = s.id
                           JOIN service_categories c ON s.category_id = c.id
                           WHERE b.customer_id = ?
                           ORDER BY b.booking_date DESC");
    $stmt->execute([$userId]);
    $providersHistory = $stmt->fetchAll();
} catch (Exception $e) {
    $providersHistory = [];
}

$pageGreeting = "Confidential Safety Reporting";
$pageSubtitle = "Submit private safety concerns directly to our platform security team";

require_once __DIR__ . '/../includes/header.php';
?>
<!-- Safety Styles -->
<link rel="stylesheet" href="../assets/css/safety.css">

<div style="max-width: 720px; margin: 1.5rem auto;">
    <div style="display: flex; justify-content: flex-end; margin-bottom: 1rem;">
        <a href="safety.php" class="btn btn-secondary btn-sm">
            <i class="fa-solid fa-arrow-left"></i> Back to Safety Hub
        </a>
    </div>

    <div class="card" style="padding: 2.25rem;">
        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 1.5rem;">
            <div style="width: 44px; height: 44px; border-radius: 10px; background: #fee2e2; color: #dc2626; display: flex; align-items: center; justify-content: center; font-size: 1.3rem;">
                <i class="fa-solid fa-flag"></i>
            </div>
            <div>
                <h2 style="font-size: 1.45rem; margin: 0; color: var(--text-dark);">File a Provider Safety Report</h2>
                <p style="margin: 3px 0 0; color: var(--text-muted); font-size: 0.88rem;">
                    🔒 <strong>Strictly Confidential:</strong> This report is never disclosed to the provider or technician.
                </p>
            </div>
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

        <form method="POST" action="report_provider.php">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label class="form-label" for="provider_id">Service Provider Involved *</label>
                <select id="provider_id" name="provider_id" class="form-control" required style="width: 100%;">
                    <option value="">-- Choose Provider from Your Bookings --</option>
                    <?php foreach ($providersHistory as $ph): ?>
                        <option value="<?php echo $ph['provider_id']; ?>" <?php echo ($preselectedProviderId == $ph['provider_id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($ph['provider_name']); ?> (<?php echo htmlspecialchars($ph['category_name']); ?>) - Booking #BK-<?php echo str_pad($ph['booking_id'], 4, '0', STR_PAD_LEFT); ?> (<?php echo date('d M Y', strtotime($ph['booking_date'])); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <small style="color: var(--text-muted); font-size: 0.8rem;">Select the technician who performed or was assigned to your service.</small>
            </div>

            <?php if ($preselectedBookingId > 0): ?>
                <input type="hidden" name="booking_id" value="<?php echo $preselectedBookingId; ?>">
            <?php endif; ?>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label class="form-label" for="category">Concern Category *</label>
                <select id="category" name="category" class="form-control" required style="width: 100%;">
                    <option value="">-- Select Nature of Safety Issue --</option>
                    <option value="harassment">Harassment (Verbal, text, or inappropriate conduct)</option>
                    <option value="threats">Threats or Intimidation</option>
                    <option value="physical_danger">Physical Danger or Assault</option>
                    <option value="suspicious_behaviour">Suspicious / Unauthorized Behavior in Property</option>
                    <option value="fraud">Fraud, Theft, or Extortion</option>
                    <option value="other">Other Serious Safety Issue</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label class="form-label" for="incident_date">Date of Occurrence *</label>
                <input type="date" id="incident_date" name="incident_date" class="form-control" required value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>" style="width: 100%;">
            </div>

            <div class="form-group" style="margin-bottom: 1.25rem;">
                <label class="form-label" for="details">Detailed Narrative of the Incident *</label>
                <textarea id="details" name="details" class="form-control" rows="5" required placeholder="Please describe exactly what transpired, including any statements made or unsafe behaviors observed..." style="width: 100%;"></textarea>
                <small style="color: var(--text-muted); font-size: 0.78rem;">Provide clear, factual specifics. Platform admin investigators review every report.</small>
            </div>

            <div class="form-group" style="margin-bottom: 1.75rem; background: var(--bg-muted); border: 1px solid var(--border-subtle); padding: 1rem; border-radius: 8px;">
                <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">
                    <input type="checkbox" name="block_provider" value="1" checked style="width: 18px; height: 18px; margin-top: 2px;">
                    <div>
                        <strong style="color: var(--text-dark); display: block; font-size: 0.95rem;">Block this Provider Permanently</strong>
                        <span style="font-size: 0.82rem; color: var(--text-muted);">
                            Prevent this technician from ever being assigned to any of your future home service bookings.
                        </span>
                    </div>
                </label>
            </div>

            <button type="submit" class="btn btn-primary" style="background: #dc2626; border-color: #dc2626; width: 100%;">
                <i class="fa-solid fa-shield-exclamation"></i> Submit Confidential Safety Report
            </button>
        </form>

        <div class="safety-disclaimer-box" style="margin-top: 1.5rem;">
            <strong>Emergency Advisory:</strong> If you are in immediate physical danger or suspect a crime in progress, please call <a href="tel:112" style="color: #dc2626; font-weight: 700;">National Emergency 112</a> right away.
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

