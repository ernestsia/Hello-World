<?php
require_once 'config/config.php';
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

echo "<h2>Installing Teacher Subjects Table...</h2>";

$sql = "CREATE TABLE IF NOT EXISTS teacher_subjects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    subject_id INT NOT NULL,
    class_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(class_id) ON DELETE CASCADE,
    UNIQUE KEY unique_teacher_subject_class (teacher_id, subject_id, class_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

if ($conn->query($sql)) {
    echo "<p style='color: green;'>✓ Teacher subjects table created successfully!</p>";
    echo "<p><a href='teachers/assign-subjects.php'>Assign Subjects to Teachers</a></p>";
} else {
    echo "<p style='color: red;'>✗ Error: " . $conn->error . "</p>";
}

$conn->close();
?>
