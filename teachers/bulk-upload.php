<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireRole('admin');

$pageTitle = 'Bulk Upload Teachers';
$db = new Database();
$conn = $db->getConnection();

$errors = [];
$success_count = 0;
$error_rows = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Error uploading file';
    } elseif ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
        $errors[] = 'File size exceeds 5MB limit';
    } elseif (pathinfo($file['name'], PATHINFO_EXTENSION) !== 'csv') {
        $errors[] = 'Only CSV files are allowed';
    } else {
        // Process CSV file
        if (($handle = fopen($file['tmp_name'], 'r')) !== false) {
            $row_number = 0;
            $headers = [];
            
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                $row_number++;
                
                // First row is header
                if ($row_number === 1) {
                    $headers = array_map('trim', $data);
                    // Validate headers
                    $required_headers = ['username', 'email', 'password', 'first_name', 'last_name'];
                    $missing_headers = array_diff($required_headers, $headers);
                    if (!empty($missing_headers)) {
                        $errors[] = 'Missing required columns: ' . implode(', ', $missing_headers);
                        break;
                    }
                    continue;
                }
                
                // Skip empty rows
                if (empty(array_filter($data))) {
                    continue;
                }
                
                // Map data to headers
                $teacher_data = array_combine($headers, $data);
                $row_errors = [];
                
                // Validate required fields
                if (empty(trim($teacher_data['username']))) {
                    $row_errors[] = 'Username is required';
                }
                if (empty(trim($teacher_data['email'])) || !validateEmail(trim($teacher_data['email']))) {
                    $row_errors[] = 'Valid email is required';
                }
                if (empty(trim($teacher_data['password']))) {
                    $row_errors[] = 'Password is required';
                }
                if (empty(trim($teacher_data['first_name']))) {
                    $row_errors[] = 'First name is required';
                }
                if (empty(trim($teacher_data['last_name']))) {
                    $row_errors[] = 'Last name is required';
                }
                
                if (!empty($row_errors)) {
                    $error_rows[] = [
                        'row' => $row_number,
                        'data' => $teacher_data['username'] . ' (' . $teacher_data['email'] . ')',
                        'errors' => $row_errors
                    ];
                    continue;
                }
                
                // Sanitize data
                $username = sanitize($teacher_data['username']);
                $email = sanitize($teacher_data['email']);
                $password = $teacher_data['password'];
                $first_name = sanitize($teacher_data['first_name']);
                $last_name = sanitize($teacher_data['last_name']);
                $date_of_birth = !empty($teacher_data['date_of_birth']) ? sanitize($teacher_data['date_of_birth']) : null;
                $gender = !empty($teacher_data['gender']) ? sanitize($teacher_data['gender']) : 'male';
                $address = !empty($teacher_data['address']) ? sanitize($teacher_data['address']) : '';
                $phone = !empty($teacher_data['phone']) ? sanitize($teacher_data['phone']) : '';
                $qualification = !empty($teacher_data['qualification']) ? sanitize($teacher_data['qualification']) : '';
                $experience_years = !empty($teacher_data['experience_years']) ? (int)$teacher_data['experience_years'] : 0;
                $joining_date = !empty($teacher_data['joining_date']) ? sanitize($teacher_data['joining_date']) : date('Y-m-d');
                $salary = !empty($teacher_data['salary']) ? (float)$teacher_data['salary'] : 0;
                
                // Check if username exists
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
                $stmt->bind_param("s", $username);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $error_rows[] = [
                        'row' => $row_number,
                        'data' => $username . ' (' . $email . ')',
                        'errors' => ['Username already exists']
                    ];
                    $stmt->close();
                    continue;
                }
                $stmt->close();
                
                // Check if email exists
                $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
                $stmt->bind_param("s", $email);
                $stmt->execute();
                if ($stmt->get_result()->num_rows > 0) {
                    $error_rows[] = [
                        'row' => $row_number,
                        'data' => $username . ' (' . $email . ')',
                        'errors' => ['Email already exists']
                    ];
                    $stmt->close();
                    continue;
                }
                $stmt->close();
                
                // Insert teacher
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
                    
                    // Insert teacher
                    $stmt = $conn->prepare("INSERT INTO teachers (user_id, first_name, last_name, date_of_birth, gender, address, phone, qualification, experience_years, joining_date, salary) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("isssssssisd", $user_id, $first_name, $last_name, $date_of_birth, $gender, $address, $phone, $qualification, $experience_years, $joining_date, $salary);
                    $stmt->execute();
                    $stmt->close();
                    
                    $conn->commit();
                    $success_count++;
                    
                } catch (Exception $e) {
                    $conn->rollback();
                    $error_rows[] = [
                        'row' => $row_number,
                        'data' => $username . ' (' . $email . ')',
                        'errors' => ['Database error: ' . $e->getMessage()]
                    ];
                }
            }
            
            fclose($handle);
            
            if ($success_count > 0) {
                setFlashMessage('success', "Successfully imported $success_count teacher(s)!");
            }
        } else {
            $errors[] = 'Unable to read CSV file';
        }
    }
}

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-file-upload"></i> Bulk Upload Teachers</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/teachers/list.php">Teachers</a></li>
                <li class="breadcrumb-item active">Bulk Upload</li>
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

<?php if ($success_count > 0): ?>
<div class="alert alert-success alert-dismissible fade show">
    <strong>Success!</strong> Successfully imported <?php echo $success_count; ?> teacher(s)!
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (!empty($error_rows)): ?>
<div class="alert alert-warning alert-dismissible fade show">
    <strong>Warning!</strong> Some rows had errors and were skipped:
    <ul class="mb-0 mt-2">
        <?php foreach ($error_rows as $error_row): ?>
        <li>
            <strong>Row <?php echo $error_row['row']; ?>:</strong> <?php echo $error_row['data']; ?>
            <ul>
                <?php foreach ($error_row['errors'] as $err): ?>
                <li><?php echo $err; ?></li>
                <?php endforeach; ?>
            </ul>
        </li>
        <?php endforeach; ?>
    </ul>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-md-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Upload CSV File</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="csv_file" class="form-label">Select CSV File <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                        <div class="form-text">Maximum file size: 5MB. Only CSV files are accepted.</div>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload"></i> Upload and Import
                        </button>
                        <a href="<?php echo APP_URL;?>/teachers/list.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Instructions</h5>
            </div>
            <div class="card-body">
                <h6>CSV Format Requirements:</h6>
                <ul class="small">
                    <li>First row must contain column headers</li>
                    <li>Required columns: username, email, password, first_name, last_name</li>
                    <li>Optional columns: date_of_birth, gender, address, phone, qualification, experience_years, joining_date, salary</li>
                    <li>Date format: YYYY-MM-DD</li>
                    <li>Gender values: male, female, other</li>
                    <li>Experience years: numeric value</li>
                    <li>Salary: numeric value (decimal allowed)</li>
                </ul>
                
                <a href="<?php echo APP_URL;?>/teachers/download-template.php" class="btn btn-sm btn-success w-100 mt-2">
                    <i class="fas fa-download"></i> Download Sample Template
                </a>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
