<?php
/**
 * Admin Password Reset Utility
 * This script resets the admin password to: admin123
 * DELETE THIS FILE AFTER USE!
 */

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'school_management';

// Connect to database
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Generate new password hash for "admin123"
$new_password = 'admin123';
$hashed_password = password_hash($new_password, PASSWORD_BCRYPT);

// Update admin password
$sql = "UPDATE users SET password = ? WHERE username = 'admin'";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $hashed_password);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Admin Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .reset-card {
            max-width: 500px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 30px;
        }
    </style>
</head>
<body>
    <div class="reset-card">
        <h3 class="text-center mb-4"><i class="fas fa-key"></i> Admin Password Reset</h3>
        
        <?php
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo '<div class="alert alert-success">
                    <h5>✓ Password Reset Successful!</h5>
                    <hr>
                    <p><strong>Username:</strong> admin</p>
                    <p><strong>Password:</strong> admin123</p>
                    <hr>
                    <p class="mb-0">You can now login with these credentials.</p>
                </div>
                <div class="text-center">
                    <a href="login.php" class="btn btn-primary">Go to Login</a>
                </div>
                <div class="alert alert-warning mt-3">
                    <strong>⚠ Important:</strong> Delete this file (reset-admin-password.php) immediately for security!
                </div>';
            } else {
                echo '<div class="alert alert-danger">
                    <strong>Error:</strong> Admin user not found in database. Please run install.php first.
                </div>
                <div class="text-center">
                    <a href="install.php" class="btn btn-primary">Run Installation</a>
                </div>';
            }
        } else {
            echo '<div class="alert alert-danger">
                <strong>Error:</strong> ' . $stmt->error . '
            </div>';
        }
        
        $stmt->close();
        $conn->close();
        ?>
    </div>
</body>
</html>
