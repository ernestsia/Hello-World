<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireRole('admin');

$pageTitle = 'Add Subject';
$db = new Database();
$conn = $db->getConnection();

$errors = [];

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
    
    // Check if subject code exists
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT subject_id FROM subjects WHERE subject_code = ?");
        $stmt->bind_param("s", $subject_code);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = 'Subject code already exists';
        }
        $stmt->close();
    }
    
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO subjects (subject_name, subject_code, description) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $subject_name, $subject_code, $description);
        
        if ($stmt->execute()) {
            setFlashMessage('success', 'Subject added successfully!');
            redirect(APP_URL . '/subjects/list.php');
        } else {
            $errors[] = 'Error adding subject';
        }
        $stmt->close();
    }
}

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-book"></i> Add New Subject</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/subjects/list.php">Subjects</a></li>
                <li class="breadcrumb-item active">Add Subject</li>
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
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Subject Information</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="subject_name" class="form-label">Subject Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="subject_name" name="subject_name" required
                               value="<?php echo isset($_POST['subject_name']) ? htmlspecialchars($_POST['subject_name']) : ''; ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="subject_code" class="form-label">Subject Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="subject_code" name="subject_code" required
                               value="<?php echo isset($_POST['subject_code']) ? htmlspecialchars($_POST['subject_code']) : ''; ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Subject
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
