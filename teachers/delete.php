<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireRole('admin');

$db = new Database();
$conn = $db->getConnection();

$teacher_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($teacher_id === 0) {
    setFlashMessage('danger', 'Invalid teacher ID');
    redirect(APP_URL . '/teachers/list.php');
}

// Get teacher info including photo and user_id
$stmt = $conn->prepare("SELECT user_id, photo FROM teachers WHERE teacher_id = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setFlashMessage('danger', 'Teacher not found');
    redirect(APP_URL . '/teachers/list.php');
}

$teacher = $result->fetch_assoc();
$stmt->close();

// Delete teacher (will cascade delete user due to foreign key)
$stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
$stmt->bind_param("i", $teacher['user_id']);

if ($stmt->execute()) {
    // Delete photo file if exists
    if (!empty($teacher['photo'])) {
        $photo_path = UPLOAD_PATH . 'teachers/' . $teacher['photo'];
        deleteFile($photo_path);
    }
    
    setFlashMessage('success', 'Teacher deleted successfully!');
} else {
    setFlashMessage('danger', 'Failed to delete teacher');
}

$stmt->close();
redirect(APP_URL . '/teachers/list.php');
?>
