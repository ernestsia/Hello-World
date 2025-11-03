<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/functions.php';

session_start();

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(APP_URL . '/index.php');
}

$error = '';
$timeout = isset($_GET['timeout']) ? true : false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        $db = new Database();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("SELECT user_id, username, password, email, role, status FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            if ($user['status'] !== 'active') {
                $error = 'Your account has been deactivated. Please contact administrator.';
            } elseif (verifyPassword($password, $user['password'])) {
                // Login successful
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['last_activity'] = time();
                $_SESSION['created'] = time();
                
                // Redirect to dashboard
                redirect(APP_URL . '/index.php');
            } else {
                $error = 'Invalid username or password';
            }
        } else {
            $error = 'Invalid username or password';
        }
        
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            background: linear-gradient(135deg, rgba(0,0,0,0.7) 0%, rgba(0,0,0,0.8) 100%), 
                        url('https://images.unsplash.com/photo-1523050854058-8df90110c9f1?w=1920') center/cover no-repeat fixed;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        /* Animated Background Elements */
        body::before {
            content: '📚';
            position: absolute;
            top: 10%;
            right: 10%;
            font-size: 80px;
            opacity: 0.15;
            z-index: 0;
            animation: float 6s ease-in-out infinite;
        }
        
        body::after {
            content: '🎓';
            position: absolute;
            bottom: 10%;
            left: 10%;
            font-size: 100px;
            opacity: 0.15;
            z-index: 0;
            animation: float 8s ease-in-out infinite reverse;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-30px); }
        }
        
        /* Additional floating icons */
        .floating-icons {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: 0;
            pointer-events: none;
        }
        
        .floating-icon {
            position: absolute;
            font-size: 60px;
            opacity: 0.1;
            animation: floatRandom 10s ease-in-out infinite;
        }
        
        .floating-icon:nth-child(1) { top: 20%; left: 15%; animation-delay: 0s; }
        .floating-icon:nth-child(2) { top: 60%; right: 20%; animation-delay: 2s; }
        .floating-icon:nth-child(3) { bottom: 30%; left: 25%; animation-delay: 4s; }
        .floating-icon:nth-child(4) { top: 40%; right: 15%; animation-delay: 6s; }
        
        @keyframes floatRandom {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            25% { transform: translateY(-20px) rotate(5deg); }
            50% { transform: translateY(-40px) rotate(-5deg); }
            75% { transform: translateY(-20px) rotate(3deg); }
        }
        
        .login-container {
            max-width: 480px;
            width: 100%;
            position: relative;
            z-index: 1;
            animation: fadeInUp 0.6s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            backdrop-filter: blur(10px);
        }
        
        .login-header {
            background: linear-gradient(135deg, #000000 0%, #1a1a1a 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
        }
        
        .login-header::after {
            content: '';
            position: absolute;
            bottom: -20px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 4px;
            background: white;
            border-radius: 2px;
        }
        
        .login-header .icon-wrapper {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            animation: pulse 2s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .login-header i {
            font-size: 40px;
        }
        
        .login-header h3 {
            font-weight: 700;
            margin-bottom: 5px;
            font-size: 1.8rem;
        }
        
        .login-header p {
            opacity: 0.9;
            font-size: 0.95rem;
        }
        
        .login-body {
            padding: 50px 40px 40px;
        }
        
        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }
        
        .input-group {
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            border-radius: 10px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .input-group:focus-within {
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
            transform: translateY(-2px);
        }
        
        .input-group-text {
            background: linear-gradient(135deg, #000000 0%, #1a1a1a 100%);
            border: none;
            color: white;
            padding: 12px 15px;
        }
        
        .form-control {
            border: none;
            padding: 12px 15px;
            font-size: 0.95rem;
        }
        
        .form-control:focus {
            box-shadow: none;
            outline: none;
        }
        
        .btn-login {
            background: linear-gradient(135deg, #000000 0%, #2a2a2a 100%);
            border: none;
            padding: 14px;
            font-weight: 600;
            letter-spacing: 0.5px;
            border-radius: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
        }
        
        .btn-login:hover {
            background: linear-gradient(135deg, #1a1a1a 0%, #000000 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.6);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .default-credentials {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            border-radius: 10px;
            padding: 15px;
            border-left: 4px solid #667eea;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            animation: slideInDown 0.4s ease-out;
        }
        
        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <!-- Floating Educational Icons -->
    <div class="floating-icons">
        <div class="floating-icon">📖</div>
        <div class="floating-icon">✏️</div>
        <div class="floating-icon">🎒</div>
        <div class="floating-icon">🏫</div>
    </div>
    
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="icon-wrapper">
                    <i class="fas fa-school"></i>
                </div>
                <h3 class="mb-0"><?php echo APP_NAME; ?></h3>
                <p class="mb-0 mt-2">Welcome back! Please login to your account</p>
            </div>
            <div class="login-body">
                <?php if ($timeout): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="fas fa-clock"></i> Your session has expired. Please login again.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" id="username" name="username" 
                                   placeholder="Enter your username" required autofocus
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="Enter your password" required>
                        </div>
                        <small class="text-muted">Leave it blank if you don't want to update the field agent password</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-login w-100">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </form>
                
                <div class="mt-4 default-credentials">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-info-circle text-primary me-2"></i>
                        <small class="text-muted mb-0">
                            <strong>Default Login:</strong> admin / admin123
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
