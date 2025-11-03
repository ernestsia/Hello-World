<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/session.php';

requireLogin();

// Redirect students to their dashboard
if (hasRole('student')) {
    redirect(APP_URL . '/students/dashboard.php');
}

// Redirect parents to their dashboard
if (hasRole('parent')) {
    redirect(APP_URL . '/parents/dashboard.php');
}

$pageTitle = 'Dashboard';
$db = new Database();
$conn = $db->getConnection();

// Get statistics
$stats = [];

// Total students
$result = $conn->query("SELECT COUNT(*) as count FROM students");
$stats['students'] = $result->fetch_assoc()['count'];

// Total teachers
$result = $conn->query("SELECT COUNT(*) as count FROM teachers");
$stats['teachers'] = $result->fetch_assoc()['count'];

// Total classes
$result = $conn->query("SELECT COUNT(*) as count FROM classes");
$stats['classes'] = $result->fetch_assoc()['count'];

// Total subjects
$result = $conn->query("SELECT COUNT(*) as count FROM subjects");
$stats['subjects'] = $result->fetch_assoc()['count'];

// Today's attendance
$today = date('Y-m-d');
$result = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE attendance_date = '$today' AND status = 'present'");
$stats['present_today'] = $result->fetch_assoc()['count'];

// Recent announcements
$announcements = [];
if (hasRole('admin') || hasRole('teacher')) {
    $query = "SELECT * FROM announcements WHERE status = 'active' 
              AND (expiry_date IS NULL OR expiry_date >= CURDATE())
              ORDER BY posted_date DESC LIMIT 5";
} else {
    $query = "SELECT * FROM announcements WHERE status = 'active' 
              AND (target_audience = 'all' OR target_audience = 'students')
              AND (expiry_date IS NULL OR expiry_date >= CURDATE())
              ORDER BY posted_date DESC LIMIT 5";
}
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $announcements[] = $row;
}

include 'includes/header.php';
?>

<!-- Welcome Banner -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-lg welcome-banner-black">
            <div class="card-body text-white p-4">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h2 class="mb-2">
                            <i class="fas fa-hand-sparkles"></i> Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>!
                        </h2>
                        <p class="mb-0 opacity-75">
                            <i class="fas fa-calendar-day"></i> <?php echo date('l, F d, Y'); ?> | 
                            <i class="fas fa-user-shield"></i> Role: <?php echo ucfirst($_SESSION['role']); ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-end d-none d-md-block">
                        <i class="fas fa-school fa-5x opacity-25"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card border-0 shadow-sm h-100 stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary rounded-circle p-3">
                        <i class="fas fa-user-graduate fa-2x"></i>
                    </div>
                    <span class="badge bg-primary">Active</span>
                </div>
                <h6 class="text-muted mb-1">Total Students</h6>
                <h2 class="mb-0 fw-bold"><?php echo $stats['students']; ?></h2>
                <div class="mt-3">
                    <a href="<?php echo APP_URL;?>/students/list.php" class="text-primary text-decoration-none small">
                        View All <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card border-0 shadow-sm h-100 stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="stat-icon bg-success bg-opacity-10 text-success rounded-circle p-3">
                        <i class="fas fa-chalkboard-teacher fa-2x"></i>
                    </div>
                    <span class="badge bg-success">Active</span>
                </div>
                <h6 class="text-muted mb-1">Total Teachers</h6>
                <h2 class="mb-0 fw-bold"><?php echo $stats['teachers']; ?></h2>
                <div class="mt-3">
                    <a href="<?php echo APP_URL;?>/teachers/list.php" class="text-success text-decoration-none small">
                        View All <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card border-0 shadow-sm h-100 stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="stat-icon bg-info bg-opacity-10 text-info rounded-circle p-3">
                        <i class="fas fa-door-open fa-2x"></i>
                    </div>
                    <span class="badge bg-info">Total</span>
                </div>
                <h6 class="text-muted mb-1">Total Classes</h6>
                <h2 class="mb-0 fw-bold"><?php echo $stats['classes']; ?></h2>
                <div class="mt-3">
                    <a href="<?php echo APP_URL;?>/classes/list.php" class="text-info text-decoration-none small">
                        View All <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card border-0 shadow-sm h-100 stat-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning rounded-circle p-3">
                        <i class="fas fa-calendar-check fa-2x"></i>
                    </div>
                    <span class="badge bg-warning">Today</span>
                </div>
                <h6 class="text-muted mb-1">Present Today</h6>
                <h2 class="mb-0 fw-bold"><?php echo $stats['present_today']; ?></h2>
                <div class="mt-3">
                    <a href="<?php echo APP_URL;?>/attendance/index.php" class="text-warning text-decoration-none small">
                        View Details <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<?php if (hasRole('admin')): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-bolt"></i> Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <a href="<?php echo APP_URL;?>/students/add.php" class="btn btn-outline-primary w-100">
                            <i class="fas fa-user-plus"></i> Add Student
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="<?php echo APP_URL;?>/teachers/add.php" class="btn btn-outline-success w-100">
                            <i class="fas fa-user-tie"></i> Add Teacher
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="<?php echo APP_URL;?>/classes/add.php" class="btn btn-outline-info w-100">
                            <i class="fas fa-plus-circle"></i> Add Class
                        </a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="<?php echo APP_URL;?>/announcements/add.php" class="btn btn-outline-warning w-100">
                            <i class="fas fa-bullhorn"></i> New Announcement
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Recent Announcements -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-bullhorn"></i> Recent Announcements</h5>
            </div>
            <div class="card-body">
                <?php if (count($announcements) > 0): ?>
                <div class="list-group">
                    <?php foreach ($announcements as $announcement): ?>
                    <div class="list-group-item">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1"><?php echo htmlspecialchars($announcement['title']); ?></h6>
                            <small class="text-muted"><?php echo formatDate($announcement['posted_date']); ?></small>
                        </div>
                        <p class="mb-1"><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                        <small class="text-muted">
                            <i class="fas fa-users"></i> Target: <?php echo ucfirst($announcement['target_audience']); ?>
                        </small>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-muted mb-0">No announcements available.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
