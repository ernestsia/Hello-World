<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireLogin();
requireRole('admin'); // Only admins can access this page

$pageTitle = 'Students Overview - Admin';
$db = new Database();
$conn = $db->getConnection();

// Get filter parameters
$class_filter = isset($_GET['class']) ? (int)$_GET['class'] : 0;
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build query
$where = "WHERE 1=1";
if (!empty($search)) {
    $search_term = $conn->real_escape_string($search);
    $where .= " AND (s.first_name LIKE '%$search_term%' OR s.last_name LIKE '%$search_term%' OR s.roll_number LIKE '%$search_term%')";
}
if ($class_filter > 0) {
    $where .= " AND s.class_id = $class_filter";
}

// Get students with attendance and grades statistics
$query = "SELECT s.student_id, s.first_name, s.last_name, s.roll_number, s.class_id,
          c.class_name, c.section,
          u.status,
          -- Attendance statistics
          COUNT(DISTINCT a.attendance_id) as total_attendance_days,
          SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_days,
          SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) as absent_days,
          SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_days,
          -- Grades statistics
          COUNT(DISTINCT g.grade_id) as total_exams,
          AVG(g.marks_obtained) as avg_marks,
          AVG((g.marks_obtained / e.total_marks) * 100) as avg_percentage
          FROM students s
          LEFT JOIN classes c ON s.class_id = c.class_id
          LEFT JOIN users u ON s.user_id = u.user_id
          LEFT JOIN attendance a ON s.student_id = a.student_id
          LEFT JOIN grades g ON s.student_id = g.student_id
          LEFT JOIN exams e ON g.exam_id = e.exam_id
          $where
          GROUP BY s.student_id, s.first_name, s.last_name, s.roll_number, s.class_id, c.class_name, c.section, u.status
          ORDER BY s.first_name, s.last_name";

$result = $conn->query($query);

