<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireLogin();

// Only students can access this page
if (!hasRole('student')) {
    redirect(APP_URL . '/index.php');
}

$pageTitle = 'My Attendance';
$db = new Database();
$conn = $db->getConnection();

// Get student information
$user_id = $_SESSION['user_id'];
$student_query = "SELECT s.*, c.class_name, c.section
                  FROM students s
                  LEFT JOIN classes c ON s.class_id = c.class_id
                  WHERE s.user_id = ?";
$stmt = $conn->prepare($student_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    setFlashMessage('error', 'Student profile not found.');
    redirect(APP_URL . '/index.php');
}

$student_id = $student['student_id'];

// Get filter parameters
$month = isset($_GET['month']) ? $_GET['month'] : date('m');
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Get attendance statistics for selected month
$att_stats_query = "SELECT 
                        COUNT(*) as total_days,
                        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
                        SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                        SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days,
                        SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused_days
                    FROM attendance 
                    WHERE student_id = ? 
                    AND MONTH(attendance_date) = ? 
                    AND YEAR(attendance_date) = ?";
$stmt = $conn->prepare($att_stats_query);
$stmt->bind_param("iii", $student_id, $month, $year);
$stmt->execute();
$att_stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get overall attendance statistics
$overall_stats_query = "SELECT 
                            COUNT(*) as total_days,
                            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_days,
                            SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_days,
                            SUM(CASE WHEN status = 'excused' THEN 1 ELSE 0 END) as excused_days
                        FROM attendance 
                        WHERE student_id = ?";
