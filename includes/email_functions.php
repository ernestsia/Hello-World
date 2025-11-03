<?php
/**
 * Send password change notification email
 * @param string $to Recipient email
 * @param string $username Username of the account
 * @param string $userType Type of user (admin, teacher, student, etc.)
 * @param string $ipAddress IP address where the change was made
 * @return bool True if email was sent successfully, false otherwise
 */
function sendPasswordChangeNotification($to, $username, $userType, $ipAddress) {
    $subject = 'Password Changed - ' . APP_NAME;
    
    // Determine the appropriate login URL based on user type
    $loginUrl = APP_URL . '/login.php';
    $portalName = 'Portal';
    
    switch (strtolower($userType)) {
        case 'admin':
            $loginUrl = APP_URL . '/admin/';
            $portalName = 'Admin Portal';
            break;
        case 'teacher':
            $loginUrl = APP_URL . '/teachers/';
            $portalName = 'Teacher Portal';
            break;
        case 'student':
            $loginUrl = APP_URL . '/students/';
            $portalName = 'Student Portal';
            break;
        case 'parent':
            $loginUrl = APP_URL . '/parents/';
            $portalName = 'Parent Portal';
            break;
    }
    
    $message = "
    <html>
    <head>
        <title>$subject</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #4a6fa5; color: white; padding: 15px; text-align: center; }
            .content { padding: 20px; background-color: #f9f9f9; }
            .button {
                display: inline-block; 
                padding: 10px 20px; 
                background-color: #4a6fa5; 
                color: white; 
                text-decoration: none; 
                border-radius: 4px;
                margin: 15px 0;
            }
            .footer { 
                margin-top: 20px; 
                font-size: 12px; 
                color: #777; 
                text-align: center;
                border-top: 1px solid #eee;
                padding-top: 15px;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Password Changed Successfully</h2>
            </div>
            <div class='content'>
                <p>Hello $username,</p>
                <p>Your password was successfully changed on " . date('F j, Y \a\t g:i A') . " from IP address: $ipAddress.</p>
                
                <p>If you did not make this change, please contact the system administrator immediately.</p>
                
                <p>You can now log in to your account using your new password:</p>
                <div style='text-align: center;'>
                    <a href='$loginUrl' class='button'>Go to $portalName</a>
                </div>
                
                <p>Or copy and paste this link into your browser:<br>
                <a href='$loginUrl'>$loginUrl</a></p>
                
                <p>For security reasons, please do not share this email with anyone.</p>
            </div>
            <div class='footer'>
                <p>This is an automated message. Please do not reply to this email.</p>
                <p>© " . date('Y') . ' ' . APP_NAME . ". All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    // Set content-type header for sending HTML email
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM_ADDRESS . '>' . "\r\n";
    $headers .= 'Reply-To: ' . MAIL_REPLY_TO . "\r\n";
    $headers .= 'X-Mailer: PHP/' . phpversion();

    // Send email
    return mail($to, $subject, $message, $headers);
}

/**
 * Get the user's role name from user ID
 * @param mysqli $conn Database connection
 * @param int $userId User ID
 * @return string User role name
 */
function getUserRoleName($conn, $userId) {
    $stmt = $conn->prepare("
        SELECT r.role_name 
        FROM users u 
        JOIN roles r ON u.role_id = r.role_id 
        WHERE u.user_id = ?
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $role = $result->fetch_assoc();
    $stmt->close();
    
    return $role ? strtolower($role['role_name']) : 'user';
}

/**
 * Get the user's email by user ID
 * @param mysqli $conn Database connection
 * @param int $userId User ID
 * @return string User's email address
 */
function getUserEmail($conn, $userId) {
    $stmt = $conn->prepare("SELECT email FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    return $user ? $user['email'] : '';
}

/**
 * Get the user's full name by user ID
 * @param mysqli $conn Database connection
 * @param int $userId User ID
 * @return string User's full name
 */
function getUserFullNameById($conn, $userId) {
    $stmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    return $user ? trim($user['first_name'] . ' ' . $user['last_name']) : 'User';
}

/**
 * Send welcome email with login credentials
 * @param string $to Recipient email
 * @param string $username Username for login
 * @param string $password Plain text password (will be shown only in this email)
 * @param string $userType Type of user (admin, teacher, student, etc.)
 * @return bool True if email was sent successfully, false otherwise
 */
function sendWelcomeEmail($to, $username, $password, $userType) {
    $subject = 'Welcome to ' . APP_NAME . ' - Your Account Details';
    
    // Determine the appropriate login URL based on user type
    $loginUrl = APP_URL . '/login.php';
    $portalName = 'Portal';
    
    switch (strtolower($userType)) {
        case 'admin':
            $loginUrl = APP_URL . '/admin/';
            $portalName = 'Admin Portal';
            break;
        case 'teacher':
            $loginUrl = APP_URL . '/teachers/';
            $portalName = 'Teacher Portal';
            break;
        case 'student':
            $loginUrl = APP_URL . '/students/';
            $portalName = 'Student Portal';
            break;
        case 'parent':
            $loginUrl = APP_URL . '/parents/';
            $portalName = 'Parent Portal';
            break;
    }
    
    $message = "
    <html>
    <head>
        <title>$subject</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #4a6fa5; color: white; padding: 15px; text-align: center; }
            .content { padding: 20px; background-color: #f9f9f9; }
            .credentials { 
                background-color: #f0f0f0; 
                padding: 15px; 
                border-left: 4px solid #4a6fa5;
                margin: 15px 0;
            }
            .button {
                display: inline-block; 
                padding: 10px 20px; 
                background-color: #4a6fa5; 
                color: white; 
                text-decoration: none; 
                border-radius: 4px;
                margin: 15px 0;
            }
            .footer { 
                margin-top: 20px; 
                font-size: 12px; 
                color: #777; 
                text-align: center;
                border-top: 1px solid #eee;
                padding-top: 15px;
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Welcome to " . APP_NAME . "</h2>
            </div>
            <div class='content'>
                <p>Hello,</p>
                <p>Your account has been created successfully. Below are your login credentials:</p>
                
                <div class='credentials'>
                    <p><strong>Portal:</strong> $portalName</p>
                    <p><strong>Login URL:</strong> <a href='$loginUrl'>$loginUrl</a></p>
                    <p><strong>Username:</strong> $username</p>
                    <p><strong>Password:</strong> $password</p>
                </div>
                
                <p>For security reasons, we recommend that you change your password after your first login.</p>
                
                <div style='text-align: center;'>
                    <a href='$loginUrl' class='button'>Go to $portalName</a>
                </div>
                
                <p>If you have any questions or need assistance, please contact our support team.</p>
            </div>
            <div class='footer'>
                <p>This is an automated message. Please do not reply to this email.</p>
                <p>© " . date('Y') . ' ' . APP_NAME . ". All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";

    // Set content-type header for sending HTML email
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM_ADDRESS . '>' . "\r\n";
    $headers .= 'Reply-To: ' . MAIL_REPLY_TO . "\r\n";
    $headers .= 'X-Mailer: PHP/' . phpversion();

    // Send email
    return mail($to, $subject, $message, $headers);
}
