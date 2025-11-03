<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireRole('admin');

$pageTitle = 'Add Teacher';
$db = new Database();
$conn = $db->getConnection();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    $date_of_birth = sanitize($_POST['date_of_birth']);
    $gender = sanitize($_POST['gender']);
    $address = sanitize($_POST['address']);
    $phone = sanitize($_POST['phone']);
    $qualification = sanitize($_POST['qualification']);
    $experience_years = !empty($_POST['experience_years']) ? (int)$_POST['experience_years'] : 0;
    $joining_date = sanitize($_POST['joining_date']);
    $salary = !empty($_POST['salary']) ? (float)$_POST['salary'] : 0;
    
    // Validation
    if (empty($username)) $errors[] = 'Username is required';
    if (empty($email) || !validateEmail($email)) $errors[] = 'Valid email is required';
    if (empty($password)) $errors[] = 'Password is required';
    if (empty($first_name)) $errors[] = 'First name is required';
    if (empty($last_name)) $errors[] = 'Last name is required';
    
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
    
    // Insert if no errors
    if (empty($errors)) {
        $conn->begin_transaction();
        
        try {
            // Insert user
            $hashed_password = hashPassword($password);
            $role = 'teacher';
            $status = 'active';
            
            $stmt = $conn->prepare("INSERT INTO users (username, password, email, role, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("sssss", $username, $hashed_password, $email, $role, $status);
            $stmt->execute();
            $user_id = $conn->insert_id;
            $stmt->close();
            
            // Handle photo upload
            $photo = null;
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $upload_result = uploadFile($_FILES['photo'], UPLOAD_PATH . 'teachers/');
                if ($upload_result['success']) {
                    $photo = $upload_result['filename'];
                }
            }
            
            // Insert teacher
            $stmt = $conn->prepare("INSERT INTO teachers (user_id, first_name, last_name, date_of_birth, gender, address, phone, qualification, experience_years, joining_date, salary, photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssssssisds", $user_id, $first_name, $last_name, $date_of_birth, $gender, $address, $phone, $qualification, $experience_years, $joining_date, $salary, $photo);
            $stmt->execute();
            $stmt->close();
            
            $conn->commit();
            
            setFlashMessage('success', 'Teacher added successfully!');
            redirect(APP_URL . '/teachers/list.php');
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'Error adding teacher: ' . $e->getMessage();
        }
    }
}

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-user-plus"></i> Add New Teacher</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/teachers/list.php">Teachers</a></li>
                <li class="breadcrumb-item active">Add Teacher</li>
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
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Teacher Information</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data">
                    <h6 class="mb-3 text-primary"><i class="fas fa-user-lock"></i> Account Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="username" name="username" required
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" required
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                    </div>
                    
                    <hr>
                    <h6 class="mb-3 text-primary"><i class="fas fa-user"></i> Personal Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required
                                   value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required
                                   value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="date_of_birth" class="form-label">Date of Birth</label>
                            <input type="date" class="form-control" id="date_of_birth" name="date_of_birth"
                                   value="<?php echo isset($_POST['date_of_birth']) ? htmlspecialchars($_POST['date_of_birth']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="gender" class="form-label">Gender</label>
                            <select class="form-select" id="gender" name="gender">
                                <option value="male" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'male') ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'female') ? 'selected' : ''; ?>>Female</option>
                                <option value="other" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="phone" name="phone"
                                   value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="photo" class="form-label">Photo</label>
                            <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                        </div>
                    </div>
                    
                    <hr>
                    <h6 class="mb-3 text-primary"><i class="fas fa-graduation-cap"></i> Professional Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="qualification" class="form-label">Qualification</label>
                            <input type="text" class="form-control" id="qualification" name="qualification"
                                   value="<?php echo isset($_POST['qualification']) ? htmlspecialchars($_POST['qualification']) : ''; ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="experience_years" class="form-label">Experience (Years)</label>
                            <input type="number" class="form-control" id="experience_years" name="experience_years" min="0"
                                   value="<?php echo isset($_POST['experience_years']) ? htmlspecialchars($_POST['experience_years']) : '0'; ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="joining_date" class="form-label">Joining Date</label>
                            <input type="date" class="form-control" id="joining_date" name="joining_date"
                                   value="<?php echo isset($_POST['joining_date']) ? htmlspecialchars($_POST['joining_date']) : date('Y-m-d'); ?>">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="salary" class="form-label">Salary</label>
                            <input type="number" class="form-control" id="salary" name="salary" step="0.01" min="0"
                                   value="<?php echo isset($_POST['salary']) ? htmlspecialchars($_POST['salary']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Teacher
                            </button>
                            <a href="<?php echo APP_URL;?>/teachers/list.php" class="btn btn-secondary">
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
