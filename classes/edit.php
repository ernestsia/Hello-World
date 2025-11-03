<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireRole('admin');

$pageTitle = 'Edit Class';
$db = new Database();
$conn = $db->getConnection();

$class_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];

if ($class_id === 0) {
    setFlashMessage('danger', 'Invalid class ID');
    redirect(APP_URL . '/classes/list.php');
}

// Get class details
$stmt = $conn->prepare("SELECT * FROM classes WHERE class_id = ?");
$stmt->bind_param("i", $class_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setFlashMessage('danger', 'Class not found');
    redirect(APP_URL . '/classes/list.php');
}

$class = $result->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $class_name = sanitize($_POST['class_name']);
    $section = sanitize($_POST['section']);
    $class_teacher_id = !empty($_POST['class_teacher_id']) ? (int)$_POST['class_teacher_id'] : null;
    $room_number = sanitize($_POST['room_number']);
    $capacity = !empty($_POST['capacity']) ? (int)$_POST['capacity'] : null;
    
    // Validation
    if (empty($class_name)) {
        $errors[] = 'Class name is required';
    }
    if (empty($section)) {
        $errors[] = 'Section is required';
    }
    
    // Check if class name and section combination already exists (excluding current class)
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT class_id FROM classes WHERE class_name = ? AND section = ? AND class_id != ?");
        $stmt->bind_param("ssi", $class_name, $section, $class_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = 'This class and section combination already exists';
        }
        $stmt->close();
    }
    
    // Update if no errors
    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE classes SET class_name = ?, section = ?, class_teacher_id = ?, room_number = ?, capacity = ? WHERE class_id = ?");
        $stmt->bind_param("ssissi", $class_name, $section, $class_teacher_id, $room_number, $capacity, $class_id);
        
        if ($stmt->execute()) {
            setFlashMessage('success', 'Class updated successfully!');
            redirect(APP_URL . '/classes/view.php?id=' . $class_id);
        } else {
            $errors[] = 'Error updating class: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        // Keep form values
        $class['class_name'] = $class_name;
        $class['section'] = $section;
        $class['class_teacher_id'] = $class_teacher_id;
        $class['room_number'] = $room_number;
        $class['capacity'] = $capacity;
    }
}

// Get all teachers for dropdown
$teachers_result = $conn->query("SELECT teacher_id, first_name, last_name FROM teachers ORDER BY first_name, last_name");

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-edit"></i> Edit Class</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/classes/list.php">Classes</a></li>
                <li class="breadcrumb-item active">Edit Class</li>
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

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-warning text-white">
                <h5 class="mb-0">Class Information</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="class_name" class="form-label">Class Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="class_name" name="class_name" required
                                   placeholder="e.g., Grade 1, Class 10, Year 5"
                                   value="<?php echo htmlspecialchars($class['class_name']); ?>">
                            <small class="text-muted">Enter the class/grade name</small>
                        </div>
                        <div class="col-md-6">
                            <label for="section" class="form-label">Section <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="section" name="section" required
                                   placeholder="e.g., A, B, C"
                                   value="<?php echo htmlspecialchars($class['section']); ?>">
                            <small class="text-muted">Enter the section/division</small>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="class_teacher_id" class="form-label">Class Teacher</label>
                            <select class="form-select" id="class_teacher_id" name="class_teacher_id">
                                <option value="">Select Class Teacher (Optional)</option>
                                <?php while ($teacher = $teachers_result->fetch_assoc()): ?>
                                <option value="<?php echo $teacher['teacher_id']; ?>"
                                        <?php echo ($class['class_teacher_id'] == $teacher['teacher_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                            <small class="text-muted">Assign a class teacher (optional)</small>
                        </div>
                        <div class="col-md-6">
                            <label for="room_number" class="form-label">Room Number</label>
                            <input type="text" class="form-control" id="room_number" name="room_number"
                                   placeholder="e.g., 101, Room A"
                                   value="<?php echo htmlspecialchars($class['room_number']); ?>">
                            <small class="text-muted">Enter the classroom number</small>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="capacity" class="form-label">Capacity</label>
                            <input type="number" class="form-control" id="capacity" name="capacity" min="1"
                                   placeholder="e.g., 30, 40"
                                   value="<?php echo htmlspecialchars($class['capacity']); ?>">
                            <small class="text-muted">Maximum number of students</small>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-save"></i> Update Class
                            </button>
                            <a href="<?php echo APP_URL;?>/classes/view.php?id=<?php echo $class_id; ?>" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Cancel
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
