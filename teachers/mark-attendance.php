<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireLogin();

// Only teachers and admins can access
if (!hasRole('teacher') && !hasRole('admin')) {
    redirect(APP_URL . '/index.php');
}

$pageTitle = 'Mark Attendance';
$db = new Database();
$conn = $db->getConnection();

$errors = [];
$success = false;

// Get teacher's assigned classes
$teacher_classes = [];
if (hasRole('teacher')) {
    $stmt = $conn->prepare("SELECT teacher_id FROM teachers WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $teacher_id = $result->fetch_assoc()['teacher_id'];
        
        // Get classes taught by this teacher
        $stmt = $conn->prepare("SELECT DISTINCT c.class_id, c.class_name, c.section 
                               FROM class_subjects cs
                               JOIN classes c ON cs.class_id = c.class_id
                               WHERE cs.teacher_id = ?
                               ORDER BY c.class_name, c.section");
        $stmt->bind_param("i", $teacher_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $teacher_classes[] = $row;
        }
        $stmt->close();
    }
}

// Get selected date and class
$selected_date = isset($_GET['date']) ? sanitize($_GET['date']) : date('Y-m-d');
$selected_class = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;

// Handle attendance submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $attendance_date = sanitize($_POST['attendance_date']);
    $class_id = (int)$_POST['class_id'];
    $attendance_data = $_POST['attendance'];
    $remarks = $_POST['remarks'] ?? [];
    
    if (empty($class_id)) {
        $errors[] = 'Please select a class';
    }
    
    // Verify teacher has access to this class
    if (hasRole('teacher')) {
        $has_access = false;
        foreach ($teacher_classes as $tc) {
            if ($tc['class_id'] == $class_id) {
                $has_access = true;
                break;
            }
        }
        if (!$has_access) {
            $errors[] = 'You do not have permission to mark attendance for this class';
        }
    }
    
    if (empty($errors)) {
        $conn->begin_transaction();
        
        try {
            // Delete existing attendance for this date and class
            $stmt = $conn->prepare("DELETE FROM attendance WHERE attendance_date = ? AND class_id = ?");
            $stmt->bind_param("si", $attendance_date, $class_id);
            $stmt->execute();
            $stmt->close();
            
            // Insert new attendance records
            $stmt = $conn->prepare("INSERT INTO attendance (student_id, class_id, attendance_date, status, remarks, marked_by) VALUES (?, ?, ?, ?, ?, ?)");
            
            foreach ($attendance_data as $student_id => $status) {
                $remark = isset($remarks[$student_id]) ? sanitize($remarks[$student_id]) : '';
                $stmt->bind_param("iisssi", $student_id, $class_id, $attendance_date, $status, $remark, $_SESSION['user_id']);
                $stmt->execute();
            }
            
            $stmt->close();
            $conn->commit();
            
            setFlashMessage('success', 'Attendance marked successfully!');
            redirect(APP_URL . '/teachers/mark-attendance.php?date=' . $attendance_date . '&class_id=' . $class_id);
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'Error marking attendance: ' . $e->getMessage();
        }
    }
}

// Get all classes for admin
$classes_result = null;
if (hasRole('admin')) {
    $classes_result = $conn->query("SELECT class_id, class_name, section FROM classes ORDER BY class_name");
}

// Get students and attendance if class is selected
$students = [];
$attendance_records = [];

