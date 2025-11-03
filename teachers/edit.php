<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireRole('admin');

$pageTitle = 'Edit Teacher';
$db = new Database();
$conn = $db->getConnection();

$teacher_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($teacher_id === 0) {
    setFlashMessage('danger', 'Invalid teacher ID');
    redirect(APP_URL . '/teachers/list.php');
}

// Get teacher details
$query = "SELECT t.*, u.username, u.email, u.status
          FROM teachers t
          JOIN users u ON t.user_id = u.user_id
          WHERE t.teacher_id = ?";
$stmt = $conn->prepare($query);
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
    $status = sanitize($_POST['status']);
    
    // Validation
    if (empty($username)) $errors[] = 'Username is required';
    if (empty($email) || !validateEmail($email)) $errors[] = 'Valid email is required';
    if (empty($first_name)) $errors[] = 'First name is required';
    if (empty($last_name)) $errors[] = 'Last name is required';
    
    // Check if username exists (excluding current user)
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
        $stmt->bind_param("si", $username, $teacher['user_id']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = 'Username already exists';
        }
        $stmt->close();
    }
    
    // Check if email exists (excluding current user)
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $stmt->bind_param("si", $email, $teacher['user_id']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = 'Email already exists';
        }
        $stmt->close();
    }
    
    // Update if no errors
    if (empty($errors)) {
        $conn->begin_transaction();
        
        try {
            // Update user
            if (!empty($password)) {
                $hashed_password = hashPassword($password);
                $stmt = $conn->prepare("UPDATE users SET username = ?, password = ?, email = ?, status = ? WHERE user_id = ?");
                $stmt->bind_param("ssssi", $username, $hashed_password, $email, $status, $teacher['user_id']);
            } else {
                $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, status = ? WHERE user_id = ?");
                $stmt->bind_param("sssi", $username, $email, $status, $teacher['user_id']);
            }
            $stmt->execute();
            $stmt->close();
            
            // Handle photo upload
            $photo = $teacher['photo'];
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $upload_result = uploadFile($_FILES['photo'], UPLOAD_PATH . 'teachers/');
                if ($upload_result['success']) {
                    if (!empty($teacher['photo'])) {
                        deleteFile(UPLOAD_PATH . 'teachers/' . $teacher['photo']);
                    }
                    $photo = $upload_result['filename'];
                }
            }
            
            // Update teacher
            $stmt = $conn->prepare("UPDATE teachers SET first_name = ?, last_name = ?, date_of_birth = ?, gender = ?, address = ?, phone = ?, qualification = ?, experience_years = ?, joining_date = ?, salary = ?, photo = ? WHERE teacher_id = ?");
            $stmt->bind_param("sssssssisssi", $first_name, $last_name, $date_of_birth, $gender, $address, $phone, $qualification, $experience_years, $joining_date, $salary, $photo, $teacher_id);
            $stmt->execute();
            $stmt->close();
            
            $conn->commit();
            
            setFlashMessage('success', 'Teacher updated successfully!');
            redirect(APP_URL . '/teachers/view.php?id=' . $teacher_id);
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'Error updating teacher: ' . $e->getMessage();
        }
    }
}

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-user-edit"></i> Edit Teacher</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/teachers/list.php">Teachers</a></li>
                <li class="breadcrumb-item active">Edit Teacher</li>
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
                                   value="<?php echo htmlspecialchars($teacher['username']); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" required
                                   value="<?php echo htmlspecialchars($teacher['email']); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password">
                            <small class="text-muted">Leave blank to keep current password</small>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="active" <?php echo $teacher['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $teacher['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <hr>
                    <h6 class="mb-3 text-primary"><i class="fas fa-user"></i> Personal Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required
                                   value="<?php echo htmlspecialchars($teacher['first_name']); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required
                                   value="<?php echo htmlspecialchars($teacher['last_name']); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="date_of_birth" class="form-label">Date of Birth</label>
                            <input type="date" class="form-control" id="date_of_birth" name="date_of_birth"
                                   value="<?php echo htmlspecialchars($teacher['date_of_birth']); ?>">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="gender" class="form-label">Gender</label>
                            <select class="form-select" id="gender" name="gender">
                                <option value="male" <?php echo $teacher['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo $teacher['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                                <option value="other" <?php echo $teacher['gender'] === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="phone" name="phone"
                                   value="<?php echo htmlspecialchars($teacher['phone']); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="photo" class="form-label">Photo</label>
                            <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                            <?php if (!empty($teacher['photo'])): ?>
                            <small class="text-muted">Current: <?php echo htmlspecialchars($teacher['photo']); ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($teacher['address']); ?></textarea>
                        </div>
                    </div>
                    
                    <hr>
                    <h6 class="mb-3 text-primary"><i class="fas fa-graduation-cap"></i> Professional Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="qualification" class="form-label">Qualification</label>
                            <input type="text" class="form-control" id="qualification" name="qualification"
                                   value="<?php echo htmlspecialchars($teacher['qualification']); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="experience_years" class="form-label">Experience (Years)</label>
                            <input type="number" class="form-control" id="experience_years" name="experience_years" min="0"
                                   value="<?php echo htmlspecialchars($teacher['experience_years']); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="joining_date" class="form-label">Joining Date</label>
                            <input type="date" class="form-control" id="joining_date" name="joining_date"
                                   value="<?php echo htmlspecialchars($teacher['joining_date']); ?>">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="salary" class="form-label">Salary</label>
                            <input type="number" class="form-control" id="salary" name="salary" step="0.01" min="0"
                                   value="<?php echo htmlspecialchars($teacher['salary']); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Teacher
                            </button>
                            <a href="<?php echo APP_URL;?>/teachers/view.php?id=<?php echo $teacher_id; ?>" class="btn btn-secondary">
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
