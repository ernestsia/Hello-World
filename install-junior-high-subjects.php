<?php
/**
 * Junior High Division Subjects Installer
 * This script adds subjects for Grades 7-9 and assigns them to junior high classes
 * Includes duplicate prevention and cleanup
 */

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/session.php';

// Only admins can run this
requireLogin();
if (!hasRole('admin')) {
    die('Access denied. Only administrators can run this installer.');
}

$db = new Database();
$conn = $db->getConnection();

$messages = [];
$errors = [];

// Junior High subjects to be added
$junior_high_subjects = [
    ['name' => 'Holy Bible', 'code' => 'BIBLE-JH', 'desc' => 'Bible Studies for Junior High Division'],
    ['name' => 'English', 'code' => 'ENG-JH', 'desc' => 'English Language and Literature for Junior High'],
    ['name' => 'Vocabulary', 'code' => 'VOCAB-JH', 'desc' => 'Vocabulary Development'],
    ['name' => 'French', 'code' => 'FRE-JH', 'desc' => 'French Language'],
    ['name' => 'Literature', 'code' => 'LIT-JH', 'desc' => 'Literature Studies'],
    ['name' => 'Mathematics', 'code' => 'MATH-JH', 'desc' => 'Mathematics for Junior High'],
    ['name' => 'Geography', 'code' => 'GEO-JH', 'desc' => 'Geography and World Studies'],
    ['name' => 'History', 'code' => 'HIST-JH', 'desc' => 'History Studies'],
    ['name' => 'Civics', 'code' => 'CIV-JH', 'desc' => 'Civics and Government'],
    ['name' => 'General Science', 'code' => 'GSCI-JH', 'desc' => 'General Science for Junior High'],
    ['name' => 'Computer Science', 'code' => 'CS-JH', 'desc' => 'Computer Science and Technology'],
    ['name' => 'Writing', 'code' => 'WRIT-JH', 'desc' => 'Writing and Composition'],
    ['name' => 'Physical Education', 'code' => 'PE-JH', 'desc' => 'Physical Education and Sports']
];

