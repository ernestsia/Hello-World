<?php
// Start session and check authentication
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    die('Access denied. Admin privileges required.');
}

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="students_import_template.csv"');
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
    'roll_number',
    'date_of_birth',
    'gender',
    'address',
    'phone',
    'parent_name',
    'parent_phone',
    'parent_email',
    'admission_date',
    'class_id'
];

fputcsv($output, $headers);

// Write sample data
$sample_data = [
    'john_doe',
    'john.doe@example.com',
    'password123',
    'John',
    'Doe',
    'STU001',
    '2010-05-15',
    'male',
    '123 Main Street, City',
    '1234567890',
    'Jane Doe',
    '0987654321',
    'jane.doe@example.com',
    date('Y-m-d'),
    '1'
];

fputcsv($output, $sample_data);

// Add another sample
$sample_data2 = [
    'jane_smith',
    'jane.smith@example.com',
    'password456',
    'Jane',
    'Smith',
    'STU002',
    '2011-08-20',
    'female',
    '456 Oak Avenue, Town',
    '2345678901',
    'Robert Smith',
    '8765432109',
    'robert.smith@example.com',
    date('Y-m-d'),
    '2'
];

fputcsv($output, $sample_data2);

fclose($output);
exit();
?>
