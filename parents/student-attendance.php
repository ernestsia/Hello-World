<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireLogin();

if (!hasRole('parent')) {
    redirect(APP_URL . '/index.php');
}

$pageTitle = 'Student Attendance';
$db = new Database();
$conn = $db->getConnection();

$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($student_id === 0) {
    setFlashMessage('danger', 'Invalid student ID');
    redirect(APP_URL . '/parents/dashboard.php');
}

// Verify student belongs to parent
$stmt = $conn->prepare("SELECT first_name, last_name, roll_number, parent_email FROM students WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    setFlashMessage('danger', 'Student not found');
    redirect(APP_URL . '/parents/dashboard.php');
}

$student = $result->fetch_assoc();

if ($student['parent_email'] !== $_SESSION['email']) {
    setFlashMessage('danger', 'Access denied');
    redirect(APP_URL . '/parents/dashboard.php');
}

$stmt->close();

// Get attendance statistics
$stats_query = "SELECT 
                    COUNT(*) as total_days,
                    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                    SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late,
                    SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused
                FROM attendance 
                WHERE student_id = ?";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get attendance records
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$pagination = getPaginationData($stats['total_days'], $page, 20);

$attendance_query = "SELECT attendance_date, status, remarks 
                     FROM attendance 
                     WHERE student_id = ? 
                     ORDER BY attendance_date DESC
                     LIMIT {$pagination['offset']}, {$pagination['records_per_page']}";
$stmt = $conn->prepare($attendance_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$attendance_result = $stmt->get_result();
$stmt->close();

$attendance_rate = $stats['total_days'] > 0 ? ($stats['present'] / $stats['total_days']) * 100 : 0;

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-calendar-alt"></i> Student Attendance Report</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/parents/dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Attendance</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Student Banner -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);">
            <div class="card-body text-white p-4">
                <h4 class="mb-0">
                    <i class="fas fa-user-graduate"></i> 
                    <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                    <span class="badge bg-light text-dark ms-2">Roll: <?php echo htmlspecialchars($student['roll_number']); ?></span>
                </h4>
            </div>
        </div>
    </div>
</div>

<!-- Statistics -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="fas fa-calendar fa-3x text-secondary mb-2"></i>
                <h3><?php echo $stats['total_days']; ?></h3>
                <p class="text-muted mb-0">Total Days</p>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="fas fa-check-circle fa-3x text-success mb-2"></i>
                <h3><?php echo $stats['present']; ?></h3>
                <p class="text-muted mb-0">Present</p>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="fas fa-times-circle fa-3x text-danger mb-2"></i>
                <h3><?php echo $stats['absent']; ?></h3>
                <p class="text-muted mb-0">Absent</p>
            </div>
        </div>
    </div>
    <div class="col-md-3 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="fas fa-clock fa-3x text-warning mb-2"></i>
                <h3><?php echo $stats['late'] + $stats['excused']; ?></h3>
                <p class="text-muted mb-0">Late/Excused</p>
            </div>
        </div>
    </div>
</div>

<!-- Attendance Rate -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Attendance Rate</h5>
            </div>
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="progress" style="height: 40px;">
                            <div class="progress-bar bg-success" role="progressbar" 
                                 style="width: <?php echo $attendance_rate; ?>%">
                                <strong style="font-size: 1.2rem;"><?php echo number_format($attendance_rate, 1); ?>%</strong>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 text-center">
                        <?php if ($attendance_rate >= 90): ?>
                        <h4 class="text-success mb-0">Excellent!</h4>
                        <p class="text-muted mb-0">Keep up the good work</p>
                        <?php elseif ($attendance_rate >= 75): ?>
                        <h4 class="text-primary mb-0">Good</h4>
                        <p class="text-muted mb-0">Maintain consistency</p>
                        <?php elseif ($attendance_rate >= 60): ?>
                        <h4 class="text-warning mb-0">Fair</h4>
                        <p class="text-muted mb-0">Needs improvement</p>
                        <?php else: ?>
                        <h4 class="text-danger mb-0">Poor</h4>
                        <p class="text-muted mb-0">Immediate attention needed</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Attendance Records -->
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-list"></i> Attendance History</h5>
            </div>
            <div class="card-body">
                <?php if ($attendance_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Day</th>
                                <th>Status</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($record = $attendance_result->fetch_assoc()): 
                                $badge_class = 'secondary';
                                $icon = 'fa-question';
                                
                                if ($record['status'] === 'present') {
                                    $badge_class = 'success';
                                    $icon = 'fa-check';
                                } elseif ($record['status'] === 'absent') {
                                    $badge_class = 'danger';
                                    $icon = 'fa-times';
                                } elseif ($record['status'] === 'late') {
                                    $badge_class = 'warning';
                                    $icon = 'fa-clock';
                                } elseif ($record['status'] === 'excused') {
                                    $badge_class = 'info';
                                    $icon = 'fa-file-medical';
                                }
                            ?>
                            <tr>
                                <td><strong><?php echo formatDate($record['attendance_date']); ?></strong></td>
                                <td><?php echo date('l', strtotime($record['attendance_date'])); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $badge_class; ?>">
                                        <i class="fas <?php echo $icon; ?>"></i>
                                        <?php echo ucfirst($record['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    if (!empty($record['remarks'])) {
                                        echo '<i class="fas fa-comment text-muted"></i> ' . htmlspecialchars($record['remarks']);
                                    } else {
                                        echo '<span class="text-muted">-</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($pagination['total_pages'] > 1): ?>
                <nav>
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?id=<?php echo $student_id; ?>&page=<?php echo $page - 1; ?>">Previous</a>
                        </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?id=<?php echo $student_id; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $pagination['total_pages']): ?>
                        <li class="page-item">
                            <a class="page-link" href="?id=<?php echo $student_id; ?>&page=<?php echo $page + 1; ?>">Next</a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>
                
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-calendar-times fa-5x text-muted mb-3"></i>
                    <h4 class="text-muted">No Attendance Records</h4>
                    <p class="text-muted">Attendance records will appear here once marked by teachers.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
