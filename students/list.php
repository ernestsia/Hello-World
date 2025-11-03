<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireLogin();

$pageTitle = 'Students List';
$db = new Database();
$conn = $db->getConnection();

// Get current page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// Search functionality
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$class_filter = isset($_GET['class']) ? (int)$_GET['class'] : 0;

// Build query with role-based filtering
$where = "WHERE 1=1";

// Teachers only see students from classes where they teach subjects
if (hasRole('teacher')) {
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
        // Show students from classes where teacher teaches any subject OR is class teacher
        $where .= " AND (s.class_id IN (SELECT DISTINCT class_id FROM class_subjects WHERE teacher_id = $teacher_id)
                    OR s.class_id IN (SELECT class_id FROM classes WHERE class_teacher_id = $teacher_id))";
    } else {
        $where .= " AND 1=0"; // No students if teacher not found
    }
}

if (!empty($search)) {
    $search_term = $conn->real_escape_string($search);
    $where .= " AND (s.first_name LIKE '%$search_term%' OR s.last_name LIKE '%$search_term%' OR s.roll_number LIKE '%$search_term%')";
}
if ($class_filter > 0) {
    $where .= " AND s.class_id = $class_filter";
}

// Get total records
$count_query = "SELECT COUNT(*) as total FROM students s $where";
$count_result = $conn->query($count_query);
$total_records = $count_result->fetch_assoc()['total'];

// Pagination
$pagination = getPaginationData($total_records, $page);

// Get students
$query = "SELECT s.*, c.class_name, c.section, u.status
          FROM students s
          LEFT JOIN classes c ON s.class_id = c.class_id
          LEFT JOIN users u ON s.user_id = u.user_id
          $where
          ORDER BY s.first_name, s.last_name
          LIMIT {$pagination['offset']}, {$pagination['records_per_page']}";
$result = $conn->query($query);

// Get classes for filter based on user role
if (hasRole('teacher')) {
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
        // Show classes where teacher teaches any subject OR is class teacher
        $stmt = $conn->prepare("SELECT DISTINCT c.class_id, c.class_name, c.section 
                               FROM classes c
                               WHERE c.class_teacher_id = ? 
                               OR c.class_id IN (SELECT DISTINCT class_id FROM class_subjects WHERE teacher_id = ?)
                               ORDER BY c.class_name, c.section");
        $stmt->bind_param("ii", $teacher_id, $teacher_id);
        $stmt->execute();
        $classes_result = $stmt->get_result();
    } else {
        $classes_result = $conn->query("SELECT class_id, class_name, section FROM classes WHERE 1=0");
    }
} else {
    $classes_result = $conn->query("SELECT class_id, class_name, section FROM classes ORDER BY class_name");
}

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-user-graduate"></i> Students Management</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Students</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Search and Filter -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-5">
                        <input type="text" class="form-control" name="search" placeholder="Search by name or roll number" 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="class">
                            <option value="0">All Classes</option>
                            <?php while ($class = $classes_result->fetch_assoc()): ?>
                            <option value="<?php echo $class['class_id']; ?>" 
                                    <?php echo $class_filter == $class['class_id'] ? 'selected' : ''; ?>>
                                <?php echo $class['class_name'] . ' - ' . $class['section']; ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <a href="<?php echo APP_URL;?>/students/list.php" class="btn btn-secondary">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                        <?php if (hasRole('admin')): ?>
                        <a href="<?php echo APP_URL;?>/students/add.php" class="btn btn-success">
                            <i class="fas fa-plus"></i> Add Student
                        </a>
                        <a href="<?php echo APP_URL;?>/students/bulk-upload.php" class="btn btn-info">
                            <i class="fas fa-file-upload"></i> Bulk Upload
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Students Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    Students List 
                    <span class="badge bg-primary"><?php echo $total_records; ?> Total</span>
                </h5>
            </div>
            <div class="card-body">
                <?php if ($result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Roll No</th>
                                <th>Name</th>
                                <th>Class</th>
                                <th>Gender</th>
                                <th>Phone</th>
                                <th>Admission Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($student = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['roll_number']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong>
                                </td>
                                <td>
                                    <?php 
                                    if ($student['class_name']) {
                                        echo htmlspecialchars($student['class_name'] . ' - ' . $student['section']);
                                    } else {
                                        echo '<span class="text-muted">Not Assigned</span>';
                                    }
                                    ?>
                                </td>
                                <td><?php echo ucfirst($student['gender']); ?></td>
                                <td><?php echo htmlspecialchars($student['phone']); ?></td>
                                <td><?php echo formatDate($student['admission_date']); ?></td>
                                <td>
                                    <?php if ($student['status'] === 'active'): ?>
                                    <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                    <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo APP_URL;?>/students/view.php?id=<?php echo $student['student_id']; ?>" 
                                       class="btn btn-sm btn-info" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if (hasRole('admin')): ?>
                                    <a href="<?php echo APP_URL;?>/students/edit.php?id=<?php echo $student['student_id']; ?>" 
                                       class="btn btn-sm btn-warning" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="<?php echo APP_URL;?>/students/delete.php?id=<?php echo $student['student_id']; ?>" 
                                       class="btn btn-sm btn-danger" title="Delete"
                                       onclick="return confirm('Are you sure you want to delete this student?');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php echo renderPagination($pagination, APP_URL . '/students/list.php' . (!empty($search) ? '&search=' . urlencode($search) : '') . ($class_filter > 0 ? '&class=' . $class_filter : '')); ?>
                
                <?php else: ?>
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle"></i> No students found.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
