<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireLogin();

$pageTitle = 'View Teacher';
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
          LEFT JOIN users u ON t.user_id = u.user_id
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

// Get classes taught by this teacher
$classes_query = "SELECT DISTINCT c.class_name, c.section, s.subject_name
                  FROM class_subjects cs
                  JOIN classes c ON cs.class_id = c.class_id
                  JOIN subjects s ON cs.subject_id = s.subject_id
                  WHERE cs.teacher_id = ?
                  ORDER BY c.class_name, s.subject_name";
$stmt = $conn->prepare($classes_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$classes_result = $stmt->get_result();
$stmt->close();

// Get classes where this teacher is class teacher
$class_teacher_query = "SELECT class_name, section, room_number
                        FROM classes
                        WHERE class_teacher_id = ?";
$stmt = $conn->prepare($class_teacher_query);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$class_teacher_result = $stmt->get_result();
$stmt->close();

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-chalkboard-teacher"></i> Teacher Details</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/teachers/list.php">Teachers</a></li>
                <li class="breadcrumb-item active">View Teacher</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <!-- Profile Card -->
    <div class="col-md-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <?php if (!empty($teacher['photo'])): ?>
                <img src="<?php echo APP_URL;?>/uploads/teachers/<?php echo $teacher['photo']; ?>" 
                     class="img-fluid rounded-circle mb-3" style="max-width: 200px; border: 5px solid #28a745;" alt="Teacher Photo">
                <?php else: ?>
                <div class="mb-3">
                    <i class="fas fa-user-circle fa-10x text-success"></i>
                </div>
                <?php endif; ?>
                
                <h4><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></h4>
                <p class="text-muted mb-2">
                    <span class="badge bg-success">Teacher</span>
                </p>
                <p class="mb-2">
                    <?php if ($teacher['status'] === 'active'): ?>
                    <span class="badge bg-success">Active</span>
                    <?php else: ?>
                    <span class="badge bg-danger">Inactive</span>
                    <?php endif; ?>
                </p>
                
                <?php if (hasRole('admin')): ?>
                <div class="mt-3">
                    <a href="<?php echo APP_URL;?>/teachers/assign-subjects.php?id=<?php echo $teacher_id; ?>" 
                       class="btn btn-primary btn-sm mb-2 w-100">
                        <i class="fas fa-book"></i> Assign Subjects
                    </a>
                    <a href="<?php echo APP_URL;?>/teachers/edit.php?id=<?php echo $teacher_id; ?>" 
                       class="btn btn-warning btn-sm mb-2 w-100">
                        <i class="fas fa-edit"></i> Edit Profile
                    </a>
                    <a href="<?php echo APP_URL;?>/teachers/delete.php?id=<?php echo $teacher_id; ?>" 
                       class="btn btn-danger btn-sm w-100"
                       onclick="return confirm('Are you sure you want to delete this teacher?');">
                        <i class="fas fa-trash"></i> Delete
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Class Teacher Info -->
        <?php if ($class_teacher_result->num_rows > 0): ?>
        <div class="card mt-3 border-0 shadow-sm">
            <div class="card-header bg-success text-white">
                <h6 class="mb-0"><i class="fas fa-user-tie"></i> Class Teacher Of</h6>
            </div>
            <div class="card-body">
                <?php while ($ct = $class_teacher_result->fetch_assoc()): ?>
                <div class="mb-2">
                    <strong><?php echo htmlspecialchars($ct['class_name'] . ' - ' . $ct['section']); ?></strong>
                    <?php if ($ct['room_number']): ?>
                    <br><small class="text-muted">Room: <?php echo htmlspecialchars($ct['room_number']); ?></small>
                    <?php endif; ?>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Details Card -->
    <div class="col-md-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">Personal Information</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tbody>
                        <tr>
                            <th width="30%">Username:</th>
                            <td><?php echo htmlspecialchars($teacher['username']); ?></td>
                        </tr>
                        <tr>
                            <th>Email:</th>
                            <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                        </tr>
                        <tr>
                            <th>Date of Birth:</th>
                            <td><?php echo formatDate($teacher['date_of_birth']); ?> (Age: <?php echo calculateAge($teacher['date_of_birth']); ?>)</td>
                        </tr>
                        <tr>
                            <th>Gender:</th>
                            <td><?php echo ucfirst($teacher['gender']); ?></td>
                        </tr>
                        <tr>
                            <th>Phone:</th>
                            <td><?php echo htmlspecialchars($teacher['phone']); ?></td>
                        </tr>
                        <tr>
                            <th>Address:</th>
                            <td><?php echo nl2br(htmlspecialchars($teacher['address'])); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="card mt-3 border-0 shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">Professional Information</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tbody>
                        <tr>
                            <th width="30%">Qualification:</th>
                            <td><?php echo htmlspecialchars($teacher['qualification']); ?></td>
                        </tr>
                        <tr>
                            <th>Experience:</th>
                            <td><?php echo $teacher['experience_years']; ?> years</td>
                        </tr>
                        <tr>
                            <th>Joining Date:</th>
                            <td><?php echo formatDate($teacher['joining_date']); ?></td>
                        </tr>
                        <?php if (hasRole('admin')): ?>
                        <tr>
                            <th>Salary:</th>
                            <td>$<?php echo number_format($teacher['salary'], 2); ?></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Subjects Teaching -->
        <div class="card mt-3 border-0 shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0">Subjects Teaching</h5>
            </div>
            <div class="card-body">
                <?php if ($classes_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Class</th>
                                <th>Subject</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($class = $classes_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section']); ?></td>
                                <td><?php echo htmlspecialchars($class['subject_name']); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-muted mb-0">No subjects assigned yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
