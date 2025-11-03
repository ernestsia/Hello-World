<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireLogin();

// Only parents can access this page
if (!hasRole('parent')) {
    redirect(APP_URL . '/index.php');
}

$pageTitle = 'Student Details';
$db = new Database();
$conn = $db->getConnection();

$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($student_id === 0) {
    setFlashMessage('danger', 'Invalid student ID');
    redirect(APP_URL . '/parents/dashboard.php');
}

// Verify this student belongs to the logged-in parent
$stmt = $conn->prepare("SELECT s.*, c.class_name, c.section, u.username, u.email, u.status
                        FROM students s
                        LEFT JOIN classes c ON s.class_id = c.class_id
                        LEFT JOIN users u ON s.user_id = u.user_id
                        WHERE s.student_id = ? AND s.parent_email = ?");
$stmt->bind_param("is", $student_id, $_SESSION['email']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setFlashMessage('danger', 'Access denied or student not found');
    redirect(APP_URL . '/parents/dashboard.php');
}

$student = $result->fetch_assoc();
$stmt->close();

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-user-graduate"></i> Student Details</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/parents/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Student Details</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <!-- Profile Card -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <?php if (!empty($student['photo'])): ?>
                <img src="<?php echo APP_URL;?>/uploads/students/<?php echo $student['photo']; ?>" 
                     class="img-fluid rounded-circle mb-3" style="max-width: 200px; border: 5px solid #667eea;" alt="Student Photo">
                <?php else: ?>
                <div class="mb-3">
                    <i class="fas fa-user-circle fa-10x text-primary"></i>
                </div>
                <?php endif; ?>
                
                <h4 class="mb-2"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h4>
                <p class="text-muted mb-2">
                    <span class="badge bg-primary">Roll No: <?php echo htmlspecialchars($student['roll_number']); ?></span>
                </p>
                <p class="mb-2">
                    <?php if ($student['class_name']): ?>
                    <span class="badge bg-info"><?php echo htmlspecialchars($student['class_name'] . ' - ' . $student['section']); ?></span>
                    <?php endif; ?>
                </p>
                <p class="mb-0">
                    <?php if ($student['status'] === 'active'): ?>
                    <span class="badge bg-success">Active</span>
                    <?php else: ?>
                    <span class="badge bg-danger">Inactive</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
        <!-- Quick Links -->
        <div class="card mt-3 border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0"><i class="fas fa-link"></i> Quick Links</h6>
            </div>
            <div class="card-body">
                <a href="<?php echo APP_URL;?>/parents/student-grades.php?id=<?php echo $student_id; ?>" 
                   class="btn btn-success btn-sm w-100 mb-2">
                    <i class="fas fa-chart-line"></i> View Grades
                </a>
                <a href="<?php echo APP_URL;?>/parents/student-attendance.php?id=<?php echo $student_id; ?>" 
                   class="btn btn-info btn-sm w-100">
                    <i class="fas fa-calendar-alt"></i> View Attendance
                </a>
            </div>
        </div>
    </div>
    
    <!-- Details Card -->
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-user"></i> Personal Information</h5>
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
        
        <div class="card mt-3 border-0 shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-graduation-cap"></i> Academic Information</h5>
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
    </div>
</div>

<?php include '../includes/footer.php'; ?>
