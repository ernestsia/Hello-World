<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireLogin();

// Only teachers can mark attendance
if (!hasRole('teacher') && !hasRole('admin')) {
    setFlashMessage('danger', 'Access denied. Only teachers can mark attendance.');
    redirect(APP_URL . '/index.php');
}

$pageTitle = 'Attendance Management';
$db = new Database();
$conn = $db->getConnection();

$errors = [];
$success = false;

// Get selected date, class, and subject
$selected_date = isset($_GET['date']) ? sanitize($_GET['date']) : date('Y-m-d');
$selected_class = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$selected_subject = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;

// Handle attendance submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (hasRole('teacher') || hasRole('admin'))) {
    $attendance_date = sanitize($_POST['attendance_date']);
    $class_id = (int)$_POST['class_id'];
    $subject_id = isset($_POST['subject_id']) ? (int)$_POST['subject_id'] : null;
    $attendance_data = $_POST['attendance'];
    
    if (empty($class_id)) {
        $errors[] = 'Please select a class';
    }
    
    // Teachers must select a subject
    if (hasRole('teacher') && empty($subject_id)) {
        $errors[] = 'Please select a subject';
    }
    
    if (empty($errors)) {
        $conn->begin_transaction();
        
        try {
            // Insert or update attendance records
            $stmt = $conn->prepare("INSERT INTO attendance (student_id, class_id, subject_id, attendance_date, status, marked_by) 
                                   VALUES (?, ?, ?, ?, ?, ?)
                                   ON DUPLICATE KEY UPDATE 
                                   subject_id = VALUES(subject_id),
                                   status = VALUES(status),
                                   marked_by = VALUES(marked_by)");
            
            foreach ($attendance_data as $student_id => $status) {
                $stmt->bind_param("iiissi", $student_id, $class_id, $subject_id, $attendance_date, $status, $_SESSION['user_id']);
                $stmt->execute();
            }
            
            $stmt->close();
            $conn->commit();
            
            setFlashMessage('success', 'Attendance marked successfully!');
            $redirect_url = APP_URL . '/attendance/index.php?date=' . $attendance_date . '&class_id=' . $class_id;
            if ($subject_id) {
                $redirect_url .= '&subject_id=' . $subject_id;
            }
            redirect($redirect_url);
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'Error marking attendance: ' . $e->getMessage();
        }
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
        // No classes if teacher not found
        $classes_result = $conn->query("SELECT class_id, class_name, section FROM classes WHERE 1=0");
    }
} else {
    // Admins see all classes
    $classes_result = $conn->query("SELECT class_id, class_name, section FROM classes ORDER BY class_name");
}

// Get subjects for selected class based on user role
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
        // Admins see all subjects for the class (optional for general attendance)
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

// Get students and attendance if class and subject are selected
$students = [];
$attendance_records = [];

if ($selected_class > 0 && (hasRole('admin') || $selected_subject > 0)) {
    // Get students in the class
    $stmt = $conn->prepare("SELECT student_id, first_name, last_name, roll_number FROM students WHERE class_id = ? ORDER BY roll_number");
    $stmt->bind_param("i", $selected_class);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    $stmt->close();
    
    // Get existing attendance for the selected date and subject
    if ($selected_subject > 0) {
        $stmt = $conn->prepare("SELECT student_id, status FROM attendance WHERE class_id = ? AND attendance_date = ? AND subject_id = ?");
        $stmt->bind_param("isi", $selected_class, $selected_date, $selected_subject);
    } else {
        // Admin viewing general attendance
        $stmt = $conn->prepare("SELECT student_id, status FROM attendance WHERE class_id = ? AND attendance_date = ? AND subject_id IS NULL");
        $stmt->bind_param("is", $selected_class, $selected_date);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $attendance_records[$row['student_id']] = $row['status'];
    }
    $stmt->close();
}

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-calendar-check"></i> Attendance Management</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Attendance</li>
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

