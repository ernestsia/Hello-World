<?php
/**
 * Check if the password meets the complexity requirements
 * @param string $password Password to check
 * @return array Array of error messages, empty if valid
 */
function validatePasswordComplexity($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters long';
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter (A-Z)';
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter (a-z)';
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number (0-9)';
    }
    
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Password must contain at least one special character (!@#$%^&*)';
    }
    
    return $errors;
}

/**
 * Check if the password has been used before
 * @param int $userId User ID
 * @param string $passwordHash Hashed password to check
 * @param Database $db Database connection
 * @param int $historyCount Number of previous passwords to check (0 = no check)
 * @return bool True if password is unique in history, false otherwise
 */
function isPasswordInHistory($userId, $passwordHash, $db, $historyCount = 5) {
    if ($historyCount <= 0) {
        return false;
    }
    
    $conn = $db->getConnection();
    $stmt = $conn->prepare("
        SELECT password_hash 
        FROM password_history 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT ?
    ");
    $stmt->bind_param("ii", $userId, $historyCount);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    while ($row = $result->fetch_assoc()) {
        if (password_verify($passwordHash, $row['password_hash'])) {
            return true;
        }
    }
    
    return false;
}

/**
 * Add a password to the user's password history
 * @param int $userId User ID
 * @param string $passwordHash Hashed password to add
 * @param Database $db Database connection
 * @return bool True on success, false on failure
 */
function addToPasswordHistory($userId, $passwordHash, $db) {
    $conn = $db->getConnection();
    $stmt = $conn->prepare("INSERT INTO password_history (user_id, password_hash) VALUES (?, ?)");
    $stmt->bind_param("is", $userId, $passwordHash);
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * Check if the password is different from the current one
 * @param int $userId User ID
 * @param string $password New password (plain text)
 * @param Database $db Database connection
 * @return bool True if different, false if same as current
 */
function isPasswordDifferent($userId, $password, $db) {
    $conn = $db->getConnection();
    $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    return !password_verify($password, $user['password']);
}

/**
 * Check if the password meets all policies
 * @param int $userId User ID
 * @param string $password New password (plain text)
 * @param Database $db Database connection
 * @return array Array of error messages, empty if valid
 */
function validatePasswordPolicy($userId, $password, $db) {
    $errors = validatePasswordComplexity($password);
    
    if (empty($errors)) {
        // Check if password is different from current
        if (!isPasswordDifferent($userId, $password, $db)) {
            $errors[] = 'New password must be different from current password';
        }
        
        // Check if password is in history (last 5 passwords)
        if (isPasswordInHistory($userId, $password, $db, 5)) {
            $errors[] = 'You have used this password recently. Please choose a different one.';
        }
    }
    
    return $errors;
}
