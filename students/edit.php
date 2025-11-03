<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireRole('admin');

$pageTitle = 'Edit Student';
$db = new Database();
$conn = $db->getConnection();

$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($student_id === 0) {
    setFlashMessage('danger', 'Invalid student ID');
    redirect(APP_URL . '/students/list.php');
}

// Get student details
$query = "SELECT s.*, u.username, u.email, u.status
          FROM students s
          JOIN users u ON s.user_id = u.user_id
          WHERE s.student_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setFlashMessage('danger', 'Student not found');
    redirect(APP_URL . '/students/list.php');
}

$student = $result->fetch_assoc();
$stmt->close();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password']; // Optional for update
    $first_name = sanitize($_POST['first_name']);
    $last_name = sanitize($_POST['last_name']);
    $date_of_birth = sanitize($_POST['date_of_birth']);
    $gender = sanitize($_POST['gender']);
    $address = sanitize($_POST['address']);
    $phone = sanitize($_POST['phone']);
    $parent_name = sanitize($_POST['parent_name']);
    $parent_phone = sanitize($_POST['parent_phone']);
    $parent_email = sanitize($_POST['parent_email']);
    $admission_date = sanitize($_POST['admission_date']);
    $class_id = !empty($_POST['class_id']) ? (int)$_POST['class_id'] : null;
    $roll_number = sanitize($_POST['roll_number']);
    $status = sanitize($_POST['status']);
    
    // Validation
    if (empty($username)) $errors[] = 'Username is required';
    if (empty($email) || !validateEmail($email)) $errors[] = 'Valid email is required';
    if (empty($first_name)) $errors[] = 'First name is required';
    if (empty($last_name)) $errors[] = 'Last name is required';
    if (empty($roll_number)) $errors[] = 'Roll number is required';
    
    // Check if username exists (excluding current user)
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
        $stmt->bind_param("si", $username, $student['user_id']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            $errors[] = 'Username already exists';
        }
        $stmt->close();
    }
    
    // Check if email exists (excluding current user)
    if (empty($errors)) {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
        $stmt->bind_param("si", $email, $student['user_id']);
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
                // Update with new password
                $hashed_password = hashPassword($password);
                $stmt = $conn->prepare("UPDATE users SET username = ?, password = ?, email = ?, status = ? WHERE user_id = ?");
                $stmt->bind_param("ssssi", $username, $hashed_password, $email, $status, $student['user_id']);
            } else {
                // Update without changing password
                $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, status = ? WHERE user_id = ?");
                $stmt->bind_param("sssi", $username, $email, $status, $student['user_id']);
            }
            $stmt->execute();
            $stmt->close();
            
            // Handle photo upload
            $photo = $student['photo'];
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $upload_result = uploadFile($_FILES['photo'], UPLOAD_PATH . 'students/');
                if ($upload_result['success']) {
                    // Delete old photo
                    if (!empty($student['photo'])) {
                        deleteFile(UPLOAD_PATH . 'students/' . $student['photo']);
                    }
                    $photo = $upload_result['filename'];
                }
            }
            
            // Update student
            $stmt = $conn->prepare("UPDATE students SET first_name = ?, last_name = ?, date_of_birth = ?, gender = ?, address = ?, phone = ?, parent_name = ?, parent_phone = ?, parent_email = ?, admission_date = ?, class_id = ?, roll_number = ?, photo = ? WHERE student_id = ?");
            $stmt->bind_param("ssssssssssissi", $first_name, $last_name, $date_of_birth, $gender, $address, $phone, $parent_name, $parent_phone, $parent_email, $admission_date, $class_id, $roll_number, $photo, $student_id);
            $stmt->execute();
            $stmt->close();
            
            $conn->commit();
            
            setFlashMessage('success', 'Student updated successfully!');
            redirect(APP_URL . '/students/view.php?id=' . $student_id);
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = 'Error updating student: ' . $e->getMessage();
        }
    }
}

// Get classes for dropdown
$classes_result = $conn->query("SELECT class_id, class_name, section FROM classes ORDER BY class_name");

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-user-edit"></i> Edit Student</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/students/list.php">Students</a></li>
                <li class="breadcrumb-item active">Edit Student</li>
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
                <h5 class="mb-0">Student Information</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data">
                    <h6 class="mb-3 text-primary"><i class="fas fa-user-lock"></i> Account Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="username" name="username" required
                                   value="<?php echo htmlspecialchars($student['username']); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" required
                                   value="<?php echo htmlspecialchars($student['email']); ?>">
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
                                <option value="active" <?php echo $student['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $student['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                    </div>
                    
                    <hr>
                    <h6 class="mb-3 text-primary"><i class="fas fa-user"></i> Personal Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required
                                   value="<?php echo htmlspecialchars($student['first_name']); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required
                                   value="<?php echo htmlspecialchars($student['last_name']); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="date_of_birth" class="form-label">Date of Birth</label>
                            <input type="date" class="form-control" id="date_of_birth" name="date_of_birth"
                                   value="<?php echo htmlspecialchars($student['date_of_birth']); ?>">
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="gender" class="form-label">Gender</label>
                            <select class="form-select" id="gender" name="gender">
                                <option value="male" <?php echo $student['gender'] === 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo $student['gender'] === 'female' ? 'selected' : ''; ?>>Female</option>
                                <option value="other" <?php echo $student['gender'] === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="phone" name="phone"
                                   value="<?php echo htmlspecialchars($student['phone']); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="photo" class="form-label">Photo</label>
                            <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                            <?php if (!empty($student['photo'])): ?>
                            <small class="text-muted">Current: <?php echo htmlspecialchars($student['photo']); ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2"><?php echo htmlspecialchars($student['address']); ?></textarea>
                        </div>
                    </div>
                    
                    <hr>
                    <h6 class="mb-3 text-primary"><i class="fas fa-users"></i> Parent/Guardian Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="parent_name" class="form-label">Parent/Guardian Name</label>
                            <input type="text" class="form-control" id="parent_name" name="parent_name"
                                   value="<?php echo htmlspecialchars($student['parent_name']); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="parent_phone" class="form-label">Parent Phone</label>
                            <input type="text" class="form-control" id="parent_phone" name="parent_phone"
                                   value="<?php echo htmlspecialchars($student['parent_phone']); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="parent_email" class="form-label">Parent Email</label>
                            <input type="email" class="form-control" id="parent_email" name="parent_email"
                                   value="<?php echo htmlspecialchars($student['parent_email']); ?>">
                        </div>
                    </div>
                    
                    <hr>
                    <h6 class="mb-3 text-primary"><i class="fas fa-graduation-cap"></i> Academic Information</h6>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="roll_number" class="form-label">Roll Number <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="roll_number" name="roll_number" required
                                   value="<?php echo htmlspecialchars($student['roll_number']); ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="class_id" class="form-label">Class</label>
                            <select class="form-select" id="class_id" name="class_id">
                                <option value="">Select Class</option>
                                <?php while ($class = $classes_result->fetch_assoc()): ?>
                                <option value="<?php echo $class['class_id']; ?>"
                                        <?php echo $student['class_id'] == $class['class_id'] ? 'selected' : ''; ?>>
                                    <?php echo $class['class_name'] . ' - ' . $class['section']; ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="admission_date" class="form-label">Admission Date</label>
                            <input type="date" class="form-control" id="admission_date" name="admission_date"
                                   value="<?php echo htmlspecialchars($student['admission_date']); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Student
                            </button>
                            <a href="<?php echo APP_URL;?>/students/view.php?id=<?php echo $student_id; ?>" class="btn btn-secondary">
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
