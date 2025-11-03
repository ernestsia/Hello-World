<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/session.php';

requireLogin();

$pageTitle = 'My Profile';
$db = new Database();
$conn = $db->getConnection();

// Get user profile data
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

$profile = [];

if ($role === 'student') {
    $query = "SELECT u.*, s.* FROM users u 
              JOIN students s ON u.user_id = s.user_id 
              WHERE u.user_id = ?";
} elseif ($role === 'teacher') {
    $query = "SELECT u.*, t.* FROM users u 
              JOIN teachers t ON u.user_id = t.user_id 
              WHERE u.user_id = ?";
} else {
    $query = "SELECT * FROM users WHERE user_id = ?";
}

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$profile = $result->fetch_assoc();
$stmt->close();

include 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-user-circle"></i> My Profile</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Profile</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-body text-center">
                <?php if (isset($profile['photo']) && !empty($profile['photo'])): ?>
                <img src="<?php echo APP_URL;?>/uploads/<?php echo $role; ?>s/<?php echo $profile['photo']; ?>" 
                     class="img-fluid rounded-circle mb-3" style="max-width: 200px;" alt="Profile Photo">
                <?php else: ?>
                <div class="mb-3">
                    <i class="fas fa-user-circle fa-10x text-muted"></i>
                </div>
                <?php endif; ?>
                
                <h4><?php echo htmlspecialchars($profile['first_name'] ?? '') . ' ' . htmlspecialchars($profile['last_name'] ?? ''); ?></h4>
                <p class="text-muted mb-2">
                    <span class="badge bg-primary"><?php echo ucfirst($role); ?></span>
                </p>
                <p class="mb-0">
                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($profile['email']); ?>
                </p>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Profile Information</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tbody>
                        <tr>
                            <th width="30%">Username:</th>
                            <td><?php echo htmlspecialchars($profile['username']); ?></td>
                        </tr>
                        <tr>
                            <th>Email:</th>
                            <td><?php echo htmlspecialchars($profile['email']); ?></td>
                        </tr>
                        <tr>
                            <th>Role:</th>
                            <td><span class="badge bg-info"><?php echo ucfirst($role); ?></span></td>
                        </tr>
                        <tr>
                            <th>Status:</th>
                            <td>
                                <?php if ($profile['status'] === 'active'): ?>
                                <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                <span class="badge bg-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        
                        <?php if ($role === 'student' || $role === 'teacher'): ?>
                        <tr>
                            <th>Full Name:</th>
                            <td><?php echo htmlspecialchars($profile['first_name'] . ' ' . $profile['last_name']); ?></td>
                        </tr>
                        <tr>
                            <th>Date of Birth:</th>
                            <td><?php echo formatDate($profile['date_of_birth']); ?></td>
                        </tr>
                        <tr>
                            <th>Gender:</th>
                            <td><?php echo ucfirst($profile['gender']); ?></td>
                        </tr>
                        <tr>
                            <th>Phone:</th>
                            <td><?php echo htmlspecialchars($profile['phone']); ?></td>
                        </tr>
                        <tr>
                            <th>Address:</th>
                            <td><?php echo nl2br(htmlspecialchars($profile['address'])); ?></td>
                        </tr>
                        <?php endif; ?>
                        
                        <?php if ($role === 'student'): ?>
                        <tr>
                            <th>Roll Number:</th>
                            <td><?php echo htmlspecialchars($profile['roll_number']); ?></td>
                        </tr>
                        <tr>
                            <th>Admission Date:</th>
                            <td><?php echo formatDate($profile['admission_date']); ?></td>
                        </tr>
                        <tr>
                            <th>Parent Name:</th>
                            <td><?php echo htmlspecialchars($profile['parent_name']); ?></td>
                        </tr>
                        <tr>
                            <th>Parent Phone:</th>
                            <td><?php echo htmlspecialchars($profile['parent_phone']); ?></td>
                        </tr>
                        <?php endif; ?>
                        
                        <?php if ($role === 'teacher'): ?>
                        <tr>
                            <th>Qualification:</th>
                            <td><?php echo htmlspecialchars($profile['qualification']); ?></td>
                        </tr>
                        <tr>
                            <th>Experience:</th>
                            <td><?php echo $profile['experience_years']; ?> years</td>
                        </tr>
                        <tr>
                            <th>Joining Date:</th>
                            <td><?php echo formatDate($profile['joining_date']); ?></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <div class="mt-3">
                    <a href="<?php echo APP_URL;?>/change-password.php" class="btn btn-warning">
                        <i class="fas fa-key"></i> Change Password
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
