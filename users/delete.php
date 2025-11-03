<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireRole('admin');

$db = new Database();
$conn = $db->getConnection();

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id === 0) {
    setFlashMessage('danger', 'Invalid user ID');
    redirect(APP_URL . '/users/list.php');
}

// Prevent deleting own account
if ($user_id == $_SESSION['user_id']) {
    setFlashMessage('danger', 'You cannot delete your own account!');
    redirect(APP_URL . '/users/list.php');
}

// Delete user
$stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    setFlashMessage('success', 'User deleted successfully!');
} else {
    setFlashMessage('danger', 'Failed to delete user');
}

$stmt->close();
redirect(APP_URL . '/users/list.php');
?>
