<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireRole('admin');

$db = new Database();
$conn = $db->getConnection();

$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($student_id === 0) {
    setFlashMessage('danger', 'Invalid student ID');
    redirect(APP_URL . '/students/list.php');
}

// Get student info including photo and user_id
$stmt = $conn->prepare("SELECT user_id, photo FROM students WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setFlashMessage('danger', 'Student not found');
    redirect(APP_URL . '/students/list.php');
}

$student = $result->fetch_assoc();
$stmt->close();

// Delete student (will cascade delete user due to foreign key)
$stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
$stmt->bind_param("i", $student['user_id']);

if ($stmt->execute()) {
    // Delete photo file if exists
    if (!empty($student['photo'])) {
        $photo_path = UPLOAD_PATH . 'students/' . $student['photo'];
        deleteFile($photo_path);
    }
    
    setFlashMessage('success', 'Student deleted successfully!');
} else {
    setFlashMessage('danger', 'Failed to delete student');
}

$stmt->close();
redirect(APP_URL . '/students/list.php');
?>
