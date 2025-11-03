<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireRole('admin');

$db = new Database();
$conn = $db->getConnection();

$announcement_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($announcement_id === 0) {
    setFlashMessage('danger', 'Invalid announcement ID');
    redirect(APP_URL . '/announcements/index.php');
}

// Delete announcement
$stmt = $conn->prepare("DELETE FROM announcements WHERE announcement_id = ?");
$stmt->bind_param("i", $announcement_id);

if ($stmt->execute()) {
    setFlashMessage('success', 'Announcement deleted successfully!');
} else {
    setFlashMessage('danger', 'Failed to delete announcement');
}

$stmt->close();
redirect(APP_URL . '/announcements/index.php');
?>
