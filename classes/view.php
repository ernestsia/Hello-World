<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireLogin();

$pageTitle = 'Class Details';
$db = new Database();
$conn = $db->getConnection();

$class_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($class_id === 0) {
    setFlashMessage('danger', 'Invalid class ID');
    redirect(APP_URL . '/classes/list.php');
}

// Get class details
$query = "SELECT c.*, 
                 CONCAT(t.first_name, ' ', t.last_name) as teacher_name,
                 t.teacher_id,
                 t.phone as teacher_phone,
                 u.email as teacher_email
          FROM classes c
          LEFT JOIN teachers t ON c.class_teacher_id = t.teacher_id
          LEFT JOIN users u ON t.user_id = u.user_id
          WHERE c.class_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $class_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setFlashMessage('danger', 'Class not found');
    redirect(APP_URL . '/classes/list.php');
}

$class = $result->fetch_assoc();
$stmt->close();

// Get students in this class
$students_query = "SELECT s.*, u.status 
                   FROM students s
                   LEFT JOIN users u ON s.user_id = u.user_id
                   WHERE s.class_id = ?
                   ORDER BY s.roll_number";
$stmt = $conn->prepare($students_query);
$stmt->bind_param("i", $class_id);
$stmt->execute();
$students_result = $stmt->get_result();
$stmt->close();

// Get subjects assigned to this class
$subjects_query = "SELECT cs.*, s.subject_name, s.subject_code,
                          CONCAT(t.first_name, ' ', t.last_name) as teacher_name
                   FROM class_subjects cs
                   JOIN subjects s ON cs.subject_id = s.subject_id
                   LEFT JOIN teachers t ON cs.teacher_id = t.teacher_id
                   WHERE cs.class_id = ?
                   ORDER BY s.subject_name";
$stmt = $conn->prepare($subjects_query);
$stmt->bind_param("i", $class_id);
$stmt->execute();
$subjects_result = $stmt->get_result();
$stmt->close();