if ($selected_class > 0) {
    // Verify access for teachers
    if (hasRole('teacher')) {
        $has_access = false;
        foreach ($teacher_classes as $tc) {
            if ($tc['class_id'] == $selected_class) {
                $has_access = true;
                break;
            }
        }
        if (!$has_access) {
            setFlashMessage('danger', 'You do not have permission to view this class');
            redirect(APP_URL . '/teachers/mark-attendance.php');
        }
    }
    
    // Get students in the class
    $stmt = $conn->prepare("SELECT student_id, first_name, last_name, roll_number, photo FROM students WHERE class_id = ? ORDER BY roll_number");
    $stmt->bind_param("i", $selected_class);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }
    $stmt->close();
    
    // Get existing attendance for the selected date
    $stmt = $conn->prepare("SELECT student_id, status, remarks FROM attendance WHERE class_id = ? AND attendance_date = ?");
    $stmt->bind_param("is", $selected_class, $selected_date);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $attendance_records[$row['student_id']] = $row;
    }
    $stmt->close();
}

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-calendar-check"></i> Mark Attendance</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Mark Attendance</li>
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
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-filter"></i> Select Date and Class</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-4">
                        <label for="date" class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="date" name="date" 
                               value="<?php echo $selected_date; ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label for="class_id" class="form-label">Class <span class="text-danger">*</span></label>
                        <select class="form-select" id="class_id" name="class_id" required>
                            <option value="">Select Class</option>
                            <?php if (hasRole('admin') && $classes_result): ?>
                                <?php while ($class = $classes_result->fetch_assoc()): ?>
                                <option value="<?php echo $class['class_id']; ?>"
                                        <?php echo $selected_class == $class['class_id'] ? 'selected' : ''; ?>>
                                    <?php echo $class['class_name'] . ' - ' . $class['section']; ?>
                                </option>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <?php foreach ($teacher_classes as $class): ?>
                                <option value="<?php echo $class['class_id']; ?>"
                                        <?php echo $selected_class == $class['class_id'] ? 'selected' : ''; ?>>
                                    <?php echo $class['class_name'] . ' - ' . $class['section']; ?>
                                </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i> Load
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Attendance Form -->
<?php if ($selected_class > 0 && count($students) > 0): ?>
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="fas fa-users"></i> Mark Attendance - <?php echo formatDate($selected_date); ?>
                    <span class="badge bg-light text-dark float-end"><?php echo count($students); ?> Students</span>
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="attendance_date" value="<?php echo $selected_date; ?>">
                    <input type="hidden" name="class_id" value="<?php echo $selected_class; ?>">
                    
                    <!-- Quick Actions -->
                    <div class="mb-3 p-3 bg-light rounded">
                        <strong>Quick Actions:</strong>
                        <button type="button" class="btn btn-sm btn-success ms-2" onclick="markAll('present')">
                            <i class="fas fa-check"></i> Mark All Present
                        </button>
                        <button type="button" class="btn btn-sm btn-danger ms-2" onclick="markAll('absent')">
                            <i class="fas fa-times"></i> Mark All Absent
                        </button>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th width="80">Photo</th>
                                    <th width="100">Roll No</th>
                                    <th>Student Name</th>
                                    <th width="100" class="text-center">Present</th>
                                    <th width="100" class="text-center">Absent</th>
                                    <th width="100" class="text-center">Late</th>
                                    <th width="100" class="text-center">Excused</th>
                                    <th width="200">Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): 
                                    $current_status = isset($attendance_records[$student['student_id']]) 
                                        ? $attendance_records[$student['student_id']]['status'] 
                                        : 'present';
                                    $current_remark = isset($attendance_records[$student['student_id']]) 
                                        ? $attendance_records[$student['student_id']]['remarks'] 
                                        : '';
                                ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($student['photo'])): ?>
                                        <img src="<?php echo APP_URL;?>/uploads/students/<?php echo $student['photo']; ?>" 
                                             class="rounded-circle" style="width: 50px; height: 50px; object-fit: cover;" 
                                             alt="Student Photo">
                                        <?php else: ?>
                                        <i class="fas fa-user-circle fa-3x text-muted"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($student['roll_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                    <td class="text-center">
                                        <input type="radio" 
                                               name="attendance[<?php echo $student['student_id']; ?>]" 
                                               value="present" 
                                               class="form-check-input status-radio"
                                               <?php echo $current_status === 'present' ? 'checked' : ''; ?>>
                                    </td>
                                    <td class="text-center">
                                        <input type="radio" 
                                               name="attendance[<?php echo $student['student_id']; ?>]" 
                                               value="absent" 
                                               class="form-check-input status-radio"
                                               <?php echo $current_status === 'absent' ? 'checked' : ''; ?>>
                                    </td>
                                    <td class="text-center">
                                        <input type="radio" 
                                               name="attendance[<?php echo $student['student_id']; ?>]" 
                                               value="late" 
                                               class="form-check-input status-radio"
                                               <?php echo $current_status === 'late' ? 'checked' : ''; ?>>
                                    </td>
                                    <td class="text-center">
                                        <input type="radio" 
                                               name="attendance[<?php echo $student['student_id']; ?>]" 
                                               value="excused" 
                                               class="form-check-input status-radio"
                                               <?php echo $current_status === 'excused' ? 'checked' : ''; ?>>
                                    </td>
                                    <td>
                                        <input type="text" 
                                               name="remarks[<?php echo $student['student_id']; ?>]" 
                                               class="form-control form-control-sm" 
                                               placeholder="Optional"
                                               value="<?php echo htmlspecialchars($current_remark); ?>">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-3">
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-save"></i> Save Attendance
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php elseif ($selected_class > 0): ?>
<div class="alert alert-info">
    <i class="fas fa-info-circle"></i> No students found in the selected class.
</div>
<?php endif; ?>

<script>
function markAll(status) {
    const radios = document.querySelectorAll(`input[type="radio"][value="${status}"]`);
    radios.forEach(radio => {
        radio.checked = true;
    });
}
</script>

<?php include '../includes/footer.php'; ?>
