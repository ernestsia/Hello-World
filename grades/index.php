<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireLogin();

$pageTitle = 'Grades Management';
$db = new Database();
$conn = $db->getConnection();

// Get filter parameters
$selected_class = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$selected_exam = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

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
        $classes_result = $conn->query("SELECT class_id, class_name, section FROM classes WHERE 1=0");
    }
} else {
    // Admins see all classes
    $classes_result = $conn->query("SELECT class_id, class_name, section FROM classes ORDER BY class_name");
}

// Get exams for selected class
$exams = [];
if ($selected_class > 0) {
    $stmt = $conn->prepare("SELECT e.*, s.subject_name 
                           FROM exams e 
                           JOIN subjects s ON e.subject_id = s.subject_id 
                           WHERE e.class_id = ? 
                           ORDER BY e.exam_date DESC");
    $stmt->bind_param("i", $selected_class);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $exams[] = $row;
    }
    $stmt->close();
}

// Get grades if exam is selected
$grades = [];
if ($selected_exam > 0) {
    $query = "SELECT g.*, s.first_name, s.last_name, s.roll_number, e.total_marks, e.passing_marks
              FROM grades g
              JOIN students s ON g.student_id = s.student_id
              JOIN exams e ON g.exam_id = e.exam_id
              WHERE g.exam_id = ?
              ORDER BY s.roll_number";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $selected_exam);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $grades[] = $row;
    }
    $stmt->close();
}

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-graduation-cap"></i> Grades Management</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Grades</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Quick Actions -->
<div class="row mb-3">
    <div class="col-12 text-end">
        <a href="<?php echo APP_URL;?>/grades/liberian-grade-sheet.php" class="btn btn-info text-white">
            <i class="fas fa-file-alt"></i> Grade Sheet
        </a>
        <?php if (hasRole('teacher')): ?>
        <a href="<?php echo APP_URL;?>/grades/add-exam.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Create Exam
        </a>
        <a href="<?php echo APP_URL;?>/grades/enter-marks.php" class="btn btn-success">
            <i class="fas fa-pen"></i> Enter Marks
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filter Form -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Select Class and Exam</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-5">
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
                    <div class="col-md-5">
                        <label for="exam_id" class="form-label">Exam</label>
                        <select class="form-select" id="exam_id" name="exam_id" required>
                            <option value="">Select Exam</option>
                            <?php foreach ($exams as $exam): ?>
                            <option value="<?php echo $exam['exam_id']; ?>"
                                    <?php echo $selected_exam == $exam['exam_id'] ? 'selected' : ''; ?>>
                                <?php echo $exam['exam_name'] . ' - ' . $exam['subject_name'] . ' (' . formatDate($exam['exam_date']) . ')'; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
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

<!-- Grades Table -->
<?php if ($selected_exam > 0 && count($grades) > 0): ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Exam Results</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Roll No</th>
                                <th>Student Name</th>
                                <th>Marks Obtained</th>
                                <th>Total Marks</th>
                                <th>Percentage</th>
                                <th>Status</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($grades as $grade): 
                                $percentage = ($grade['marks_obtained'] / $grade['total_marks']) * 100;
                                $passed = $grade['marks_obtained'] >= $grade['passing_marks'];
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($grade['roll_number']); ?></td>
                                <td><?php echo htmlspecialchars($grade['first_name'] . ' ' . $grade['last_name']); ?></td>
                                <td><?php echo $grade['marks_obtained']; ?></td>
                                <td><?php echo $grade['total_marks']; ?></td>
                                <td><?php echo number_format($percentage, 2); ?>%</td>
                                <td>
                                    <?php if ($passed): ?>
                                    <span class="badge bg-success">Pass</span>
                                    <?php else: ?>
                                    <span class="badge bg-danger">Fail</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($grade['remarks']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Statistics -->
                <?php
                $total_students = count($grades);
                $passed_students = array_filter($grades, function($g) {
                    return $g['marks_obtained'] >= $g['passing_marks'];
                });
                $pass_count = count($passed_students);
                $fail_count = $total_students - $pass_count;
                $pass_percentage = $total_students > 0 ? ($pass_count / $total_students) * 100 : 0;
                ?>
                
                <div class="row mt-4">
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h3><?php echo $total_students; ?></h3>
                                <p class="mb-0">Total Students</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h3><?php echo $pass_count; ?></h3>
                                <p class="mb-0">Passed</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white">
                            <div class="card-body text-center">
                                <h3><?php echo $fail_count; ?></h3>
                                <p class="mb-0">Failed</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center">
                                <h3><?php echo number_format($pass_percentage, 1); ?>%</h3>
                                <p class="mb-0">Pass Rate</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php elseif ($selected_exam > 0): ?>
<div class="alert alert-info">
    <i class="fas fa-info-circle"></i> No grades recorded for this exam yet.
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
