<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireRole('admin');

$pageTitle = 'Assign Subjects to Teacher';
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_subjects = isset($_POST['subjects']) ? $_POST['subjects'] : [];
    $selected_classes = isset($_POST['class_assignments']) ? $_POST['class_assignments'] : [];
    
    $conn->begin_transaction();
    
    try {
        // Delete existing assignments for this teacher
        $stmt = $conn->prepare("DELETE FROM class_subjects WHERE teacher_id = ?");
        $stmt->bind_param("i", $teacher_id);
        $stmt->execute();
        $stmt->close();
        
        // Insert new assignments or update existing ones
        if (!empty($selected_classes)) {
            $stmt = $conn->prepare("INSERT INTO class_subjects (class_id, subject_id, teacher_id) 
                                   VALUES (?, ?, ?) 
                                   ON DUPLICATE KEY UPDATE teacher_id = VALUES(teacher_id)");
            
            foreach ($selected_classes as $assignment) {
                list($class_id, $subject_id) = explode('_', $assignment);
                $stmt->bind_param("iii", $class_id, $subject_id, $teacher_id);
                $stmt->execute();
            }
            
            $stmt->close();
        }
        
        $conn->commit();
        
        setFlashMessage('success', 'Subjects assigned successfully!');
        redirect(APP_URL . '/teachers/view.php?id=' . $teacher_id);
        
    } catch (Exception $e) {
        $conn->rollback();
        $errors[] = 'Error assigning subjects: ' . $e->getMessage();
    }
}

// Get all subjects
$subjects_query = "SELECT subject_id, subject_name, subject_code, description FROM subjects ORDER BY subject_name";
$subjects_result = $conn->query($subjects_query);

// Get all classes
$classes_query = "SELECT class_id, class_name, section FROM classes ORDER BY class_name, section";
$classes_result = $conn->query($classes_query);

