<?php
/**
 * Cleanup Duplicate Subject Assignments
 * This script removes duplicate subject assignments from class_subjects table
 */

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/session.php';

// Only admins can run this
requireLogin();
if (!hasRole('admin')) {
    die('Access denied. Only administrators can run this cleanup script.');
}

$db = new Database();
$conn = $db->getConnection();

$messages = [];
$errors = [];

// Process cleanup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cleanup'])) {
    $conn->begin_transaction();
    
    try {
        // Step 1: Find duplicates
        $messages[] = "Step 1: Scanning for duplicate subject assignments...";
        
        $find_duplicates = "SELECT class_id, subject_id, COUNT(*) as count, 
                                   GROUP_CONCAT(class_subject_id ORDER BY class_subject_id) as ids
                           FROM class_subjects
                           GROUP BY class_id, subject_id
                           HAVING count > 1";
        
        $result = $conn->query($find_duplicates);
        $duplicates = [];
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $duplicates[] = $row;
            }
            $messages[] = "✓ Found " . count($duplicates) . " duplicate subject assignments";
        } else {
            $messages[] = "✓ No duplicates found!";
        }
        
        // Step 2: Remove duplicates (keep the first one, delete the rest)
        if (!empty($duplicates)) {
            $messages[] = "<br>Step 2: Removing duplicate entries...";
            $total_removed = 0;
            
            foreach ($duplicates as $dup) {
                $ids = explode(',', $dup['ids']);
                // Keep the first ID, delete the rest
                $keep_id = array_shift($ids);
                $delete_ids = $ids;
                
                if (!empty($delete_ids)) {
                    $delete_ids_str = implode(',', $delete_ids);
                    $delete_query = "DELETE FROM class_subjects WHERE class_subject_id IN ($delete_ids_str)";
                    $conn->query($delete_query);
                    $removed = $conn->affected_rows;
                    $total_removed += $removed;
                    
                    // Get class and subject names for logging
                    $info_query = "SELECT c.class_name, c.section, s.subject_name
                                  FROM class_subjects cs
                                  JOIN classes c ON cs.class_id = c.class_id
                                  JOIN subjects s ON cs.subject_id = s.subject_id
                                  WHERE cs.class_subject_id = ?";
                    $stmt = $conn->prepare($info_query);
                    $stmt->bind_param("i", $keep_id);
                    $stmt->execute();
                    $info = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    
                    $messages[] = "✓ Removed {$removed} duplicate(s) of '{$info['subject_name']}' from {$info['class_name']}-{$info['section']}";
                }
            }
            
            $messages[] = "<br><strong>Total duplicates removed: {$total_removed}</strong>";
        }
        
        // Step 3: Add unique constraint to prevent future duplicates
        $messages[] = "<br>Step 3: Adding unique constraint to prevent future duplicates...";
        
        // Check if constraint already exists
        $check_constraint = "SELECT COUNT(*) as count 
                            FROM information_schema.TABLE_CONSTRAINTS 
                            WHERE CONSTRAINT_SCHEMA = DATABASE() 
                            AND TABLE_NAME = 'class_subjects' 
                            AND CONSTRAINT_NAME = 'unique_class_subject'";
        $result = $conn->query($check_constraint);
        $constraint_exists = $result->fetch_assoc()['count'] > 0;
        
        if (!$constraint_exists) {
            $add_constraint = "ALTER TABLE class_subjects 
                              ADD CONSTRAINT unique_class_subject 
                              UNIQUE (class_id, subject_id)";
            $conn->query($add_constraint);
            $messages[] = "✓ Unique constraint added successfully";
        } else {
            $messages[] = "✓ Unique constraint already exists";
        }
        
        $conn->commit();
        $messages[] = "<br><h3 style='color: green;'>✅ Cleanup Complete!</h3>";
        $messages[] = "All duplicate subject assignments have been removed.";
        $messages[] = "Future duplicates will be prevented by the unique constraint.";
        
    } catch (Exception $e) {
        $conn->rollback();
        $errors[] = "Cleanup failed: " . $e->getMessage();
    }
}

// Get current statistics
$stats_query = "SELECT 
                    COUNT(*) as total_assignments,
                    COUNT(DISTINCT CONCAT(class_id, '-', subject_id)) as unique_assignments,
                    COUNT(*) - COUNT(DISTINCT CONCAT(class_id, '-', subject_id)) as duplicates
                FROM class_subjects";
$stats = $conn->query($stats_query)->fetch_assoc();

$pageTitle = 'Cleanup Duplicate Subjects';
include 'includes/header.php';
?>

<style>
.cleanup-container {
    max-width: 900px;
    margin: 50px auto;
    padding: 30px;
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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
.stats-card {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    margin: 20px 0;
}
.stat-item {
    padding: 15px;
    background: white;
    border-left: 4px solid #4F46E5;
    border-radius: 4px;
    margin-bottom: 10px;
}
</style>

<div class="cleanup-container">
    <h1><i class="fas fa-broom"></i> Cleanup Duplicate Subject Assignments</h1>
    <p class="lead">Remove duplicate subject assignments from the class_subjects table.</p>
    
    <hr>
    
    <div class="stats-card">
        <h5><i class="fas fa-chart-bar"></i> Current Statistics</h5>
        <div class="stat-item">
            <strong>Total Assignments:</strong> <?php echo $stats['total_assignments']; ?>
        </div>
        <div class="stat-item">
            <strong>Unique Assignments:</strong> <?php echo $stats['unique_assignments']; ?>
        </div>
        <div class="stat-item <?php echo $stats['duplicates'] > 0 ? 'border-danger' : 'border-success'; ?>">
            <strong>Duplicates Found:</strong> 
            <span class="<?php echo $stats['duplicates'] > 0 ? 'text-danger' : 'text-success'; ?>">
                <?php echo $stats['duplicates']; ?>
            </span>
        </div>
    </div>
    
    <?php if ($stats['duplicates'] > 0): ?>
    <div class="alert alert-warning">
        <i class="fas fa-exclamation-triangle"></i> <strong>Duplicates Detected!</strong><br>
        There are <?php echo $stats['duplicates']; ?> duplicate subject assignments that need to be cleaned up.
    </div>
    <?php else: ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <strong>All Clean!</strong><br>
        No duplicate subject assignments found.
    </div>
    <?php endif; ?>
    
    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i> <strong>What This Will Do:</strong>
        <ul class="mb-0">
            <li>Scan for duplicate subject assignments (same class + same subject)</li>
            <li>Keep one assignment and remove all duplicates</li>
            <li>Add a unique constraint to prevent future duplicates</li>
            <li>Students will see each subject only once</li>
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
        <h5><i class="fas fa-check-circle"></i> Cleanup Log</h5>
        <?php foreach ($messages as $message): ?>
        <p><?php echo $message; ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    
    <?php if (empty($messages) || !empty($errors)): ?>
    <form method="POST" action="">
        <div class="d-grid gap-2">
            <button type="submit" name="cleanup" class="btn btn-danger btn-lg">
                <i class="fas fa-broom"></i> Clean Up Duplicates
            </button>
            <a href="<?php echo APP_URL;?>/subjects/list.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Subjects
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
