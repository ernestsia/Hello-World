<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireLogin();
// Only teachers can enter marks
if (!hasRole('teacher')) {
    setFlashMessage('danger', 'Only teachers can enter marks');
    redirect(APP_URL . '/index.php');
}

$pageTitle = 'Enter Marks';
$db = new Database();
$conn = $db->getConnection();

$errors = [];
$success = false;

// Get selected exam
$selected_exam = isset($_GET['exam_id']) ? (int)$_GET['exam_id'] : 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $exam_id = (int)$_POST['exam_id'];
    $marks_data = $_POST['marks'];
    
    if ($exam_id === 0) {
        $errors[] = 'Please select an exam';
    }
    
    if (empty($errors)) {
        $conn->begin_transaction();
        
        try {
            // Delete existing grades for this exam
            $stmt = $conn->prepare("DELETE FROM grades WHERE exam_id = ?");
            $stmt->bind_param("i", $exam_id);
            $stmt->execute();
            $stmt->close();
            
            // Insert new grades
            $stmt = $conn->prepare("INSERT INTO grades (student_id, exam_id, marks_obtained, remarks) VALUES (?, ?, ?, ?)");
            
            foreach ($marks_data as $student_id => $data) {
                if (!empty($data['marks']) && $data['marks'] !== '') {
                    $marks = (float)$data['marks'];
                    $remarks = sanitize($data['remarks']);
                    
                    $stmt->bind_param("iids", $student_id, $exam_id, $marks, $remarks);
                    $stmt->execute();
                }
            }
            
            $stmt->close();
            $conn->commit();
            
            setFlashMessage('success', 'Marks entered successfully!');
            redirect(APP_URL . '/grades/index.php?exam_id=' . $exam_id);
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'Error entering marks: ' . $e->getMessage();
        }
    }
}

