<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireRole('student');

$pageTitle = 'My Grade Sheet';
$db = new Database();
$conn = $db->getConnection();

// Get student info
$student_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT s.*, c.class_id, c.class_name, c.section 
                       FROM students s 
                       LEFT JOIN classes c ON s.class_id = c.class_id 
                       WHERE s.user_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    setFlashMessage('danger', 'Student record not found.');
    redirect(APP_URL . '/index.php');
}

$academic_year = isset($_GET['academic_year']) ? sanitize($_GET['academic_year']) : date('Y');
$class_id = $student['class_id'];
$student_id = $student['student_id'];

// Get subjects for the class
$subjects = [];
if ($class_id > 0) {
    $stmt = $conn->prepare("SELECT DISTINCT s.subject_id, s.subject_name 
                           FROM subjects s 
                           JOIN class_subjects cs ON s.subject_id = cs.subject_id 
                           WHERE cs.class_id = ? 
                           ORDER BY s.subject_name");
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row;
    }
    $stmt->close();
}

// Get grade data for student
$grade_data = [];
if ($student_id > 0 && $class_id > 0) {
    foreach ($subjects as $subject) {
        $stmt = $conn->prepare("SELECT * FROM liberian_grades 
                               WHERE student_id = ? AND subject_id = ? AND academic_year = ?");
        $stmt->bind_param("iis", $student_id, $subject['subject_id'], $academic_year);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $grade_data[$subject['subject_id']] = $row;
        }
        $stmt->close();
    }
    
    // Get attendance and remarks
    $stmt = $conn->prepare("SELECT * FROM student_attendance_summary WHERE student_id = ? AND academic_year = ?");
    $stmt->bind_param("is", $student_id, $academic_year);
    $stmt->execute();
    $attendance_result = $stmt->get_result();
    $attendance = $attendance_result->fetch_assoc();
    $stmt->close();
}

// Function to calculate average
function calculateAverage($scores) {
    $valid_scores = array_filter($scores, function($score) {
        return $score !== null && $score !== '';
    });
    return count($valid_scores) > 0 ? round(array_sum($valid_scores) / count($valid_scores), 1) : '';
}

// Function to get grade remark
function getGradeRemark($score) {
    if ($score === '' || $score === null) return '';
    if ($score >= 94) return 'Excellent';
    if ($score >= 88) return 'Very Good';
    if ($score >= 77) return 'Good';
    if ($score >= 70) return 'Improvement Needed';
    return 'Below Failure';
}

// Function to get grade color
function getGradeColor($score) {
    if ($score === '' || $score === null) return '';
    if ($score < 70) return 'color: red; font-weight: bold;';
    return 'color: royalblue; font-weight: bold;';
}

include '../includes/header.php';
?>

