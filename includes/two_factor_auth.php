<?php
require_once __DIR__ . '/../vendor/autoload.php';
use OTPHP\TOTP;
use ParagonIE\ConstantTime\Base32;

/**
 * Generate a new 2FA secret for a user
 * @return array Secret and QR code URL
 */
function generate2FASecret($username) {
    $secret = Base32::encodeUpper(random_bytes(20));
    $otp = TOTP::create(
        $secret,
        30,                     // 30-second window
        'sha1',                 // SHA-1 is the standard for most authenticator apps
        6,                      // 6-digit tokens
        
    );
    
    $otp->setLabel(urlencode(APP_NAME) . ':' . urlencode($username));
    
    return [
        'secret' => $secret,
        'qr_code_url' => $otp->getQrCodeUri(
            'https://api.qrserver.com/v1/create-qr-code/?data=[DATA]&size=300x300&ecc=M&margin=10',
            '[DATA]'
        ),
        'manual_code' => $otp->getSecret()
    ];
}

/**
 * Verify a 2FA code
 * @param string $secret User's 2FA secret
 * @param string $code Code to verify
 * @return bool True if valid, false otherwise
 */
function verify2FACode($secret, $code) {
    $otp = TOTP::create($secret);
    return $otp->verify($code, null, 1); // Allow 1 step in either direction (30 seconds)
}

/**
 * Generate backup codes for 2FA
 * @param int $count Number of codes to generate (default: 8)
 * @return array Array of backup codes
 */
function generateBackupCodes($count = 8) {
    $codes = [];
    for ($i = 0; $i < $count; $i++) {
        $codes[] = strtoupper(bin2hex(random_bytes(4))); // 8-character alphanumeric codes
    }
    return $codes;
}

/**
 * Check if 2FA is required for the current user
 * @param int $userId User ID
 * @param Database $db Database connection
 * @return bool True if 2FA is required
 */
function is2FARequired($userId, $db) {
    // Check if 2FA is enabled for this user
    $conn = $db->getConnection();
    $stmt = $conn->prepare("SELECT is_enabled FROM user_2fa WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row && $row['is_enabled'] == 1;
}

/**
 * Verify a backup code and mark it as used
 * @param int $userId User ID
 * @param string $code Backup code to verify
 * @param Database $db Database connection
 * @return bool True if valid, false otherwise
 */
function verifyAndUseBackupCode($userId, $code, $db) {
    $conn = $db->getConnection();
    $stmt = $conn->prepare("SELECT backup_codes FROM user_2fa WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if (!$row || empty($row['backup_codes'])) {
        return false;
    }
    
    $backupCodes = json_decode($row['backup_codes'], true);
    $codeIndex = array_search($code, $backupCodes);
    
    if ($codeIndex !== false) {
        // Remove the used code
        unset($backupCodes[$codeIndex]);
        $backupCodes = array_values($backupCodes); // Re-index array
        
        // Update the database
        $stmt = $conn->prepare("UPDATE user_2fa SET backup_codes = ? WHERE user_id = ?");
        $backupCodesJson = json_encode($backupCodes);
        $stmt->bind_param("si", $backupCodesJson, $userId);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    return false;
}

/**
 * Check if a user needs to set up 2FA
 * @param int $userId User ID
 * @param Database $db Database connection
 * @return bool True if 2FA setup is required
 */
function is2FASetupRequired($userId, $db) {
    // For now, we'll require 2FA for all admin users
    // You can modify this based on your requirements
    $conn = $db->getConnection();
    $stmt = $conn->prepare("
        SELECT r.role_name 
        FROM users u 
        JOIN roles r ON u.role_id = r.role_id 
        WHERE u.user_id = ? AND r.role_name IN ('admin', 'teacher')
    ");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if ($row) {
        // Check if 2FA is already set up
        $stmt = $conn->prepare("SELECT id FROM user_2fa WHERE user_id = ? AND is_enabled = 1");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $has2FA = $result->num_rows > 0;
        $stmt->close();
        
        return !$has2FA;
    }
    
    return false;
}
