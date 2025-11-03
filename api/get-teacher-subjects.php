<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

header('Content-Type: application/json');

requireLogin();

$db = new Database();
$conn = $db->getConnection();

$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$subjects = [];

if (hasRole('teacher')) {
    // Get teacher ID
    $teacher_id = null;
    $stmt = $conn->prepare("SELECT teacher_id FROM teachers WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $teacher_id = $row['teacher_id'];
    }
    $stmt->close();
    
    if ($teacher_id) {
        // Check if teacher_subjects table exists
        $check_table = $conn->query("SHOW TABLES LIKE 'teacher_subjects'");
        
        if ($check_table->num_rows > 0) {
            // Use teacher_subjects table
            if ($class_id > 0) {
                $stmt = $conn->prepare("SELECT DISTINCT s.subject_id, s.subject_name, s.subject_code 
                                       FROM subjects s 
                                       JOIN teacher_subjects ts ON s.subject_id = ts.subject_id 
                                       WHERE ts.teacher_id = ? AND ts.class_id = ?
                                       ORDER BY s.subject_name");
                $stmt->bind_param("ii", $teacher_id, $class_id);
            } else {
                $stmt = $conn->prepare("SELECT DISTINCT s.subject_id, s.subject_name, s.subject_code 
                                       FROM subjects s 
                                       JOIN teacher_subjects ts ON s.subject_id = ts.subject_id 
                                       WHERE ts.teacher_id = ?
                                       ORDER BY s.subject_name");
                $stmt->bind_param("i", $teacher_id);
            }
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $subjects[] = $row;
            }
            $stmt->close();
        } else {
            // Fallback: Get subjects from classes they teach
            if ($class_id > 0) {
                $stmt = $conn->prepare("SELECT DISTINCT s.subject_id, s.subject_name, s.subject_code 
                                       FROM subjects s 
                                       JOIN class_subjects cs ON s.subject_id = cs.subject_id 
                                       WHERE cs.class_id = ?
                                       ORDER BY s.subject_name");
                $stmt->bind_param("i", $class_id);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $subjects[] = $row;
                }
                $stmt->close();
            }
        }
    }
} else {
    // Admins see all subjects for the class
    if ($class_id > 0) {
        $stmt = $conn->prepare("SELECT DISTINCT s.subject_id, s.subject_name, s.subject_code 
                               FROM subjects s 
                               JOIN class_subjects cs ON s.subject_id = cs.subject_id 
                               WHERE cs.class_id = ?
                               ORDER BY s.subject_name");
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $subjects[] = $row;
        }
        $stmt->close();
    } else {
        // All subjects if no class selected
        $result = $conn->query("SELECT subject_id, subject_name, subject_code FROM subjects ORDER BY subject_name");
        while ($row = $result->fetch_assoc()) {
            $subjects[] = $row;
        }
    }
}

echo json_encode(['success' => true, 'subjects' => $subjects]);
$conn->close();
?>
