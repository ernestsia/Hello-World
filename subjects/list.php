<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireLogin();

$pageTitle = 'Subjects List';
$db = new Database();
$conn = $db->getConnection();

// Get subjects based on user role
if (hasRole('teacher')) {
    // Teachers only see subjects they teach
    $teacher_id = null;
    $stmt = $conn->prepare("SELECT teacher_id FROM teachers WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result_teacher = $stmt->get_result();
    if ($row = $result_teacher->fetch_assoc()) {
        $teacher_id = $row['teacher_id'];
    }
    $stmt->close();
    
    if ($teacher_id) {
        $stmt = $conn->prepare("SELECT DISTINCT s.*, 
                                       (SELECT COUNT(*) FROM class_subjects WHERE subject_id = s.subject_id AND teacher_id = ?) as class_count
                               FROM subjects s
                               INNER JOIN class_subjects cs ON s.subject_id = cs.subject_id
                               WHERE cs.teacher_id = ?
                               ORDER BY s.subject_name");
        $stmt->bind_param("ii", $teacher_id, $teacher_id);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query("SELECT * FROM subjects WHERE 1=0");
    }
} else {
    // Admins see all subjects
    $query = "SELECT s.*, 
                     (SELECT COUNT(*) FROM class_subjects WHERE subject_id = s.subject_id) as class_count
              FROM subjects s
              ORDER BY s.subject_name";
    $result = $conn->query($query);
}

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-book"></i> Subjects Management</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Subjects</li>
            </ol>
        </nav>
    </div>
</div>

<?php if (hasRole('admin')): ?>
<div class="row mb-3">
    <div class="col-12 text-end">
        <a href="<?php echo APP_URL;?>/subjects/add.php" class="btn btn-success">
            <i class="fas fa-plus"></i> Add Subject
        </a>
    </div>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><?php echo hasRole('teacher') ? 'My Subjects' : 'All Subjects'; ?></h5>
            </div>
            <div class="card-body">
                <?php if ($result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Subject Code</th>
                                <th>Subject Name</th>
                                <th>Description</th>
                                <th>Classes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($subject = $result->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($subject['subject_code']); ?></strong></td>
                                <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                <td><?php echo htmlspecialchars($subject['description']); ?></td>
                                <td>
                                    <span class="badge bg-info"><?php echo $subject['class_count']; ?> Classes</span>
                                </td>
                                <td>
                                    <?php if (hasRole('admin')): ?>
                                    <a href="<?php echo APP_URL;?>/subjects/edit.php?id=<?php echo $subject['subject_id']; ?>" 
                                       class="btn btn-sm btn-warning" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="<?php echo APP_URL;?>/subjects/delete.php?id=<?php echo $subject['subject_id']; ?>" 
                                       class="btn btn-sm btn-danger" title="Delete"
                                       onclick="return confirm('Are you sure you want to delete this subject?');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle"></i> No subjects found.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
