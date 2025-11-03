<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireLogin();

$pageTitle = 'View Student';
$db = new Database();
$conn = $db->getConnection();

$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// If student role, only allow viewing own profile
if (hasRole('student')) {
    // Get student_id from user_id
    $stmt = $conn->prepare("SELECT student_id FROM students WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $student_id = $result->fetch_assoc()['student_id'];
    } else {
        setFlashMessage('danger', 'Student profile not found');
        redirect(APP_URL . '/index.php');
    }
    $stmt->close();
}

if ($student_id === 0) {
    setFlashMessage('danger', 'Invalid student ID');
    redirect(APP_URL . '/students/list.php');
}

// Get student details
$query = "SELECT s.*, c.class_name, c.section, u.username, u.email, u.status
          FROM students s
          LEFT JOIN classes c ON s.class_id = c.class_id
          LEFT JOIN users u ON s.user_id = u.user_id
          WHERE s.student_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setFlashMessage('danger', 'Student not found');
    redirect(APP_URL . '/students/list.php');
}

$student = $result->fetch_assoc();
$stmt->close();

// Get attendance statistics
$attendance_query = "SELECT 
                        COUNT(*) as total_days,
                        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
                        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days
                     FROM attendance 
                     WHERE student_id = ?";
$stmt = $conn->prepare($attendance_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$attendance_stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get recent grades
$grades_query = "SELECT g.*, e.exam_name, e.total_marks, s.subject_name, e.exam_date
                 FROM grades g
                 JOIN exams e ON g.exam_id = e.exam_id
                 JOIN subjects s ON e.subject_id = s.subject_id
                 WHERE g.student_id = ?
                 ORDER BY e.exam_date DESC
                 LIMIT 5";
$stmt = $conn->prepare($grades_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$grades_result = $stmt->get_result();
$stmt->close();

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-user-graduate"></i> Student Details</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/students/list.php">Students</a></li>
                <li class="breadcrumb-item active">View Student</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <!-- Profile Card -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <?php if (!empty($student['photo'])): ?>
                <img src="<?php echo APP_URL;?>/uploads/students/<?php echo $student['photo']; ?>" 
                     class="img-fluid rounded-circle mb-3" style="max-width: 200px;" alt="Student Photo">
                <?php else: ?>
                <div class="mb-3">
                    <i class="fas fa-user-circle fa-10x text-muted"></i>
                </div>
                <?php endif; ?>
                
                <h4><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h4>
                <p class="text-muted mb-2">
                    <span class="badge bg-primary">Roll No: <?php echo htmlspecialchars($student['roll_number']); ?></span>
                </p>
                <p class="mb-2">
                    <?php if ($student['status'] === 'active'): ?>
                    <span class="badge bg-success">Active</span>
                    <?php else: ?>
                    <span class="badge bg-danger">Inactive</span>
                    <?php endif; ?>
                </p>
                
                <?php if (hasRole('admin')): ?>
                <div class="mt-3">
                    <a href="<?php echo APP_URL;?>/students/edit.php?id=<?php echo $student_id; ?>" 
                       class="btn btn-warning btn-sm">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <a href="<?php echo APP_URL;?>/students/delete.php?id=<?php echo $student_id; ?>" 
                       class="btn btn-danger btn-sm"
                       onclick="return confirm('Are you sure you want to delete this student?');">
                        <i class="fas fa-trash"></i> Delete
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Attendance Statistics -->
        <div class="card mt-3">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0"><i class="fas fa-calendar-check"></i> Attendance Statistics</h6>
            </div>
            <div class="card-body">
                <div class="mb-2">
                    <strong>Total Days:</strong> <?php echo $attendance_stats['total_days']; ?>
                </div>
                <div class="mb-2">
                    <strong>Present:</strong> <span class="text-success"><?php echo $attendance_stats['present_days']; ?></span>
                </div>
                <div class="mb-2">
                    <strong>Absent:</strong> <span class="text-danger"><?php echo $attendance_stats['absent_days']; ?></span>
                </div>
                <div class="mb-2">
                    <strong>Late:</strong> <span class="text-warning"><?php echo $attendance_stats['late_days']; ?></span>
                </div>
                <?php if ($attendance_stats['total_days'] > 0): ?>
                <div class="mt-3">
                    <strong>Attendance Rate:</strong>
                    <div class="progress">
                        <?php 
                        $attendance_rate = ($attendance_stats['present_days'] / $attendance_stats['total_days']) * 100;
                        ?>
                        <div class="progress-bar bg-success" role="progressbar" 
                             style="width: <?php echo $attendance_rate; ?>%">
                            <?php echo number_format($attendance_rate, 1); ?>%
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Details Card -->
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Personal Information</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tbody>
                        <tr>
                            <th width="30%">Username:</th>
                            <td><?php echo htmlspecialchars($student['username']); ?></td>
                        </tr>
                        <tr>
                            <th>Email:</th>
                            <td><?php echo htmlspecialchars($student['email']); ?></td>
                        </tr>
                        <tr>
                            <th>Date of Birth:</th>
                            <td><?php echo formatDate($student['date_of_birth']); ?> (Age: <?php echo calculateAge($student['date_of_birth']); ?>)</td>
                        </tr>
                        <tr>
                            <th>Gender:</th>
                            <td><?php echo ucfirst($student['gender']); ?></td>
                        </tr>
                        <tr>
                            <th>Phone:</th>
                            <td><?php echo htmlspecialchars($student['phone']); ?></td>
                        </tr>
                        <tr>
                            <th>Address:</th>
                            <td><?php echo nl2br(htmlspecialchars($student['address'])); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Academic Information</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tbody>
                        <tr>
                            <th width="30%">Roll Number:</th>
                            <td><?php echo htmlspecialchars($student['roll_number']); ?></td>
                        </tr>
                        <tr>
                            <th>Class:</th>
                            <td>
                                <?php 
                                if ($student['class_name']) {
                                    echo htmlspecialchars($student['class_name'] . ' - ' . $student['section']);
                                } else {
                                    echo '<span class="text-muted">Not Assigned</span>';
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Admission Date:</th>
                            <td><?php echo formatDate($student['admission_date']); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header bg-warning text-white">
                <h5 class="mb-0">Parent/Guardian Information</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tbody>
                        <tr>
                            <th width="30%">Parent Name:</th>
                            <td><?php echo htmlspecialchars($student['parent_name']); ?></td>
                        </tr>
                        <tr>
                            <th>Parent Phone:</th>
                            <td><?php echo htmlspecialchars($student['parent_phone']); ?></td>
                        </tr>
                        <tr>
                            <th>Parent Email:</th>
                            <td><?php echo htmlspecialchars($student['parent_email']); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Recent Grades -->
        <div class="card mt-3">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">Recent Exam Results</h5>
            </div>
            <div class="card-body">
                <?php if ($grades_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Exam</th>
                                <th>Subject</th>
                                <th>Date</th>
                                <th>Marks</th>
                                <th>Percentage</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($grade = $grades_result->fetch_assoc()): 
                                $percentage = ($grade['marks_obtained'] / $grade['total_marks']) * 100;
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($grade['exam_name']); ?></td>
                                <td><?php echo htmlspecialchars($grade['subject_name']); ?></td>
                                <td><?php echo formatDate($grade['exam_date']); ?></td>
                                <td><?php echo $grade['marks_obtained'] . '/' . $grade['total_marks']; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $percentage >= 60 ? 'success' : 'danger'; ?>">
                                        <?php echo number_format($percentage, 1); ?>%
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-muted mb-0">No exam results available.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
