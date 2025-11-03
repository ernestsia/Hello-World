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

$pageTitle = 'Student Dashboard';
$db = new Database();
$conn = $db->getConnection();

// Get student information
$user_id = $_SESSION['user_id'];
$student_query = "SELECT s.*, c.class_name, c.section, u.email
                  FROM students s
                  LEFT JOIN classes c ON s.class_id = c.class_id
                  LEFT JOIN users u ON s.user_id = u.user_id
                  WHERE s.user_id = ?";
$stmt = $conn->prepare($student_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    setFlashMessage('error', 'Student profile not found.');
    redirect(APP_URL . '/index.php');
}

$student_id = $student['student_id'];

// Get attendance statistics
$att_query = "SELECT 
                COUNT(*) as total_days,
                SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
                SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days,
                SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused_days
             FROM attendance 
             WHERE student_id = ?";
$stmt = $conn->prepare($att_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$att_stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

$attendance_rate = $att_stats['total_days'] > 0 
    ? ($att_stats['present_days'] / $att_stats['total_days']) * 100 
    : 0;

// Get grade statistics
$grades_query = "SELECT 
                    COUNT(DISTINCT e.exam_id) as total_exams,
                    AVG((g.marks_obtained / e.total_marks) * 100) as avg_percentage,
                    SUM(CASE WHEN g.marks_obtained >= e.passing_marks THEN 1 ELSE 0 END) as passed_exams
                 FROM grades g
                 JOIN exams e ON g.exam_id = e.exam_id
                 WHERE g.student_id = ?";
$stmt = $conn->prepare($grades_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$grade_stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get recent grades
$recent_grades_query = "SELECT e.exam_name, s.subject_name, e.exam_date, 
                               g.marks_obtained, e.total_marks, e.passing_marks,
                               (g.marks_obtained / e.total_marks * 100) as percentage
                        FROM grades g
                        JOIN exams e ON g.exam_id = e.exam_id
                        JOIN subjects s ON e.subject_id = s.subject_id
                        WHERE g.student_id = ?
                        ORDER BY e.exam_date DESC
                        LIMIT 5";
$stmt = $conn->prepare($recent_grades_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$recent_grades = $stmt->get_result();
$stmt->close();

// Get class subjects and teachers
$subjects_query = "SELECT DISTINCT s.subject_id, s.subject_name, s.subject_code, 
                          CONCAT(t.first_name, ' ', t.last_name) as teacher_name
                   FROM class_subjects cs
                   JOIN subjects s ON cs.subject_id = s.subject_id
                   LEFT JOIN teachers t ON cs.teacher_id = t.teacher_id
                   WHERE cs.class_id = ?
                   GROUP BY s.subject_id
                   ORDER BY s.subject_name";
$stmt = $conn->prepare($subjects_query);
$stmt->bind_param("i", $student['class_id']);
$stmt->execute();
$subjects = $stmt->get_result();
$stmt->close();

// Get recent announcements
$announcements_query = "SELECT * FROM announcements 
                        WHERE status = 'active' 
                        AND (target_audience = 'all' OR target_audience = 'students')
                        AND (expiry_date IS NULL OR expiry_date >= CURDATE())
                        ORDER BY posted_date DESC 
                        LIMIT 3";
$announcements = $conn->query($announcements_query);

include '../includes/header.php';
?>

<!-- Welcome Banner -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-lg welcome-banner-black" style="background: #000000 !important;">
            <div class="card-body text-white p-4" style="background: #000000 !important;">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="mb-2 text-white">
                            <i class="fas fa-user-graduate"></i> Welcome Back, <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>!
                        </h2>
                        <p class="mb-0 text-white" style="opacity: 0.85;">
                            <i class="fas fa-calendar-day"></i> <?php echo date('l, F d, Y'); ?> | 
                            <i class="fas fa-door-open"></i> Class: <?php echo htmlspecialchars($student['class_name'] . ' - ' . $student['section']); ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-end d-none d-md-block">
                        <i class="fas fa-graduation-cap fa-5x text-white" style="opacity: 0.25;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <!-- Attendance Card -->
    <div class="col-md-4 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="stat-icon bg-success bg-opacity-10 text-success rounded-circle p-3">
                        <i class="fas fa-calendar-check fa-2x"></i>
                    </div>
                    <span class="badge bg-success">Attendance</span>
                </div>
                <h6 class="text-muted mb-1">Attendance Rate</h6>
                <h2 class="mb-3 fw-bold"><?php echo number_format($attendance_rate, 1); ?>%</h2>
                <div class="progress mb-2" style="height: 10px;">
                    <div class="progress-bar bg-success" role="progressbar" 
                         style="width: <?php echo $attendance_rate; ?>%"></div>
                </div>
                <small class="text-muted">
                    <?php echo $att_stats['present_days']; ?> present / <?php echo $att_stats['total_days']; ?> total days
                </small>
                <div class="mt-3">
                    <a href="<?php echo APP_URL;?>/students/my-attendance.php" class="text-success text-decoration-none small">
                        View Details <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Academic Performance Card -->
    <div class="col-md-4 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary rounded-circle p-3">
                        <i class="fas fa-chart-line fa-2x"></i>
                    </div>
                    <span class="badge bg-primary">Academic</span>
                </div>
                <h6 class="text-muted mb-1">Average Score</h6>
                <h2 class="mb-3 fw-bold">
                    <?php echo $grade_stats['total_exams'] > 0 ? number_format($grade_stats['avg_percentage'], 1) . '%' : 'N/A'; ?>
                </h2>
                <?php if ($grade_stats['total_exams'] > 0): ?>
                <div class="progress mb-2" style="height: 10px;">
                    <div class="progress-bar bg-primary" role="progressbar" 
                         style="width: <?php echo $grade_stats['avg_percentage']; ?>%"></div>
                </div>
                <small class="text-muted">
                    <?php echo $grade_stats['passed_exams']; ?> passed / <?php echo $grade_stats['total_exams']; ?> exams
                </small>
                <?php else: ?>
                <p class="text-muted mb-0">No exam results yet</p>
                <?php endif; ?>
                <div class="mt-3">
                    <a href="<?php echo APP_URL;?>/students/my-grades.php" class="text-primary text-decoration-none small">
                        View Grades <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Class Info Card -->
    <div class="col-md-4 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="stat-icon bg-info bg-opacity-10 text-info rounded-circle p-3">
                        <i class="fas fa-book fa-2x"></i>
                    </div>
                    <span class="badge bg-info">Subjects</span>
                </div>
                <h6 class="text-muted mb-1">Total Subjects</h6>
                <h2 class="mb-3 fw-bold"><?php echo $subjects->num_rows; ?></h2>
                <p class="text-muted mb-0">
                    <i class="fas fa-door-open"></i> <?php echo htmlspecialchars($student['class_name'] . ' - ' . $student['section']); ?>
                </p>
                <div class="mt-3">
                    <a href="<?php echo APP_URL;?>/students/my-teachers.php" class="text-info text-decoration-none small">
                        View Teachers <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-bolt"></i> Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <a href="<?php echo APP_URL;?>/students/my-grade-sheet.php" class="btn btn-outline-primary w-100">
                            <i class="fas fa-file-alt"></i> My Grade Sheet
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="<?php echo APP_URL;?>/students/my-grades.php" class="btn btn-outline-success w-100">
                            <i class="fas fa-graduation-cap"></i> My Grades
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="<?php echo APP_URL;?>/students/my-attendance.php" class="btn btn-outline-info w-100">
                            <i class="fas fa-calendar-check"></i> My Attendance
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="<?php echo APP_URL;?>/students/my-profile.php" class="btn btn-outline-warning w-100">
                            <i class="fas fa-user"></i> My Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recent Grades -->
    <div class="col-md-6 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Recent Exam Results</h5>
            </div>
            <div class="card-body">
                <?php if ($recent_grades->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Exam</th>
                                <th>Subject</th>
                                <th>Score</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($grade = $recent_grades->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($grade['exam_name']); ?></strong>
                                    <br><small class="text-muted"><?php echo formatDate($grade['exam_date']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($grade['subject_name']); ?></td>
                                <td>
                                    <strong><?php echo $grade['marks_obtained']; ?>/<?php echo $grade['total_marks']; ?></strong>
                                    <br><small class="text-muted"><?php echo number_format($grade['percentage'], 1); ?>%</small>
                                </td>
                                <td>
                                    <?php if ($grade['marks_obtained'] >= $grade['passing_marks']): ?>
                                    <span class="badge bg-success">Pass</span>
                                    <?php else: ?>
                                    <span class="badge bg-danger">Fail</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-muted text-center py-4">No exam results available yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- My Subjects & Teachers -->
    <div class="col-md-6 mb-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-book-open"></i> My Subjects & Teachers</h5>
            </div>
            <div class="card-body">
                <?php if ($subjects->num_rows > 0): ?>
                <div class="list-group">
                    <?php while ($subject = $subjects->fetch_assoc()): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1"><?php echo htmlspecialchars($subject['subject_name']); ?></h6>
                                <small class="text-muted">
                                    <i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($subject['subject_code']); ?>
                                </small>
                            </div>
                            <div class="text-end">
                                <small class="text-muted">
                                    <i class="fas fa-chalkboard-teacher"></i>
                                    <?php echo $subject['teacher_name'] ? htmlspecialchars($subject['teacher_name']) : 'Not Assigned'; ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php else: ?>
                <p class="text-muted text-center py-4">No subjects assigned yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Announcements -->
<?php if ($announcements->num_rows > 0): ?>
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-warning text-white">
                <h5 class="mb-0"><i class="fas fa-bullhorn"></i> Recent Announcements</h5>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <?php while ($announcement = $announcements->fetch_assoc()): ?>
                    <div class="list-group-item">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><?php echo htmlspecialchars($announcement['title']); ?></h6>
                            <small class="text-muted"><?php echo formatDate($announcement['posted_date']); ?></small>
                        </div>
                        <p class="mb-1"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                    </div>
                    <?php endwhile; ?>
                </div>
                <div class="text-center mt-3">
                    <a href="<?php echo APP_URL;?>/announcements/index.php" class="btn btn-sm btn-outline-warning">
                        View All Announcements <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
