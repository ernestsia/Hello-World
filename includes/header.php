<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' . APP_NAME : APP_NAME; ?></title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo APP_URL;?>/assets/css/style.css">
    
    <?php if (isset($additionalCSS)): ?>
        <?php echo $additionalCSS; ?>
    <?php endif; ?>
</head>
<body>
    <?php if (isLoggedIn()): ?>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark" style="background-color: #000000;">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo APP_URL;?>/index.php">
                <i class="fas fa-school"></i> <?php echo APP_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <?php if (hasRole('student')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL;?>/students/dashboard.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL;?>/students/my-grade-sheet.php">
                            <i class="fas fa-file-alt"></i> My Grade Sheet
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL;?>/students/my-grades.php">
                            <i class="fas fa-graduation-cap"></i> My Grades
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL;?>/students/my-attendance.php">
                            <i class="fas fa-calendar-check"></i> My Attendance
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL;?>/students/my-teachers.php">
                            <i class="fas fa-chalkboard-teacher"></i> My Teachers
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL;?>/announcements/index.php">
                            <i class="fas fa-bullhorn"></i> Announcements
                        </a>
                    </li>
                    <?php elseif (hasRole('parent')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL;?>/parents/dashboard.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL;?>/announcements/index.php">
                            <i class="fas fa-bullhorn"></i> Announcements
                        </a>
                    </li>
                    <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL;?>/index.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (hasRole('admin')): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="studentsDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-graduate"></i> Students
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo APP_URL;?>/students/list.php">All Students</a></li>
                            <li><a class="dropdown-item" href="<?php echo APP_URL;?>/students/admin-overview.php"><i class="fas fa-chart-line"></i> Admin Overview</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo APP_URL;?>/students/add.php">Add Student</a></li>
                        </ul>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="teachersDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-chalkboard-teacher"></i> Teachers
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo APP_URL;?>/teachers/list.php">All Teachers</a></li>
                            <li><a class="dropdown-item" href="<?php echo APP_URL;?>/teachers/admin-overview.php"><i class="fas fa-chart-bar"></i> Admin Overview</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo APP_URL;?>/teachers/add.php">Add Teacher</a></li>
                        </ul>
                    </li>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="classesDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-door-open"></i> Classes
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo APP_URL;?>/classes/list.php">All Classes</a></li>
                            <li><a class="dropdown-item" href="<?php echo APP_URL;?>/classes/add.php">Add Class</a></li>
                            <li><a class="dropdown-item" href="<?php echo APP_URL;?>/subjects/list.php">Subjects</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (hasRole('teacher') || hasRole('admin')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL;?>/attendance/reports.php">
                            <i class="fas fa-calendar-check"></i> Attendance
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if (!hasRole('student')): ?>
                    
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL;?>/grades/index.php">
                            <i class="fas fa-graduation-cap"></i> Grades
                        </a>
                    </li>
                    
                    <?php if (hasRole('admin')): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL;?>/announcements/index.php">
                            <i class="fas fa-bullhorn"></i> Announcements
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL;?>/users/list.php">
                            <i class="fas fa-users-cog"></i> User Management
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php endif; ?>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?php echo $_SESSION['username']; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?php echo APP_URL;?>/profile.php">
                                <i class="fas fa-user-circle"></i> Profile
                            </a></li>
                            <li><a class="dropdown-item" href="<?php echo APP_URL;?>/change-password.php">
                                <i class="fas fa-key"></i> Change Password
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo APP_URL;?>/logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <?php endif; ?>
    
    <!-- Main Content -->
    <div class="<?php echo isLoggedIn() ? 'container-fluid mt-4' : ''; ?>">
        <?php
        // Display flash messages
        $flash = getFlashMessage();
        if ($flash):
        ?>
        <div class="alert alert-<?php echo $flash['type']; ?> alert-dismissible fade show" role="alert">
            <?php echo $flash['message']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
