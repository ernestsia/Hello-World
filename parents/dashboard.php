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

$pageTitle = 'Parent Dashboard';
$db = new Database();
$conn = $db->getConnection();

// Get parent's children (students with matching parent email)
$parent_email = $_SESSION['email'];

$students_query = "SELECT s.*, c.class_name, c.section, u.status
                   FROM students s
                   LEFT JOIN classes c ON s.class_id = c.class_id
                   LEFT JOIN users u ON s.user_id = u.user_id
                   WHERE s.parent_email = ?
                   ORDER BY s.first_name, s.last_name";
$stmt = $conn->prepare($students_query);
$stmt->bind_param("s", $parent_email);
$stmt->execute();
$students_result = $stmt->get_result();
$stmt->close();

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-lg" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <div class="card-body text-white p-4">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="mb-2">
                            <i class="fas fa-users"></i> Welcome, Parent!
                        </h2>
                        <p class="mb-0 opacity-75">
                            <i class="fas fa-calendar-day"></i> <?php echo date('l, F d, Y'); ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-end d-none d-md-block">
                        <i class="fas fa-user-friends fa-5x opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <h4><i class="fas fa-child"></i> My Children</h4>
    </div>
</div>

<?php if ($students_result->num_rows > 0): ?>
    <?php while ($student = $students_result->fetch_assoc()): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-user-graduate"></i> 
                        <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                        <span class="badge bg-light text-dark float-end">
                            Roll No: <?php echo htmlspecialchars($student['roll_number']); ?>
                        </span>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <!-- Student Info -->
                        <div class="col-md-4">
                            <h6 class="text-primary"><i class="fas fa-info-circle"></i> Student Information</h6>
                            <p class="mb-1"><strong>Class:</strong> 
                                <?php 
                                if ($student['class_name']) {
                                    echo htmlspecialchars($student['class_name'] . ' - ' . $student['section']);
                                } else {
                                    echo '<span class="text-muted">Not Assigned</span>';
                                }
                                ?>
                            </p>
                            <p class="mb-1"><strong>Gender:</strong> <?php echo ucfirst($student['gender']); ?></p>
                            <p class="mb-1"><strong>Date of Birth:</strong> <?php echo formatDate($student['date_of_birth']); ?></p>
                            <p class="mb-1"><strong>Admission Date:</strong> <?php echo formatDate($student['admission_date']); ?></p>
                            <p class="mb-0">
                                <strong>Status:</strong> 
                                <?php if ($student['status'] === 'active'): ?>
                                <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                <span class="badge bg-danger">Inactive</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <!-- Attendance Stats -->
                        <div class="col-md-4">
                            <?php
                            // Get attendance statistics for this student
                            $att_query = "SELECT 
                                            COUNT(*) as total_days,
                                            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
                                            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days
                                         FROM attendance 
                                         WHERE student_id = ?";
                            $stmt = $conn->prepare($att_query);
                            $stmt->bind_param("i", $student['student_id']);
                            $stmt->execute();
                            $att_stats = $stmt->get_result()->fetch_assoc();
                            $stmt->close();
                            
                            $attendance_rate = $att_stats['total_days'] > 0 
                                ? ($att_stats['present_days'] / $att_stats['total_days']) * 100 
                                : 0;
                            ?>
                            <h6 class="text-success"><i class="fas fa-calendar-check"></i> Attendance</h6>
                            <p class="mb-1"><strong>Total Days:</strong> <?php echo $att_stats['total_days']; ?></p>
                            <p class="mb-1"><strong>Present:</strong> <span class="text-success"><?php echo $att_stats['present_days']; ?></span></p>
                            <p class="mb-2"><strong>Absent:</strong> <span class="text-danger"><?php echo $att_stats['absent_days']; ?></span></p>
                            <?php if ($att_stats['total_days'] > 0): ?>
                            <div class="progress" style="height: 25px;">
                                <div class="progress-bar bg-success" role="progressbar" 
                                     style="width: <?php echo $attendance_rate; ?>%">
                                    <?php echo number_format($attendance_rate, 1); ?>%
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Recent Grades -->
                        <div class="col-md-4">
                            <?php
                            // Get recent grades for this student
                            $grades_query = "SELECT AVG((g.marks_obtained / e.total_marks) * 100) as avg_percentage,
                                                   COUNT(*) as total_exams
                                            FROM grades g
                                            JOIN exams e ON g.exam_id = e.exam_id
                                            WHERE g.student_id = ?";
                            $stmt = $conn->prepare($grades_query);
                            $stmt->bind_param("i", $student['student_id']);
                            $stmt->execute();
                            $grade_stats = $stmt->get_result()->fetch_assoc();
                            $stmt->close();
                            ?>
                            <h6 class="text-info"><i class="fas fa-graduation-cap"></i> Academic Performance</h6>
                            <p class="mb-1"><strong>Total Exams:</strong> <?php echo $grade_stats['total_exams']; ?></p>
                            <?php if ($grade_stats['total_exams'] > 0): ?>
                            <p class="mb-2"><strong>Average Score:</strong></p>
                            <div class="progress" style="height: 25px;">
                                <div class="progress-bar bg-info" role="progressbar" 
                                     style="width: <?php echo $grade_stats['avg_percentage']; ?>%">
                                    <?php echo number_format($grade_stats['avg_percentage'], 1); ?>%
                                </div>
                            </div>
                            <?php else: ?>
                            <p class="text-muted">No exam results yet</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="text-center">
                        <a href="<?php echo APP_URL;?>/parents/student-details.php?id=<?php echo $student['student_id']; ?>" 
                           class="btn btn-primary">
                            <i class="fas fa-eye"></i> View Full Details
                        </a>
                        <a href="<?php echo APP_URL;?>/parents/student-grades.php?id=<?php echo $student['student_id']; ?>" 
                           class="btn btn-success">
                            <i class="fas fa-chart-line"></i> View Grades
                        </a>
                        <a href="<?php echo APP_URL;?>/parents/student-attendance.php?id=<?php echo $student['student_id']; ?>" 
                           class="btn btn-info">
                            <i class="fas fa-calendar-alt"></i> View Attendance
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endwhile; ?>
<?php else: ?>
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-user-graduate fa-5x text-muted mb-3"></i>
                <h4 class="text-muted">No Children Found</h4>
                <p class="text-muted">No students are linked to your account. Please contact the school administration.</p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Recent Announcements -->
<?php
$announcements_query = "SELECT * FROM announcements 
                        WHERE status = 'active' 
                        AND (target_audience = 'all' OR target_audience = 'parents')
                        AND (expiry_date IS NULL OR expiry_date >= CURDATE())
                        ORDER BY posted_date DESC 
                        LIMIT 5";
$announcements_result = $conn->query($announcements_query);
?>

<?php if ($announcements_result->num_rows > 0): ?>
<div class="row mt-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-warning text-white">
                <h5 class="mb-0"><i class="fas fa-bullhorn"></i> Recent Announcements</h5>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <?php while ($announcement = $announcements_result->fetch_assoc()): ?>
                    <div class="list-group-item">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><?php echo htmlspecialchars($announcement['title']); ?></h6>
                            <small class="text-muted"><?php echo formatDate($announcement['posted_date']); ?></small>
                        </div>
                        <p class="mb-1"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
