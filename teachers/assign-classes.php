<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireRole('admin');

$pageTitle = 'Assign Classes to Teacher';
$db = new Database();
$conn = $db->getConnection();

$teacher_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($teacher_id === 0) {
    setFlashMessage('danger', 'Invalid teacher ID');
    redirect(APP_URL . '/teachers/list.php');
}

// Get teacher details
$stmt = $conn->prepare("SELECT first_name, last_name FROM teachers WHERE teacher_id = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setFlashMessage('danger', 'Teacher not found');
    redirect(APP_URL . '/teachers/list.php');
}

$teacher = $result->fetch_assoc();
$stmt->close();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assignments']) && is_array($_POST['assignments'])) {
        $conn->begin_transaction();
        
        try {
            // Delete existing assignments for this teacher
            $stmt = $conn->prepare("DELETE FROM class_subjects WHERE teacher_id = ?");
            $stmt->bind_param("i", $teacher_id);
            $stmt->execute();
            $stmt->close();
            
            // Insert new assignments
            $stmt = $conn->prepare("INSERT INTO class_subjects (class_id, subject_id, teacher_id) VALUES (?, ?, ?)");
            
            foreach ($_POST['assignments'] as $assignment) {
                list($class_id, $subject_id) = explode('_', $assignment);
                $stmt->bind_param("iii", $class_id, $subject_id, $teacher_id);
                $stmt->execute();
            }
            
            $stmt->close();
            $conn->commit();
            
            setFlashMessage('success', 'Classes assigned successfully!');
            redirect(APP_URL . '/teachers/view.php?id=' . $teacher_id);
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'Error assigning classes: ' . $e->getMessage();
        }
    } else {
        // No assignments selected - delete all
        $stmt = $conn->prepare("DELETE FROM class_subjects WHERE teacher_id = ?");
        $stmt->bind_param("i", $teacher_id);
        
        if ($stmt->execute()) {
            setFlashMessage('success', 'All class assignments removed!');
            redirect(APP_URL . '/teachers/view.php?id=' . $teacher_id);
        }
        $stmt->close();
    }
}

// Get all classes and subjects
$classes_query = "SELECT c.class_id, c.class_name, c.section, s.subject_id, s.subject_name, s.subject_code
                  FROM classes c
                  CROSS JOIN subjects s
                  ORDER BY c.class_name, c.section, s.subject_name";
$classes_result = $conn->query($classes_query);

// Get current assignments
$current_assignments = [];
$stmt = $conn->prepare("SELECT class_id, subject_id FROM class_subjects WHERE teacher_id = ?");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $current_assignments[] = $row['class_id'] . '_' . $row['subject_id'];
}
$stmt->close();

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-chalkboard"></i> Assign Classes to Teacher</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/teachers/list.php">Teachers</a></li>
                <li class="breadcrumb-item active">Assign Classes</li>
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
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-user-tie"></i> 
                    <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Select the classes and subjects you want to assign to this teacher.
                </div>
                
                <form method="POST" action="">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th width="50">
                                        <input type="checkbox" id="select-all" class="form-check-input">
                                    </th>
                                    <th>Class</th>
                                    <th>Subject</th>
                                    <th>Subject Code</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $current_class = '';
                                while ($row = $classes_result->fetch_assoc()): 
                                    $assignment_key = $row['class_id'] . '_' . $row['subject_id'];
                                    $is_checked = in_array($assignment_key, $current_assignments);
                                    $class_display = $row['class_name'] . ' - ' . $row['section'];
                                    
                                    // Add separator for different classes
                                    if ($current_class !== $class_display) {
                                        if ($current_class !== '') {
                                            echo '<tr><td colspan="4"><hr></td></tr>';
                                        }
                                        $current_class = $class_display;
                                    }
                                ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" 
                                               name="assignments[]" 
                                               value="<?php echo $assignment_key; ?>"
                                               class="form-check-input assignment-checkbox"
                                               <?php echo $is_checked ? 'checked' : ''; ?>>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($class_display); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['subject_name']); ?></td>
                                    <td>
                                        <span class="badge bg-info">
                                            <?php echo htmlspecialchars($row['subject_code']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Assignments
                        </button>
                        <a href="<?php echo APP_URL;?>/teachers/view.php?id=<?php echo $teacher_id; ?>" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('select-all');
    const checkboxes = document.querySelectorAll('.assignment-checkbox');
    
    selectAll.addEventListener('change', function() {
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
    });
    
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            const noneChecked = Array.from(checkboxes).every(cb => !cb.checked);
            selectAll.checked = allChecked;
            selectAll.indeterminate = !allChecked && !noneChecked;
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>
