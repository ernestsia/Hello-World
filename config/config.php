<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'school_management');

// Application Configuration
define('APP_NAME', 'SchoolManagement System');
define('APP_URL', 'http://localhost/SchoolManagement');

// Email Configuration
define('MAIL_FROM_ADDRESS', 'noreply@schoolmanagement.com');
define('MAIL_FROM_NAME', 'School Management System');
define('MAIL_REPLY_TO', 'support@schoolmanagement.com');
define('MAIL_ADMIN_EMAIL', 'admin@school.com');

// SMTP Configuration (if using SMTP instead of mail())
/*
define('SMTP_HOST', 'smtp.example.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'user@example.com');
define('SMTP_PASSWORD', 'your-smtp-password');
define('SMTP_SECURE', 'tls'); // tls or ssl
*/

// Session Configuration
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds

// File Upload Configuration
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5242880); // 5MB in bytes
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif']);

// Pagination
define('RECORDS_PER_PAGE', 10);

// Date Format
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');
define('DISPLAY_DATE_FORMAT', 'd/m/Y');

// Timezone
date_default_timezone_set('UTC');

// Error Reporting (set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
