<?php
require_once 'config/config.php';
require_once 'config/database.php';

$db = new Database();
$conn = $db->getConnection();

echo "<h2>Installing Liberian Subjects...</h2>";

// Liberian subjects from the grade sheet
$subjects = [
    ['HOLY BIBLE / RME', 'RME'],
    ['LANGUAGE', 'LANG'],
    ['ENGLISH', 'ENG'],
    ['VOCABULARY', 'VOCAB'],
    ['FRENCH', 'FRE'],
    ['LITERATURE', 'LIT'],
    ['MATHEMATICS', 'MATH'],
    ['ALGEBRA', 'ALG'],
    ['GEOMETRY', 'GEOM'],
    ['TRIGONOMETRY', 'TRIG'],
    ['SOCIAL STUDIES', 'SS'],
    ['GEOGRAPHY', 'GEO'],
    ['HISTORY', 'HIST'],
    ['ECONOMICS / CIVICS', 'ECON'],
    ['GEN. SCIENCE', 'GSCI'],
    ['BIOLOGY', 'BIO'],
    ['CHEMISTRY', 'CHEM'],
    ['PHYSICS', 'PHY'],
    ['COMPUTER SCIENCE', 'CS'],
    ['WRITING', 'WRIT'],
    ['P.E.', 'PE']
];

$success_count = 0;
$error_count = 0;

$stmt = $conn->prepare("INSERT INTO subjects (subject_name, subject_code) VALUES (?, ?) 
                        ON DUPLICATE KEY UPDATE subject_name = VALUES(subject_name)");

foreach ($subjects as $subject) {
    try {
        $stmt->bind_param("ss", $subject[0], $subject[1]);
        if ($stmt->execute()) {
            echo "<p style='color: green;'>✓ Added: {$subject[0]} ({$subject[1]})</p>";
            $success_count++;
        } else {
            echo "<p style='color: red;'>✗ Error adding {$subject[0]}: " . $stmt->error . "</p>";
            $error_count++;
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>✗ Exception for {$subject[0]}: " . $e->getMessage() . "</p>";
        $error_count++;
    }
}

$stmt->close();

echo "<hr>";
echo "<h3>Installation Summary</h3>";
echo "<p>Successful: $success_count</p>";
echo "<p>Errors: $error_count</p>";

if ($error_count == 0) {
    echo "<p style='color: green; font-weight: bold;'>✓ All subjects installed successfully!</p>";
    echo "<p><a href='subjects/list.php'>View Subjects</a> | <a href='classes/assign-subjects.php?id=1'>Assign Subjects to Class</a></p>";
} else {
    echo "<p style='color: orange;'>⚠ Some subjects may already exist or there were errors.</p>";
}

$conn->close();
?>