<style>
.grade-sheet-table {
    font-size: 0.85rem;
    border-collapse: collapse;
}
.grade-sheet-table th,
.grade-sheet-table td {
    border: 1px solid #444;
    padding: 8px 4px;
    text-align: center;
}
.grade-sheet-table th {
    background-color: #2a2a2a;
    font-weight: 600;
    color: #fff;
}
.subject-name {
    text-align: left;
    font-weight: 600;
}
.section-header {
    background-color: #333 !important;
    font-weight: 700;
    color: #fff;
}
.average-cell {
    background-color: #f8f9fa;
    font-weight: 600;
}
@media print {
    .no-print { display: none; }
    body { background: white; color: black; }
    .grade-sheet-table th, .grade-sheet-table td { border-color: #000; }
    .grade-sheet-table th { background-color: #e9ecef !important; color: #000 !important; }
    .section-header { background-color: #dee2e6 !important; color: #000 !important; }
}
</style>

<div class="row mb-4 no-print">
    <div class="col-12">
        <h2><i class="fas fa-file-alt"></i> My Grade Sheet</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">My Grade Sheet</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Academic Year Selection -->
<div class="row mb-3 no-print">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="GET" action="" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="academic_year" class="form-label">Academic Year</label>
                        <select class="form-select" id="academic_year" name="academic_year" onchange="this.form.submit()">
                            <?php for ($year = date('Y'); $year >= 2020; $year--): ?>
                            <option value="<?php echo $year; ?>" <?php echo $academic_year == $year ? 'selected' : ''; ?>>
                                <?php echo $year; ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-8 text-end">
                        <button type="button" onclick="window.print()" class="btn btn-primary">
                            <i class="fas fa-print"></i> Print Grade Sheet
                        </button>
                        <button type="button" onclick="downloadPDF()" class="btn btn-success">
                            <i class="fas fa-download"></i> Download PDF
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if (count($subjects) > 0): ?>
<!-- Grade Sheet -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body" id="grade-sheet-content">
                <div class="text-center mb-4">
                    <h3><?php echo APP_NAME; ?></h3>
                    <h5>Student Grade Sheet - Academic Year <?php echo $academic_year; ?></h5>
                    <p class="mb-1"><strong>Student Name:</strong> <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></p>
                    <p class="mb-1"><strong>Roll Number:</strong> <?php echo htmlspecialchars($student['roll_number']); ?></p>
                    <p class="mb-0"><strong>Class:</strong> <?php echo htmlspecialchars($student['class_name'] . ' - ' . $student['section']); ?></p>
                </div>
                
                <div class="table-responsive">
                    <table class="table grade-sheet-table">
                        <thead>
                            <tr>
                                <th rowspan="2" class="subject-name">SUBJECT</th>
                                <th colspan="5" class="section-header">FIRST SEMESTER</th>
                                <th colspan="5" class="section-header">SECOND SEMESTER</th>
                                <th rowspan="2">Final<br>Ave.</th>
                            </tr>
                            <tr>
                                <th>1st</th>
                                <th>2nd</th>
                                <th>3rd</th>
                                <th>Sem.<br>Exam</th>
                                <th>Sem.<br>Ave.</th>
                                <th>4th</th>
                                <th>5th</th>
                                <th>6th</th>
                                <th>Sem.<br>Exam</th>
                                <th>Sem.<br>Ave.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subjects as $subject): 
                                $grades = isset($grade_data[$subject['subject_id']]) ? $grade_data[$subject['subject_id']] : [];
                                
                                // First semester calculations
                                $first_three_avg = calculateAverage([
                                    $grades['first_period'] ?? null,
                                    $grades['second_period'] ?? null,
                                    $grades['third_period'] ?? null
                                ]);
                                $first_sem_exam = $grades['first_sem_exam'] ?? '';
                                $first_sem_avg = calculateAverage([
                                    $first_three_avg,
                                    $first_sem_exam
                                ]);
                                
                                // Second semester calculations
                                $second_three_avg = calculateAverage([
                                    $grades['fourth_period'] ?? null,
                                    $grades['fifth_period'] ?? null,
                                    $grades['sixth_period'] ?? null
                                ]);
                                $second_sem_exam = $grades['second_sem_exam'] ?? '';
                                $second_sem_avg = calculateAverage([
                                    $second_three_avg,
                                    $second_sem_exam
                                ]);
                                
                                // Final average
                                $final_avg = calculateAverage([$first_sem_avg, $second_sem_avg]);
                            ?>
                            <tr>
                                <td class="subject-name"><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                <td style="<?php echo getGradeColor($grades['first_period'] ?? ''); ?>"><?php echo $grades['first_period'] ?? ''; ?></td>
                                <td style="<?php echo getGradeColor($grades['second_period'] ?? ''); ?>"><?php echo $grades['second_period'] ?? ''; ?></td>
                                <td style="<?php echo getGradeColor($grades['third_period'] ?? ''); ?>"><?php echo $grades['third_period'] ?? ''; ?></td>
                                <td style="<?php echo getGradeColor($first_sem_exam); ?>"><?php echo $first_sem_exam; ?></td>
                                <td class="average-cell" style="<?php echo getGradeColor($first_sem_avg); ?>"><?php echo $first_sem_avg; ?></td>
                                <td style="<?php echo getGradeColor($grades['fourth_period'] ?? ''); ?>"><?php echo $grades['fourth_period'] ?? ''; ?></td>
                                <td style="<?php echo getGradeColor($grades['fifth_period'] ?? ''); ?>"><?php echo $grades['fifth_period'] ?? ''; ?></td>
                                <td style="<?php echo getGradeColor($grades['sixth_period'] ?? ''); ?>"><?php echo $grades['sixth_period'] ?? ''; ?></td>
                                <td style="<?php echo getGradeColor($second_sem_exam); ?>"><?php echo $second_sem_exam; ?></td>
                                <td class="average-cell" style="<?php echo getGradeColor($second_sem_avg); ?>"><?php echo $second_sem_avg; ?></td>
                                <td class="average-cell" style="<?php echo getGradeColor($final_avg); ?>"><?php echo $final_avg; ?></td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <!-- Attendance -->
                            <tr>
                                <td class="subject-name"><strong>DAYS ABSENT</strong></td>
                                <td colspan="11"><?php echo $attendance['days_absent'] ?? 0; ?></td>
                            </tr>
                            <tr>
                                <td class="subject-name"><strong>DAYS LATE</strong></td>
                                <td colspan="11"><?php echo $attendance['days_late'] ?? 0; ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-4">
                    <p><strong>GRADING SYSTEM:</strong></p>
                    <p>94 – 100 Excellent, 88 – 93 Very Good, 77 – 87 Good, 70 – 76 Improvement Needed, 69 – Below Failure</p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
<div class="alert alert-info">
    <i class="fas fa-info-circle"></i> No subjects found for your class. Please contact your administrator.
</div>
<?php endif; ?>

<script>
function downloadPDF() {
    alert('PDF download feature coming soon! For now, please use the Print button and save as PDF.');
    window.print();
}
</script>

<?php include '../includes/footer.php'; ?>
