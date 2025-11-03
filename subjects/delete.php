<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireRole('admin');

$db = new Database();
$conn = $db->getConnection();

$subject_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($subject_id === 0) {
    setFlashMessage('danger', 'Invalid subject ID');
    redirect(APP_URL . '/subjects/list.php');
}

// Check if subject exists
$stmt = $conn->prepare("SELECT subject_name, subject_code FROM subjects WHERE subject_id = ?");
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setFlashMessage('danger', 'Subject not found');
    redirect(APP_URL . '/subjects/list.php');
}

$subject = $result->fetch_assoc();
$stmt->close();

// Check if subject is assigned to any classes
$stmt = $conn->prepare("SELECT COUNT(*) as class_count FROM class_subjects WHERE subject_id = ?");
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$result = $stmt->get_result();
$class_count = $result->fetch_assoc()['class_count'];
$stmt->close();

// Check if subject has any exams
$stmt = $conn->prepare("SELECT COUNT(*) as exam_count FROM exams WHERE subject_id = ?");
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$result = $stmt->get_result();
$exam_count = $result->fetch_assoc()['exam_count'];
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($class_count > 0 || $exam_count > 0) {
        setFlashMessage('danger', 'Cannot delete subject that is assigned to classes or has exams. Please remove all assignments first.');
        redirect(APP_URL . '/subjects/list.php');
    }
    
    // Delete subject
    $stmt = $conn->prepare("DELETE FROM subjects WHERE subject_id = ?");
    $stmt->bind_param("i", $subject_id);
    
    if ($stmt->execute()) {
        setFlashMessage('success', 'Subject deleted successfully!');
        redirect(APP_URL . '/subjects/list.php');
    } else {
        setFlashMessage('danger', 'Error deleting subject: ' . $conn->error);
        redirect(APP_URL . '/subjects/list.php');
    }
    $stmt->close();
}

$pageTitle = 'Delete Subject';
include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-trash"></i> Delete Subject</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/subjects/list.php">Subjects</a></li>
                <li class="breadcrumb-item active">Delete Subject</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Confirm Deletion</h5>
            </div>
            <div class="card-body">
                <?php if ($class_count > 0 || $exam_count > 0): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <strong>Warning!</strong> This subject cannot be deleted because:
                    <ul class="mb-0 mt-2">
                        <?php if ($class_count > 0): ?>
                        <li>It is assigned to <strong><?php echo $class_count; ?></strong> class(es)</li>
                        <?php endif; ?>
                        <?php if ($exam_count > 0): ?>
                        <li>It has <strong><?php echo $exam_count; ?></strong> exam(s) associated with it</li>
                        <?php endif; ?>
                    </ul>
                </div>
                <p>Please remove all class assignments and exams before deleting this subject.</p>
                <div class="mt-3">
                    <a href="<?php echo APP_URL;?>/subjects/list.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Go Back
                    </a>
                </div>
                <?php else: ?>
                <p class="mb-3">Are you sure you want to delete the following subject?</p>
                <div class="alert alert-info">
                    <h5><?php echo htmlspecialchars($subject['subject_name']); ?></h5>
                    <p class="mb-0"><strong>Code:</strong> <?php echo htmlspecialchars($subject['subject_code']); ?></p>
                </div>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <strong>Warning!</strong> This action cannot be undone.
                </div>
                <form method="POST" action="">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Yes, Delete Subject
                    </button>
                    <a href="<?php echo APP_URL;?>/subjects/list.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
