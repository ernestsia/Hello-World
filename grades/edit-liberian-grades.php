<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

// Check if user is teacher only (admins can only view, not edit)
if (!hasRole('teacher')) {
    setFlashMessage('danger', 'Access denied. Only teachers can edit grades.');
    redirect(APP_URL . '/grades/liberian-grade-sheet.php');
}

$pageTitle = 'Edit Liberian Grades';
$db = new Database();
$conn = $db->getConnection();

$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$academic_year = isset($_GET['academic_year']) ? sanitize($_GET['academic_year']) : date('Y');

$errors = [];
$success = false;

// Get student info
$student = null;
if ($student_id > 0) {
    $stmt = $conn->prepare("SELECT s.*, c.class_name, c.section FROM students s 
                           LEFT JOIN classes c ON s.class_id = c.class_id 
                           WHERE s.student_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Get subjects for the class based on user role
$subjects = [];
if ($class_id > 0) {
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
            $stmt->bind_param("ii", $class_id, $teacher_id);
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
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $subjects[] = $row;
        }
        $stmt->close();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $student_id > 0) {
    $conn->begin_transaction();
    
    try {
        foreach ($subjects as $subject) {
            $subject_id = $subject['subject_id'];
            
            // Get grade values from POST
            $first_period = !empty($_POST["first_period_{$subject_id}"]) ? (float)$_POST["first_period_{$subject_id}"] : null;
            $second_period = !empty($_POST["second_period_{$subject_id}"]) ? (float)$_POST["second_period_{$subject_id}"] : null;
            $third_period = !empty($_POST["third_period_{$subject_id}"]) ? (float)$_POST["third_period_{$subject_id}"] : null;
            $first_sem_exam = !empty($_POST["first_sem_exam_{$subject_id}"]) ? (float)$_POST["first_sem_exam_{$subject_id}"] : null;
            $fourth_period = !empty($_POST["fourth_period_{$subject_id}"]) ? (float)$_POST["fourth_period_{$subject_id}"] : null;
            $fifth_period = !empty($_POST["fifth_period_{$subject_id}"]) ? (float)$_POST["fifth_period_{$subject_id}"] : null;
            $sixth_period = !empty($_POST["sixth_period_{$subject_id}"]) ? (float)$_POST["sixth_period_{$subject_id}"] : null;
            $second_sem_exam = !empty($_POST["second_sem_exam_{$subject_id}"]) ? (float)$_POST["second_sem_exam_{$subject_id}"] : null;
            
            // Insert or update grades
            $stmt = $conn->prepare("INSERT INTO liberian_grades 
                                   (student_id, subject_id, academic_year, first_period, second_period, third_period, 
                                    first_sem_exam, fourth_period, fifth_period, sixth_period, second_sem_exam) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                                   ON DUPLICATE KEY UPDATE 
                                   first_period = VALUES(first_period),
                                   second_period = VALUES(second_period),
                                   third_period = VALUES(third_period),
                                   first_sem_exam = VALUES(first_sem_exam),
                                   fourth_period = VALUES(fourth_period),
                                   fifth_period = VALUES(fifth_period),
                                   sixth_period = VALUES(sixth_period),
                                   second_sem_exam = VALUES(second_sem_exam)");
            $stmt->bind_param("iisdddddddd", $student_id, $subject_id, $academic_year, 
                            $first_period, $second_period, $third_period, $first_sem_exam,
                            $fourth_period, $fifth_period, $sixth_period, $second_sem_exam);
            $stmt->execute();
            $stmt->close();
        }
        
        // Update attendance
        $days_absent = !empty($_POST['days_absent']) ? (int)$_POST['days_absent'] : 0;
        $days_late = !empty($_POST['days_late']) ? (int)$_POST['days_late'] : 0;
        
        $stmt = $conn->prepare("INSERT INTO student_attendance_summary 
                               (student_id, academic_year, days_absent, days_late) 
                               VALUES (?, ?, ?, ?)
                               ON DUPLICATE KEY UPDATE 
                               days_absent = VALUES(days_absent),
                               days_late = VALUES(days_late)");
        $stmt->bind_param("isii", $student_id, $academic_year, $days_absent, $days_late);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        $success = true;
        setFlashMessage('success', 'Grades updated successfully!');
        redirect(APP_URL . "/grades/liberian-grade-sheet.php?student_id={$student_id}&class_id={$class_id}&academic_year={$academic_year}");
        
    } catch (Exception $e) {
        $conn->rollback();
        $errors[] = 'Error updating grades: ' . $e->getMessage();
    }
}

// Get existing grades
$grade_data = [];
if ($student_id > 0) {
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
    
    // Get attendance
    $stmt = $conn->prepare("SELECT * FROM student_attendance_summary WHERE student_id = ? AND academic_year = ?");
    $stmt->bind_param("is", $student_id, $academic_year);
    $stmt->execute();
    $attendance_result = $stmt->get_result();
    $attendance = $attendance_result->fetch_assoc();
    $stmt->close();
}

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-edit"></i> Edit Grades</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/grades/index.php">Grades</a></li>
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/grades/liberian-grade-sheet.php">Grade Sheet</a></li>
                <li class="breadcrumb-item active">Edit Grades</li>
            </ol>
        </nav>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <strong>Error!</strong>
    <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
        <li><?php echo $error; ?></li>
        <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($student): ?>
<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h5>Student: <strong><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></strong></h5>
                <p class="mb-0">
                    Roll Number: <?php echo htmlspecialchars($student['roll_number']); ?> | 
                    Class: <?php echo htmlspecialchars($student['class_name'] . ' - ' . $student['section']); ?> | 
                    Academic Year: <?php echo $academic_year; ?>
                </p>
            </div>
        </div>
    </div>
</div>

<form method="POST" action="">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Enter Grades</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th rowspan="2">Subject</th>
                                    <th colspan="4" class="text-center bg-primary text-white">First Semester</th>
                                    <th colspan="4" class="text-center bg-success text-white">Second Semester</th>
                                </tr>
                                <tr>
                                    <th>1st Period</th>
                                    <th>2nd Period</th>
                                    <th>3rd Period</th>
                                    <th>Sem. Exam</th>
                                    <th>4th Period</th>
                                    <th>5th Period</th>
                                    <th>6th Period</th>
                                    <th>Sem. Exam</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subjects as $subject): 
                                    $grades = isset($grade_data[$subject['subject_id']]) ? $grade_data[$subject['subject_id']] : [];
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($subject['subject_name']); ?></strong></td>
                                    <?php
                                    // Determine which fields should be readonly based on what's already filled
                                    $has_first = !empty($grades['first_period']);
                                    $has_second = !empty($grades['second_period']);
                                    $has_third = !empty($grades['third_period']);
                                    $has_first_exam = !empty($grades['first_sem_exam']);
                                    $has_fourth = !empty($grades['fourth_period']);
                                    $has_fifth = !empty($grades['fifth_period']);
                                    $has_sixth = !empty($grades['sixth_period']);
                                    ?>
                                    <td>
                                        <input type="number" class="form-control grade-input" 
                                               name="first_period_<?php echo $subject['subject_id']; ?>" 
                                               value="<?php echo $grades['first_period'] ?? ''; ?>" 
                                               min="0" max="100" step="0.1"
                                               <?php echo $has_first ? 'readonly style="background-color: #e9ecef !important; cursor: not-allowed; opacity: 0.7;"' : ''; ?>>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control grade-input" 
                                               name="second_period_<?php echo $subject['subject_id']; ?>" 
                                               value="<?php echo $grades['second_period'] ?? ''; ?>" 
                                               min="0" max="100" step="0.1"
                                               <?php echo $has_second ? 'readonly style="background-color: #e9ecef !important; cursor: not-allowed; opacity: 0.7;"' : ''; ?>>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control grade-input" 
                                               name="third_period_<?php echo $subject['subject_id']; ?>" 
                                               value="<?php echo $grades['third_period'] ?? ''; ?>" 
                                               min="0" max="100" step="0.1"
                                               <?php echo $has_third ? 'readonly style="background-color: #e9ecef !important; cursor: not-allowed; opacity: 0.7;"' : ''; ?>>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control grade-input" 
                                               name="first_sem_exam_<?php echo $subject['subject_id']; ?>" 
                                               value="<?php echo $grades['first_sem_exam'] ?? ''; ?>" 
                                               min="0" max="100" step="0.1" placeholder="Exam"
                                               <?php echo $has_first_exam ? 'readonly style="background-color: #e9ecef !important; cursor: not-allowed; opacity: 0.7;"' : ''; ?>>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control grade-input" 
                                               name="fourth_period_<?php echo $subject['subject_id']; ?>" 
                                               value="<?php echo $grades['fourth_period'] ?? ''; ?>" 
                                               min="0" max="100" step="0.1"
                                               <?php echo $has_fourth ? 'readonly style="background-color: #e9ecef !important; cursor: not-allowed; opacity: 0.7;"' : ''; ?>>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control grade-input" 
                                               name="fifth_period_<?php echo $subject['subject_id']; ?>" 
                                               value="<?php echo $grades['fifth_period'] ?? ''; ?>" 
                                               min="0" max="100" step="0.1"
                                               <?php echo $has_fifth ? 'readonly style="background-color: #e9ecef !important; cursor: not-allowed; opacity: 0.7;"' : ''; ?>>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control grade-input" 
                                               name="sixth_period_<?php echo $subject['subject_id']; ?>" 
                                               value="<?php echo $grades['sixth_period'] ?? ''; ?>" 
                                               min="0" max="100" step="0.1"
                                               <?php echo $has_sixth ? 'readonly style="background-color: #e9ecef !important; cursor: not-allowed; opacity: 0.7;"' : ''; ?>>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control" name="second_sem_exam_<?php echo $subject['subject_id']; ?>" 
                                               value="<?php echo $grades['second_sem_exam'] ?? ''; ?>" min="0" max="100" step="0.1" 
                                               placeholder="Exam">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <hr>
                    
                    <h6>Attendance</h6>
                    <div class="row">
                        <div class="col-md-6">
                            <label class="form-label">Days Absent</label>
                            <input type="number" class="form-control" name="days_absent" 
                                   value="<?php echo $attendance['days_absent'] ?? 0; ?>" min="0">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Days Late</label>
                            <input type="number" class="form-control" name="days_late" 
                                   value="<?php echo $attendance['days_late'] ?? 0; ?>" min="0">
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Grades
                        </button>
                        <a href="<?php echo APP_URL;?>/grades/liberian-grade-sheet.php?student_id=<?php echo $student_id; ?>&class_id=<?php echo $class_id; ?>&academic_year=<?php echo $academic_year; ?>" 
                           class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
<?php else: ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-triangle"></i> Student not found.
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
