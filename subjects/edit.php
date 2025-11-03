<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireRole('admin');

$pageTitle = 'Edit Subject';
$db = new Database();
$conn = $db->getConnection();

$subject_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$errors = [];

if ($subject_id === 0) {
    setFlashMessage('danger', 'Invalid subject ID');
    redirect(APP_URL . '/subjects/list.php');
}

// Get subject details
$stmt = $conn->prepare("SELECT * FROM subjects WHERE subject_id = ?");
$stmt->bind_param("i", $subject_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setFlashMessage('danger', 'Subject not found');
    redirect(APP_URL . '/subjects/list.php');
}

$subject = $result->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject_name = sanitize($_POST['subject_name']);
    $subject_code = sanitize($_POST['subject_code']);
    $description = sanitize($_POST['description']);
    
    if (empty($subject_name)) {
        $errors[] = 'Subject name is required';
    }
    if (empty($subject_code)) {
        $errors[] = 'Subject code is required';
    }
    
    // Check if subject code exists (excluding current subject)
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT subject_id FROM subjects WHERE subject_code = ? AND subject_id != ?");
        $stmt->bind_param("si", $subject_code, $subject_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = 'Subject code already exists';
        }
        $stmt->close();
    }
    
    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE subjects SET subject_name = ?, subject_code = ?, description = ? WHERE subject_id = ?");
        $stmt->bind_param("sssi", $subject_name, $subject_code, $description, $subject_id);
        
        if ($stmt->execute()) {
            setFlashMessage('success', 'Subject updated successfully!');
            redirect(APP_URL . '/subjects/list.php');
        } else {
            $errors[] = 'Error updating subject';
        }
        $stmt->close();
    } else {
        // Keep form values
        $subject['subject_name'] = $subject_name;
        $subject['subject_code'] = $subject_code;
        $subject['description'] = $description;
    }
}

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-edit"></i> Edit Subject</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/subjects/list.php">Subjects</a></li>
                <li class="breadcrumb-item active">Edit Subject</li>
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
                <h5 class="mb-0">Subject Information</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="subject_name" class="form-label">Subject Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="subject_name" name="subject_name" required
                               value="<?php echo htmlspecialchars($subject['subject_name']); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="subject_code" class="form-label">Subject Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="subject_code" name="subject_code" required
                               value="<?php echo htmlspecialchars($subject['subject_code']); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($subject['description']); ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-save"></i> Update Subject
                    </button>
                    <a href="<?php echo APP_URL;?>/subjects/list.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
