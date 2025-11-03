<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireRole('admin');

$db = new Database();
$conn = $db->getConnection();

$class_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($class_id === 0) {
    setFlashMessage('danger', 'Invalid class ID');
    redirect(APP_URL . '/classes/list.php');
}

// Check if class exists
$stmt = $conn->prepare("SELECT class_name, section FROM classes WHERE class_id = ?");
$stmt->bind_param("i", $class_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setFlashMessage('danger', 'Class not found');
    redirect(APP_URL . '/classes/list.php');
}

$class = $result->fetch_assoc();
$stmt->close();

// Check if class has students
$stmt = $conn->prepare("SELECT COUNT(*) as student_count FROM students WHERE class_id = ?");
$stmt->bind_param("i", $class_id);
$stmt->execute();
$result = $stmt->get_result();
$student_count = $result->fetch_assoc()['student_count'];
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($student_count > 0) {
        setFlashMessage('danger', 'Cannot delete class with enrolled students. Please reassign or remove students first.');
        redirect(APP_URL . '/classes/view.php?id=' . $class_id);
    }
    
    // Delete class
    $stmt = $conn->prepare("DELETE FROM classes WHERE class_id = ?");
    $stmt->bind_param("i", $class_id);
    
    if ($stmt->execute()) {
        setFlashMessage('success', 'Class deleted successfully!');
        redirect(APP_URL . '/classes/list.php');
    } else {
        setFlashMessage('danger', 'Error deleting class: ' . $conn->error);
        redirect(APP_URL . '/classes/view.php?id=' . $class_id);
    }
    $stmt->close();
}

$pageTitle = 'Delete Class';
include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-trash"></i> Delete Class</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/classes/list.php">Classes</a></li>
                <li class="breadcrumb-item active">Delete Class</li>
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
                <?php if ($student_count > 0): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <strong>Warning!</strong> This class has <strong><?php echo $student_count; ?></strong> enrolled student(s).
                </div>
                <p>You cannot delete a class with enrolled students. Please reassign or remove all students from this class first.</p>
                <div class="mt-3">
                    <a href="<?php echo APP_URL;?>/classes/view.php?id=<?php echo $class_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Go Back
                    </a>
                </div>
                <?php else: ?>
                <p class="mb-3">Are you sure you want to delete the following class?</p>
                <div class="alert alert-info">
                    <h5><?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section']); ?></h5>
                </div>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <strong>Warning!</strong> This action cannot be undone. All related data including:
                    <ul class="mb-0 mt-2">
                        <li>Class subjects assignments</li>
                        <li>Timetable entries</li>
                        <li>Attendance records</li>
                    </ul>
                    will also be deleted.
                </div>
                <form method="POST" action="">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Yes, Delete Class
                    </button>
                    <a href="<?php echo APP_URL;?>/classes/view.php?id=<?php echo $class_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