// Process installation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    $conn->begin_transaction();
    
    try {
        // Step 0: Add unique constraint if it doesn't exist
        $messages[] = "Step 0: Ensuring database integrity...";
        
        $check_constraint = "SELECT COUNT(*) as count 
                            FROM information_schema.TABLE_CONSTRAINTS 
                            WHERE CONSTRAINT_SCHEMA = DATABASE() 
                            AND TABLE_NAME = 'class_subjects' 
                            AND CONSTRAINT_NAME = 'unique_class_subject'";
        $result = $conn->query($check_constraint);
        $constraint_exists = $result->fetch_assoc()['count'] > 0;
        
        if (!$constraint_exists) {
            // First, remove any existing duplicates
            $remove_duplicates = "DELETE cs1 FROM class_subjects cs1
                                 INNER JOIN class_subjects cs2 
                                 WHERE cs1.class_subject_id > cs2.class_subject_id 
                                 AND cs1.class_id = cs2.class_id 
                                 AND cs1.subject_id = cs2.subject_id";
            $conn->query($remove_duplicates);
            
            // Then add the constraint
            $add_constraint = "ALTER TABLE class_subjects 
                              ADD CONSTRAINT unique_class_subject 
                              UNIQUE (class_id, subject_id)";
            $conn->query($add_constraint);
            $messages[] = "✓ Added unique constraint to prevent duplicates";
        } else {
            $messages[] = "✓ Unique constraint already exists";
        }
        
        $subject_ids = [];
        
        // Step 1: Insert subjects
        $messages[] = "<br>Step 1: Adding Junior High Division subjects...";
        
        $insert_subject = $conn->prepare("INSERT INTO subjects (subject_name, subject_code, description) 
                                          VALUES (?, ?, ?) 
                                          ON DUPLICATE KEY UPDATE 
                                          subject_name = VALUES(subject_name),
                                          description = VALUES(description)");
        
        foreach ($junior_high_subjects as $subject) {
            $insert_subject->bind_param("sss", $subject['name'], $subject['code'], $subject['desc']);
            $insert_subject->execute();
            
            // Get the subject_id
            $get_id = $conn->prepare("SELECT subject_id FROM subjects WHERE subject_code = ?");
            $get_id->bind_param("s", $subject['code']);
            $get_id->execute();
            $result = $get_id->get_result();
            $row = $result->fetch_assoc();
            $subject_ids[$subject['code']] = $row['subject_id'];
            $get_id->close();
            
            $messages[] = "✓ Added: {$subject['name']} ({$subject['code']})";
        }
        $insert_subject->close();
        
        // Step 2: Find all junior high classes (Grades 7-9)
        $messages[] = "<br>Step 2: Finding Junior High Division classes (Grades 7-9)...";
        
        $junior_high_classes = [];
        $class_query = "SELECT class_id, class_name, section FROM classes 
                       WHERE class_name REGEXP 'Grade [7-9]|7th|8th|9th|Seven|Eight|Nine|Junior'
                       ORDER BY class_name, section";
        $class_result = $conn->query($class_query);
        
        if ($class_result->num_rows > 0) {
            while ($class = $class_result->fetch_assoc()) {
                $junior_high_classes[] = $class;
                $messages[] = "✓ Found: {$class['class_name']} - {$class['section']}";
            }
        } else {
            $errors[] = "⚠ No junior high classes found. Please create classes for Grades 7-9 first.";
        }
        
        // Step 3: Assign subjects to junior high classes (with duplicate prevention)
        if (!empty($junior_high_classes)) {
            $messages[] = "<br>Step 3: Assigning subjects to junior high classes...";
            
            $assign_subject = $conn->prepare("INSERT INTO class_subjects (class_id, subject_id, teacher_id) 
                                             VALUES (?, ?, NULL) 
                                             ON DUPLICATE KEY UPDATE class_subject_id = class_subject_id");
            
            $total_assignments = 0;
            $skipped_duplicates = 0;
            
            foreach ($junior_high_classes as $class) {
                $messages[] = "<br><strong>Assigning to: {$class['class_name']} - {$class['section']}</strong>";
                
                foreach ($subject_ids as $code => $subject_id) {
                    // Check if already assigned
                    $check_existing = $conn->prepare("SELECT COUNT(*) as count FROM class_subjects 
                                                     WHERE class_id = ? AND subject_id = ?");
                    $check_existing->bind_param("ii", $class['class_id'], $subject_id);
                    $check_existing->execute();
                    $exists = $check_existing->get_result()->fetch_assoc()['count'] > 0;
                    $check_existing->close();
                    
                    if (!$exists) {
                        $assign_subject->bind_param("ii", $class['class_id'], $subject_id);
                        $assign_subject->execute();
                        $total_assignments++;
                        
                        // Find subject name
                        $subject_name = '';
                        foreach ($junior_high_subjects as $s) {
                            if ($s['code'] === $code) {
                                $subject_name = $s['name'];
                                break;
                            }
                        }
                        $messages[] = "  ✓ {$subject_name}";
                    } else {
                        $skipped_duplicates++;
                        
                        // Find subject name
                        $subject_name = '';
                        foreach ($junior_high_subjects as $s) {
                            if ($s['code'] === $code) {
                                $subject_name = $s['name'];
                                break;
                            }
                        }
                        $messages[] = "  ⊘ {$subject_name} (already assigned, skipped)";
                    }
                }
            }
            $assign_subject->close();
            
            $messages[] = "<br><strong>Total New Assignments: {$total_assignments}</strong>";
            if ($skipped_duplicates > 0) {
                $messages[] = "<strong>Duplicates Skipped: {$skipped_duplicates}</strong>";
            }
        }
        
        $conn->commit();
        $messages[] = "<br><h3 style='color: green;'>✅ Installation Complete!</h3>";
        $messages[] = "All junior high subjects have been added and assigned to Grades 7-9.";
        $messages[] = "Students in these classes now have automatic access to all subjects.";
        $messages[] = "No duplicates were created!";
        
    } catch (Exception $e) {
        $conn->rollback();
        $errors[] = "Installation failed: " . $e->getMessage();
    }
}

// Get current subjects count
$subject_count_query = "SELECT COUNT(*) as count FROM subjects WHERE subject_code LIKE '%-JH'";
$subject_count_result = $conn->query($subject_count_query);
$subject_count = $subject_count_result->fetch_assoc()['count'];

// Get junior high classes count
$class_count_query = "SELECT COUNT(*) as count FROM classes WHERE class_name REGEXP 'Grade [7-9]|7th|8th|9th|Seven|Eight|Nine|Junior'";
$class_count_result = $conn->query($class_count_query);
$class_count = $class_count_result->fetch_assoc()['count'];

// Check for duplicates
$duplicate_check = "SELECT COUNT(*) - COUNT(DISTINCT CONCAT(class_id, '-', subject_id)) as duplicates 
                   FROM class_subjects";
$duplicate_count = $conn->query($duplicate_check)->fetch_assoc()['duplicates'];

$pageTitle = 'Install Junior High Subjects';
include 'includes/header.php';
?>

<style>
.install-container {
    max-width: 900px;
    margin: 50px auto;
    padding: 30px;
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.subject-list {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    margin: 20px 0;
}
.subject-item {
    padding: 10px;
    background: #f8f9fa;
    border-left: 4px solid #10B981;
    border-radius: 4px;
}
.message-box {
    background: #f0f9ff;
    border: 1px solid #0ea5e9;
    border-radius: 8px;
    padding: 15px;
    margin: 20px 0;
    max-height: 500px;
    overflow-y: auto;
}
.error-box {
    background: #fef2f2;
    border: 1px solid #ef4444;
    border-radius: 8px;
    padding: 15px;
    margin: 20px 0;
}
.info-card {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin: 15px 0;
}
</style>

<div class="install-container">
    <h1><i class="fas fa-school"></i> Junior High Division Subjects Installer</h1>
    <p class="lead">Add subjects for Grades 7-9 and automatically assign them to junior high classes.</p>
    
    <hr>
    
    <div class="info-card">
        <h5><i class="fas fa-info-circle"></i> System Status</h5>
        <p><strong>Junior High Subjects Already Installed:</strong> <?php echo $subject_count; ?> of 13</p>
        <p><strong>Junior High Classes Found:</strong> <?php echo $class_count; ?> classes</p>
        <p><strong>Duplicate Assignments in Database:</strong> 
            <span class="<?php echo $duplicate_count > 0 ? 'text-danger' : 'text-success'; ?>">
                <?php echo $duplicate_count; ?>
            </span>
        </p>
    </div>
    
    <?php if ($duplicate_count > 0): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i> <strong>Duplicates Detected!</strong><br>
        There are <?php echo $duplicate_count; ?> duplicate subject assignments in the database.
        The installer will skip duplicates and add a unique constraint to prevent future duplicates.
        <br><br>
        <a href="<?php echo APP_URL;?>/cleanup-duplicate-subjects.php" class="btn btn-sm btn-warning">
            <i class="fas fa-broom"></i> Clean Up Duplicates First
        </a>
    </div>
    <?php endif; ?>
    
    <h4>Subjects to be Added:</h4>
    <div class="subject-list">
        <?php foreach ($junior_high_subjects as $subject): ?>
        <div class="subject-item">
            <strong><?php echo $subject['name']; ?></strong><br>
            <small class="text-muted"><?php echo $subject['code']; ?></small>
        </div>
        <?php endforeach; ?>
    </div>
    
    <div class="alert alert-info">
        <i class="fas fa-lightbulb"></i> <strong>What This Will Do:</strong>
        <ul>
            <li>Add 13 junior high subjects to the database</li>
            <li>Find all classes for Grades 7-9</li>
            <li>Automatically assign all subjects to each junior high class</li>
            <li><strong>Skip any duplicates</strong> to ensure each subject appears only once</li>
            <li>Add unique constraint to prevent future duplicates</li>
            <li>Students in these classes will immediately have access to all subjects</li>
        </ul>
    </div>
    
    <?php if (!empty($errors)): ?>
    <div class="error-box">
        <h5><i class="fas fa-exclamation-triangle"></i> Errors</h5>
        <?php foreach ($errors as $error): ?>
        <p><?php echo $error; ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($messages)): ?>
    <div class="message-box">
        <h5><i class="fas fa-check-circle"></i> Installation Log</h5>
        <?php foreach ($messages as $message): ?>
        <p><?php echo $message; ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <?php if (empty($messages) || !empty($errors)): ?>
    <form method="POST" action="">
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-circle"></i> <strong>Important:</strong> 
            Make sure you have created classes for Grades 7-9 before running this installer.
        </div>
        
        <div class="d-grid gap-2">
            <button type="submit" name="install" class="btn btn-success btn-lg" 
                    style="background-color: #10B981 !important; color: white !important;">
                <i class="fas fa-play"></i> Install Junior High Subjects (No Duplicates)
            </button>
            <a href="<?php echo APP_URL;?>/classes/list.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Classes
            </a>
        </div>
    </form>
    <?php else: ?>
    <div class="d-grid gap-2">
        <a href="<?php echo APP_URL;?>/subjects/list.php" class="btn btn-success btn-lg">
            <i class="fas fa-list"></i> View All Subjects
        </a>
        <a href="<?php echo APP_URL;?>/classes/list.php" class="btn btn-primary">
            <i class="fas fa-school"></i> View Classes
        </a>
        <a href="<?php echo APP_URL;?>/index.php" class="btn btn-secondary">
            <i class="fas fa-home"></i> Back to Dashboard
        </a>
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
