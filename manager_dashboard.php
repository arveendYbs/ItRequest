<?php
session_start();
require_once 'config.php';

// Access control: only managers can view this page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'manager') {
    header('Location: login.php');
    exit();
}

$manager_id = $_SESSION['user_id'];

// Get total requests for this manager's subordinates that they need to approve
$stmt_total = $pdo->prepare('SELECT COUNT(r.id) FROM requests r WHERE r.current_approver_id = ? AND r.status = "Pending Manager"');
$stmt_total->execute([$manager_id]);
$pendingRequestsForMe = $stmt_total->fetchColumn();

// Get IT HOD ID and Username for display purposes (assuming admin user is IT HOD)
$stmt_it_hod = $pdo->prepare("SELECT id, username FROM users WHERE role = 'admin' LIMIT 1");
$stmt_it_hod->execute();
$it_hod_user = $stmt_it_hod->fetch(PDO::FETCH_ASSOC);
$it_hod_id = $it_hod_user['id'] ?? null;
$it_hod_username = $it_hod_user['username'] ?? 'N/A';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manager Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom Stylesheet -->
    <link rel="stylesheet" href="custom.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light bg-light shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">IT Request System</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="manager_dashboard.php">Manager Dashboard</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="btn btn-primary" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="container mt-5">
        <h1 class="mb-4">Welcome, Manager <?php echo htmlspecialchars($_SESSION['username']); ?></h1>

        <!-- Summary Card -->
        <div class="card p-4 shadow mb-5">
            <h2 class="h4 text-dark mb-0">Requests Pending My Approval: <span class="badge bg-primary"><?php echo $pendingRequestsForMe; ?></span></h2>
            <p class="mt-3 mb-0">IT HOD: <?php echo htmlspecialchars($it_hod_username); ?> (ID: <?php echo htmlspecialchars($it_hod_id); ?>)</p>
        </div>

        <!-- My Subordinates' Requests Table Card -->
        <div class="card p-4 shadow">
            <h3 class="mb-3">My Subordinates' Requests</h3>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-purple-header">
                        <tr>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Category</th>
                            <th>Subcategory</th>
                            <th>User</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Current Approver</th> <!-- Added this column -->
                            <th>Attachment</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Fetch all requests where the user's reporting_manager_id matches the current manager's ID
                        // Also join 'ca' (current approver alias) to get their username
                        $stmt = $pdo->prepare('SELECT r.*, u.username, c.name as category_name, sc.name as subcategory_name, ca.username as current_approver_username
                                             FROM requests r 
                                             JOIN users u ON r.user_id = u.id 
                                             LEFT JOIN categories c ON r.category_id = c.id
                                             LEFT JOIN subcategories sc ON r.subcategory_id = sc.id
                                             LEFT JOIN users ca ON r.current_approver_id = ca.id 
                                             WHERE r.current_approver_id = ? AND r.status = "Pending Manager"
                                             ORDER BY r.created_at DESC');
                        $stmt->execute([$manager_id]);
                        
                        while ($request = $stmt->fetch()) {
                            $status = trim($request['status']); 
                            $status_class = '';

                            switch ($status) {
                                case 'Approved':
                                    $status_class = 'bg-success';
                                    break;
                                case 'Pending Manager':
                                case 'Pending IT HOD':
                                    $status_class = 'bg-warning text-dark';
                                    break;
                                case 'Rejected':
                                    $status_class = 'bg-danger';
                                    break;
                                case 'Approved by Manager': 
                                    $status_class = 'bg-info'; 
                                    break;
                                default:
                                    $status_class = 'bg-secondary';
                                    break;
                            }
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($request['title']) . '</td>';
                            echo '<td>' . htmlspecialchars($request['description']) . '</td>';
                            echo '<td>' . htmlspecialchars($request['category_name'] ?? 'N/A') . '</td>';
                            echo '<td>' . htmlspecialchars($request['subcategory_name'] ?? 'N/A') . '</td>';
                            echo '<td>' . htmlspecialchars($request['username']) . '</td>';
                            echo '<td><span class="badge ' . $status_class . '">' . htmlspecialchars($status) . '</span></td>'; 
                            echo '<td>' . htmlspecialchars($request['priority']) . '</td>';
                            echo '<td>' . htmlspecialchars($request['current_approver_username'] ?? 'N/A') . '</td>'; /* Display current approver */
                            echo '<td>';
                            if ($request['attachment_path']) {
                                echo '<a href="' . htmlspecialchars($request['attachment_path']) . '" target="_blank" class="btn btn-info btn-sm">View</a>';
                            } else {
                                echo 'N/A';
                            }
                            echo '</td>';
                            echo '<td>';
                            
                            // Buttons logic for manager: Only approve/reject if it's pending their approval
                            if ($status === 'Pending Manager' && $request['current_approver_id'] == $manager_id) {
                                echo '<form method="POST" action="backend.php" class="d-inline-block me-2">
                                          <input type="hidden" name="id" value="' . htmlspecialchars($request['id']) . '">
                                          <button type="submit" name="approve_request" class="btn btn-success btn-sm">Approve</button>
                                      </form>';
                                echo '<form method="POST" action="backend.php" class="d-inline-block">
                                          <input type="hidden" name="id" value="' . htmlspecialchars($request['id']) . '">
                                          <button type="submit" name="reject_request" class="btn btn-danger btn-sm">Reject</button>
                                      </form>';
                                // Manager can delete pending requests they are assigned to approve
                                echo '<form method="POST" action="backend.php" class="d-inline-block ms-2">
                                          <input type="hidden" name="id" value="' . htmlspecialchars($request['id']) . '">
                                          <button type="submit" name="delete_request" class="btn btn-danger btn-sm">Delete</button>
                                      </form>';
                            } else {
                                echo 'No actions required'; // Requests not pending their approval
                            }
                            echo '</td>';
                            echo '</tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        
        <!-- All Subordinates' Requests (Read-only for tracking) -->
        <div class="card p-4 shadow mt-5">
            <h3 class="mb-3">All Subordinates' Requests (For Tracking)</h3>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-purple-header">
                        <tr>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Category</th>
                            <th>Subcategory</th>
                            <th>User</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Current Approver</th>
                            <th>Attachment</th>
                            <th>Actions</th> <!-- No approval actions here -->
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Fetch all requests where the user's reporting_manager_id matches the current manager's ID
                        // This table is for tracking purposes, not for active approval.
                        $stmt_all_subordinates = $pdo->prepare('SELECT r.*, u.username, c.name as category_name, sc.name as subcategory_name, ca.username as current_approver_username
                                             FROM requests r 
                                             JOIN users u ON r.user_id = u.id 
                                             LEFT JOIN categories c ON r.category_id = c.id
                                             LEFT JOIN subcategories sc ON r.subcategory_id = sc.id
                                             LEFT JOIN users ca ON r.current_approver_id = ca.id
                                             WHERE u.reporting_manager_id = ? 
                                             ORDER BY r.created_at DESC');
                        $stmt_all_subordinates->execute([$manager_id]);
                        
                        while ($request = $stmt_all_subordinates->fetch()) {
                            $status = trim($request['status']); 
                            $status_class = '';

                            switch ($status) {
                                case 'Approved':
                                    $status_class = 'bg-success';
                                    break;
                                case 'Pending Manager':
                                case 'Pending IT HOD':
                                    $status_class = 'bg-warning text-dark';
                                    break;
                                case 'Rejected':
                                    $status_class = 'bg-danger';
                                    break;
                                case 'Approved by Manager': 
                                    $status_class = 'bg-info'; 
                                    break;
                                default:
                                    $status_class = 'bg-secondary';
                                    break;
                            }
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($request['title']) . '</td>';
                            echo '<td>' . htmlspecialchars($request['description']) . '</td>';
                            echo '<td>' . htmlspecialchars($request['category_name'] ?? 'N/A') . '</td>';
                            echo '<td>' . htmlspecialchars($request['subcategory_name'] ?? 'N/A') . '</td>';
                            echo '<td>' . htmlspecialchars($request['username']) . '</td>';
                            echo '<td><span class="badge ' . $status_class . '">' . htmlspecialchars($status) . '</span></td>'; 
                            echo '<td>' . htmlspecialchars($request['priority']) . '</td>';
                            echo '<td>' . htmlspecialchars($request['current_approver_username'] ?? 'N/A') . '</td>'; 
                            echo '<td>';
                            if ($request['attachment_path']) {
                                echo '<a href="' . htmlspecialchars($request['attachment_path']) . '" target="_blank" class="btn btn-info btn-sm">View</a>';
                            } else {
                                echo 'N/A';
                            }
                            echo '</td>';
                            echo '<td>';
                            // No approval/reject actions here, this is a read-only tracking table.
                            // Only allow delete if the manager is allowed to delete this type of request.
                            if ($status === 'Pending Manager' && $request['current_approver_id'] == $manager_id) {
                                echo '<form method="POST" action="backend.php" class="d-inline-block">
                                          <input type="hidden" name="id" value="' . htmlspecialchars($request['id']) . '">
                                          <button type="submit" name="delete_request" class="btn btn-danger btn-sm">Delete</button>
                                      </form>';
                            } else {
                                echo 'N/A';
                            }
                            echo '</td>';
                            echo '</tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>


    </div>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
