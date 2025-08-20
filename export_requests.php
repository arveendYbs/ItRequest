<?php
session_start();
require_once 'config.php';
/*
// Access control: Only admins can export data
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}

*/
// Access control: Allow manager, admin, IT HOD to export data
if (!isset($_SESSION['role']) || (!in_array($_SESSION['role'], ['manager', 'admin', 'it_hod']))) {
    header('Location: login.php');
    exit();
}
// Fetch all requests with related data
$stmt = $pdo->query('SELECT 
                        r.id, 
                        u.username as requested_by, 
                        cpn.name as user_company,
                        dt.name as user_department_type,
                        r.title, 
                        r.description, 
                        c.name as category_name, 
                        sc.name as subcategory_name, 
                        r.status, 
                        r.priority,
                        ca.username as current_approver,
                        r.created_at
                     FROM requests r 
                     JOIN users u ON r.user_id = u.id 
                     LEFT JOIN companies cpn ON u.company_id = cpn.id
                     LEFT JOIN department_types dt ON u.department_type_id = dt.id 
                     LEFT JOIN categories c ON r.category_id = c.id
                     LEFT JOIN subcategories sc ON r.subcategory_id = sc.id
                     LEFT JOIN users ca ON r.current_approver_id = ca.id
                     ORDER BY r.created_at DESC');
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=it_requests_' . date('Y-m-d') . '.csv');

// Open the output stream
$output = fopen('php://output', 'w');

// Output the CSV header (column names)
fputcsv($output, array(
    'Request ID', 
    'Requested By', 
    'User Company',
    'Department',
    'Title', 
    'Description', 
    'Category', 
    'Subcategory', 
    'Status', 
    'Priority',
    'Current Approver',
    'Created At'
));

// Output data rows
foreach ($requests as $request) {
    // Replace nulls with empty strings or 'N/A' for better CSV readability
    $row = [
        $request['id'],
        $request['requested_by'],
        $request['Company'],
        $request['Department'],
        $request['title'],
        $request['description'],
        $request['category_name'] ?? 'N/A',
        $request['subcategory_name'] ?? 'N/A',
        $request['status'],
        $request['priority'],
      //$request['attachment_path'] ?? 'N/A',
        $request['current_approver'] ?? 'N/A',
        $request['created_at']
    ];
    fputcsv($output, $row);
}

// Close the output stream
fclose($output);
exit();
?>
