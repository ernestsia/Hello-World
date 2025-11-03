<?php
/**
 * Elementary Division Subjects Installer
 * This script adds subjects for Grades 1-6 and assigns them to elementary classes
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

// Elementary subjects to be added
$elementary_subjects = [
    ['name' => 'Bible', 'code' => 'BIBLE', 'desc' => 'Bible Studies for Elementary Division'],
    ['name' => 'Reading', 'code' => 'READ', 'desc' => 'Reading and Comprehension'],
    ['name' => 'Spelling/Dictation', 'code' => 'SPELL', 'desc' => 'Spelling and Dictation Exercises'],
    ['name' => 'Phonics', 'code' => 'PHON', 'desc' => 'Phonics and Pronunciation'],
    ['name' => 'English', 'code' => 'ENG-ELEM', 'desc' => 'English Language for Elementary'],
    ['name' => 'Arithmetic', 'code' => 'ARITH', 'desc' => 'Basic Arithmetic and Mathematics'],
    ['name' => 'Science', 'code' => 'SCI-ELEM', 'desc' => 'General Science for Elementary'],
    ['name' => 'Hygiene', 'code' => 'HYG', 'desc' => 'Health and Hygiene'],
    ['name' => 'Writing', 'code' => 'WRIT-ELEM', 'desc' => 'Handwriting and Composition'],
    ['name' => 'Coloring', 'code' => 'COLOR', 'desc' => 'Art and Coloring'],
    ['name' => 'Social Studies', 'code' => 'SS-ELEM', 'desc' => 'Social Studies for Elementary'],
    ['name' => 'Physical Education', 'code' => 'PE-ELEM', 'desc' => 'Physical Education and Sports']
];

// Process installation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    $conn->begin_transaction();
    
    try {
        $subject_ids = [];
        
        // Step 1: Insert subjects
        $messages[] = "Step 1: Adding Elementary Division subjects...";
        
        $insert_subject = $conn->prepare("INSERT INTO subjects (subject_name, subject_code, description) 
                                          VALUES (?, ?, ?) 
                                          ON DUPLICATE KEY UPDATE 
                                          subject_name = VALUES(subject_name),
                                          description = VALUES(description)");
        
        foreach ($elementary_subjects as $subject) {
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
        
        // Step 2: Find all elementary classes (Grades 1-6)
        $messages[] = "<br>Step 2: Finding Elementary Division classes (Grades 1-6)...";
        
        $elementary_classes = [];
        $class_query = "SELECT class_id, class_name, section FROM classes 
                       WHERE class_name REGEXP 'Grade [1-6]|1st|2nd|3rd|4th|5th|6th|One|Two|Three|Four|Five|Six'
                       ORDER BY class_name, section";
        $class_result = $conn->query($class_query);
        
        if ($class_result->num_rows > 0) {
            while ($class = $class_result->fetch_assoc()) {
                $elementary_classes[] = $class;
                $messages[] = "✓ Found: {$class['class_name']} - {$class['section']}";
            }
        } else {
            $errors[] = "⚠ No elementary classes found. Please create classes for Grades 1-6 first.";
        }
        
        // Step 3: Assign subjects to elementary classes
        if (!empty($elementary_classes)) {
            $messages[] = "<br>Step 3: Assigning subjects to elementary classes...";
            
            $assign_subject = $conn->prepare("INSERT INTO class_subjects (class_id, subject_id, teacher_id) 
                                             VALUES (?, ?, NULL) 
                                             ON DUPLICATE KEY UPDATE subject_id = VALUES(subject_id)");
            
            $total_assignments = 0;
            foreach ($elementary_classes as $class) {
                $messages[] = "<br><strong>Assigning to: {$class['class_name']} - {$class['section']}</strong>";
                
                foreach ($subject_ids as $code => $subject_id) {
                    $assign_subject->bind_param("ii", $class['class_id'], $subject_id);
                    $assign_subject->execute();
                    $total_assignments++;
                    
                    // Find subject name
                    $subject_name = '';
                    foreach ($elementary_subjects as $s) {
                        if ($s['code'] === $code) {
                            $subject_name = $s['name'];
                            break;
                        }
                    }
                    $messages[] = "  ✓ {$subject_name}";
                }
            }
            $assign_subject->close();
            
            $messages[] = "<br><strong>Total Assignments: {$total_assignments}</strong>";
        }
        
        $conn->commit();
        $messages[] = "<br><h3 style='color: green;'>✅ Installation Complete!</h3>";
        $messages[] = "All elementary subjects have been added and assigned to Grades 1-6.";
        $messages[] = "Students in these classes now have automatic access to all subjects.";
        
    } catch (Exception $e) {
        $conn->rollback();
        $errors[] = "Installation failed: " . $e->getMessage();
    }
}

// Get current subjects count
$subject_count_query = "SELECT COUNT(*) as count FROM subjects WHERE subject_code LIKE '%-ELEM' OR subject_code IN ('BIBLE', 'READ', 'SPELL', 'PHON', 'ARITH', 'HYG', 'COLOR')";
$subject_count_result = $conn->query($subject_count_query);
$subject_count = $subject_count_result->fetch_assoc()['count'];

// Get elementary classes count
$class_count_query = "SELECT COUNT(*) as count FROM classes WHERE class_name REGEXP 'Grade [1-6]|1st|2nd|3rd|4th|5th|6th|One|Two|Three|Four|Five|Six'";
$class_count_result = $conn->query($class_count_query);
$class_count = $class_count_result->fetch_assoc()['count'];

$pageTitle = 'Install Elementary Subjects';
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
    border-left: 4px solid #4F46E5;
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
    <h1><i class="fas fa-graduation-cap"></i> Elementary Division Subjects Installer</h1>
    <p class="lead">Add subjects for Grades 1-6 and automatically assign them to elementary classes.</p>
    
    <hr>
    
    <div class="info-card">
        <h5><i class="fas fa-info-circle"></i> System Status</h5>
        <p><strong>Elementary Subjects Already Installed:</strong> <?php echo $subject_count; ?> of 12</p>
        <p><strong>Elementary Classes Found:</strong> <?php echo $class_count; ?> classes</p>
    </div>
    
    <h4>Subjects to be Added:</h4>
    <div class="subject-list">
        <?php foreach ($elementary_subjects as $subject): ?>
        <div class="subject-item">
            <strong><?php echo $subject['name']; ?></strong><br>
            <small class="text-muted"><?php echo $subject['code']; ?></small>
        </div>
        <?php endforeach; ?>
    </div>
    
    <div class="alert alert-info">
        <i class="fas fa-lightbulb"></i> <strong>What This Will Do:</strong>
        <ul>
            <li>Add 12 elementary subjects to the database</li>
            <li>Find all classes for Grades 1-6</li>
            <li>Automatically assign all subjects to each elementary class</li>
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
            Make sure you have created classes for Grades 1-6 before running this installer.
        </div>
        
        <div class="d-grid gap-2">
            <button type="submit" name="install" class="btn btn-primary btn-lg" 
                    style="background-color: #4F46E5 !important; color: white !important;">
                <i class="fas fa-play"></i> Install Elementary Subjects
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
