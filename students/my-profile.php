<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireLogin();

// Only students can access this page
if (!hasRole('student')) {
    redirect(APP_URL . '/index.php');
}

$pageTitle = 'My Profile';
$db = new Database();
$conn = $db->getConnection();

// Get student's own information
$stmt = $conn->prepare("SELECT s.*, c.class_name, c.section, u.username, u.email, u.status
                        FROM students s
                        LEFT JOIN classes c ON s.class_id = c.class_id
                        LEFT JOIN users u ON s.user_id = u.user_id
                        WHERE s.user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setFlashMessage('danger', 'Student profile not found');
    redirect(APP_URL . '/index.php');
}

$student = $result->fetch_assoc();
$student_id = $student['student_id'];
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
$grades_query = "SELECT g.*, e.exam_name, e.total_marks, e.passing_marks, s.subject_name, e.exam_date
                 FROM grades g
                 JOIN exams e ON g.exam_id = e.exam_id
                 JOIN subjects s ON e.subject_id = s.subject_id
                 WHERE g.student_id = ?
                 ORDER BY e.exam_date DESC
                 LIMIT 10";
$stmt = $conn->prepare($grades_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$grades_result = $stmt->get_result();
$stmt->close();

// Get recent attendance
$recent_attendance_query = "SELECT attendance_date, status, remarks
                            FROM attendance
                            WHERE student_id = ?
                            ORDER BY attendance_date DESC
                            LIMIT 10";
$stmt = $conn->prepare($recent_attendance_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$recent_attendance = $stmt->get_result();
$stmt->close();

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-user-circle"></i> My Profile</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">My Profile</li>
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
            </div>
        </div>
        
        <!-- Attendance Statistics -->
        <div class="card mt-3 border-0 shadow-sm">
            <div class="card-header bg-gradient" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                <h6 class="mb-0"><i class="fas fa-calendar-check"></i> Attendance Overview</h6>
            </div>
            <div class="card-body">
                <?php if ($attendance_stats['total_days'] > 0): ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span><strong>Total Days:</strong></span>
                        <span class="badge bg-secondary"><?php echo $attendance_stats['total_days']; ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span><strong>Present:</strong></span>
                        <span class="badge bg-success"><?php echo $attendance_stats['present_days']; ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span><strong>Absent:</strong></span>
                        <span class="badge bg-danger"><?php echo $attendance_stats['absent_days']; ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span><strong>Late:</strong></span>
                        <span class="badge bg-warning"><?php echo $attendance_stats['late_days']; ?></span>
                    </div>
                    
                    <?php 
                    $attendance_rate = ($attendance_stats['present_days'] / $attendance_stats['total_days']) * 100;
                    $progress_color = $attendance_rate >= 75 ? 'success' : ($attendance_rate >= 50 ? 'warning' : 'danger');
                    ?>
                    <div>
                        <strong>Attendance Rate:</strong>
                        <div class="progress mt-2" style="height: 25px;">
                            <div class="progress-bar bg-<?php echo $progress_color; ?>" role="progressbar" 
                                 style="width: <?php echo $attendance_rate; ?>%">
                                <?php echo number_format($attendance_rate, 1); ?>%
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                <p class="text-muted mb-0">No attendance records yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Details and Performance -->
    <div class="col-md-8">
        <!-- Personal Information -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-user"></i> Personal Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-2">
                        <strong><i class="fas fa-envelope text-primary"></i> Email:</strong><br>
                        <?php echo htmlspecialchars($student['email']); ?>
                    </div>
                    <div class="col-md-6 mb-2">
                        <strong><i class="fas fa-phone text-success"></i> Phone:</strong><br>
                        <?php echo htmlspecialchars($student['phone']); ?>
                    </div>
                    <div class="col-md-6 mb-2">
                        <strong><i class="fas fa-birthday-cake text-warning"></i> Date of Birth:</strong><br>
                        <?php echo formatDate($student['date_of_birth']); ?> (Age: <?php echo calculateAge($student['date_of_birth']); ?>)
                    </div>
                    <div class="col-md-6 mb-2">
                        <strong><i class="fas fa-venus-mars text-info"></i> Gender:</strong><br>
                        <?php echo ucfirst($student['gender']); ?>
                    </div>
                    <div class="col-12 mb-2">
                        <strong><i class="fas fa-map-marker-alt text-danger"></i> Address:</strong><br>
                        <?php echo nl2br(htmlspecialchars($student['address'])); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Parent Information -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-users"></i> Parent/Guardian Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-2">
                        <strong>Name:</strong><br>
                        <?php echo htmlspecialchars($student['parent_name']); ?>
                    </div>
                    <div class="col-md-4 mb-2">
                        <strong>Phone:</strong><br>
                        <?php echo htmlspecialchars($student['parent_phone']); ?>
                    </div>
                    <div class="col-md-4 mb-2">
                        <strong>Email:</strong><br>
                        <?php echo htmlspecialchars($student['parent_email']); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Exam Results -->
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-graduation-cap"></i> Recent Exam Results</h5>
                <a href="<?php echo APP_URL;?>/students/my-grades.php" class="btn btn-sm btn-light">
                    <i class="fas fa-chart-line"></i> View All Grades
                </a>
            </div>
            <div class="card-body">
                <?php if ($grades_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Exam</th>
                                <th>Subject</th>
                                <th>Date</th>
                                <th>Marks</th>
                                <th>Grade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($grade = $grades_result->fetch_assoc()): 
                                $percentage = ($grade['marks_obtained'] / $grade['total_marks']) * 100;
                                $passed = $grade['marks_obtained'] >= $grade['passing_marks'];
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($grade['exam_name']); ?></td>
                                <td><?php echo htmlspecialchars($grade['subject_name']); ?></td>
                                <td><?php echo formatDate($grade['exam_date']); ?></td>
                                <td><?php echo $grade['marks_obtained'] . '/' . $grade['total_marks']; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $passed ? 'success' : 'danger'; ?>">
                                        <?php echo number_format($percentage, 1); ?>%
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-muted mb-0">No exam results available yet.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Attendance -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-warning text-white">
                <h5 class="mb-0"><i class="fas fa-calendar-alt"></i> Recent Attendance</h5>
            </div>
            <div class="card-body">
                <?php if ($recent_attendance->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($att = $recent_attendance->fetch_assoc()): 
                                $badge_class = 'secondary';
                                if ($att['status'] === 'present') $badge_class = 'success';
                                elseif ($att['status'] === 'absent') $badge_class = 'danger';
                                elseif ($att['status'] === 'late') $badge_class = 'warning';
                                elseif ($att['status'] === 'excused') $badge_class = 'info';
                            ?>
                            <tr>
                                <td><?php echo formatDate($att['attendance_date']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $badge_class; ?>">
                                        <?php echo ucfirst($att['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($att['remarks']); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-muted mb-0">No attendance records available yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
