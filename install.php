<?php
/**
 * SchoolManagement System - Database Installation Script
 * This script will create the database and import all tables
 */

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'school_management';

// Connect to MySQL server (without database)
$conn = new mysqli($db_host, $db_user, $db_pass);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>SchoolManagement System - Installation</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 50px 0; }
        .install-card { max-width: 800px; margin: 0 auto; background: white; border-radius: 15px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        .log-item { padding: 10px; margin: 5px 0; border-radius: 5px; }
        .log-success { background: #d4edda; color: #155724; }
        .log-error { background: #f8d7da; color: #721c24; }
        .log-info { background: #d1ecf1; color: #0c5460; }
    </style>
</head>
<body>
<div class='container'>
    <div class='install-card'>
        <div class='card-header bg-primary text-white text-center py-4'>
            <h2><i class='fas fa-school'></i> SchoolManagement System</h2>
            <p class='mb-0'>Database Installation</p>
        </div>
        <div class='card-body p-4'>
            <div id='installation-log'>";

// Step 1: Create Database
echo "<div class='log-item log-info'><strong>Step 1:</strong> Creating database '$db_name'...</div>";

$sql = "CREATE DATABASE IF NOT EXISTS $db_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci";
if ($conn->query($sql) === TRUE) {
    echo "<div class='log-item log-success'>✓ Database '$db_name' created successfully!</div>";
} else {
    echo "<div class='log-item log-error'>✗ Error creating database: " . $conn->error . "</div>";
    exit();
}

// Select the database
$conn->select_db($db_name);

// Step 2: Read and execute SQL file
echo "<div class='log-item log-info'><strong>Step 2:</strong> Reading SQL file...</div>";

$sql_file = __DIR__ . '/database/school_management.sql';
if (!file_exists($sql_file)) {
    echo "<div class='log-item log-error'>✗ SQL file not found at: $sql_file</div>";
    exit();
}

$sql_content = file_get_contents($sql_file);
echo "<div class='log-item log-success'>✓ SQL file loaded successfully!</div>";

// Step 3: Execute SQL statements
echo "<div class='log-item log-info'><strong>Step 3:</strong> Executing SQL statements...</div>";

// Remove USE database statement and split by semicolon
$sql_content = preg_replace('/USE\s+`?school_management`?;/i', '', $sql_content);
$statements = array_filter(array_map('trim', explode(';', $sql_content)));

$success_count = 0;
$error_count = 0;

foreach ($statements as $statement) {
    if (empty($statement)) continue;
    
    if ($conn->query($statement) === TRUE) {
        $success_count++;
    } else {
        $error_count++;
        // Only show critical errors
        if (strpos($conn->error, 'already exists') === false) {
            echo "<div class='log-item log-error'>✗ Error: " . $conn->error . "</div>";
        }
    }
}

echo "<div class='log-item log-success'>✓ Executed $success_count SQL statements successfully!</div>";
if ($error_count > 0) {
    echo "<div class='log-item log-info'>ℹ Skipped $error_count statements (already exist)</div>";
}

// Step 4: Verify installation
echo "<div class='log-item log-info'><strong>Step 4:</strong> Verifying installation...</div>";

$tables = ['users', 'students', 'teachers', 'classes', 'subjects', 'attendance', 'grades', 'exams', 'announcements'];
$all_tables_exist = true;

foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        echo "<div class='log-item log-success'>✓ Table '$table' exists</div>";
    } else {
        echo "<div class='log-item log-error'>✗ Table '$table' not found</div>";
        $all_tables_exist = false;
    }
}

// Check if default admin user exists
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
$row = $result->fetch_assoc();
if ($row['count'] > 0) {
    echo "<div class='log-item log-success'>✓ Default admin user exists</div>";
} else {
    echo "<div class='log-item log-error'>✗ Default admin user not found</div>";
    $all_tables_exist = false;
}

echo "</div>"; // Close installation-log

// Final message
if ($all_tables_exist) {
    echo "
    <div class='alert alert-success mt-4'>
        <h4 class='alert-heading'>✓ Installation Completed Successfully!</h4>
        <hr>
        <p class='mb-0'>Your SchoolManagement System is ready to use.</p>
        <p class='mb-0'><strong>Default Login Credentials:</strong></p>
        <ul>
            <li>Username: <strong>admin</strong></li>
            <li>Password: <strong>admin123</strong></li>
        </ul>
        <hr>
        <a href='login.php' class='btn btn-primary'>Go to Login Page</a>
        <a href='index.php' class='btn btn-success'>Go to Dashboard</a>
    </div>
    <div class='alert alert-warning mt-3'>
        <strong>⚠ Security Notice:</strong> Please delete this install.php file after installation for security reasons.
    </div>";
} else {
    echo "
    <div class='alert alert-danger mt-4'>
        <h4 class='alert-heading'>✗ Installation Failed!</h4>
        <p>Some tables or data are missing. Please check the errors above and try again.</p>
        <a href='install.php' class='btn btn-danger'>Retry Installation</a>
    </div>";
}

echo "
        </div>
    </div>
</div>
<script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js'></script>
</body>
</html>";

$conn->close();
?>