// Get all classes for filter
$classes_result = $conn->query("SELECT class_id, class_name, section FROM classes ORDER BY class_name");

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-chart-line"></i> Students Overview - Admin Dashboard</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/students/list.php">Students</a></li>
                <li class="breadcrumb-item active">Admin Overview</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Search and Filter -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-5">
                        <input type="text" class="form-control" name="search" placeholder="Search by name or roll number" 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="class">
                            <option value="0">All Classes</option>
                            <?php while ($class = $classes_result->fetch_assoc()): ?>
                            <option value="<?php echo $class['class_id']; ?>" 
                                    <?php echo $class_filter == $class['class_id'] ? 'selected' : ''; ?>>
                                <?php echo $class['class_name'] . ' - ' . $class['section']; ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <a href="<?php echo APP_URL;?>/students/admin-overview.php" class="btn btn-secondary">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                        <a href="<?php echo APP_URL;?>/students/list.php" class="btn btn-info">
                            <i class="fas fa-list"></i> Regular View
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Students Overview Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-users"></i> All Students Information
                    <span class="badge bg-light text-dark"><?php echo $result->num_rows; ?> Students</span>
                </h5>
            </div>
            <div class="card-body">
                <?php if ($result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-sm">
                        <thead class="table-dark">
                            <tr>
                                <th>Roll No</th>
                                <th>Name</th>
                                <th>Class</th>
                                <th>Status</th>
                                <th colspan="3" class="text-center bg-info">Attendance</th>
                                <th colspan="3" class="text-center bg-success">Academic Performance</th>
                                <th>Actions</th>
                            </tr>
                            <tr>
                                <th colspan="4"></th>
                                <th class="text-center bg-info-subtle">Total Days</th>
                                <th class="text-center bg-info-subtle">Present</th>
                                <th class="text-center bg-info-subtle">Rate %</th>
                                <th class="text-center bg-success-subtle">Exams</th>
                                <th class="text-center bg-success-subtle">Avg Marks</th>
                                <th class="text-center bg-success-subtle">Avg %</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($student = $result->fetch_assoc()): 
                                $attendance_rate = $student['total_attendance_days'] > 0 
                                    ? ($student['present_days'] / $student['total_attendance_days']) * 100 
                                    : 0;
                                $avg_percentage = $student['avg_percentage'] ?? 0;
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($student['roll_number']); ?></strong></td>
                                <td>
                                    <a href="<?php echo APP_URL;?>/students/view.php?id=<?php echo $student['student_id']; ?>" 
                                       class="text-decoration-none">
                                        <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php 
                                    if ($student['class_name']) {
                                        echo htmlspecialchars($student['class_name'] . ' - ' . $student['section']);
                                    } else {
                                        echo '<span class="text-muted">Not Assigned</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($student['status'] === 'active'): ?>
                                    <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                    <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Attendance Data -->
                                <td class="text-center"><?php echo $student['total_attendance_days']; ?></td>
                                <td class="text-center">
                                    <span class="badge bg-success"><?php echo $student['present_days']; ?></span>
                                    <?php if ($student['absent_days'] > 0): ?>
                                    <span class="badge bg-danger"><?php echo $student['absent_days']; ?></span>
                                    <?php endif; ?>
                                    <?php if ($student['late_days'] > 0): ?>
                                    <span class="badge bg-warning"><?php echo $student['late_days']; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($student['total_attendance_days'] > 0): ?>
                                    <span class="badge bg-<?php echo $attendance_rate >= 75 ? 'success' : ($attendance_rate >= 50 ? 'warning' : 'danger'); ?>">
                                        <?php echo number_format($attendance_rate, 1); ?>%
                                    </span>
                                    <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Grades Data -->
                                <td class="text-center"><?php echo $student['total_exams']; ?></td>
                                <td class="text-center">
                                    <?php if ($student['total_exams'] > 0): ?>
                                    <?php echo number_format($student['avg_marks'], 1); ?>
                                    <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($student['total_exams'] > 0): ?>
                                    <span class="badge bg-<?php echo $avg_percentage >= 60 ? 'success' : ($avg_percentage >= 40 ? 'warning' : 'danger'); ?>">
                                        <?php echo number_format($avg_percentage, 1); ?>%
                                    </span>
                                    <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Actions -->
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="<?php echo APP_URL;?>/students/view.php?id=<?php echo $student['student_id']; ?>" 
                                           class="btn btn-info" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?php echo APP_URL;?>/students/edit.php?id=<?php echo $student['student_id']; ?>" 
                                           class="btn btn-warning" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Summary Statistics -->
                <?php
                $result->data_seek(0);
                $total_students = $result->num_rows;
                $active_students = 0;
                $total_attendance_rate = 0;
                $total_grade_avg = 0;
                $students_with_attendance = 0;
                $students_with_grades = 0;
                
                while ($student = $result->fetch_assoc()) {
                    if ($student['status'] === 'active') $active_students++;
                    
                    if ($student['total_attendance_days'] > 0) {
                        $students_with_attendance++;
                        $total_attendance_rate += ($student['present_days'] / $student['total_attendance_days']) * 100;
                    }
                    
                    if ($student['total_exams'] > 0) {
                        $students_with_grades++;
                        $total_grade_avg += $student['avg_percentage'];
                    }
                }
                
                $overall_attendance_rate = $students_with_attendance > 0 ? $total_attendance_rate / $students_with_attendance : 0;
                $overall_grade_avg = $students_with_grades > 0 ? $total_grade_avg / $students_with_grades : 0;
                ?>
                
                <div class="row mt-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h3><?php echo $total_students; ?></h3>
                                <p class="mb-0">Total Students</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h3><?php echo $active_students; ?></h3>
                                <p class="mb-0">Active Students</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h3><?php echo number_format($overall_attendance_rate, 1); ?>%</h3>
                                <p class="mb-0">Avg Attendance Rate</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center">
                                <h3><?php echo number_format($overall_grade_avg, 1); ?>%</h3>
                                <p class="mb-0">Avg Academic Performance</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php else: ?>
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle"></i> No students found.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Export Options -->
<div class="row mt-3">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title"><i class="fas fa-download"></i> Export Options</h6>
                <p class="text-muted mb-2">Export student data with attendance and grades information</p>
                <button class="btn btn-success btn-sm" onclick="exportToCSV()">
                    <i class="fas fa-file-csv"></i> Export to CSV
                </button>
                <button class="btn btn-danger btn-sm" onclick="window.print()">
                    <i class="fas fa-file-pdf"></i> Print/PDF
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function exportToCSV() {
    // Get table
    const table = document.querySelector('table');
    let csv = [];
    
    // Get headers
    const headers = [];
    table.querySelectorAll('thead tr:last-child th').forEach(th => {
        headers.push(th.textContent.trim());
    });
    csv.push(headers.join(','));
    
    // Get data rows
    table.querySelectorAll('tbody tr').forEach(row => {
        const rowData = [];
        row.querySelectorAll('td').forEach(td => {
            // Clean the text content
            let text = td.textContent.trim().replace(/\s+/g, ' ');
            // Escape commas and quotes
            if (text.includes(',') || text.includes('"')) {
                text = '"' + text.replace(/"/g, '""') + '"';
            }
            rowData.push(text);
        });
        csv.push(rowData.join(','));
    });
    
    // Create download link
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'students_overview_' + new Date().toISOString().split('T')[0] + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}
</script>

<style>
@media print {
    .btn, .breadcrumb, .card:last-child {
        display: none !important;
    }
    .table {
        font-size: 10px;
    }
}
</style>

<?php include '../includes/footer.php'; ?>
