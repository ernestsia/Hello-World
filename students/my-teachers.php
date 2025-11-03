<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireLogin();
requireRole('student');

$pageTitle = 'My Teachers';
$db = new Database();
$conn = $db->getConnection();

// Get student's class
$stmt = $conn->prepare("SELECT class_id FROM students WHERE user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setFlashMessage('danger', 'Student profile not found');
    redirect(APP_URL . '/index.php');
}

$student_data = $result->fetch_assoc();
$class_id = $student_data['class_id'];
$stmt->close();

if (!$class_id) {
    $teachers = [];
    $class_teacher = null;
} else {
    // Get class teacher
    $query = "SELECT t.*, u.email, c.class_name, c.section
              FROM classes c
              LEFT JOIN teachers t ON c.class_teacher_id = t.teacher_id
              LEFT JOIN users u ON t.user_id = u.user_id
              WHERE c.class_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $class_info = $result->fetch_assoc();
    $class_teacher = $class_info['teacher_id'] ? $class_info : null;
    $stmt->close();
    
    // Get subject teachers
    $query = "SELECT DISTINCT t.*, u.email, s.subject_name, s.subject_code
              FROM class_subjects cs
              JOIN teachers t ON cs.teacher_id = t.teacher_id
              JOIN subjects s ON cs.subject_id = s.subject_id
              LEFT JOIN users u ON t.user_id = u.user_id
              WHERE cs.class_id = ?
              ORDER BY s.subject_name";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $teachers = [];
    while ($row = $result->fetch_assoc()) {
        $teachers[] = $row;
    }
    $stmt->close();
}

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-chalkboard-teacher"></i> My Teachers</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">My Teachers</li>
            </ol>
        </nav>
    </div>
</div>

<?php if (!$class_id): ?>
<div class="alert alert-info">
    <i class="fas fa-info-circle"></i> You are not assigned to any class yet. Please contact the administrator.
</div>
<?php else: ?>

<!-- Class Information -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h5 class="mb-0">
                    <i class="fas fa-door-open"></i> My Class: 
                    <?php echo htmlspecialchars($class_info['class_name'] . ' - ' . $class_info['section']); ?>
                </h5>
            </div>
        </div>
    </div>
</div>

<!-- Class Teacher -->
<?php if ($class_teacher): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-success">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-user-tie"></i> Class Teacher</h5>
            </div>
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-2 text-center">
                        <?php if (!empty($class_teacher['photo'])): ?>
                        <img src="<?php echo APP_URL;?>/uploads/teachers/<?php echo $class_teacher['photo']; ?>" 
                             class="img-fluid rounded-circle" style="max-width: 120px; border: 3px solid #28a745;" alt="Teacher Photo">
                        <?php else: ?>
                        <i class="fas fa-user-circle fa-7x text-success"></i>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-8">
                        <h4><?php echo htmlspecialchars($class_teacher['first_name'] . ' ' . $class_teacher['last_name']); ?></h4>
                        <p class="mb-1"><i class="fas fa-graduation-cap"></i> <strong>Qualification:</strong> <?php echo htmlspecialchars($class_teacher['qualification']); ?></p>
                        <p class="mb-1"><i class="fas fa-briefcase"></i> <strong>Experience:</strong> <?php echo $class_teacher['experience_years']; ?> years</p>
                        <p class="mb-1"><i class="fas fa-envelope"></i> <strong>Email:</strong> <?php echo htmlspecialchars($class_teacher['email']); ?></p>
                        <p class="mb-0"><i class="fas fa-phone"></i> <strong>Phone:</strong> <?php echo htmlspecialchars($class_teacher['phone']); ?></p>
                    </div>
                    <div class="col-md-2 text-center">
                        <a href="<?php echo APP_URL;?>/teachers/view.php?id=<?php echo $class_teacher['teacher_id']; ?>" 
                           class="btn btn-success">
                            <i class="fas fa-eye"></i> View Profile
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Subject Teachers -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-book"></i> Subject Teachers</h5>
            </div>
            <div class="card-body">
                <?php if (count($teachers) > 0): ?>
                <div class="row">
                    <?php foreach ($teachers as $teacher): ?>
                    <div class="col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-3 text-center">
                                        <?php if (!empty($teacher['photo'])): ?>
                                        <img src="<?php echo APP_URL;?>/uploads/teachers/<?php echo $teacher['photo']; ?>" 
                                             class="img-fluid rounded-circle" style="max-width: 80px;" alt="Teacher Photo">
                                        <?php else: ?>
                                        <i class="fas fa-user-circle fa-4x text-info"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-9">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></h6>
                                        <p class="mb-1">
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($teacher['subject_name']); ?></span>
                                        </p>
                                        <p class="mb-1 small"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($teacher['email']); ?></p>
                                        <p class="mb-2 small"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($teacher['phone']); ?></p>
                                        <a href="<?php echo APP_URL;?>/teachers/view.php?id=<?php echo $teacher['teacher_id']; ?>" 
                                           class="btn btn-sm btn-info">
                                            <i class="fas fa-eye"></i> View Profile
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-muted mb-0">No subject teachers assigned yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<?php include '../includes/footer.php'; ?>
