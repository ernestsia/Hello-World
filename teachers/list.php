<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireLogin();

$pageTitle = 'Teachers List';
$db = new Database();
$conn = $db->getConnection();

// Get current page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// Search functionality
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build query
$where = "WHERE 1=1";
if (!empty($search)) {
    $search_term = $conn->real_escape_string($search);
    $where .= " AND (t.first_name LIKE '%$search_term%' OR t.last_name LIKE '%$search_term%' OR t.phone LIKE '%$search_term%')";
}

// Get total records
$count_query = "SELECT COUNT(*) as total FROM teachers t $where";
$count_result = $conn->query($count_query);
$total_records = $count_result->fetch_assoc()['total'];

// Pagination
$pagination = getPaginationData($total_records, $page);

// Get teachers
$query = "SELECT t.*, u.status, u.email
          FROM teachers t
          LEFT JOIN users u ON t.user_id = u.user_id
          $where
          ORDER BY t.first_name, t.last_name
          LIMIT {$pagination['offset']}, {$pagination['records_per_page']}";
$result = $conn->query($query);

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-chalkboard-teacher"></i> Teachers Management</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Teachers</li>
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
                        <a href="<?php echo APP_URL;?>/teachers/list.php" class="btn btn-secondary">
                            <i class="fas fa-redo"></i> Reset
                        </a>
                        <?php if (hasRole('admin')): ?>
                        <a href="<?php echo APP_URL;?>/teachers/add.php" class="btn btn-success">
                            <i class="fas fa-plus"></i> Add Teacher
                        </a>
                        <a href="<?php echo APP_URL;?>/teachers/bulk-upload.php" class="btn btn-info">
                            <i class="fas fa-file-upload"></i> Bulk Upload
                        </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Teachers Table -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    Teachers List 
                    <span class="badge bg-primary"><?php echo $total_records; ?> Total</span>
                </h5>
            </div>
            <div class="card-body">
                <?php if ($result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Qualification</th>
                                <th>Experience</th>
                                <th>Joining Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($teacher = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['phone']); ?></td>
                                <td><?php echo htmlspecialchars($teacher['qualification']); ?></td>
                                <td><?php echo $teacher['experience_years'] . ' years'; ?></td>
                                <td><?php echo formatDate($teacher['joining_date']); ?></td>
                                <td>
                                    <?php if ($teacher['status'] === 'active'): ?>
                                    <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                    <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo APP_URL;?>/teachers/view.php?id=<?php echo $teacher['teacher_id']; ?>" 
                                       class="btn btn-sm btn-info" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if (hasRole('admin')): ?>
                                    <a href="<?php echo APP_URL;?>/teachers/assign-subjects.php?id=<?php echo $teacher['teacher_id']; ?>" 
                                       class="btn btn-sm btn-primary" title="Assign Subjects">
                                        <i class="fas fa-book"></i>
                                    </a>
                                    <a href="<?php echo APP_URL;?>/teachers/edit.php?id=<?php echo $teacher['teacher_id']; ?>" 
                                       class="btn btn-sm btn-warning" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="<?php echo APP_URL;?>/teachers/delete.php?id=<?php echo $teacher['teacher_id']; ?>" 
                                       class="btn btn-sm btn-danger" title="Delete"
                                       onclick="return confirm('Are you sure you want to delete this teacher?');">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php echo renderPagination($pagination, APP_URL . '/teachers/list.php' . (!empty($search) ? '&search=' . urlencode($search) : '')); ?>
                
                <?php else: ?>
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle"></i> No teachers found.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
