<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireRole('admin');

$pageTitle = 'Edit User';
$db = new Database();
$conn = $db->getConnection();

$user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($user_id === 0) {
    setFlashMessage('danger', 'Invalid user ID');
    redirect(APP_URL . '/users/list.php');
}

// Get user details
$stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setFlashMessage('danger', 'User not found');
    redirect(APP_URL . '/users/list.php');
}

$user = $result->fetch_assoc();
$stmt->close();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $role = sanitize($_POST['role']);
    $status = sanitize($_POST['status']);
    
    // Validation
    if (empty($username)) {
        $errors[] = 'Username is required';
    }
    if (empty($email) || !validateEmail($email)) {
        $errors[] = 'Valid email is required';
    }
    if (!empty($password) && strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters';
    }
    
    // Check if username exists (excluding current user)
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
        $stmt->bind_param("si", $username, $user_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = 'Username already exists';
        }
        $stmt->close();
    }
    
    // Check if email exists (excluding current user)
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $stmt->bind_param("si", $email, $user_id);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = 'Email already exists';
        }
        $stmt->close();
    }
    
    // Update user
    if (empty($errors)) {
        if (!empty($password)) {
            $hashed_password = hashPassword($password);
            $stmt = $conn->prepare("UPDATE users SET username = ?, password = ?, email = ?, role = ?, status = ? WHERE user_id = ?");
            $stmt->bind_param("sssssi", $username, $hashed_password, $email, $role, $status, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ?, status = ? WHERE user_id = ?");
            $stmt->bind_param("ssssi", $username, $email, $role, $status, $user_id);
        }
        
        if ($stmt->execute()) {
            setFlashMessage('success', 'User updated successfully!');
            redirect(APP_URL . '/users/list.php');
        } else {
            $errors[] = 'Error updating user: ' . $stmt->error;
        }
        $stmt->close();
    }
}

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-user-edit"></i> Edit User</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/users/list.php">Users</a></li>
                <li class="breadcrumb-item active">Edit User</li>
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
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-warning text-white">
                <h5 class="mb-0">Edit User Account</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="username" name="username" required
                               value="<?php echo htmlspecialchars($user['username']); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" required
                               value="<?php echo htmlspecialchars($user['email']); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="password" name="password"
                               placeholder="Leave blank to keep current password" minlength="6">
                        <small class="text-muted">Only fill if you want to change the password</small>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                <option value="teacher" <?php echo $user['role'] === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                                <option value="student" <?php echo $user['role'] === 'student' ? 'selected' : ''; ?>>Student</option>
                                <option value="parent" <?php echo $user['role'] === 'parent' ? 'selected' : ''; ?>>Parent</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $user['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <?php if ($user_id == $_SESSION['user_id']): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> <strong>Warning:</strong> 
                        You are editing your own account. Be careful when changing role or status.
                    </div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-warning btn-lg">
                                <i class="fas fa-save"></i> Update User
                            </button>
                            <a href="<?php echo APP_URL;?>/users/list.php" class="btn btn-secondary btn-lg">
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
