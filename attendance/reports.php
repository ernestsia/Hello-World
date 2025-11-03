<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireLogin();

// Only teachers and admins can access this page
if (!hasRole('admin') && !hasRole('teacher')) {
    redirect(APP_URL . '/index.php');
}

$pageTitle = 'Attendance Reports';
$db = new Database();
$conn = $db->getConnection();

// Get filter parameters
$class_id = isset($_GET['class_id']) ? (int)$_GET['class_id'] : 0;
$subject_id = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$month = isset($_GET['month']) ? $_GET['month'] : date('m');
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'summary';

// Get teacher_id if user is a teacher
$teacher_id = null;
if (hasRole('teacher')) {
    $stmt = $conn->prepare("SELECT teacher_id FROM teachers WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result_teacher = $stmt->get_result();
    if ($row = $result_teacher->fetch_assoc()) {
        $teacher_id = $row['teacher_id'];
    }
    $stmt->close();
}

// Get classes based on user role
if (hasRole('teacher')) {
    // Teachers see classes where they teach any subject OR are class teacher
    if ($teacher_id) {
        $stmt = $conn->prepare("SELECT DISTINCT c.* 
                               FROM classes c
                               WHERE c.class_teacher_id = ? 
                               OR c.class_id IN (SELECT DISTINCT class_id FROM class_subjects WHERE teacher_id = ?)
                               ORDER BY c.class_name, c.section");
        $stmt->bind_param("ii", $teacher_id, $teacher_id);
        $stmt->execute();
        $classes = $stmt->get_result();
    } else {
        $classes = $conn->query("SELECT * FROM classes WHERE 1=0");
    }
} else {
    // Admins see all classes
    $classes_query = "SELECT * FROM classes ORDER BY class_name, section";
    $classes = $conn->query($classes_query);
}

// Get subjects for selected class based on user role
$subjects = [];
if ($class_id > 0) {
    if (hasRole('teacher') && $teacher_id) {
        // Teachers only see subjects they teach in this class
        $stmt = $conn->prepare("SELECT DISTINCT s.subject_id, s.subject_name 
                               FROM subjects s 
                               JOIN class_subjects cs ON s.subject_id = cs.subject_id 
                               WHERE cs.class_id = ? AND cs.teacher_id = ?
                               ORDER BY s.subject_name");
        $stmt->bind_param("ii", $class_id, $teacher_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $subjects[] = $row;
        }
        $stmt->close();
    } else {
        // Admins see all subjects for the class
        $stmt = $conn->prepare("SELECT DISTINCT s.subject_id, s.subject_name 
                               FROM subjects s 
                               JOIN class_subjects cs ON s.subject_id = cs.subject_id 
                               WHERE cs.class_id = ? 
                               ORDER BY s.subject_name");
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $subjects[] = $row;
        }
        $stmt->close();
    }
}

// Initialize variables
$students = null;
$class_info = null;
$date_range_stats = null;

if ($class_id > 0) {
    // Get class information
    $class_query = "SELECT * FROM classes WHERE class_id = ?";
    $stmt = $conn->prepare($class_query);
    $stmt->bind_param("i", $class_id);
    $stmt->execute();
    $class_info = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($report_type === 'summary') {
        // Get students with attendance summary
        if ($subject_id > 0) {
            // Filter by specific subject
            $students_query = "SELECT 
                                s.student_id,
                                s.roll_number,
                                s.first_name,
                                s.last_name,
                                COUNT(a.attendance_id) as total_days,
                                SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_days,
                                SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                                SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_days,
                                SUM(CASE WHEN a.status = 'excused' THEN 1 ELSE 0 END) as excused_days
                              FROM students s
                              LEFT JOIN attendance a ON s.student_id = a.student_id 
                                AND MONTH(a.attendance_date) = ? 
                                AND YEAR(a.attendance_date) = ?
                                AND a.subject_id = ?
                              WHERE s.class_id = ?
                              GROUP BY s.student_id
                              ORDER BY s.roll_number, s.first_name";
            $stmt = $conn->prepare($students_query);
            $stmt->bind_param("iiii", $month, $year, $subject_id, $class_id);
        } else {
            // All subjects
            $students_query = "SELECT 
                                s.student_id,
                                s.roll_number,
                                s.first_name,
                                s.last_name,
                                COUNT(a.attendance_id) as total_days,
                                SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_days,
                                SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                                SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_days,
                                SUM(CASE WHEN a.status = 'excused' THEN 1 ELSE 0 END) as excused_days
                              FROM students s
                              LEFT JOIN attendance a ON s.student_id = a.student_id 
                                AND MONTH(a.attendance_date) = ? 
                                AND YEAR(a.attendance_date) = ?
                              WHERE s.class_id = ?
                              GROUP BY s.student_id
                              ORDER BY s.roll_number, s.first_name";
            $stmt = $conn->prepare($students_query);
            $stmt->bind_param("iii", $month, $year, $class_id);
        }
        $stmt->execute();
        $students = $stmt->get_result();
        $stmt->close();
    } elseif ($report_type === 'daily') {
        // Get daily attendance for the month
        if ($subject_id > 0) {
            // Filter by specific subject
            $daily_query = "SELECT DISTINCT attendance_date 
                           FROM attendance 
                           WHERE MONTH(attendance_date) = ? 
                           AND YEAR(attendance_date) = ?
                           AND subject_id = ?
                           AND student_id IN (SELECT student_id FROM students WHERE class_id = ?)
                           ORDER BY attendance_date DESC";
            $stmt = $conn->prepare($daily_query);
            $stmt->bind_param("iiii", $month, $year, $subject_id, $class_id);
        } else {
            // All subjects
            $daily_query = "SELECT DISTINCT attendance_date 
                           FROM attendance 
                           WHERE MONTH(attendance_date) = ? 
                           AND YEAR(attendance_date) = ?
                           AND student_id IN (SELECT student_id FROM students WHERE class_id = ?)
                           ORDER BY attendance_date DESC";
            $stmt = $conn->prepare($daily_query);
            $stmt->bind_param("iii", $month, $year, $class_id);
        }
        $stmt->execute();
        $dates_result = $stmt->get_result();
        $dates = [];
        while ($row = $dates_result->fetch_assoc()) {
            $dates[] = $row['attendance_date'];
        }
        $stmt->close();
        
        // Get students
        $students_query = "SELECT student_id, roll_number, first_name, last_name 
                          FROM students 
                          WHERE class_id = ?
                          ORDER BY roll_number, first_name";
        $stmt = $conn->prepare($students_query);
        $stmt->bind_param("i", $class_id);
        $stmt->execute();
        $students = $stmt->get_result();
        $stmt->close();
    } elseif ($report_type === 'defaulters') {
        // Get students with low attendance (below 75%)
        if ($subject_id > 0) {
            // Filter by specific subject
            $students_query = "SELECT 
                                s.student_id,
                                s.roll_number,
                                s.first_name,
                                s.last_name,
                                COUNT(a.attendance_id) as total_days,
                                SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_days,
                                SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                                (SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / COUNT(a.attendance_id) * 100) as attendance_rate
                              FROM students s
                              LEFT JOIN attendance a ON s.student_id = a.student_id 
                                AND MONTH(a.attendance_date) = ? 
                                AND YEAR(a.attendance_date) = ?
                                AND a.subject_id = ?
                              WHERE s.class_id = ?
                              GROUP BY s.student_id
                              HAVING attendance_rate < 75 OR attendance_rate IS NULL
                              ORDER BY attendance_rate ASC, s.roll_number";
            $stmt = $conn->prepare($students_query);
            $stmt->bind_param("iiii", $month, $year, $subject_id, $class_id);
        } else {
            // All subjects
            $students_query = "SELECT 
                                s.student_id,
                                s.roll_number,
                                s.first_name,
                                s.last_name,
                                COUNT(a.attendance_id) as total_days,
                                SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_days,
                                SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
                                (SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) / COUNT(a.attendance_id) * 100) as attendance_rate
                              FROM students s
                              LEFT JOIN attendance a ON s.student_id = a.student_id 
                                AND MONTH(a.attendance_date) = ? 
                                AND YEAR(a.attendance_date) = ?
                              WHERE s.class_id = ?
                              GROUP BY s.student_id
                              HAVING attendance_rate < 75 OR attendance_rate IS NULL
                              ORDER BY attendance_rate ASC, s.roll_number";
            $stmt = $conn->prepare($students_query);
            $stmt->bind_param("iii", $month, $year, $class_id);
        }
        $stmt->execute();
        $students = $stmt->get_result();
        $stmt->close();
    }
    
    // Get overall class statistics for the period
    if ($subject_id > 0) {
        // Filter by specific subject
        $stats_query = "SELECT 
                            COUNT(DISTINCT a.student_id) as students_marked,
                            COUNT(a.attendance_id) as total_records,
                            SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as total_present,
                            SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as total_absent,
                            SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as total_late
                        FROM attendance a
                        JOIN students s ON a.student_id = s.student_id
                        WHERE s.class_id = ?
                        AND MONTH(a.attendance_date) = ?
                        AND YEAR(a.attendance_date) = ?
                        AND a.subject_id = ?";
        $stmt = $conn->prepare($stats_query);
        $stmt->bind_param("iiii", $class_id, $month, $year, $subject_id);
    } else {
        // All subjects
        $stats_query = "SELECT 
                            COUNT(DISTINCT a.student_id) as students_marked,
                            COUNT(a.attendance_id) as total_records,
                            SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as total_present,
                            SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as total_absent,
                            SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as total_late
                        FROM attendance a
                        JOIN students s ON a.student_id = s.student_id
                        WHERE s.class_id = ?
                        AND MONTH(a.attendance_date) = ?
                        AND YEAR(a.attendance_date) = ?";
        $stmt = $conn->prepare($stats_query);
        $stmt->bind_param("iii", $class_id, $month, $year);
    }
    $stmt->execute();
    $date_range_stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="mb-1"><i class="fas fa-chart-bar"></i> Attendance Reports</h4>
                        <p class="text-muted mb-0">View and analyze attendance data</p>
                    </div>
                    <a href="<?php echo APP_URL;?>/attendance/index.php" class="btn btn-outline-primary">
                        <i class="fas fa-calendar-check"></i> Mark Attendance
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-filter"></i> Report Filters</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3" id="reportForm">
                    <div class="col-md-3">
                        <label class="form-label">Class</label>
                        <select name="class_id" class="form-select" required onchange="this.form.submit()">
                            <option value="">Select Class</option>
                            <?php 
                            $classes->data_seek(0);
                            while ($class = $classes->fetch_assoc()): 
                            ?>
                            <option value="<?php echo $class['class_id']; ?>" 
                                    <?php echo $class['class_id'] == $class_id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Subject</label>
                        <select name="subject_id" class="form-select">
                            <option value="0">All Subjects</option>
                            <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo $subject['subject_id']; ?>" 
                                    <?php echo $subject['subject_id'] == $subject_id ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subject['subject_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Month</label>
                        <select name="month" class="form-select">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m == $month ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-1">
                        <label class="form-label">Year</label>
                        <select name="year" class="form-select">
                            <?php 
                            $current_year = date('Y');
                            for ($y = $current_year - 2; $y <= $current_year + 1; $y++): 
                            ?>
                            <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">Report Type</label>
                        <select name="report_type" class="form-select">
                            <option value="summary" <?php echo $report_type === 'summary' ? 'selected' : ''; ?>>
                                Summary Report
                            </option>
                            <option value="daily" <?php echo $report_type === 'daily' ? 'selected' : ''; ?>>
                                Daily Report
                            </option>
                            <option value="defaulters" <?php echo $report_type === 'defaulters' ? 'selected' : ''; ?>>
                                Low Attendance (&lt;75%)
                            </option>
                        </select>
                    </div>
                    
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search"></i> Generate Report
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if ($class_id > 0 && $class_info): ?>

<!-- Class Statistics -->
<?php if ($date_range_stats): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="fas fa-chart-pie"></i> 
                    <?php echo htmlspecialchars($class_info['class_name'] . ' - ' . $class_info['section']); ?> 
                    - <?php echo date('F Y', mktime(0, 0, 0, $month, 1, $year)); ?>
                </h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3">
                        <div class="p-3">
                            <i class="fas fa-users fa-2x text-primary mb-2"></i>
                            <h3 class="mb-0"><?php echo $date_range_stats['students_marked']; ?></h3>
                            <small class="text-muted">Students Marked</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3">
                            <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                            <h3 class="mb-0"><?php echo $date_range_stats['total_present']; ?></h3>
                            <small class="text-muted">Total Present</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3">
                            <i class="fas fa-times-circle fa-2x text-danger mb-2"></i>
                            <h3 class="mb-0"><?php echo $date_range_stats['total_absent']; ?></h3>
                            <small class="text-muted">Total Absent</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="p-3">
                            <i class="fas fa-percentage fa-2x text-info mb-2"></i>
                            <h3 class="mb-0">
                                <?php 
                                $class_rate = $date_range_stats['total_records'] > 0 
                                    ? ($date_range_stats['total_present'] / $date_range_stats['total_records']) * 100 
                                    : 0;
                                echo number_format($class_rate, 1); 
                                ?>%
                            </h3>
                            <small class="text-muted">Class Attendance Rate</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Report Content -->
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-table"></i> 
                    <?php 
                    switch ($report_type) {
                        case 'summary':
                            echo 'Attendance Summary';
                            break;
                        case 'daily':
                            echo 'Daily Attendance';
                            break;
                        case 'defaulters':
                            echo 'Low Attendance Students';
                            break;
                    }
                    
                    // Show subject name if filtering by subject
                    if ($subject_id > 0 && !empty($subjects)) {
                        foreach ($subjects as $subj) {
                            if ($subj['subject_id'] == $subject_id) {
                                echo ' - <span class="badge bg-primary">' . htmlspecialchars($subj['subject_name']) . '</span>';
                                break;
                            }
                        }
                    } else {
                        echo ' - <span class="badge bg-secondary">All Subjects</span>';
                    }
                    ?>
                </h5>
                <button onclick="window.print()" class="btn btn-light btn-sm">
                    <i class="fas fa-print"></i> Print Report
                </button>
            </div>
            <div class="card-body">
                <?php if ($students && $students->num_rows > 0): ?>
                
                <?php if ($report_type === 'summary' || $report_type === 'defaulters'): ?>
                <!-- Summary Report -->
                <div class="table-responsive">
                    <table class="table table-hover table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Roll No</th>
                                <th>Student Name</th>
                                <th class="text-center">Total Days</th>
                                <th class="text-center">Present</th>
                                <th class="text-center">Absent</th>
                                <th class="text-center">Late</th>
                                <th class="text-center">Excused</th>
                                <th class="text-center">Attendance %</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($student = $students->fetch_assoc()): 
                                $total_days = $student['total_days'] ?? 0;
                                $present_days = $student['present_days'] ?? 0;
                                $absent_days = $student['absent_days'] ?? 0;
                                $late_days = $student['late_days'] ?? 0;
                                $excused_days = $student['excused_days'] ?? 0;
                                
                                $attendance_rate = $total_days > 0 
                                    ? ($present_days / $total_days) * 100 
                                    : 0;
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['roll_number']); ?></td>
                                <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                <td class="text-center"><?php echo $total_days; ?></td>
                                <td class="text-center text-success"><strong><?php echo $present_days; ?></strong></td>
                                <td class="text-center text-danger"><strong><?php echo $absent_days; ?></strong></td>
                                <td class="text-center text-warning"><strong><?php echo $late_days; ?></strong></td>
                                <td class="text-center text-info"><strong><?php echo $excused_days; ?></strong></td>
                                <td class="text-center">
                                    <strong><?php echo number_format($attendance_rate, 1); ?>%</strong>
                                </td>
                                <td class="text-center">
                                    <?php if ($attendance_rate >= 90): ?>
                                    <span class="badge bg-success">Excellent</span>
                                    <?php elseif ($attendance_rate >= 75): ?>
                                    <span class="badge bg-primary">Good</span>
                                    <?php elseif ($attendance_rate >= 60): ?>
                                    <span class="badge bg-warning">Average</span>
                                    <?php else: ?>
                                    <span class="badge bg-danger">Poor</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php elseif ($report_type === 'daily' && isset($dates)): ?>
                <!-- Daily Report -->
                <?php if ($subject_id == 0): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <strong>Please select a specific subject</strong> to view the daily attendance report. 
                    Daily reports show day-by-day attendance for a single subject.
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-bordered table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Roll No</th>
                                <th>Student Name</th>
                                <?php foreach ($dates as $date): ?>
                                <th class="text-center" style="min-width: 80px;">
                                    <?php echo date('d M', strtotime($date)); ?><br>
                                    <small><?php echo date('D', strtotime($date)); ?></small>
                                </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $students->data_seek(0); // Reset pointer
                            while ($student = $students->fetch_assoc()): 
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['roll_number']); ?></td>
                                <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                <?php foreach ($dates as $date): 
                                    // Get attendance for this student on this date
                                    if ($subject_id > 0) {
                                        // Filter by specific subject
                                        $att_query = "SELECT status FROM attendance 
                                                     WHERE student_id = ? AND attendance_date = ? AND subject_id = ?";
                                        $stmt = $conn->prepare($att_query);
                                        $stmt->bind_param("isi", $student['student_id'], $date, $subject_id);
                                    } else {
                                        // All subjects - show if present in ANY subject
                                        $att_query = "SELECT status FROM attendance 
                                                     WHERE student_id = ? AND attendance_date = ? 
                                                     ORDER BY FIELD(status, 'present', 'late', 'excused', 'absent') LIMIT 1";
                                        $stmt = $conn->prepare($att_query);
                                        $stmt->bind_param("is", $student['student_id'], $date);
                                    }
                                    $stmt->execute();
                                    $att_result = $stmt->get_result();
                                    $attendance = $att_result->fetch_assoc();
                                    $stmt->close();
                                ?>
                                <td class="text-center">
                                    <?php if ($attendance): 
                                        switch ($attendance['status']) {
                                            case 'present':
                                                echo '<span class="badge bg-success">P</span>';
                                                break;
                                            case 'absent':
                                                echo '<span class="badge bg-danger">A</span>';
                                                break;
                                            case 'late':
                                                echo '<span class="badge bg-warning">L</span>';
                                                break;
                                            case 'excused':
                                                echo '<span class="badge bg-info">E</span>';
                                                break;
                                        }
                                    else:
                                        echo '<span class="text-muted">-</span>';
                                    endif; ?>
                                </td>
                                <?php endforeach; ?>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <div class="mt-3">
                        <small>
                            <strong>Legend:</strong> 
                            <span class="badge bg-success">P</span> Present | 
                            <span class="badge bg-danger">A</span> Absent | 
                            <span class="badge bg-warning">L</span> Late | 
                            <span class="badge bg-info">E</span> Excused
                        </small>
                    </div>
                </div>
                <?php endif; // end if subject_id > 0 ?>
                <?php endif; // end if daily report ?>
                
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-chart-bar fa-4x text-muted mb-3"></i>
                    <h5 class="text-muted">No Data Available</h5>
                    <p class="text-muted">No attendance records found for the selected criteria.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- No Class Selected -->
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-filter fa-4x text-muted mb-3"></i>
                <h5 class="text-muted">Select Filters</h5>
                <p class="text-muted">Please select a class and other filters to generate the attendance report.</p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
@media print {
    .navbar, .btn, .card-header button, form { display: none !important; }
    .card { border: 1px solid #000 !important; box-shadow: none !important; }
}
</style>

<?php include '../includes/footer.php'; ?>
