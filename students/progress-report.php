<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireLogin();
requireRole('student');

$pageTitle = 'Progress Report';
$db = new Database();
$conn = $db->getConnection();

// Get student information
$stmt = $conn->prepare("SELECT s.*, c.class_name, c.section, u.email 
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
$stmt->close();

// Get all grades grouped by subject
$grades_query = "SELECT 
                    s.subject_name,
                    s.subject_code,
                    e.exam_name,
                    e.exam_type,
                    e.exam_date,
                    e.total_marks,
                    e.passing_marks,
                    g.marks_obtained,
                    g.remarks,
                    ROUND((g.marks_obtained / e.total_marks) * 100, 2) as percentage
                 FROM grades g
                 JOIN exams e ON g.exam_id = e.exam_id
                 JOIN subjects s ON e.subject_id = s.subject_id
                 WHERE g.student_id = ?
                 ORDER BY s.subject_name, e.exam_date DESC";
$stmt = $conn->prepare($grades_query);
$stmt->bind_param("i", $student['student_id']);
$stmt->execute();
$grades_result = $stmt->get_result();

// Organize grades by subject
$subjects_data = [];
while ($grade = $grades_result->fetch_assoc()) {
    $subject_name = $grade['subject_name'];
    if (!isset($subjects_data[$subject_name])) {
        $subjects_data[$subject_name] = [
            'subject_code' => $grade['subject_code'],
            'exams' => [],
            'total_marks' => 0,
            'obtained_marks' => 0,
            'exam_count' => 0
        ];
    }
    $subjects_data[$subject_name]['exams'][] = $grade;
    $subjects_data[$subject_name]['total_marks'] += $grade['total_marks'];
    $subjects_data[$subject_name]['obtained_marks'] += $grade['marks_obtained'];
    $subjects_data[$subject_name]['exam_count']++;
}
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
$stmt->bind_param("i", $student['student_id']);
$stmt->execute();
$attendance = $stmt->get_result()->fetch_assoc();
$stmt->close();

$attendance_rate = $attendance['total_days'] > 0 ? ($attendance['present_days'] / $attendance['total_days']) * 100 : 0;

// Calculate overall performance
$overall_percentage = 0;
$total_subjects = count($subjects_data);
if ($total_subjects > 0) {
    foreach ($subjects_data as $subject) {
        if ($subject['total_marks'] > 0) {
            $overall_percentage += ($subject['obtained_marks'] / $subject['total_marks']) * 100;
        }
    }
    $overall_percentage = $overall_percentage / $total_subjects;
}

include '../includes/header.php';
?>

<style>
@media print {
    .no-print {
        display: none !important;
    }
    .navbar, .breadcrumb, .btn {
        display: none !important;
    }
    body {
        background: white !important;
    }
    .card {
        border: 1px solid #000 !important;
        box-shadow: none !important;
    }
    .print-header {
        text-align: center;
        margin-bottom: 20px;
    }
    .print-header h1 {
        font-size: 24px;
        margin-bottom: 5px;
    }
    .print-header p {
        margin: 2px 0;
    }
}

.grade-excellent { background-color: #d4edda !important; }
.grade-good { background-color: #d1ecf1 !important; }
.grade-average { background-color: #fff3cd !important; }
.grade-poor { background-color: #f8d7da !important; }
</style>

<div class="no-print">
    <div class="row mb-4">
        <div class="col-12">
            <h2><i class="fas fa-file-alt"></i> Progress Report</h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/index.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Progress Report</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row mb-3">
        <div class="col-12 text-end">
            <button onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print"></i> Print Report
            </button>
            <button onclick="downloadPDF()" class="btn btn-danger">
                <i class="fas fa-file-pdf"></i> Download PDF
            </button>
        </div>
    </div>
</div>

<!-- Print Header (only visible when printing) -->
<div class="print-header" style="display: none;">
    <h1><?php echo APP_NAME; ?></h1>
    <p><strong>STUDENT PROGRESS REPORT</strong></p>
    <p>Academic Year: <?php echo date('Y'); ?></p>
    <hr>
</div>

<style>
@media print {
    .print-header {
        display: block !important;
    }
}
</style>

<!-- Student Information -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-user"></i> Student Information</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></p>
                        <p><strong>Roll Number:</strong> <?php echo htmlspecialchars($student['roll_number']); ?></p>
                        <p><strong>Class:</strong> <?php echo htmlspecialchars($student['class_name'] . ' - ' . $student['section']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Date of Birth:</strong> <?php echo formatDate($student['date_of_birth']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($student['email']); ?></p>
                        <p><strong>Report Date:</strong> <?php echo date('d/m/Y'); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Overall Performance Summary -->
<div class="row mb-3">
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-primary"><?php echo number_format($overall_percentage, 2); ?>%</h3>
                <p class="mb-0">Overall Percentage</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-success"><?php echo number_format($attendance_rate, 2); ?>%</h3>
                <p class="mb-0">Attendance Rate</p>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-center">
            <div class="card-body">
                <h3 class="text-info"><?php echo $total_subjects; ?></h3>
                <p class="mb-0">Total Subjects</p>
            </div>
        </div>
    </div>
</div>

<!-- Subject-wise Performance -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Subject-wise Performance</h5>
            </div>
            <div class="card-body">
                <?php if (count($subjects_data) > 0): ?>
                    <?php foreach ($subjects_data as $subject_name => $subject_info): 
                        $subject_percentage = $subject_info['total_marks'] > 0 ? 
                            ($subject_info['obtained_marks'] / $subject_info['total_marks']) * 100 : 0;
                        
                        // Determine grade class
                        $grade_class = 'grade-poor';
                        if ($subject_percentage >= 80) $grade_class = 'grade-excellent';
                        elseif ($subject_percentage >= 60) $grade_class = 'grade-good';
                        elseif ($subject_percentage >= 40) $grade_class = 'grade-average';
                    ?>
                    <div class="mb-4">
                        <h6 class="border-bottom pb-2">
                            <?php echo htmlspecialchars($subject_name); ?> 
                            <span class="badge bg-secondary"><?php echo htmlspecialchars($subject_info['subject_code']); ?></span>
                            <span class="badge bg-primary float-end"><?php echo number_format($subject_percentage, 2); ?>%</span>
                        </h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead class="table-light">
                                    <tr>
                                        <th>Exam</th>
                                        <th>Type</th>
                                        <th>Date</th>
                                        <th>Marks</th>
                                        <th>Percentage</th>
                                        <th>Status</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subject_info['exams'] as $exam): 
                                        $passed = $exam['marks_obtained'] >= $exam['passing_marks'];
                                        $exam_grade_class = 'grade-poor';
                                        if ($exam['percentage'] >= 80) $exam_grade_class = 'grade-excellent';
                                        elseif ($exam['percentage'] >= 60) $exam_grade_class = 'grade-good';
                                        elseif ($exam['percentage'] >= 40) $exam_grade_class = 'grade-average';
                                    ?>
                                    <tr class="<?php echo $exam_grade_class; ?>">
                                        <td><?php echo htmlspecialchars($exam['exam_name']); ?></td>
                                        <td><?php echo ucfirst($exam['exam_type']); ?></td>
                                        <td><?php echo formatDate($exam['exam_date']); ?></td>
                                        <td><?php echo $exam['marks_obtained'] . '/' . $exam['total_marks']; ?></td>
                                        <td><?php echo number_format($exam['percentage'], 2); ?>%</td>
                                        <td>
                                            <?php if ($passed): ?>
                                            <span class="badge bg-success">Pass</span>
                                            <?php else: ?>
                                            <span class="badge bg-danger">Fail</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($exam['remarks']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr class="table-secondary">
                                        <td colspan="3"><strong>Subject Total</strong></td>
                                        <td><strong><?php echo $subject_info['obtained_marks'] . '/' . $subject_info['total_marks']; ?></strong></td>
                                        <td colspan="3"><strong><?php echo number_format($subject_percentage, 2); ?>%</strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted mb-0">No exam results available yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Attendance Summary -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-calendar-check"></i> Attendance Summary</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3">
                        <h4><?php echo $attendance['total_days']; ?></h4>
                        <p class="mb-0">Total Days</p>
                    </div>
                    <div class="col-md-3">
                        <h4 class="text-success"><?php echo $attendance['present_days']; ?></h4>
                        <p class="mb-0">Present</p>
                    </div>
                    <div class="col-md-3">
                        <h4 class="text-danger"><?php echo $attendance['absent_days']; ?></h4>
                        <p class="mb-0">Absent</p>
                    </div>
                    <div class="col-md-3">
                        <h4 class="text-warning"><?php echo $attendance['late_days']; ?></h4>
                        <p class="mb-0">Late</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Grading Scale -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h6 class="mb-0"><i class="fas fa-info-circle"></i> Grading Scale</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="grade-excellent p-2 text-center">
                            <strong>Excellent: 80-100%</strong>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="grade-good p-2 text-center">
                            <strong>Good: 60-79%</strong>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="grade-average p-2 text-center">
                            <strong>Average: 40-59%</strong>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="grade-poor p-2 text-center">
                            <strong>Poor: Below 40%</strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function downloadPDF() {
    window.print();
    alert('Please use your browser\'s "Save as PDF" option in the print dialog to save this report as PDF.');
}
</script>

<?php include '../includes/footer.php'; ?>
