<?php
// Session management

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check session timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
    // Session expired
    session_unset();
    session_destroy();
    redirect(APP_URL . '/login.php?timeout=1');
}

// Update last activity time
$_SESSION['last_activity'] = time();

// Regenerate session ID periodically for security
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} else if (time() - $_SESSION['created'] > 1800) {
    // Regenerate session ID every 30 minutes
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}
?>