// Get timetable for this class
$timetable_query = "SELECT tt.*, s.subject_name, s.subject_code,
                           CONCAT(t.first_name, ' ', t.last_name) as teacher_name
                    FROM timetable tt
                    JOIN subjects s ON tt.subject_id = s.subject_id
                    JOIN teachers t ON tt.teacher_id = t.teacher_id
                    WHERE tt.class_id = ?
                    ORDER BY FIELD(tt.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
                             tt.start_time";
$stmt = $conn->prepare($timetable_query);
$stmt->bind_param("i", $class_id);
$stmt->execute();
$timetable_result = $stmt->get_result();
$stmt->close();

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-door-open"></i> Class Details</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/classes/list.php">Classes</a></li>
                <li class="breadcrumb-item active">View Class</li>
            </ol>
        </nav>
    </div>
</div>

<div class="row">
    <!-- Class Information -->
    <div class="col-md-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle"></i> Class Information
                </h5>
            </div>
            <div class="card-body">
                <h4 class="mb-3"><?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section']); ?></h4>
                
                <table class="table table-borderless table-sm">
                    <tbody>
                        <tr>
                            <th width="40%"><i class="fas fa-door-closed"></i> Room:</th>
                            <td><?php echo $class['room_number'] ? htmlspecialchars($class['room_number']) : 'N/A'; ?></td>
                        </tr>
                        <tr>
                            <th><i class="fas fa-chair"></i> Capacity:</th>
                            <td><?php echo $class['capacity'] ? $class['capacity'] : 'N/A'; ?></td>
                        </tr>
                        <tr>
                            <th><i class="fas fa-users"></i> Students:</th>
                            <td><span class="badge bg-info"><?php echo $students_result->num_rows; ?></span></td>
                        </tr>
                        <tr>
                            <th><i class="fas fa-book"></i> Subjects:</th>
                            <td><span class="badge bg-success"><?php echo $subjects_result->num_rows; ?></span></td>
                        </tr>
                    </tbody>
                </table>
                
                <?php if (hasRole('admin')): ?>
                <div class="mt-3">
                    <a href="<?php echo APP_URL;?>/classes/edit.php?id=<?php echo $class_id; ?>" 
                       class="btn btn-warning btn-sm w-100 mb-2">
                        <i class="fas fa-edit"></i> Edit Class
                    </a>
                    <a href="<?php echo APP_URL;?>/classes/delete.php?id=<?php echo $class_id; ?>" 
                       class="btn btn-danger btn-sm w-100"
                       onclick="return confirm('Are you sure you want to delete this class?');">
                        <i class="fas fa-trash"></i> Delete Class
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Class Teacher Info -->
        <div class="card mt-3">
            <div class="card-header bg-success text-white">
                <h6 class="mb-0"><i class="fas fa-chalkboard-teacher"></i> Class Teacher</h6>
            </div>
            <div class="card-body">
                <?php if ($class['teacher_name']): ?>
                <h6><?php echo htmlspecialchars($class['teacher_name']); ?></h6>
                <p class="mb-1"><small><i class="fas fa-phone"></i> <?php echo htmlspecialchars($class['teacher_phone']); ?></small></p>
                <p class="mb-0"><small><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($class['teacher_email']); ?></small></p>
                <a href="<?php echo APP_URL;?>/teachers/view.php?id=<?php echo $class['teacher_id']; ?>" 
                   class="btn btn-sm btn-info mt-2">
                    <i class="fas fa-eye"></i> View Profile
                </a>
                <?php else: ?>
                <p class="text-muted mb-0">No class teacher assigned</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Students List -->
    <div class="col-md-8">
        <div class="card mb-3">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-users"></i> Students in this Class</h5>
            </div>
            <div class="card-body">
                <?php if ($students_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-sm">
                        <thead>
                            <tr>
                                <th>Roll No</th>
                                <th>Name</th>
                                <th>Gender</th>
                                <th>Phone</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($student = $students_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['roll_number']); ?></td>
                                <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                <td><?php echo ucfirst($student['gender']); ?></td>
                                <td><?php echo htmlspecialchars($student['phone']); ?></td>
                                <td>
                                    <?php if ($student['status'] === 'active'): ?>
                                    <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                    <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo APP_URL;?>/students/view.php?id=<?php echo $student['student_id']; ?>" 
                                       class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-muted mb-0">No students enrolled in this class yet.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Subjects -->
        <div class="card mb-3">
            <div class="card-header bg-warning text-white">
                <h5 class="mb-0"><i class="fas fa-book"></i> Subjects</h5>
            </div>
            <div class="card-body">
                <?php if ($subjects_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-sm">
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Code</th>
                                <th>Teacher</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($subject = $subjects_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($subject['subject_name']); ?></td>
                                <td><?php echo htmlspecialchars($subject['subject_code']); ?></td>
                                <td><?php echo $subject['teacher_name'] ? htmlspecialchars($subject['teacher_name']) : '<span class="text-muted">Not Assigned</span>'; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-muted mb-0">No subjects assigned to this class yet.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Timetable -->
        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h5 class="mb-0"><i class="fas fa-calendar-alt"></i> Class Timetable</h5>
            </div>
            <div class="card-body">
                <?php if ($timetable_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th>Day</th>
                                <th>Time</th>
                                <th>Subject</th>
                                <th>Teacher</th>
                                <th>Room</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($schedule = $timetable_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($schedule['day_of_week']); ?></td>
                                <td><?php echo date('h:i A', strtotime($schedule['start_time'])) . ' - ' . date('h:i A', strtotime($schedule['end_time'])); ?></td>
                                <td><?php echo htmlspecialchars($schedule['subject_name']); ?></td>
                                <td><?php echo htmlspecialchars($schedule['teacher_name']); ?></td>
                                <td><?php echo $schedule['room_number'] ? htmlspecialchars($schedule['room_number']) : 'N/A'; ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <p class="text-muted mb-0">No timetable created for this class yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
