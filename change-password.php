<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/session.php';
require_once 'includes/email_functions.php';
require_once 'includes/two_factor_auth.php';
require_once 'includes/password_policy.php';

// Load Composer autoloader if it exists
$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

requireLogin();

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$pageTitle = 'Change Password';
$db = new Database();
$conn = $db->getConnection();

$errors = [];
$success = false;

// Rate limiting for password change attempts
$maxAttempts = 5;
$lockoutTime = 300; // 5 minutes

// Check if user is rate limited
if (isset($_SESSION['password_attempts']) && 
    $_SESSION['password_attempts'] >= $maxAttempts && 
    (time() - $_SESSION['last_attempt_time']) < $lockoutTime) {
    $timeLeft = $lockoutTime - (time() - $_SESSION['last_attempt_time']);
    $errors[] = "Too many attempts. Please try again in " . ceil($timeLeft / 60) . " minutes.";
} else {
    // Reset attempt counter if lockout time has passed
    if (isset($_SESSION['password_attempts']) && (time() - $_SESSION['last_attempt_time']) >= $lockoutTime) {
        $_SESSION['password_attempts'] = 0;
    }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid request. Please try again.';
        $_SESSION['password_attempts'] = isset($_SESSION['password_attempts']) ? $_SESSION['password_attempts'] + 1 : 1;
        $_SESSION['last_attempt_time'] = time();
    } else {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Validate current password
        if (empty($current_password)) {
            $errors[] = 'Current password is required';
        }
        
        // Validate new password
        if (empty($new_password)) {
            $errors[] = 'New password is required';
        } else {
            // Check password complexity
            $complexityErrors = validatePasswordPolicy($_SESSION['user_id'], $new_password, $db);
            $errors = array_merge($errors, $complexityErrors);
        }
        
        // Check password confirmation
        if ($new_password !== $confirm_password) {
            $errors[] = 'New passwords do not match';
        }
        
        if (empty($errors)) {
            // Verify current password
            $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
            $stmt->bind_param("i", $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            $stmt->close();
            
            if (!verifyPassword($current_password, $user['password'])) {
                $errors[] = 'Current password is incorrect';
            } else {
                // Update password
                $hashed_password = hashPassword($new_password);
                $conn->begin_transaction();
                
                try {
                    // Update user's password
                    $stmt = $conn->prepare("UPDATE users SET password = ?, last_password_change = NOW() WHERE user_id = ?");
                    $stmt->bind_param("si", $hashed_password, $_SESSION['user_id']);
                    
                    if ($stmt->execute()) {
                        // Add to password history
                        addToPasswordHistory($_SESSION['user_id'], $hashed_password, $db);
                        
                        // Commit transaction
                        $conn->commit();
                        
                        // Regenerate session ID to prevent session fixation
                        session_regenerate_id(true);
                        
                        // Update session token
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                        
                        // Reset attempt counter on success
                        unset($_SESSION['password_attempts']);
                        unset($_SESSION['last_attempt_time']);
                        
                        $success = true;
                        setFlashMessage('success', 'Password changed successfully!');
                        
                        // Send email notification
                        $userEmail = getUserEmail($conn, $_SESSION['user_id']);
                        $username = getUserFullNameById($conn, $_SESSION['user_id']);
                        $userRole = getUserRoleName($conn, $_SESSION['user_id']);
                        $ipAddress = $_SERVER['REMOTE_ADDR'];
                        
                        if (!empty($userEmail)) {
                            sendPasswordChangeNotification(
                                $userEmail,
                                $username,
                                $userRole,
                                $ipAddress
                            );
                        }
                        
                        // Check if 2FA setup is required
                        if (is2FASetupRequired($_SESSION['user_id'], $db)) {
                            $_SESSION['pending_2fa_setup'] = true;
                            header('Location: setup-2fa.php');
                            exit();
                        }
                    } else {
                        throw new Exception('Failed to update password');
                    }
                } catch (Exception $e) {
                    $conn->rollback();
                    $errors[] = 'An error occurred while updating your password. Please try again.';
                    error_log('Password update error: ' . $e->getMessage());
                }
            }
            $stmt->close();
        } else {
            // Increment attempt counter on validation errors
            $_SESSION['password_attempts'] = isset($_SESSION['password_attempts']) ? $_SESSION['password_attempts'] + 1 : 1;
            $_SESSION['last_attempt_time'] = time();
        }
        $_SESSION['last_attempt_time'] = time();
    }
}

include 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-key"></i> Change Password</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Change Password</li>
            </ol>
        </nav>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <strong>Error!</strong>
    <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
        <li><?php echo $error; ?></li>
        <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="fas fa-check-circle"></i> Password changed successfully!
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Change Your Password</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="new_password" name="new_password" required 
                                   pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[^a-zA-Z0-9]).{8,}"
                                   title="Must be at least 8 characters long and include: uppercase, lowercase, number, and special character">
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="new_password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">
                            <small>Password must be at least 8 characters long and include:</small>
                            <ul class="small text-muted mb-0">
                                <li>One uppercase letter (A-Z)</li>
                                <li>One lowercase letter (a-z)</li>
                                <li>One number (0-9)</li>
                                <li>One special character (!@#$%^&*)</li>
                            </ul>
                            <div id="password-strength" class="mt-2">
                                <div class="progress" style="height: 5px;">
                                    <div class="progress-bar" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <small id="strength-text" class="form-text"></small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
// Toggle password visibility
document.addEventListener('DOMContentLoaded', function() {
    // Toggle password visibility
    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            const icon = this.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
    });

    // Password strength meter
    const passwordInput = document.getElementById('new_password');
    const strengthMeter = document.getElementById('password-strength');
    const strengthBar = strengthMeter.querySelector('.progress-bar');
    const strengthText = document.getElementById('strength-text');

    if (passwordInput) {
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            const strength = calculatePasswordStrength(password);
            updateStrengthMeter(strength);
        });
    }

    function calculatePasswordStrength(password) {
        let strength = 0;
        
        // Length check (max 2 points)
        if (password.length >= 8) strength += 1;
        if (password.length >= 12) strength += 1;
        
        // Character type checks (1 point each)
        if (/[A-Z]/.test(password)) strength += 1; // Uppercase
        if (/[a-z]/.test(password)) strength += 1; // Lowercase
        if (/[0-9]/.test(password)) strength += 1; // Numbers
        if (/[^A-Za-z0-9]/.test(password)) strength += 1; // Special chars
        
        // Deduct for common patterns
        if (/(.)\1{2,}/.test(password)) strength -= 1; // Repeated chars
        if (/(123|abc|password|qwerty)/i.test(password)) strength -= 2; // Common patterns
        
        // Ensure strength is between 0 and 5
        return Math.max(0, Math.min(5, strength));
    }

    function updateStrengthMeter(strength) {
        const strengthPercent = (strength / 5) * 100;
        let strengthClass = 'bg-danger';
        let strengthLabel = 'Very Weak';
        
        if (strength >= 2 && strength < 3) {
            strengthClass = 'bg-warning';
            strengthLabel = 'Weak';
        } else if (strength >= 3 && strength < 4) {
            strengthClass = 'bg-info';
            strengthLabel = 'Good';
        } else if (strength >= 4) {
            strengthClass = 'bg-success';
            strengthLabel = 'Strong';
        }
        
        strengthBar.style.width = strengthPercent + '%';
        strengthBar.className = 'progress-bar ' + strengthClass;
        strengthBar.setAttribute('aria-valuenow', strengthPercent);
        strengthText.textContent = 'Strength: ' + strengthLabel;
    }
});
</script>
