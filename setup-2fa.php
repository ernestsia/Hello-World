<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/session.php';
require_once 'includes/two_factor_auth.php';
require_once 'includes/email_functions.php';

// Load Composer autoloader if it exists
$composerAutoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

requireLogin();

// Check if 2FA setup is required
if (!isset($_SESSION['pending_2fa_setup'])) {
    header('Location: index.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();
$pageTitle = 'Set Up Two-Factor Authentication';
$success = false;
$errors = [];

// Generate a new 2FA secret if not already set
if (!isset($_SESSION['2fa_secret'])) {
    $user = getUserById($conn, $_SESSION['user_id']);
    $secret = generate2FASecret($user['username']);
    $_SESSION['2fa_secret'] = $secret['secret'];
    $_SESSION['2fa_qr_code'] = $secret['qr_code_url'];
    $_SESSION['2fa_manual_code'] = $secret['manual_code'];
    
    // Generate backup codes
    $backupCodes = generateBackupCodes();
    $_SESSION['2fa_backup_codes'] = $backupCodes;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['verification_code']) || empty(trim($_POST['verification_code']))) {
        $errors[] = 'Please enter the verification code from your authenticator app';
    } else {
        $verificationCode = trim($_POST['verification_code']);
        
        // Verify the code
        if (verify2FACode($_SESSION['2fa_secret'], $verificationCode)) {
            // Save 2FA settings to database
            $stmt = $conn->prepare("
                INSERT INTO user_2fa (user_id, secret, backup_codes, is_enabled) 
                VALUES (?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE 
                    secret = VALUES(secret), 
                    backup_codes = VALUES(backup_codes),
                    is_enabled = 1
            ");
            
            $backupCodesJson = json_encode($_SESSION['2fa_backup_codes']);
            $stmt->bind_param("iss", $_SESSION['user_id'], $_SESSION['2fa_secret'], $backupCodesJson);
            
            if ($stmt->execute()) {
                // Clear the session
                unset($_SESSION['2fa_secret']);
                unset($_SESSION['2fa_qr_code']);
                unset($_SESSION['2fa_manual_code']);
                unset($_SESSION['pending_2fa_setup']);
                
                // Store backup codes in session to display once
                $_SESSION['show_backup_codes'] = true;
                $_SESSION['backup_codes'] = $_SESSION['2fa_backup_codes'];
                unset($_SESSION['2fa_backup_codes']);
                
                // Send email notification
                $userEmail = getUserEmail($conn, $_SESSION['user_id']);
                $username = getUserFullNameById($conn, $_SESSION['user_id']);
                
                if (!empty($userEmail)) {
                    // Send email with backup codes
                    $subject = 'Your 2FA Backup Codes - ' . APP_NAME;
                    $message = "
                    <p>Hello $username,</p>
                    <p>Two-factor authentication has been successfully enabled for your account. Below are your backup codes. Please save them in a safe place as they won't be shown again.</p>
                    <div style='background-color: #f8f9fa; padding: 15px; border-left: 4px solid #4a6fa5; margin: 15px 0;'>
                        <strong>Backup Codes:</strong><br>
                        " . implode("<br>", $_SESSION['backup_codes']) . "
                    </div>
                    <p>Each code can only be used once. If you run out of backup codes, you can generate new ones from your security settings.</p>
                    <p>If you did not enable two-factor authentication, please contact support immediately.</p>
                    ";
                    
                    // Use the email sending function from email_functions.php
                    sendEmail(
                        $userEmail,
                        $username,
                        $subject,
                        $message
                    );
                }
                
                // Redirect to success page
                header('Location: 2fa-success.php');
                exit();
            } else {
                $errors[] = 'Failed to enable two-factor authentication. Please try again.';
            }
            
            $stmt->close();
        } else {
            $errors[] = 'Invalid verification code. Please try again.';
        }
    }
}

include 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Set Up Two-Factor Authentication</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <div class="row">
                    <div class="col-md-6">
                        <h5>Step 1: Set up your authenticator app</h5>
                        <p>Scan the QR code below with your authenticator app (like Google Authenticator or Authy):</p>
                        <div class="text-center mb-4">
                            <img src="<?php echo htmlspecialchars($_SESSION['2fa_qr_code']); ?>" alt="QR Code" class="img-fluid" style="max-width: 200px;">
                        </div>
                        
                        <p class="mt-3">Or enter this code manually:</p>
                        <div class="alert alert-secondary">
                            <code class="h4"><?php echo chunk_split($_SESSION['2fa_manual_code'], 4, ' '); ?></code>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            <strong>Note:</strong> Make sure your device's time is synchronized with the internet time for the codes to work properly.
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <h5>Step 2: Verify the code</h5>
                        <p>Enter the 6-digit code from your authenticator app:</p>
                        
                        <form method="post" class="mt-4">
                            <div class="mb-3">
                                <label for="verification_code" class="form-label">Verification Code</label>
                                <input type="text" class="form-control form-control-lg text-center" 
                                       id="verification_code" name="verification_code" 
                                       placeholder="123456" maxlength="6" required 
                                       autocomplete="one-time-code" inputmode="numeric"
                                       pattern="\d{6}" title="Please enter a 6-digit code">
                                <div class="form-text">Enter the 6-digit code from your authenticator app</div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-check-circle"></i> Verify and Enable 2FA
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="mt-4 pt-3 border-top">
                    <h5><i class="fas fa-shield-alt"></i> Security Tips</h5>
                    <ul>
                        <li>Use a dedicated authenticator app like Google Authenticator, Authy, or Microsoft Authenticator</li>
                        <li>Save your backup codes in a secure location</li>
                        <li>Do not share your 2FA codes with anyone</li>
                        <li>If you lose access to your authenticator app, you can use one of your backup codes</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
// Auto-advance to next input field
const inputs = document.querySelectorAll('input[type="text"][maxlength="6"]');
inputs.forEach((input, index) => {
    input.addEventListener('input', function(e) {
        if (this.value.length >= this.maxLength) {
            if (index < inputs.length - 1) {
                inputs[index + 1].focus();
            }
        }
    });
    
    // Allow only numbers
    input.addEventListener('keypress', function(e) {
        if (e.key < '0' || e.key > '9') {
            e.preventDefault();
        }
    });
});
</script>
