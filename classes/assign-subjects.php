<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireRole('admin');

$pageTitle = 'Assign Subjects to Class';
$db = new Database();
$conn = $db->getConnection();

$class_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];
$success = false;

// Get class info
$class = null;
if ($class_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM classes WHERE class_id = ?");
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $class = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Get all subjects
$subjects_result = $conn->query("SELECT * FROM subjects ORDER BY subject_name");

// Get currently assigned subjects
$assigned_subjects = [];
if ($class_id > 0) {
    $stmt = $conn->prepare("SELECT subject_id, teacher_id FROM class_subjects WHERE class_id = ?");
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $assigned_subjects[$row['subject_id']] = $row['teacher_id'];
    }
    $stmt->close();
}

// Get all teachers
$teachers_result = $conn->query("SELECT teacher_id, first_name, last_name FROM teachers ORDER BY first_name, last_name");
$teachers = [];
while ($teacher = $teachers_result->fetch_assoc()) {
    $teachers[] = $teacher;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $class_id > 0) {
    $selected_subjects = isset($_POST['subjects']) ? $_POST['subjects'] : [];
    
    $conn->begin_transaction();
    
    try {
        // Delete all existing assignments for this class
        $stmt = $conn->prepare("DELETE FROM class_subjects WHERE class_id = ?");
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $stmt->close();
        
        // Insert new assignments
        if (!empty($selected_subjects)) {
            $stmt = $conn->prepare("INSERT INTO class_subjects (class_id, subject_id, teacher_id) VALUES (?, ?, ?)");
            
            foreach ($selected_subjects as $subject_id) {
                $teacher_id = !empty($_POST["teacher_{$subject_id}"]) ? (int)$_POST["teacher_{$subject_id}"] : null;
                $stmt->bind_param("iii", $class_id, $subject_id, $teacher_id);
                $stmt->execute();
            }
            $stmt->close();
        }
        
        $conn->commit();
        $success = true;
        setFlashMessage('success', 'Subjects assigned successfully!');
        redirect(APP_URL . "/classes/view.php?id={$class_id}");
        
    } catch (Exception $e) {
        $conn->rollback();
        $errors[] = 'Error assigning subjects: ' . $e->getMessage();
    }
}

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-book"></i> Assign Subjects to Class</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/classes/list.php">Classes</a></li>
                <li class="breadcrumb-item active">Assign Subjects</li>
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

<?php if ($class): ?>
<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h5>Class: <strong><?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section']); ?></strong></h5>
            </div>
        </div>
    </div>
</div>

<form method="POST" action="">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Select Subjects and Assign Teachers</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th width="50">
                                        <input type="checkbox" id="select_all" onclick="toggleAll(this)">
                                    </th>
                                    <th>Subject Name</th>
                                    <th>Assign Teacher (Optional)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $subjects_result->data_seek(0);
                                while ($subject = $subjects_result->fetch_assoc()): 
                                    $is_assigned = isset($assigned_subjects[$subject['subject_id']]);
                                    $assigned_teacher = $is_assigned ? $assigned_subjects[$subject['subject_id']] : null;
                                ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" name="subjects[]" value="<?php echo $subject['subject_id']; ?>"
                                               class="subject-checkbox" <?php echo $is_assigned ? 'checked' : ''; ?>>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($subject['subject_name']); ?></strong>
                                    </td>
                                    <td>
                                        <select class="form-select" name="teacher_<?php echo $subject['subject_id']; ?>">
                                            <option value="">No Teacher Assigned</option>
                                            <?php foreach ($teachers as $teacher): ?>
                                            <option value="<?php echo $teacher['teacher_id']; ?>"
                                                    <?php echo ($assigned_teacher == $teacher['teacher_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
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
                        <a href="<?php echo APP_URL;?>/classes/view.php?id=<?php echo $class_id; ?>" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
function toggleAll(source) {
    const checkboxes = document.querySelectorAll('.subject-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = source.checked;
    });
}
</script>

<?php else: ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-triangle"></i> Class not found.
</div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
