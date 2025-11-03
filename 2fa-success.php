<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/session.php';

requireLogin();

// Check if backup codes should be shown
if (!isset($_SESSION['show_backup_codes']) || !$_SESSION['show_backup_codes']) {
    header('Location: index.php');
    exit();
}

$pageTitle = '2FA Setup Complete';
$db = new Database();
$conn = $db->getConnection();

// Get the backup codes from session
$backupCodes = $_SESSION['backup_codes'] ?? [];

// Clear the session after displaying the codes
unset($_SESSION['show_backup_codes']);
unset($_SESSION['backup_codes']);

include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-check-circle"></i> Two-Factor Authentication Enabled</h5>
            </div>
            <div class="card-body">
                <div class="text-center mb-4">
                    <div class="mb-3">
                        <i class="fas fa-shield-alt fa-5x text-success mb-3"></i>
                    </div>
                    <h3>Two-Factor Authentication is Now Active</h3>
                    <p class="lead">Your account is now more secure with two-factor authentication enabled.</p>
                </div>
                
                <div class="alert alert-warning">
                    <h5><i class="fas fa-exclamation-triangle"></i> Save Your Backup Codes</h5>
                    <p class="mb-2">These backup codes can be used to access your account if you lose access to your authenticator app. Each code can only be used once.</p>
                    <p class="mb-0"><strong>Save these codes in a safe place!</strong> You won't be able to see them again.</p>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">Your Backup Codes</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php foreach (array_chunk($backupCodes, 4) as $codeGroup): ?>
                                <div class="col-md-6">
                                    <div class="list-group">
                                        <?php foreach ($codeGroup as $code): ?>
                                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                                <code class="font-monospace"><?php echo htmlspecialchars($code); ?></code>
                                                <span class="badge bg-success">Unused</span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="d-print-none">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <button onclick="window.print()" class="btn btn-outline-primary w-100">
                                <i class="fas fa-print"></i> Print Backup Codes
                            </button>
                        </div>
                        <div class="col-md-6 mb-3">
                            <a href="download-backup-codes.php" class="btn btn-outline-primary w-100">
                                <i class="fas fa-download"></i> Download as Text File
                            </a>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <a href="index.php" class="btn btn-success">
                            <i class="fas fa-check"></i> I've Saved My Backup Codes
                        </a>
                    </div>
                </div>
                
                <div class="mt-4 pt-3 border-top">
                    <h5><i class="fas fa-question-circle"></i> Need Help?</h5>
                    <p>If you have any questions about two-factor authentication or need assistance, please contact our support team.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Disable right-click to prevent saving the page
// Note: This is not a security measure, just a gentle reminder
window.oncontextmenu = function() {
    return false;
};

// Show print dialog when the page loads
// window.onload = function() {
//     window.print();
// };
</script>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    .card, .card * {
        visibility: visible;
    }
    .card {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        border: none;
    }
    .d-print-none, .d-print-none * {
        display: none !important;
    }
    .card-header {
        background-color: #198754 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .alert-warning {
        border: 1px solid #000 !important;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
