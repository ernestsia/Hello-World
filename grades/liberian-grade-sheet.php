<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireLogin();

$pageTitle = 'Grade Sheet';
$db = new Database();
$conn = $db->getConnection();

// Get filter parameters
$selected_class = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$selected_student = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$academic_year = isset($_GET['academic_year']) ? sanitize($_GET['academic_year']) : date('Y');

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
        $stmt = $conn->prepare("SELECT DISTINCT c.class_id, c.class_name, c.section 
                               FROM classes c
                               WHERE c.class_teacher_id = ? 
                               OR c.class_id IN (SELECT DISTINCT class_id FROM class_subjects WHERE teacher_id = ?)
                               ORDER BY c.class_name, c.section");
        $stmt->bind_param("ii", $teacher_id, $teacher_id);
        $stmt->execute();
        $classes_result = $stmt->get_result();
    } else {
        // No classes if teacher not found
        $classes_result = $conn->query("SELECT class_id, class_name, section FROM classes WHERE 1=0");
    }
} else {
    // Admins see all classes
    $classes_result = $conn->query("SELECT class_id, class_name, section FROM classes ORDER BY class_name");
}

// Get students for selected class
$students = [];
if ($selected_class > 0) {
    $stmt = $conn->prepare("SELECT student_id, first_name, last_name, roll_number FROM students WHERE class_id = ? ORDER BY roll_number");
    $stmt->bind_param("i", $selected_class);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    $stmt->close();
}

// Get subjects for the class based on user role
$subjects = [];
if ($selected_class > 0) {
    if (hasRole('teacher')) {
        // Teachers only see subjects they teach in this class
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
            $stmt = $conn->prepare("SELECT DISTINCT s.subject_id, s.subject_name 
                                   FROM subjects s 
                                   JOIN class_subjects cs ON s.subject_id = cs.subject_id 
                                   WHERE cs.class_id = ? AND cs.teacher_id = ?
                                   ORDER BY s.subject_name");
            $stmt->bind_param("ii", $selected_class, $teacher_id);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $subjects[] = $row;
            }
            $stmt->close();
        }
    } else {
        // Admins see all subjects for the class
        $stmt = $conn->prepare("SELECT DISTINCT s.subject_id, s.subject_name 
                               FROM subjects s 
                               JOIN class_subjects cs ON s.subject_id = cs.subject_id 
                               WHERE cs.class_id = ? 
                               ORDER BY s.subject_name");
        $stmt->bind_param("i", $selected_class);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $subjects[] = $row;
        }
        $stmt->close();
    }
}

// Get grade data for selected student
$grade_data = [];
if ($selected_student > 0 && $selected_class > 0) {
    foreach ($subjects as $subject) {
        $stmt = $conn->prepare("SELECT * FROM liberian_grades 
                               WHERE student_id = ? AND subject_id = ? AND academic_year = ?");
        $stmt->bind_param("iis", $selected_student, $subject['subject_id'], $academic_year);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $grade_data[$subject['subject_id']] = $row;
        }
        $stmt->close();
    }
    
    // Get attendance and remarks
    $stmt = $conn->prepare("SELECT * FROM student_attendance_summary WHERE student_id = ? AND academic_year = ?");
    $stmt->bind_param("is", $selected_student, $academic_year);
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
}
.subject-name {
    text-align: left;
    font-weight: 600;
}
.grade-input {
    width: 50px;
    padding: 4px;
    text-align: center;
    background: #222;
    border: 1px solid #444;
    color: #fff;
}
.grade-input:focus {
    background: #2a2a2a;
    border-color: var(--primary-color);
}
.section-header {
    background-color: #333 !important;
    font-weight: 700;
}
.average-cell {
    background-color: #2a2a2a;
    font-weight: 600;
}
@media print {
    .no-print { display: none; }
    body { background: white; color: black; }
    .grade-sheet-table th, .grade-sheet-table td { border-color: #000; }
}
</style>

<div class="row mb-4 no-print">
    <div class="col-12">
        <h2><i class="fas fa-file-alt"></i> Grade Sheet</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/grades/index.php">Grades</a></li>
                <li class="breadcrumb-item active">Grade Sheet</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Filter Form -->
<div class="row mb-3 no-print">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Select Student</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-4">
                        <label for="class_id" class="form-label">Class</label>
                        <select class="form-select" id="class_id" name="class_id" required onchange="this.form.submit()">
                            <option value="">Select Class</option>
                            <?php 
                            $classes_result->data_seek(0);
                            while ($class = $classes_result->fetch_assoc()): 
                            ?>
                            <option value="<?php echo $class['class_id']; ?>"
                                    <?php echo $selected_class == $class['class_id'] ? 'selected' : ''; ?>>
                                <?php echo $class['class_name'] . ' - ' . $class['section']; ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="student_id" class="form-label">Student</label>
                        <select class="form-select" id="student_id" name="student_id" required>
                            <option value="">Select Student</option>
                            <?php foreach ($students as $student): ?>
                            <option value="<?php echo $student['student_id']; ?>"
                                    <?php echo $selected_student == $student['student_id'] ? 'selected' : ''; ?>>
                                <?php echo $student['roll_number'] . ' - ' . $student['first_name'] . ' ' . $student['last_name']; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="academic_year" class="form-label">Academic Year</label>
                        <input type="number" class="form-control" id="academic_year" name="academic_year" 
                               value="<?php echo $academic_year; ?>" min="2020" max="2030">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i> View
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if ($selected_student > 0 && count($subjects) > 0): ?>
<!-- Grade Sheet -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <div class="text-end mb-3 no-print">
                    <button onclick="window.print()" class="btn btn-primary" style="background-color: #4F46E5 !important; color: white !important;">
                        <i class="fas fa-print"></i> Print Grade Sheet
                    </button>
                    <?php if (hasRole('teacher')): ?>
                    <a href="<?php echo APP_URL;?>/grades/edit-liberian-grades.php?student_id=<?php echo $selected_student; ?>&class_id=<?php echo $selected_class; ?>&academic_year=<?php echo $academic_year; ?>" 
                       class="btn btn-success" style="background-color: #10B981 !important; color: white !important; border: none !important;">
                        <i class="fas fa-edit"></i> Edit Grades
                    </a>
                    <?php endif; ?>
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
                            
                            <!-- Average Row -->
                            <tr class="section-header">
                                <td class="subject-name"><strong>AVERAGE</strong></td>
                                <td colspan="11"></td>
                            </tr>
                            
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
                
                <!-- Remarks Section -->
                <div class="mt-4">
                    <h6><strong>REMARKS</strong></h6>
                    <table class="table grade-sheet-table">
                        <thead>
                            <tr>
                                <th>FIRST UNIT</th>
                                <th>SECOND UNIT</th>
                                <th>THIRD UNIT</th>
                                <th>FOURTH UNIT</th>
                                <th>FIFTH UNIT</th>
                                <th>SIXTH UNIT</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="height: 60px;"></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                    
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <p><strong>GRADING SYSTEM:</strong></p>
                            <p>94 – 100 Excellent, 88 – 93 Very Good, 77 – 87 Good, 70 – 76 Improvement Needed, 69 – Below Failure</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php elseif ($selected_student > 0): ?>
<div class="alert alert-info">
    <i class="fas fa-info-circle"></i> No subjects found for this class. Please assign subjects to the class first.
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
