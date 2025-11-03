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

$pageTitle = 'My Grades';
$db = new Database();
$conn = $db->getConnection();

// Get student's own information
$stmt = $conn->prepare("SELECT student_id, first_name, last_name, roll_number FROM students WHERE user_id = ?");
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

// Get all grades grouped by exam type and subject
$grades_query = "SELECT 
                    e.exam_id,
                    e.exam_name,
                    e.exam_type,
                    e.exam_date,
                    e.total_marks,
                    e.passing_marks,
                    s.subject_name,
                    s.subject_code,
                    g.marks_obtained,
                    g.remarks,
                    CASE 
                        WHEN g.marks_obtained >= e.passing_marks THEN 'Pass'
                        ELSE 'Fail'
                    END as result,
                    ROUND((g.marks_obtained / e.total_marks) * 100, 2) as percentage
                 FROM grades g
                 JOIN exams e ON g.exam_id = e.exam_id
                 JOIN subjects s ON e.subject_id = s.subject_id
                 WHERE g.student_id = ?
                 ORDER BY e.exam_date DESC, s.subject_name";

$stmt = $conn->prepare($grades_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$grades_result = $stmt->get_result();

// Group grades by exam type
$grades_by_type = [];
$overall_stats = [
    'total_exams' => 0,
    'passed' => 0,
    'failed' => 0,
    'total_percentage' => 0
];

while ($grade = $grades_result->fetch_assoc()) {
    $exam_type = ucfirst($grade['exam_type']);
    if (!isset($grades_by_type[$exam_type])) {
        $grades_by_type[$exam_type] = [];
    }
    $grades_by_type[$exam_type][] = $grade;
    
    // Calculate overall stats
    $overall_stats['total_exams']++;
    if ($grade['result'] === 'Pass') {
        $overall_stats['passed']++;
    } else {
        $overall_stats['failed']++;
    }
    $overall_stats['total_percentage'] += $grade['percentage'];
}

$stmt->close();

// Calculate average percentage
$average_percentage = $overall_stats['total_exams'] > 0 
    ? $overall_stats['total_percentage'] / $overall_stats['total_exams'] 
    : 0;

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-graduation-cap"></i> My Grades & Results</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/students/my-profile.php">My Profile</a></li>
                <li class="breadcrumb-item active">My Grades</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Student Info Banner -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <div class="card-body text-white p-4">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h4 class="mb-2">
                            <i class="fas fa-user-graduate"></i> 
                            <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                        </h4>
                        <p class="mb-0 opacity-75">
                            <i class="fas fa-id-badge"></i> Roll Number: <?php echo htmlspecialchars($student['roll_number']); ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-end d-none d-md-block">
                        <i class="fas fa-chart-line fa-5x opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Overall Statistics -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="mb-2">
                    <i class="fas fa-file-alt fa-3x text-primary"></i>
                </div>
                <h3 class="mb-1"><?php echo $overall_stats['total_exams']; ?></h3>
                <p class="text-muted mb-0">Total Exams</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="mb-2">
                    <i class="fas fa-check-circle fa-3x text-success"></i>
                </div>
                <h3 class="mb-1"><?php echo $overall_stats['passed']; ?></h3>
                <p class="text-muted mb-0">Passed</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="mb-2">
                    <i class="fas fa-times-circle fa-3x text-danger"></i>
                </div>
                <h3 class="mb-1"><?php echo $overall_stats['failed']; ?></h3>
                <p class="text-muted mb-0">Failed</p>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="mb-2">
                    <i class="fas fa-percentage fa-3x text-info"></i>
                </div>
                <h3 class="mb-1"><?php echo number_format($average_percentage, 1); ?>%</h3>
                <p class="text-muted mb-0">Average Score</p>
            </div>
        </div>
    </div>
</div>

<!-- Grades by Exam Type -->
<?php if (count($grades_by_type) > 0): ?>
    <?php foreach ($grades_by_type as $exam_type => $grades): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-clipboard-list"></i> <?php echo $exam_type; ?> Exams
                        <span class="badge bg-light text-dark float-end"><?php echo count($grades); ?> Exams</span>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Exam Name</th>
                                    <th>Subject</th>
                                    <th>Date</th>
                                    <th>Marks Obtained</th>
                                    <th>Total Marks</th>
                                    <th>Percentage</th>
                                    <th>Grade</th>
                                    <th>Result</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $type_total_percentage = 0;
                                foreach ($grades as $grade): 
                                    $type_total_percentage += $grade['percentage'];
                                    
                                    // Determine grade letter
                                    $percentage = $grade['percentage'];
                                    if ($percentage >= 90) $grade_letter = 'A+';
                                    elseif ($percentage >= 80) $grade_letter = 'A';
                                    elseif ($percentage >= 70) $grade_letter = 'B';
                                    elseif ($percentage >= 60) $grade_letter = 'C';
                                    elseif ($percentage >= 50) $grade_letter = 'D';
                                    else $grade_letter = 'F';
                                    
                                    $grade_color = $percentage >= 60 ? 'success' : 'danger';
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($grade['exam_name']); ?></strong></td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo htmlspecialchars($grade['subject_code']); ?>
                                        </span>
                                        <?php echo htmlspecialchars($grade['subject_name']); ?>
                                    </td>
                                    <td><?php echo formatDate($grade['exam_date']); ?></td>
                                    <td class="text-center">
                                        <strong class="text-primary"><?php echo $grade['marks_obtained']; ?></strong>
                                    </td>
                                    <td class="text-center"><?php echo $grade['total_marks']; ?></td>
                                    <td>
                                        <div class="progress" style="height: 25px;">
                                            <div class="progress-bar bg-<?php echo $grade_color; ?>" 
                                                 role="progressbar" 
                                                 style="width: <?php echo $percentage; ?>%">
                                                <?php echo number_format($percentage, 1); ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $grade_color; ?> fs-6">
                                            <?php echo $grade_letter; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($grade['result'] === 'Pass'): ?>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check"></i> Pass
                                        </span>
                                        <?php else: ?>
                                        <span class="badge bg-danger">
                                            <i class="fas fa-times"></i> Fail
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php if (!empty($grade['remarks'])): ?>
                                <tr class="table-light">
                                    <td colspan="8">
                                        <small class="text-muted">
                                            <i class="fas fa-comment"></i> <strong>Remarks:</strong> 
                                            <?php echo htmlspecialchars($grade['remarks']); ?>
                                        </small>
                                    </td>
                                </tr>
                                <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="5" class="text-end"><strong>Average for <?php echo $exam_type; ?>:</strong></td>
                                    <td colspan="3">
                                        <strong class="text-primary">
                                            <?php echo number_format($type_total_percentage / count($grades), 2); ?>%
                                        </strong>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    
    <!-- Grade Scale Reference -->
    <div class="row">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-secondary text-white">
                    <h6 class="mb-0"><i class="fas fa-info-circle"></i> Grading Scale</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-2">
                            <span class="badge bg-success fs-6">A+</span>
                            <p class="small mb-0">90-100%</p>
                        </div>
                        <div class="col-md-2">
                            <span class="badge bg-success fs-6">A</span>
                            <p class="small mb-0">80-89%</p>
                        </div>
                        <div class="col-md-2">
                            <span class="badge bg-primary fs-6">B</span>
                            <p class="small mb-0">70-79%</p>
                        </div>
                        <div class="col-md-2">
                            <span class="badge bg-info fs-6">C</span>
                            <p class="small mb-0">60-69%</p>
                        </div>
                        <div class="col-md-2">
                            <span class="badge bg-warning fs-6">D</span>
                            <p class="small mb-0">50-59%</p>
                        </div>
                        <div class="col-md-2">
                            <span class="badge bg-danger fs-6">F</span>
                            <p class="small mb-0">Below 50%</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
<?php else: ?>
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-clipboard-list fa-5x text-muted mb-3"></i>
                <h4 class="text-muted">No Exam Results Available</h4>
                <p class="text-muted">Your exam results will appear here once they are published by your teachers.</p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
