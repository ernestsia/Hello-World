<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/session.php';

requireLogin();

$pageTitle = 'Announcements';
$db = new Database();
$conn = $db->getConnection();

// Get current page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// Build query based on role
if (hasRole('admin') || hasRole('teacher')) {
    $where = "WHERE 1=1";
} else {
    $where = "WHERE (target_audience = 'all' OR target_audience = 'students') AND status = 'active'";
}

// Get total records
$count_query = "SELECT COUNT(*) as total FROM announcements $where";
$count_result = $conn->query($count_query);
$total_records = $count_result->fetch_assoc()['total'];

// Pagination
$pagination = getPaginationData($total_records, $page);

// Get announcements
$query = "SELECT a.*, u.username as posted_by_name
          FROM announcements a
          JOIN users u ON a.posted_by = u.user_id
          $where
          ORDER BY a.posted_date DESC
          LIMIT {$pagination['offset']}, {$pagination['records_per_page']}";
$result = $conn->query($query);

include '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h2><i class="fas fa-bullhorn"></i> Announcements</h2>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="<?php echo APP_URL;?>/index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Announcements</li>
            </ol>
        </nav>
    </div>
</div>

<?php if (hasRole('admin')): ?>
<div class="row mb-3">
    <div class="col-12 text-end">
        <a href="<?php echo APP_URL;?>/announcements/add.php" class="btn btn-success">
            <i class="fas fa-plus"></i> New Announcement
        </a>
    </div>
</div>
<?php endif; ?>

<div class="row">
    <div class="col-12">
        <?php if ($result->num_rows > 0): ?>
            <?php while ($announcement = $result->fetch_assoc()): ?>
            <div class="card mb-3">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-bullhorn"></i> <?php echo htmlspecialchars($announcement['title']); ?>
                        </h5>
                        <span class="badge bg-light text-dark">
                            <?php echo ucfirst($announcement['target_audience']); ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <p class="card-text"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            <i class="fas fa-user"></i> Posted by: <?php echo htmlspecialchars($announcement['posted_by_name']); ?> |
                            <i class="fas fa-calendar"></i> Date: <?php echo formatDate($announcement['posted_date']); ?>
                            <?php if ($announcement['expiry_date']): ?>
                            | <i class="fas fa-clock"></i> Expires: <?php echo formatDate($announcement['expiry_date']); ?>
                            <?php endif; ?>
                        </small>
                        <?php if (hasRole('admin')): ?>
                        <div>
                            <a href="<?php echo APP_URL;?>/announcements/edit.php?id=<?php echo $announcement['announcement_id']; ?>" 
                               class="btn btn-sm btn-warning">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <a href="<?php echo APP_URL;?>/announcements/delete.php?id=<?php echo $announcement['announcement_id']; ?>" 
                               class="btn btn-sm btn-danger"
                               onclick="return confirm('Are you sure you want to delete this announcement?');">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
            
            <!-- Pagination -->
            <?php echo renderPagination($pagination, APP_URL . '/announcements/index.php'); ?>
            
        <?php else: ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i> No announcements available.
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
