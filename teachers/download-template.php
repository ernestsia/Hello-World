<?php
// Start session and check authentication
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die('Access denied. Admin privileges required.');
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="teachers_import_template.csv"');
header('Pragma: no-cache');
header('Expires: 0');

// Create output stream
$output = fopen('php://output', 'w');

// Write headers
$headers = [
    'username',
    'email',
    'password',
    'first_name',
    'last_name',
    'date_of_birth',
    'gender',
    'address',
    'phone',
    'qualification',
    'experience_years',
    'joining_date',
    'salary'
];

fputcsv($output, $headers);

// Write sample data
$sample_data = [
    'john_teacher',
    'john.teacher@example.com',
    'password123',
    'John',
    'Anderson',
    '1985-03-20',
    'male',
    '789 Teacher Lane, City',
    '3456789012',
    'Master of Science in Mathematics',
    '10',
    date('Y-m-d'),
    '50000.00'
];

fputcsv($output, $sample_data);

// Add another sample
$sample_data2 = [
    'mary_teacher',
    'mary.wilson@example.com',
    'password456',
    'Mary',
    'Wilson',
    '1990-07-15',
    'female',
    '321 Education Street, Town',
    '4567890123',
    'Bachelor of Arts in English',
    '5',
    date('Y-m-d'),
    '45000.00'
];

fputcsv($output, $sample_data2);

fclose($output);
exit();
?>
