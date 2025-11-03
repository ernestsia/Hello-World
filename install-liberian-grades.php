<?php
require_once 'config/config.php';
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

echo "<h2>Installing Liberian Grade Sheet Tables...</h2>";

// SQL statements
$sql_statements = [
    "CREATE TABLE IF NOT EXISTS liberian_grades (
        grade_id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        subject_id INT NOT NULL,
        academic_year VARCHAR(10) NOT NULL,
        first_period DECIMAL(5,2) DEFAULT NULL,
        second_period DECIMAL(5,2) DEFAULT NULL,
        third_period DECIMAL(5,2) DEFAULT NULL,
        first_sem_exam DECIMAL(5,2) DEFAULT NULL,
        first_sem_average DECIMAL(5,2) DEFAULT NULL,
        fourth_period DECIMAL(5,2) DEFAULT NULL,
        fifth_period DECIMAL(5,2) DEFAULT NULL,
        sixth_period DECIMAL(5,2) DEFAULT NULL,
        second_sem_exam DECIMAL(5,2) DEFAULT NULL,
        second_sem_average DECIMAL(5,2) DEFAULT NULL,
        final_average DECIMAL(5,2) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
        FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE,
        UNIQUE KEY unique_student_subject_year (student_id, subject_id, academic_year)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    "CREATE TABLE IF NOT EXISTS student_attendance_summary (
        attendance_id INT AUTO_INCREMENT PRIMARY KEY,
        student_id INT NOT NULL,
        academic_year VARCHAR(10) NOT NULL,
        days_absent INT DEFAULT 0,
        days_late INT DEFAULT 0,
        first_unit_remark TEXT,
        second_unit_remark TEXT,
        third_unit_remark TEXT,
        fourth_unit_remark TEXT,
        fifth_unit_remark TEXT,
        sixth_unit_remark TEXT,
        attitude_remark TEXT,
        conduct_remark TEXT,
        participation_remark TEXT,
        general_appearance_remark TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
        UNIQUE KEY unique_student_year (student_id, academic_year)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    "CREATE TABLE IF NOT EXISTS class_subjects (
        class_subject_id INT AUTO_INCREMENT PRIMARY KEY,
        class_id INT NOT NULL,
        subject_id INT NOT NULL,
        teacher_id INT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (class_id) REFERENCES classes(class_id) ON DELETE CASCADE,
        FOREIGN KEY (subject_id) REFERENCES subjects(subject_id) ON DELETE CASCADE,
        FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id) ON DELETE SET NULL,
        UNIQUE KEY unique_class_subject (class_id, subject_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

$success_count = 0;
$error_count = 0;

foreach ($sql_statements as $index => $sql) {
    try {
        if ($conn->query($sql)) {
            echo "<p style='color: green;'>✓ Table " . ($index + 1) . " created successfully</p>";
            $success_count++;
        } else {
            echo "<p style='color: red;'>✗ Error creating table " . ($index + 1) . ": " . $conn->error . "</p>";
            $error_count++;
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Exception for table " . ($index + 1) . ": " . $e->getMessage() . "</p>";
        $error_count++;
    }
}

echo "<hr>";
echo "<h3>Installation Summary</h3>";
echo "<p>Successful: $success_count</p>";
echo "<p>Errors: $error_count</p>";

if ($error_count == 0) {
    echo "<p style='color: green; font-weight: bold;'>✓ All tables installed successfully!</p>";
    echo "<p><a href='grades/liberian-grade-sheet.php'>Go to Liberian Grade Sheet</a></p>";
} else {
    echo "<p style='color: orange;'>⚠ Some tables may already exist or there were errors. Check the messages above.</p>";
}

$conn->close();
?>
