<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireRole('admin');

$pageTitle = 'User Management';
$db = new Database();
$conn = $db->getConnection();

// Get search and filter parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$role_filter = isset($_GET['role']) ? sanitize($_GET['role']) : '';
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';

// Build query
$where_conditions = [];
$params = [];
$types = '';

if (!empty($search)) {
    $where_conditions[] = "(username LIKE ? OR email LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

if (!empty($role_filter)) {
    $where_conditions[] = "role = ?";
    $params[] = $role_filter;
    $types .= 's';
}

if (!empty($status_filter)) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total count
$count_query = "SELECT COUNT(*) as total FROM users $where_clause";
$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_records = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$pagination = getPaginationData($total_records, $page, 20);

// Get users
$query = "SELECT user_id, username, email, role, status, created_at 
          FROM users 
          $where_clause 
          ORDER BY created_at DESC 
          LIMIT {$pagination['offset']}, {$pagination['records_per_page']}";
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users_result = $stmt->get_result();
$stmt->close();

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-users-cog"></i> User Management</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Users</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Statistics -->
<div class="row mb-4">
    <?php
    $stats_query = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
                        SUM(CASE WHEN role = 'teacher' THEN 1 ELSE 0 END) as teachers,
                        SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) as students,
                        SUM(CASE WHEN role = 'parent' THEN 1 ELSE 0 END) as parents,
                        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active
                    FROM users";
    $stats = $conn->query($stats_query)->fetch_assoc();
    ?>
    <div class="col-md-2 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="fas fa-users fa-2x text-primary mb-2"></i>
                <h4><?php echo $stats['total']; ?></h4>
                <p class="text-muted mb-0 small">Total Users</p>
            </div>
        </div>
    </div>
    <div class="col-md-2 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="fas fa-user-shield fa-2x text-danger mb-2"></i>
                <h4><?php echo $stats['admins']; ?></h4>
                <p class="text-muted mb-0 small">Admins</p>
            </div>
        </div>
    </div>
    <div class="col-md-2 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="fas fa-chalkboard-teacher fa-2x text-success mb-2"></i>
                <h4><?php echo $stats['teachers']; ?></h4>
                <p class="text-muted mb-0 small">Teachers</p>
            </div>
        </div>
    </div>
    <div class="col-md-2 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="fas fa-user-graduate fa-2x text-info mb-2"></i>
                <h4><?php echo $stats['students']; ?></h4>
                <p class="text-muted mb-0 small">Students</p>
            </div>
        </div>
    </div>
    <div class="col-md-2 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="fas fa-user-friends fa-2x text-warning mb-2"></i>
                <h4><?php echo $stats['parents']; ?></h4>
                <p class="text-muted mb-0 small">Parents</p>
            </div>
        </div>
    </div>
    <div class="col-md-2 mb-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center">
                <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                <h4><?php echo $stats['active']; ?></h4>
                <p class="text-muted mb-0 small">Active</p>
            </div>
        </div>
    </div>
</div>

<!-- Filters and Add Button -->
<div class="row mb-3">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-10">
                        <form method="GET" action="" class="row g-2">
                            <div class="col-md-4">
                                <input type="text" class="form-control" name="search" 
                                       placeholder="Search by username or email" 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="role">
                                    <option value="">All Roles</option>
                                    <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="teacher" <?php echo $role_filter === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                                    <option value="student" <?php echo $role_filter === 'student' ? 'selected' : ''; ?>>Student</option>
                                    <option value="parent" <?php echo $role_filter === 'parent' ? 'selected' : ''; ?>>Parent</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <select class="form-select" name="status">
                                    <option value="">All Status</option>
                                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search"></i> Filter
                                </button>
                            </div>
                        </form>
                    </div>
                    <div class="col-md-2">
                        <a href="<?php echo APP_URL;?>/users/add.php" class="btn btn-success w-100">
                            <i class="fas fa-plus"></i> Add User
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Users Table -->
<div class="row">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-list"></i> All Users
                    <span class="badge bg-light text-dark float-end"><?php echo $total_records; ?> Total</span>
                </h5>
            </div>
            <div class="card-body">
                <?php if ($users_result->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($user = $users_result->fetch_assoc()): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <?php
                                    $role_colors = [
                                        'admin' => 'danger',
                                        'teacher' => 'success',
                                        'student' => 'info',
                                        'parent' => 'warning'
                                    ];
                                    $color = $role_colors[$user['role']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $color; ?>">
                                        <?php echo ucfirst($user['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($user['status'] === 'active'): ?>
                                    <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                    <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo formatDate($user['created_at']); ?></td>
                                <td>
                                    <a href="<?php echo APP_URL;?>/users/edit.php?id=<?php echo $user['user_id']; ?>" 
                                       class="btn btn-sm btn-warning" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                    <a href="<?php echo APP_URL;?>/users/delete.php?id=<?php echo $user['user_id']; ?>" 
                                       class="btn btn-sm btn-danger" title="Delete"
                                       onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
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
                <?php if ($pagination['total_pages'] > 1): ?>
                <nav>
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $role_filter; ?>&status=<?php echo $status_filter; ?>">Previous</a>
                        </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $role_filter; ?>&status=<?php echo $status_filter; ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $pagination['total_pages']): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo $role_filter; ?>&status=<?php echo $status_filter; ?>">Next</a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                <?php endif; ?>
                
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-users fa-5x text-muted mb-3"></i>
                    <h4 class="text-muted">No Users Found</h4>
                    <p class="text-muted">Try adjusting your search or filters.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
