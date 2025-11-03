<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireRole('admin');

$pageTitle = 'Add User';
$db = new Database();
$conn = $db->getConnection();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = sanitize($_POST['role']);
    $status = sanitize($_POST['status']);
    
    // Validation
    if (empty($username)) {
        $errors[] = 'Username is required';
    }
    if (empty($email) || !validateEmail($email)) {
        $errors[] = 'Valid email is required';
    }
    if (empty($password)) {
        $errors[] = 'Password is required';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters';
    }
    if ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match';
    }
    if (empty($role)) {
        $errors[] = 'Role is required';
    }
    
    // Check if username exists
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = 'Username already exists';
        }
        $stmt->close();
    }
    
    // Check if email exists
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = 'Email already exists';
        }
        $stmt->close();
    }
    
    // Insert user
    if (empty($errors)) {
        $hashed_password = hashPassword($password);
        $stmt = $conn->prepare("INSERT INTO users (username, password, email, role, status) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $username, $hashed_password, $email, $role, $status);
        
        if ($stmt->execute()) {
            setFlashMessage('success', 'User created successfully!');
            redirect(APP_URL . '/users/list.php');
        } else {
            $errors[] = 'Error creating user: ' . $stmt->error;
        }
        $stmt->close();
    }
}

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-user-plus"></i> Add New User</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/users/list.php">Users</a></li>
                <li class="breadcrumb-item active">Add User</li>
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
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">User Account Information</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> <strong>Note:</strong> 
                        Create a user account with specific role. For students, teachers, and parents, 
                        you'll need to create their detailed profiles separately after creating the user account.
                    </div>
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="username" name="username" required
                               placeholder="Enter username"
                               value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        <small class="text-muted">Must be unique</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" required
                               placeholder="Enter email address"
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        <small class="text-muted">Must be unique and valid</small>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password" name="password" required
                                   placeholder="Enter password" minlength="6">
                            <small class="text-muted">Minimum 6 characters</small>
                        </div>
                        <div class="col-md-6">
                            <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required
                                   placeholder="Confirm password" minlength="6">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="">Select Role</option>
                                <option value="admin" <?php echo (isset($_POST['role']) && $_POST['role'] === 'admin') ? 'selected' : ''; ?>>
                                    <i class="fas fa-user-shield"></i> Admin
                                </option>
                                <option value="teacher" <?php echo (isset($_POST['role']) && $_POST['role'] === 'teacher') ? 'selected' : ''; ?>>
                                    Teacher
                                </option>
                                <option value="student" <?php echo (isset($_POST['role']) && $_POST['role'] === 'student') ? 'selected' : ''; ?>>
                                    Student
                                </option>
                                <option value="parent" <?php echo (isset($_POST['role']) && $_POST['role'] === 'parent') ? 'selected' : ''; ?>>
                                    Parent
                                </option>
                            </select>
                            <small class="text-muted">Select user access level</small>
                        </div>
                        <div class="col-md-6">
                            <label for="status" class="form-label">Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="active" <?php echo (!isset($_POST['status']) || $_POST['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo (isset($_POST['status']) && $_POST['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="alert alert-warning">
                        <strong><i class="fas fa-exclamation-triangle"></i> Role Permissions:</strong>
                        <ul class="mb-0 mt-2">
                            <li><strong>Admin:</strong> Full system access, can manage all users and data</li>
                            <li><strong>Teacher:</strong> Can mark attendance, view students, manage grades</li>
                            <li><strong>Student:</strong> Can view own profile, grades, and attendance</li>
                            <li><strong>Parent:</strong> Can view their children's progress and information</li>
                        </ul>
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-success btn-lg">
                                <i class="fas fa-save"></i> Create User
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
