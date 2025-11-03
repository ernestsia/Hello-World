<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireLogin();

$pageTitle = 'Classes List';
$db = new Database();
$conn = $db->getConnection();

// Get classes based on user role
if (hasRole('teacher')) {
    // Teachers see classes where they teach any subject OR are class teacher
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
        $stmt = $conn->prepare("SELECT DISTINCT c.*, 
                                       CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
                                       (SELECT COUNT(*) FROM students WHERE class_id = c.class_id) as student_count,
                                       (SELECT COUNT(*) FROM class_subjects WHERE class_id = c.class_id AND teacher_id = ?) as my_subjects_count
                               FROM classes c
                               LEFT JOIN teachers t ON c.class_teacher_id = t.teacher_id
                               WHERE c.class_teacher_id = ? 
                               OR c.class_id IN (SELECT DISTINCT class_id FROM class_subjects WHERE teacher_id = ?)
                               ORDER BY c.class_name, c.section");
        $stmt->bind_param("iii", $teacher_id, $teacher_id, $teacher_id);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query("SELECT c.*, 
                                       CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
                                       (SELECT COUNT(*) FROM students WHERE class_id = c.class_id) as student_count
                               FROM classes c
                               LEFT JOIN teachers t ON c.class_teacher_id = t.teacher_id
                               WHERE 1=0"); // No results if teacher not found
    }
} else {
    // Admins see all classes
    $query = "SELECT c.*, 
                     CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
                     (SELECT COUNT(*) FROM students WHERE class_id = c.class_id) as student_count
              FROM classes c
              LEFT JOIN teachers t ON c.class_teacher_id = t.teacher_id
              ORDER BY c.class_name, c.section";
    $result = $conn->query($query);
}

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-door-open"></i> Classes Management</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Classes</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row mb-3">
    <div class="col-12 text-end">
        <?php if (hasRole('admin')): ?>
        <a href="<?php echo APP_URL;?>/classes/add.php" class="btn btn-success">
            <i class="fas fa-plus"></i> Add Class
        </a>
        <a href="<?php echo APP_URL;?>/subjects/list.php" class="btn btn-primary">
            <i class="fas fa-book"></i> Manage Subjects
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Classes List -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-list"></i> <?php echo hasRole('teacher') ? 'My Classes' : 'All Classes'; ?>
                    <span class="badge bg-primary ms-2"><?php echo $result->num_rows; ?> Total</span>
                </h5>
            </div>
            <div class="card-body">
                <?php if ($result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Class Name</th>
                                <th>Section</th>
                                <th>Class Teacher</th>
                                <?php if (hasRole('teacher')): ?>
                                <th>My Subjects</th>
                                <?php else: ?>
                                <th>Room Number</th>
                                <th>Capacity</th>
                                <?php endif; ?>
                                <th>Students</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $counter = 1;
                            while ($class = $result->fetch_assoc()): 
                            ?>
                            <tr>
                                <td><?php echo $counter++; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($class['class_name']); ?></strong>
                                </td>
                                <td>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($class['section']); ?></span>
                                </td>
                                <td>
                                    <?php if ($class['teacher_name']): ?>
                                        <i class="fas fa-chalkboard-teacher text-primary"></i>
                                        <?php echo htmlspecialchars($class['teacher_name']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Not Assigned</span>
                                    <?php endif; ?>
                                </td>
                                <?php if (hasRole('teacher')): ?>
                                <td>
                                    <span class="badge bg-success">
                                        <i class="fas fa-book"></i> <?php echo isset($class['my_subjects_count']) ? $class['my_subjects_count'] : 0; ?> Subjects
                                    </span>
                                </td>
                                <?php else: ?>
                                <td>
                                    <?php echo $class['room_number'] ? htmlspecialchars($class['room_number']) : '<span class="text-muted">N/A</span>'; ?>
                                </td>
                                <td>
                                    <?php echo $class['capacity'] ? $class['capacity'] : '<span class="text-muted">N/A</span>'; ?>
                                </td>
                                <?php endif; ?>
                                <td>
                                    <span class="badge bg-info">
                                        <i class="fas fa-users"></i> <?php echo $class['student_count']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="<?php echo APP_URL;?>/classes/view.php?id=<?php echo $class['class_id']; ?>" 
                                           class="btn btn-sm btn-info" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if (hasRole('admin')): ?>
                                        <a href="<?php echo APP_URL;?>/classes/edit.php?id=<?php echo $class['class_id']; ?>" 
                                           class="btn btn-sm btn-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="<?php echo APP_URL;?>/classes/delete.php?id=<?php echo $class['class_id']; ?>" 
                                           class="btn btn-sm btn-danger" title="Delete"
                                           onclick="return confirm('Are you sure you want to delete this class?');">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle"></i> No classes found. 
                    <?php if (hasRole('admin')): ?>
                    <a href="<?php echo APP_URL;?>/classes/add.php" class="alert-link">Add your first class</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
