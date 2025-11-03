<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireLogin();
requireRole('admin'); // Only admins can access this page

$pageTitle = 'Teachers Overview - Admin';
$db = new Database();
$conn = $db->getConnection();

// Get filter parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build query
$where = "WHERE 1=1";
if (!empty($search)) {
    $search_term = $conn->real_escape_string($search);
    $where .= " AND (t.first_name LIKE '%$search_term%' OR t.last_name LIKE '%$search_term%' OR t.phone LIKE '%$search_term%')";
}

// Get teachers with their assignments and workload statistics
$query = "SELECT t.*, 
          u.status, u.email,
          -- Count classes where teacher is class teacher
          COUNT(DISTINCT c.class_id) as class_teacher_count,
          -- Count subjects teaching
          COUNT(DISTINCT cs.class_subject_id) as subjects_teaching_count,
          -- Count total classes teaching (including multiple sections)
          COUNT(DISTINCT CONCAT(cs.class_id, '-', cs.subject_id)) as total_teaching_assignments,
          -- Count students in classes they teach
          (SELECT COUNT(DISTINCT s.student_id) 
           FROM class_subjects cs2 
           JOIN students s ON cs2.class_id = s.class_id 
           WHERE cs2.teacher_id = t.teacher_id) as total_students_taught,
          -- Get list of classes teaching
          GROUP_CONCAT(DISTINCT cl.class_name ORDER BY cl.class_name SEPARATOR ', ') as classes_list
          FROM teachers t
          LEFT JOIN users u ON t.user_id = u.user_id
          LEFT JOIN classes c ON t.teacher_id = c.class_teacher_id
          LEFT JOIN class_subjects cs ON t.teacher_id = cs.teacher_id
          LEFT JOIN classes cl ON cs.class_id = cl.class_id
          $where
          GROUP BY t.teacher_id
          ORDER BY t.first_name, t.last_name";