$stmt = $conn->prepare($overall_stats_query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$overall_stats = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get detailed attendance records for selected month with subject info
$attendance_query = "SELECT a.*, s.subject_name 
                     FROM attendance a
                     LEFT JOIN subjects s ON a.subject_id = s.subject_id
                     WHERE a.student_id = ? 
                     AND MONTH(a.attendance_date) = ? 
                     AND YEAR(a.attendance_date) = ?
                     ORDER BY a.attendance_date DESC, s.subject_name";
$stmt = $conn->prepare($attendance_query);
$stmt->bind_param("iii", $student_id, $month, $year);
$stmt->execute();
$attendance_records = $stmt->get_result();
$stmt->close();

// Calculate attendance rates
$monthly_rate = $att_stats['total_days'] > 0 
    ? ($att_stats['present_days'] / $att_stats['total_days']) * 100 
    : 0;
$overall_rate = $overall_stats['total_days'] > 0 
    ? ($overall_stats['present_days'] / $overall_stats['total_days']) * 100 
    : 0;

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-1"><i class="fas fa-calendar-check"></i> My Attendance</h4>
                        <p class="text-muted mb-0">
                            <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?> - 
                            <?php echo htmlspecialchars($student['class_name'] . ' ' . $student['section']); ?>
                        </p>
                    </div>
                    <a href="<?php echo APP_URL;?>/students/dashboard.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Overall Statistics -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="stat-icon bg-success bg-opacity-10 text-success rounded-circle p-3 mx-auto mb-3" style="width: 70px; height: 70px;">
                    <i class="fas fa-check-circle fa-2x"></i>
                </div>
                <h6 class="text-muted mb-1">Overall Attendance</h6>
                <h2 class="mb-2 fw-bold text-success"><?php echo number_format($overall_rate, 1); ?>%</h2>
                <small class="text-muted"><?php echo $overall_stats['present_days']; ?> / <?php echo $overall_stats['total_days']; ?> days</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary rounded-circle p-3 mx-auto mb-3" style="width: 70px; height: 70px;">
                    <i class="fas fa-user-check fa-2x"></i>
                </div>
                <h6 class="text-muted mb-1">Present Days</h6>
                <h2 class="mb-2 fw-bold text-primary"><?php echo $overall_stats['present_days']; ?></h2>
                <small class="text-muted">Total present</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="stat-icon bg-danger bg-opacity-10 text-danger rounded-circle p-3 mx-auto mb-3" style="width: 70px; height: 70px;">
                    <i class="fas fa-user-times fa-2x"></i>
                </div>
                <h6 class="text-muted mb-1">Absent Days</h6>
                <h2 class="mb-2 fw-bold text-danger"><?php echo $overall_stats['absent_days']; ?></h2>
                <small class="text-muted">Total absent</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body text-center">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning rounded-circle p-3 mx-auto mb-3" style="width: 70px; height: 70px;">
                    <i class="fas fa-clock fa-2x"></i>
                </div>
                <h6 class="text-muted mb-1">Late Days</h6>
                <h2 class="mb-2 fw-bold text-warning"><?php echo $overall_stats['late_days']; ?></h2>
                <small class="text-muted">Total late</small>
            </div>
        </div>
    </div>
</div>

<!-- Filter and Monthly View -->
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-calendar-alt"></i> Attendance Records</h5>
                    <form method="GET" class="d-flex gap-2">
                        <select name="month" class="form-select form-select-sm" style="width: auto;">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m == $month ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                        <select name="year" class="form-select form-select-sm" style="width: auto;">
                            <?php 
                            $current_year = date('Y');
                            for ($y = $current_year - 2; $y <= $current_year + 1; $y++): 
                            ?>
                            <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                        <button type="submit" class="btn btn-light btn-sm">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                    </form>
                </div>
            </div>
            <div class="card-body">
                <!-- Monthly Statistics -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="alert alert-info">
                            <div class="row text-center">
                                <div class="col-md-3">
                                    <strong>Month Attendance:</strong> <?php echo number_format($monthly_rate, 1); ?>%
                                </div>
                                <div class="col-md-3">
                                    <strong>Present:</strong> <span class="text-success"><?php echo $att_stats['present_days']; ?></span>
                                </div>
                                <div class="col-md-3">
                                    <strong>Absent:</strong> <span class="text-danger"><?php echo $att_stats['absent_days']; ?></span>
                                </div>
                                <div class="col-md-3">
                                    <strong>Late:</strong> <span class="text-warning"><?php echo $att_stats['late_days']; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Attendance Records Table -->
                <?php if ($attendance_records->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th><i class="fas fa-calendar"></i> Date</th>
                                <th><i class="fas fa-clock"></i> Day</th>
                                <th><i class="fas fa-book"></i> Subject</th>
                                <th><i class="fas fa-info-circle"></i> Status</th>
                                <th><i class="fas fa-comment"></i> Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($record = $attendance_records->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo formatDate($record['attendance_date']); ?></td>
                                <td><?php echo date('l', strtotime($record['attendance_date'])); ?></td>
                                <td>
                                    <?php if ($record['subject_name']): ?>
                                        <span class="badge bg-primary">
                                            <i class="fas fa-book"></i> <?php echo htmlspecialchars($record['subject_name']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">
                                            <i class="fas fa-users"></i> General
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $status_class = '';
                                    $status_icon = '';
                                    switch ($record['status']) {
                                        case 'present':
                                            $status_class = 'success';
                                            $status_icon = 'check-circle';
                                            break;
                                        case 'absent':
                                            $status_class = 'danger';
                                            $status_icon = 'times-circle';
                                            break;
                                        case 'late':
                                            $status_class = 'warning';
                                            $status_icon = 'clock';
                                            break;
                                        case 'excused':
                                            $status_class = 'info';
                                            $status_icon = 'info-circle';
                                            break;
                                    }
                                    ?>
                                    <span class="badge bg-<?php echo $status_class; ?>">
                                        <i class="fas fa-<?php echo $status_icon; ?>"></i> 
                                        <?php echo ucfirst($record['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    echo $record['remarks'] 
                                        ? htmlspecialchars($record['remarks']) 
                                        : '<span class="text-muted">-</span>'; 
                                    ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                    <h5 class="text-muted">No Attendance Records</h5>
                    <p class="text-muted">No attendance records found for <?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Attendance Chart -->
<div class="row mt-4">
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-chart-pie"></i> Attendance Distribution</h5>
            </div>
            <div class="card-body">
                <canvas id="attendanceChart" height="200"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-info-circle"></i> Attendance Legend</h5>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-check-circle text-success"></i> Present</span>
                        <span class="badge bg-success rounded-pill"><?php echo $overall_stats['present_days']; ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-times-circle text-danger"></i> Absent</span>
                        <span class="badge bg-danger rounded-pill"><?php echo $overall_stats['absent_days']; ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-clock text-warning"></i> Late</span>
                        <span class="badge bg-warning rounded-pill"><?php echo $overall_stats['late_days']; ?></span>
                    </div>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-info-circle text-info"></i> Excused</span>
                        <span class="badge bg-info rounded-pill"><?php echo $overall_stats['excused_days']; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Attendance Pie Chart
const ctx = document.getElementById('attendanceChart').getContext('2d');
const attendanceChart = new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: ['Present', 'Absent', 'Late', 'Excused'],
        datasets: [{
            data: [
                <?php echo $overall_stats['present_days']; ?>,
                <?php echo $overall_stats['absent_days']; ?>,
                <?php echo $overall_stats['late_days']; ?>,
                <?php echo $overall_stats['excused_days']; ?>
            ],
            backgroundColor: [
                'rgba(40, 167, 69, 0.8)',
                'rgba(220, 53, 69, 0.8)',
                'rgba(255, 193, 7, 0.8)',
                'rgba(23, 162, 184, 0.8)'
            ],
            borderColor: [
                'rgba(40, 167, 69, 1)',
                'rgba(220, 53, 69, 1)',
                'rgba(255, 193, 7, 1)',
                'rgba(23, 162, 184, 1)'
            ],
            borderWidth: 2
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom',
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        let label = context.label || '';
                        let value = context.parsed || 0;
                        let total = context.dataset.data.reduce((a, b) => a + b, 0);
                        let percentage = ((value / total) * 100).toFixed(1);
                        return label + ': ' + value + ' days (' + percentage + '%)';
                    }
                }
            }
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>