<!-- Filter Form -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Select Date, Class<?php echo hasRole('teacher') ? ' and Subject' : ''; ?></h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="row g-3" id="filterForm">
                    <div class="col-md-3">
                        <label for="date" class="form-label">Date</label>
                        <input type="date" class="form-control" id="date" name="date" 
                               value="<?php echo $selected_date; ?>" required>
                    </div>
                    <div class="col-md-<?php echo hasRole('teacher') ? '3' : '6'; ?>">
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
                    <?php if (hasRole('teacher')): ?>
                    <div class="col-md-3">
                        <label for="subject_id" class="form-label">Subject <span class="text-danger">*</span></label>
                        <select class="form-select" id="subject_id" name="subject_id" required>
                            <option value="">Select Subject</option>
                            <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo $subject['subject_id']; ?>"
                                    <?php echo $selected_subject == $subject['subject_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subject['subject_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i> Load Attendance
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- No Students Message -->
<?php if ($selected_class > 0 && hasRole('teacher') && $selected_subject == 0 && count($subjects) > 0): ?>
<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle"></i> <strong>Please select a subject</strong> to load students and mark attendance.
</div>
<?php elseif ($selected_class > 0 && hasRole('teacher') && count($subjects) == 0): ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-circle"></i> <strong>No subjects found!</strong> 
    You are not assigned to teach any subjects in this class. Please contact the administrator to assign you to subjects.
</div>
<?php endif; ?>

<!-- Attendance Form -->
<?php if ($selected_class > 0 && count($students) > 0): ?>
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Mark Attendance - <?php echo formatDate($selected_date); ?>
                <?php if ($selected_subject > 0): 
                    $subject_name = '';
                    foreach ($subjects as $subj) {
                        if ($subj['subject_id'] == $selected_subject) {
                            $subject_name = $subj['subject_name'];
                            break;
                        }
                    }
                    echo ' - ' . htmlspecialchars($subject_name);
                endif; ?>
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="attendance_date" value="<?php echo $selected_date; ?>">
                    <input type="hidden" name="class_id" value="<?php echo $selected_class; ?>">
                    <?php if ($selected_subject > 0): ?>
                    <input type="hidden" name="subject_id" value="<?php echo $selected_subject; ?>">
                    <?php endif; ?>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 10%;">Roll No</th>
                                    <th style="width: 35%;">Student Name</th>
                                    <th class="text-center" style="width: 13.75%;">
                                        <i class="fas fa-check-circle text-success"></i> Present
                                    </th>
                                    <th class="text-center" style="width: 13.75%;">
                                        <i class="fas fa-times-circle text-danger"></i> Absent
                                    </th>
                                    <th class="text-center" style="width: 13.75%;">
                                        <i class="fas fa-clock text-warning"></i> Late
                                    </th>
                                    <th class="text-center" style="width: 13.75%;">
                                        <i class="fas fa-info-circle text-info"></i> Excused
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): 
                                    $current_status = isset($attendance_records[$student['student_id']]) ? $attendance_records[$student['student_id']] : 'present';
                                ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($student['roll_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                    <td class="text-center">
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" 
                                                   name="attendance[<?php echo $student['student_id']; ?>]" 
                                                   id="present_<?php echo $student['student_id']; ?>"
                                                   value="present" <?php echo $current_status === 'present' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="present_<?php echo $student['student_id']; ?>"></label>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" 
                                                   name="attendance[<?php echo $student['student_id']; ?>]" 
                                                   id="absent_<?php echo $student['student_id']; ?>"
                                                   value="absent" <?php echo $current_status === 'absent' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="absent_<?php echo $student['student_id']; ?>"></label>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" 
                                                   name="attendance[<?php echo $student['student_id']; ?>]" 
                                                   id="late_<?php echo $student['student_id']; ?>"
                                                   value="late" <?php echo $current_status === 'late' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="late_<?php echo $student['student_id']; ?>"></label>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" 
                                                   name="attendance[<?php echo $student['student_id']; ?>]" 
                                                   id="excused_<?php echo $student['student_id']; ?>"
                                                   value="excused" <?php echo $current_status === 'excused' ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="excused_<?php echo $student['student_id']; ?>"></label>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-3 d-flex justify-content-between align-items-center">
                        <div>
                            <button type="button" class="btn btn-outline-success btn-sm" onclick="markAll('present')">
                                <i class="fas fa-check-double"></i> Mark All Present
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="markAll('absent')">
                                <i class="fas fa-times"></i> Mark All Absent
                            </button>
                        </div>
                        <button type="submit" class="btn btn-success btn-lg" style="background-color: #10B981 !important; color: white !important; border: none !important;">
                            <i class="fas fa-save"></i> Save Attendance
                        </button>
                    </div>
                </form>
                
                <script>
                function markAll(status) {
                    const radios = document.querySelectorAll('input[type="radio"][value="' + status + '"]');
                    radios.forEach(radio => {
                        radio.checked = true;
                    });
                }
                </script>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
