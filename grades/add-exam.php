<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireLogin();
// Only teachers can create exams
if (!hasRole('teacher')) {
    setFlashMessage('danger', 'Only teachers can create exams');
    redirect(APP_URL . '/index.php');
}

$pageTitle = 'Create Exam';
$db = new Database();
$conn = $db->getConnection();

$errors = [];
$success = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $exam_name = sanitize($_POST['exam_name']);
    $exam_type = sanitize($_POST['exam_type']);
    $class_id = (int)$_POST['class_id'];
    $subject_id = (int)$_POST['subject_id'];
    $exam_date = sanitize($_POST['exam_date']);
    $total_marks = (int)$_POST['total_marks'];
    $passing_marks = (int)$_POST['passing_marks'];
    
    // Validation
    if (empty($exam_name)) {
        $errors[] = 'Exam name is required';
    }
    if (empty($exam_type)) {
        $errors[] = 'Exam type is required';
    }
    if ($class_id === 0) {
        $errors[] = 'Please select a class';
    }
    if ($subject_id === 0) {
        $errors[] = 'Please select a subject';
    }
    if (empty($exam_date)) {
        $errors[] = 'Exam date is required';
    }
    if ($total_marks <= 0) {
        $errors[] = 'Total marks must be greater than 0';
    }
    if ($passing_marks <= 0) {
        $errors[] = 'Passing marks must be greater than 0';
    }
    if ($passing_marks > $total_marks) {
        $errors[] = 'Passing marks cannot be greater than total marks';
    }
    
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO exams (exam_name, exam_type, class_id, subject_id, exam_date, total_marks, passing_marks) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssiisii", $exam_name, $exam_type, $class_id, $subject_id, $exam_date, $total_marks, $passing_marks);
        
        if ($stmt->execute()) {
            setFlashMessage('success', 'Exam created successfully!');
            redirect(APP_URL . '/grades/index.php');
        } else {
            $errors[] = 'Error creating exam: ' . $conn->error;
        }
        $stmt->close();
    }
}

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

// Get subjects based on user role
if (hasRole('teacher')) {
    // Teachers only see subjects they teach
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
        // Get subjects from teacher_subjects table if it exists, otherwise get subjects from their classes
        $check_table = $conn->query("SHOW TABLES LIKE 'teacher_subjects'");
        if ($check_table->num_rows > 0) {
            // Use teacher_subjects table
            $stmt = $conn->prepare("SELECT DISTINCT s.subject_id, s.subject_name, s.subject_code 
                                   FROM subjects s 
                                   JOIN teacher_subjects ts ON s.subject_id = ts.subject_id 
                                   WHERE ts.teacher_id = ? 
                                   ORDER BY s.subject_name");
            $stmt->bind_param("i", $teacher_id);
            $stmt->execute();
            $subjects_result = $stmt->get_result();
        } else {
            // Fallback: Get subjects from classes they teach
            $stmt = $conn->prepare("SELECT DISTINCT s.subject_id, s.subject_name, s.subject_code 
                                   FROM subjects s 
                                   JOIN class_subjects cs ON s.subject_id = cs.subject_id 
                                   WHERE cs.teacher_id = ? 
                                   ORDER BY s.subject_name");
            $stmt->bind_param("i", $teacher_id);
            $stmt->execute();
            $subjects_result = $stmt->get_result();
        }
    } else {
        $subjects_result = $conn->query("SELECT subject_id, subject_name, subject_code FROM subjects WHERE 1=0");
    }
} else {
    // Admins see all subjects
    $subjects_result = $conn->query("SELECT subject_id, subject_name, subject_code FROM subjects ORDER BY subject_name");
}

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-plus-circle"></i> Create New Exam</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/grades/index.php">Grades</a></li>
                <li class="breadcrumb-item active">Create Exam</li>
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

<div class="row">
    <div class="col-md-8 offset-md-2">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Exam Details</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="exam_name" class="form-label">Exam Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="exam_name" name="exam_name" 
                                   value="<?php echo isset($_POST['exam_name']) ? htmlspecialchars($_POST['exam_name']) : ''; ?>" required>
                            <small class="text-muted">e.g., Mid-Term Exam 2024, Final Exam</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="exam_type" class="form-label">Exam Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="exam_type" name="exam_type" required>
                                <option value="">Select Type</option>
                                <option value="test" <?php echo (isset($_POST['exam_type']) && $_POST['exam_type'] === 'test') ? 'selected' : ''; ?>>Test</option>
                                <option value="final" <?php echo (isset($_POST['exam_type']) && $_POST['exam_type'] === 'final') ? 'selected' : ''; ?>>Final</option>
                                <option value="quiz" <?php echo (isset($_POST['exam_type']) && $_POST['exam_type'] === 'quiz') ? 'selected' : ''; ?>>Quiz</option>
                                <option value="assignment" <?php echo (isset($_POST['exam_type']) && $_POST['exam_type'] === 'assignment') ? 'selected' : ''; ?>>Assignment</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="class_id" class="form-label">Class <span class="text-danger">*</span></label>
                            <select class="form-select" id="class_id" name="class_id" required>
                                <option value="">Select Class</option>
                                <?php while ($class = $classes_result->fetch_assoc()): ?>
                                <option value="<?php echo $class['class_id']; ?>"
                                        <?php echo (isset($_POST['class_id']) && $_POST['class_id'] == $class['class_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section']); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="subject_id" class="form-label">Subject <span class="text-danger">*</span></label>
                            <select class="form-select" id="subject_id" name="subject_id" required>
                                <option value="">Select Subject</option>
                                <?php while ($subject = $subjects_result->fetch_assoc()): ?>
                                <option value="<?php echo $subject['subject_id']; ?>"
                                        <?php echo (isset($_POST['subject_id']) && $_POST['subject_id'] == $subject['subject_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($subject['subject_name'] . ' (' . $subject['subject_code'] . ')'); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="exam_date" class="form-label">Exam Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="exam_date" name="exam_date" 
                                   value="<?php echo isset($_POST['exam_date']) ? htmlspecialchars($_POST['exam_date']) : ''; ?>" required>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="total_marks" class="form-label">Total Marks <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="total_marks" name="total_marks" min="1"
                                   value="<?php echo isset($_POST['total_marks']) ? htmlspecialchars($_POST['total_marks']) : '100'; ?>" required>
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="passing_marks" class="form-label">Passing Marks <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="passing_marks" name="passing_marks" min="1"
                                   value="<?php echo isset($_POST['passing_marks']) ? htmlspecialchars($_POST['passing_marks']) : '40'; ?>" required>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Create Exam
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

<?php include '../includes/footer.php'; ?>
