<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireRole('admin');

$pageTitle = 'Add Announcement';
$db = new Database();
$conn = $db->getConnection();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize($_POST['title']);
    $content = sanitize($_POST['content']);
    $target_audience = sanitize($_POST['target_audience']);
    $posted_date = sanitize($_POST['posted_date']);
    $expiry_date = !empty($_POST['expiry_date']) ? sanitize($_POST['expiry_date']) : null;
    $status = sanitize($_POST['status']);
    
    // Validation
    if (empty($title)) {
        $errors[] = 'Title is required';
    }
    if (empty($content)) {
        $errors[] = 'Content is required';
    }
    if (empty($posted_date)) {
        $errors[] = 'Posted date is required';
    }
    
    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO announcements (title, content, target_audience, posted_by, posted_date, expiry_date, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssissss", $title, $content, $target_audience, $_SESSION['user_id'], $posted_date, $expiry_date, $status);
        
        if ($stmt->execute()) {
            setFlashMessage('success', 'Announcement added successfully!');
            redirect(APP_URL . '/announcements/index.php');
        } else {
            $errors[] = 'Error adding announcement: ' . $stmt->error;
        }
        $stmt->close();
    }
}

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-bullhorn"></i> Add New Announcement</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/announcements/index.php">Announcements</a></li>
                <li class="breadcrumb-item active">Add Announcement</li>
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
    <div class="col-md-10">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Announcement Details</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" required
                               placeholder="Enter announcement title"
                               value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="content" class="form-label">Content <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="content" name="content" rows="6" required
                                  placeholder="Enter announcement content"><?php echo isset($_POST['content']) ? htmlspecialchars($_POST['content']) : ''; ?></textarea>
                        <small class="text-muted">You can use line breaks for formatting</small>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="target_audience" class="form-label">Target Audience <span class="text-danger">*</span></label>
                            <select class="form-select" id="target_audience" name="target_audience" required>
                                <option value="all" <?php echo (isset($_POST['target_audience']) && $_POST['target_audience'] === 'all') ? 'selected' : ''; ?>>All</option>
                                <option value="students" <?php echo (isset($_POST['target_audience']) && $_POST['target_audience'] === 'students') ? 'selected' : ''; ?>>Students Only</option>
                                <option value="teachers" <?php echo (isset($_POST['target_audience']) && $_POST['target_audience'] === 'teachers') ? 'selected' : ''; ?>>Teachers Only</option>
                                <option value="parents" <?php echo (isset($_POST['target_audience']) && $_POST['target_audience'] === 'parents') ? 'selected' : ''; ?>>Parents Only</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="active" <?php echo (!isset($_POST['status']) || $_POST['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo (isset($_POST['status']) && $_POST['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="posted_date" class="form-label">Posted Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="posted_date" name="posted_date" required
                                   value="<?php echo isset($_POST['posted_date']) ? htmlspecialchars($_POST['posted_date']) : date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="expiry_date" class="form-label">Expiry Date <small class="text-muted">(Optional)</small></label>
                            <input type="date" class="form-control" id="expiry_date" name="expiry_date"
                                   value="<?php echo isset($_POST['expiry_date']) ? htmlspecialchars($_POST['expiry_date']) : ''; ?>">
                            <small class="text-muted">Leave blank for no expiry</small>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> <strong>Note:</strong> 
                        The announcement will be visible to the selected audience based on the status and expiry date.
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save"></i> Publish Announcement
                            </button>
                            <a href="<?php echo APP_URL;?>/announcements/index.php" class="btn btn-secondary btn-lg">
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