// Get exams based on user role
if (hasRole('teacher')) {
    // Teachers see exams for classes where they teach any subject OR are class teacher
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
        $stmt = $conn->prepare("SELECT e.*, s.subject_name, c.class_name, c.section 
                               FROM exams e 
                               JOIN subjects s ON e.subject_id = s.subject_id 
                               JOIN classes c ON e.class_id = c.class_id 
                               WHERE c.class_teacher_id = ?
                               OR c.class_id IN (SELECT DISTINCT class_id FROM class_subjects WHERE teacher_id = ?)
                               ORDER BY e.exam_date DESC");
        $stmt->bind_param("ii", $teacher_id, $teacher_id);
        $stmt->execute();
        $exams_result = $stmt->get_result();
    } else {
        $exams_result = $conn->query("SELECT e.*, s.subject_name, c.class_name, c.section 
                                      FROM exams e 
                                      JOIN subjects s ON e.subject_id = s.subject_id 
                                      JOIN classes c ON e.class_id = c.class_id 
                                      WHERE 1=0");
    }
} else {
    // Admins see all exams
    $exams_query = "SELECT e.*, s.subject_name, c.class_name, c.section 
                    FROM exams e 
                    JOIN subjects s ON e.subject_id = s.subject_id 
                    JOIN classes c ON e.class_id = c.class_id 
                    ORDER BY e.exam_date DESC";
    $exams_result = $conn->query($exams_query);
}

// Get students and existing grades if exam is selected
$students = [];
$exam_info = null;
$existing_grades = [];

if ($selected_exam > 0) {
    // Get exam info
    $stmt = $conn->prepare("SELECT e.*, s.subject_name, c.class_name, c.section 
                           FROM exams e 
                           JOIN subjects s ON e.subject_id = s.subject_id 
                           JOIN classes c ON e.class_id = c.class_id 
                           WHERE e.exam_id = ?");
    $stmt->bind_param("i", $selected_exam);
    $stmt->execute();
    $result = $stmt->get_result();
    $exam_info = $result->fetch_assoc();
    $stmt->close();
    
    if ($exam_info) {
        // Get students in the class
        $stmt = $conn->prepare("SELECT student_id, first_name, last_name, roll_number 
                               FROM students 
                               WHERE class_id = ? 
                               ORDER BY roll_number");
        $stmt->bind_param("i", $exam_info['class_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $students[] = $row;
        }
        $stmt->close();
        
        // Get existing grades
        $stmt = $conn->prepare("SELECT student_id, marks_obtained, remarks 
                               FROM grades 
                               WHERE exam_id = ?");
        $stmt->bind_param("i", $selected_exam);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $existing_grades[$row['student_id']] = $row;
        }
        $stmt->close();
    }
}

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-pen"></i> Enter Marks</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/grades/index.php">Grades</a></li>
                <li class="breadcrumb-item active">Enter Marks</li>
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

<!-- Select Exam -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Select Exam</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-10">
                        <select class="form-select" name="exam_id" required onchange="this.form.submit()">
                            <option value="">Select Exam</option>
                            <?php 
                            $exams_result->data_seek(0);
                            while ($exam = $exams_result->fetch_assoc()): 
                            ?>
                            <option value="<?php echo $exam['exam_id']; ?>"
                                    <?php echo $selected_exam == $exam['exam_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($exam['exam_name'] . ' - ' . $exam['subject_name'] . ' (' . $exam['class_name'] . ' - ' . $exam['section'] . ') - ' . formatDate($exam['exam_date'])); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <a href="<?php echo APP_URL;?>/grades/add-exam.php" class="btn btn-success w-100">
                            <i class="fas fa-plus"></i> New Exam
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Enter Marks Form -->
<?php if ($selected_exam > 0 && $exam_info && count($students) > 0): ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <?php echo htmlspecialchars($exam_info['exam_name']); ?> - 
                    <?php echo htmlspecialchars($exam_info['subject_name']); ?>
                </h5>
                <small>
                    Class: <?php echo htmlspecialchars($exam_info['class_name'] . ' - ' . $exam_info['section']); ?> | 
                    Total Marks: <?php echo $exam_info['total_marks']; ?> | 
                    Passing Marks: <?php echo $exam_info['passing_marks']; ?>
                </small>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="exam_id" value="<?php echo $selected_exam; ?>">
                    
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th width="10%">Roll No</th>
                                    <th width="30%">Student Name</th>
                                    <th width="20%">Marks Obtained (out of <?php echo $exam_info['total_marks']; ?>)</th>
                                    <th width="30%">Remarks</th>
                                    <th width="10%">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): 
                                    $current_marks = isset($existing_grades[$student['student_id']]) ? $existing_grades[$student['student_id']]['marks_obtained'] : '';
                                    $current_remarks = isset($existing_grades[$student['student_id']]) ? $existing_grades[$student['student_id']]['remarks'] : '';
                                    $is_pass = $current_marks !== '' && $current_marks >= $exam_info['passing_marks'];
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['roll_number']); ?></td>
                                    <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                    <td>
                                        <input type="number" 
                                               class="form-control" 
                                               name="marks[<?php echo $student['student_id']; ?>][marks]" 
                                               min="0" 
                                               max="<?php echo $exam_info['total_marks']; ?>" 
                                               step="0.01"
                                               value="<?php echo htmlspecialchars($current_marks); ?>"
                                               placeholder="Enter marks">
                                    </td>
                                    <td>
                                        <input type="text" 
                                               class="form-control" 
                                               name="marks[<?php echo $student['student_id']; ?>][remarks]" 
                                               value="<?php echo htmlspecialchars($current_remarks); ?>"
                                               placeholder="Optional remarks">
                                    </td>
                                    <td class="text-center">
                                        <?php if ($current_marks !== ''): ?>
                                            <?php if ($is_pass): ?>
                                            <span class="badge bg-success">Pass</span>
                                            <?php else: ?>
                                            <span class="badge bg-danger">Fail</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                        <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-3">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> Save Marks
                        </button>
                        <a href="<?php echo APP_URL;?>/grades/index.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php elseif ($selected_exam > 0 && $exam_info): ?>
<div class="alert alert-info">
    <i class="fas fa-info-circle"></i> No students found in the selected class.
</div>
<?php elseif ($selected_exam > 0): ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-triangle"></i> Exam not found.
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