$result = $conn->query($query);

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-chart-bar"></i> Teachers Overview - Admin Dashboard</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/index.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/teachers/list.php">Teachers</a></li>
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
                    <div class="col-md-8">
                        <input type="text" class="form-control" name="search" placeholder="Search by name or phone" 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <a href="<?php echo APP_URL;?>/teachers/admin-overview.php" class="btn btn-secondary">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                        <a href="<?php echo APP_URL;?>/teachers/list.php" class="btn btn-info">
                            <i class="fas fa-list"></i> Regular View
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Teachers Overview Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="fas fa-chalkboard-teacher"></i> All Teachers Information
                    <span class="badge bg-light text-dark"><?php echo $result->num_rows; ?> Teachers</span>
                </h5>
            </div>
            <div class="card-body">
                <?php if ($result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover table-sm">
                        <thead class="table-dark">
                            <tr>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Qualification</th>
                                <th>Experience</th>
                                <th>Status</th>
                                <th colspan="4" class="text-center bg-warning">Workload & Assignments</th>
                                <th>Actions</th>
                            </tr>
                            <tr>
                                <th colspan="5"></th>
                                <th class="text-center bg-warning-subtle">Class Teacher</th>
                                <th class="text-center bg-warning-subtle">Subjects</th>
                                <th class="text-center bg-warning-subtle">Classes</th>
                                <th class="text-center bg-warning-subtle">Students</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($teacher = $result->fetch_assoc()): 
                                // Calculate workload level
                                $workload_score = $teacher['total_teaching_assignments'] + ($teacher['class_teacher_count'] * 2);
                                $workload_level = 'Low';
                                $workload_color = 'success';
                                if ($workload_score >= 8) {
                                    $workload_level = 'High';
                                    $workload_color = 'danger';
                                } elseif ($workload_score >= 5) {
                                    $workload_level = 'Medium';
                                    $workload_color = 'warning';
                                }
                            ?>
                            <tr>
                                <td>
                                    <a href="<?php echo APP_URL;?>/teachers/view.php?id=<?php echo $teacher['teacher_id']; ?>" 
                                       class="text-decoration-none">
                                        <strong><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></strong>
                                    </a>
                                </td>
                                <td>
                                    <small>
                                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($teacher['email']); ?><br>
                                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($teacher['phone']); ?>
                                    </small>
                                </td>
                                <td><?php echo htmlspecialchars($teacher['qualification']); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $teacher['experience_years'] >= 5 ? 'success' : 'info'; ?>">
                                        <?php echo $teacher['experience_years']; ?> yrs
                                    </span>
                                </td>
                                <td>
                                    <?php if ($teacher['status'] === 'active'): ?>
                                    <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                    <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Workload Data -->
                                <td class="text-center">
                                    <?php if ($teacher['class_teacher_count'] > 0): ?>
                                    <span class="badge bg-primary"><?php echo $teacher['class_teacher_count']; ?></span>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($teacher['subjects_teaching_count'] > 0): ?>
                                    <span class="badge bg-info"><?php echo $teacher['subjects_teaching_count']; ?></span>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($teacher['total_teaching_assignments'] > 0): ?>
                                    <span class="badge bg-secondary"><?php echo $teacher['total_teaching_assignments']; ?></span>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($teacher['total_students_taught'] > 0): ?>
                                    <span class="badge bg-success"><?php echo $teacher['total_students_taught']; ?></span>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- Actions -->
                                <td>
                                    <div class="btn-group btn-group-sm" role="group">
                                        <a href="<?php echo APP_URL;?>/teachers/view.php?id=<?php echo $teacher['teacher_id']; ?>" 
                                           class="btn btn-info" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?php echo APP_URL;?>/teachers/assign-subjects.php?id=<?php echo $teacher['teacher_id']; ?>" 
                                           class="btn btn-primary" title="Assign Subjects">
                                            <i class="fas fa-book"></i>
                                        </a>
                                        <a href="<?php echo APP_URL;?>/teachers/edit.php?id=<?php echo $teacher['teacher_id']; ?>" 
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
                $total_teachers = $result->num_rows;
                $active_teachers = 0;
                $total_experience = 0;
                $total_workload = 0;
                $teachers_with_assignments = 0;
                $total_class_teachers = 0;
                
                while ($teacher = $result->fetch_assoc()) {
                    if ($teacher['status'] === 'active') $active_teachers++;
                    $total_experience += $teacher['experience_years'];
                    
                    if ($teacher['total_teaching_assignments'] > 0) {
                        $teachers_with_assignments++;
                        $total_workload += $teacher['total_teaching_assignments'];
                    }
                    
                    if ($teacher['class_teacher_count'] > 0) {
                        $total_class_teachers++;
                    }
                }
                
                $avg_experience = $total_teachers > 0 ? $total_experience / $total_teachers : 0;
                $avg_workload = $teachers_with_assignments > 0 ? $total_workload / $teachers_with_assignments : 0;
                ?>
                
                <div class="row mt-4">
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body text-center">
                                <h3><?php echo $total_teachers; ?></h3>
                                <p class="mb-0">Total Teachers</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body text-center">
                                <h3><?php echo $active_teachers; ?></h3>
                                <p class="mb-0">Active Teachers</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h3><?php echo number_format($avg_experience, 1); ?> yrs</h3>
                                <p class="mb-0">Avg Experience</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body text-center">
                                <h3><?php echo number_format($avg_workload, 1); ?></h3>
                                <p class="mb-0">Avg Workload</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Detailed Workload Analysis -->
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-secondary text-white">
                                <h6 class="mb-0"><i class="fas fa-tasks"></i> Workload Distribution</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span>Class Teachers:</span>
                                            <span class="badge bg-primary"><?php echo $total_class_teachers; ?></span>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span>Teachers with Assignments:</span>
                                            <span class="badge bg-info"><?php echo $teachers_with_assignments; ?></span>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span>Unassigned Teachers:</span>
                                            <span class="badge bg-warning"><?php echo $total_teachers - $teachers_with_assignments; ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php else: ?>
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle"></i> No teachers found.
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
                <p class="text-muted mb-2">Export teacher data with workload and assignment information</p>
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
    a.download = 'teachers_overview_' + new Date().toISOString().split('T')[0] + '.csv';
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
