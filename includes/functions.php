<?php
// Common functions for the application

/**
 * Get teacher ID from user ID
 */
function getTeacherId($conn, $user_id) {
    $stmt = $conn->prepare("SELECT teacher_id FROM teachers WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $teacher_id = null;
    if ($row = $result->fetch_assoc()) {
        $teacher_id = $row['teacher_id'];
    }
    $stmt->close();
    return $teacher_id;
}

/**
 * Get classes query based on user role
 */
function getClassesForUser($conn, $user_id) {
    if (hasRole('teacher')) {
        $teacher_id = getTeacherId($conn, $user_id);
        if ($teacher_id) {
            $stmt = $conn->prepare("SELECT class_id, class_name, section FROM classes WHERE class_teacher_id = ? ORDER BY class_name");
            $stmt->bind_param("i", $teacher_id);
            $stmt->execute();
            return $stmt->get_result();
        }
        return $conn->query("SELECT class_id, class_name, section FROM classes WHERE 1=0");
    }
    return $conn->query("SELECT class_id, class_name, section FROM classes ORDER BY class_name");
}

// Sanitize input data
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Validate email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Generate random password
function generatePassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $password;
}

// Hash password
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT);
}

// Verify password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Redirect function
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Check if user has specific role
function hasRole($role) {
    return isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

// Require login
function requireLogin() {
    if (!isLoggedIn()) {
        redirect(APP_URL . '/login.php');
    }
}

// Require specific role
function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        redirect(APP_URL . '/index.php');
    }
}

// Format date for display
function formatDate($date, $format = DISPLAY_DATE_FORMAT) {
    if (empty($date) || $date === '0000-00-00') {
        return 'N/A';
    }
    return date($format, strtotime($date));
}

// Calculate age from date of birth
function calculateAge($dob) {
    if (empty($dob) || $dob === '0000-00-00') {
        return 'N/A';
    }
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    $age = $birthDate->diff($today)->y;
    return $age;
}

// Upload file
function uploadFile($file, $targetDir, $allowedTypes = ALLOWED_IMAGE_TYPES) {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'No file uploaded or upload error'];
    }

    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'File size exceeds maximum allowed size'];
    }

    // Check file type
    $fileType = mime_content_type($file['tmp_name']);
    if (!in_array($fileType, $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type'];
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $targetPath = $targetDir . $filename;

    // Create directory if not exists
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => true, 'filename' => $filename, 'path' => $targetPath];
    } else {
        return ['success' => false, 'message' => 'Failed to move uploaded file'];
    }
}

// Delete file
function deleteFile($filepath) {
    if (file_exists($filepath)) {
        return unlink($filepath);
    }
    return false;
}

// Flash message functions
function setFlashMessage($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $flash = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $flash;
    }
    return null;
}

// Pagination helper
function getPaginationData($totalRecords, $currentPage = 1, $recordsPerPage = RECORDS_PER_PAGE) {
    $totalPages = ceil($totalRecords / $recordsPerPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $recordsPerPage;
    
    return [
        'total_records' => $totalRecords,
        'total_pages' => $totalPages,
        'current_page' => $currentPage,
        'records_per_page' => $recordsPerPage,
        'offset' => $offset
    ];
}

// Generate pagination HTML
function renderPagination($pagination, $baseUrl) {
    if ($pagination['total_pages'] <= 1) {
        return '';
    }
    
    $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
    
    // Previous button
    if ($pagination['current_page'] > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . ($pagination['current_page'] - 1) . '">Previous</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
    }
    
    // Page numbers
    for ($i = 1; $i <= $pagination['total_pages']; $i++) {
        if ($i == $pagination['current_page']) {
            $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . $i . '">' . $i . '</a></li>';
        }
    }
    
    // Next button
    if ($pagination['current_page'] < $pagination['total_pages']) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . ($pagination['current_page'] + 1) . '">Next</a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link">Next</span></li>';
    }
    
    $html .= '</ul></nav>';
    return $html;
}

// Get user full name
function getUserFullName($userId, $db) {
    $stmt = $db->prepare("SELECT u.role, 
                                  COALESCE(s.first_name, t.first_name) as first_name,
                                  COALESCE(s.last_name, t.last_name) as last_name
                           FROM users u
                           LEFT JOIN students s ON u.user_id = s.user_id
                           LEFT JOIN teachers t ON u.user_id = t.user_id
                           WHERE u.user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['first_name'] . ' ' . $row['last_name'];
    }
    return 'Unknown User';
}
?>