// Get current assignments grouped by subject
$current_assignments = [];
$stmt = $conn->prepare("SELECT cs.class_id, cs.subject_id, c.class_name, c.section, s.subject_name
                        FROM class_subjects cs
                        JOIN classes c ON cs.class_id = c.class_id
                        JOIN subjects s ON cs.subject_id = s.subject_id
                        WHERE cs.teacher_id = ?
                        ORDER BY s.subject_name, c.class_name");
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $key = $row['class_id'] . '_' . $row['subject_id'];
    $current_assignments[$key] = $row;
    
    if (!isset($current_assignments['by_subject'][$row['subject_id']])) {
        $current_assignments['by_subject'][$row['subject_id']] = [];
    }
    $current_assignments['by_subject'][$row['subject_id']][] = $row;
}
$stmt->close();

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-book"></i> Assign Subjects to Teacher</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/teachers/list.php">Teachers</a></li>
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
                    <i class="fas fa-info-circle"></i> 
                    <strong>Instructions:</strong> Select subjects and then choose which classes this teacher will teach each subject in.
                </div>
                
                <form method="POST" action="" id="assignmentForm">
                    <!-- Subjects Selection -->
                    <h6 class="text-primary mb-3"><i class="fas fa-book"></i> Select Subjects</h6>
                    <div class="row mb-4">
                        <?php 
                        $subjects_result->data_seek(0);
                        while ($subject = $subjects_result->fetch_assoc()): 
                            $has_assignment = isset($current_assignments['by_subject'][$subject['subject_id']]);
                        ?>
                        <div class="col-md-4 mb-3">
                            <div class="card border <?php echo $has_assignment ? 'border-success' : ''; ?>">
                                <div class="card-body">
                                    <div class="form-check">
                                        <input class="form-check-input subject-checkbox" 
                                               type="checkbox" 
                                               value="<?php echo $subject['subject_id']; ?>" 
                                               id="subject_<?php echo $subject['subject_id']; ?>"
                                               data-subject-id="<?php echo $subject['subject_id']; ?>"
                                               <?php echo $has_assignment ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="subject_<?php echo $subject['subject_id']; ?>">
                                            <strong><?php echo htmlspecialchars($subject['subject_name']); ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                <span class="badge bg-info"><?php echo htmlspecialchars($subject['subject_code']); ?></span>
                                                <?php echo htmlspecialchars($subject['description']); ?>
                                            </small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    
                    <hr>
                    
                    <!-- Class Assignments for Each Subject -->
                    <h6 class="text-primary mb-3"><i class="fas fa-chalkboard"></i> Assign Classes for Each Subject</h6>
                    
                    <?php 
                    $subjects_result->data_seek(0);
                    while ($subject = $subjects_result->fetch_assoc()): 
                        $has_assignment = isset($current_assignments['by_subject'][$subject['subject_id']]);
                    ?>
                    <div class="subject-classes mb-4" 
                         id="classes_for_subject_<?php echo $subject['subject_id']; ?>"
                         style="display: <?php echo $has_assignment ? 'block' : 'none'; ?>;">
                        <div class="card border-primary">
                            <div class="card-header bg-primary text-white">
                                <h6 class="mb-0">
                                    <i class="fas fa-book"></i> 
                                    <?php echo htmlspecialchars($subject['subject_name']); ?> 
                                    <span class="badge bg-light text-dark"><?php echo htmlspecialchars($subject['subject_code']); ?></span>
                                </h6>
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-3">Select which classes this teacher will teach <?php echo htmlspecialchars($subject['subject_name']); ?> in:</p>
                                <div class="row">
                                    <?php 
                                    $classes_result->data_seek(0);
                                    while ($class = $classes_result->fetch_assoc()): 
                                        $assignment_key = $class['class_id'] . '_' . $subject['subject_id'];
                                        $is_checked = isset($current_assignments[$assignment_key]);
                                    ?>
                                    <div class="col-md-3 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input class-checkbox" 
                                                   type="checkbox" 
                                                   name="class_assignments[]"
                                                   value="<?php echo $assignment_key; ?>"
                                                   id="class_<?php echo $assignment_key; ?>"
                                                   data-subject-id="<?php echo $subject['subject_id']; ?>"
                                                   <?php echo $is_checked ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="class_<?php echo $assignment_key; ?>">
                                                <?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section']); ?>
                                            </label>
                                        </div>
                                    </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-save"></i> Save Subject Assignments
                        </button>
                        <a href="<?php echo APP_URL;?>/teachers/view.php?id=<?php echo $teacher_id; ?>" class="btn btn-secondary btn-lg">
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
    const subjectCheckboxes = document.querySelectorAll('.subject-checkbox');
    
    // Handle subject checkbox changes
    subjectCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const subjectId = this.dataset.subjectId;
            const classesDiv = document.getElementById('classes_for_subject_' + subjectId);
            
            if (this.checked) {
                classesDiv.style.display = 'block';
                // Smooth scroll to the classes section
                setTimeout(() => {
                    classesDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                }, 100);
            } else {
                classesDiv.style.display = 'none';
                // Uncheck all class checkboxes for this subject
                const classCheckboxes = classesDiv.querySelectorAll('.class-checkbox');
                classCheckboxes.forEach(cb => cb.checked = false);
            }
        });
    });
    
    // Validate form before submission
    document.getElementById('assignmentForm').addEventListener('submit', function(e) {
        const checkedSubjects = document.querySelectorAll('.subject-checkbox:checked');
        
        if (checkedSubjects.length === 0) {
            if (!confirm('No subjects selected. This will remove all subject assignments. Continue?')) {
                e.preventDefault();
            }
        } else {
            // Check if at least one class is selected for each checked subject
            let hasError = false;
            checkedSubjects.forEach(subjectCheckbox => {
                const subjectId = subjectCheckbox.dataset.subjectId;
                const classCheckboxes = document.querySelectorAll(`.class-checkbox[data-subject-id="${subjectId}"]:checked`);
                
                if (classCheckboxes.length === 0) {
                    alert(`Please select at least one class for ${subjectCheckbox.nextElementSibling.querySelector('strong').textContent}`);
                    hasError = true;
                }
            });
            
            if (hasError) {
                e.preventDefault();
            }
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
