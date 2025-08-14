<?php
session_start();
require_once 'config.php';

// Access control: only admins can view this page
//if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
if (!isset($_SESSION['role']) || (!in_array($_SESSION['role'], ['admin', 'it_hod']))) { 
    header('Location: login.php');
    exit();
}

// Function to get IT HOD ID (assuming admin user is IT HOD)
function getItHodId($pdo) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'it_hod' LIMIT 1");
    $stmt->execute();
    return $stmt->fetchColumn();
}

$totalRequests = $pdo->query('SELECT COUNT(*) FROM requests')->fetchColumn();
$it_hod_id = getItHodId($pdo); // Get the ID of the logged-in admin (acting as IT HOD)

// Fetch all categories for dropdowns
$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

// Fetch all managers for dropdown
$managers = $pdo->query('SELECT id, username FROM users WHERE role = "manager" ORDER BY username')->fetchAll(PDO::FETCH_ASSOC);

$logged_in_user_id = $_SESSION['user_id'];
$logged_in_user_role = $_SESSION['role'];

// filtering 
$filter_category_id = $_GET['filter_category'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';

$where_clouses = ['r.user_id = ?'];
$params = [$_SESSION['user_id']];

if ($filter_category_id !== '') {
    $where_clouses[] = 'r.category_id = ?';
    $params[] = $filter_category_id;
}
if ($filter_status !== '') {
    $where_clouses[] = 'r.status = ?';
    $params[] = $filter_status;
}


?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Dashboard</title>
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
                      <?php if ($logged_in_user_role !== 'it_hod'): ?>
                        <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="admin_dashboard.php">Admin Dashboard</a>
                        </li>
                    <?php endif; ?>
                    <?php if ($logged_in_user_role === 'it_hod'): ?>
                        <li class="nav-item">
                            <a class="nav-link active" aria-current="page" href="#">IT HOD Actions</a>
                        </li>
                    <?php endif; ?>
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
        <h1 class="mb-4">Welcome,  <?php echo htmlspecialchars($_SESSION['username']); ?></h1>

        <!-- Summary Card 
        <div class="card p-4 shadow mb-5">
            <h2 class="h4 text-dark mb-0">Total Requests: <span class="badge bg-primary"><?php echo $totalRequests; ?></span></h2>
            <p class="mt-3 mb-0">You are the IT HOD for approvals.</p>
        </div> -->

 <!-- Summary Card -->
        <div class="card p-4 shadow mb-5">
            <h2 class="h4 text-dark mb-0">Total Requests: <span class="badge bg-primary"><?php echo $totalRequests; ?></span></h2>
            <?php if ($logged_in_user_role === 'it_hod'): ?>
                <h3 class="mt-3 mb-0">Requests Pending My IT HOD Approval: 
                    <?php
                    $stmt_it_hod_pending = $pdo->prepare('SELECT COUNT(*) FROM requests WHERE current_approver_id = ? AND status = "Approved by Manager"');
                    $stmt_it_hod_pending->execute([$logged_in_user_id]);
                    echo '<span class="badge bg-warning text-dark">' . $stmt_it_hod_pending->fetchColumn() . '</span>';
                    ?>
                </h3>
            <?php endif; ?>
        </div>
        
                        <!-- filter form-->
        <div class="card p-4 shadow mb-4">
            <h2 class="h4 text-dark mb-3">Filter Requests</h2>
            <form method="GET" action="index.php" class="row g-3">
                <div class="col-md-4">
                    <label for="filterCategory" class="form-label">Category</label>
                    <select name="filter_category" id="fitlerCategory" class="form-select">
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['id']); ?>"<?php echo ($filter_category_id == $cat['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>

                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="filterStatus" class="form-label">Status</label>
                    <select name="filter_status" id="filterStatus" class="form-select">
                        <option value="">AllStatuses</option>
                        <option value="Pending Manager" <?php echo ($filter_status == 'Pending Manager') ? 'selected' : ''; ?>>Pending Manager</option>
                        <option value="Approved by Manager" <?php echo ($filter_status == 'Approved by Manager') ? 'selected' : ''; ?>>Approved by Manager</option>
                        <option value="Pending IT HOD" <?php echo ($filter_status == 'Pending IT HOD') ? 'selected' : ''; ?>>Pending IT HOD</option>
                        <option value="Approved" <?php echo ($filter_status == 'Approved') ? 'selected' : ''; ?>>Approved</option>
                        <option value="Rejected" <?php echo ($filter_status == 'Rejected') ? 'selected' : ''; ?>>Rejected</option>

                    </select>
                </div>
            </form>
        </div>
        <!-- All Requests Table Card -->
        <div class="card p-4 shadow mb-5">
            <h3 class="mb-3">All Requests (Including IT HOD Approvals)</h3>
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
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $pdo->query('SELECT r.*, u.username, c.name as category_name, sc.name as subcategory_name, ca.username as current_approver_username
                                             FROM requests r 
                                             JOIN users u ON r.user_id = u.id 
                                             LEFT JOIN categories c ON r.category_id = c.id
                                             LEFT JOIN subcategories sc ON r.subcategory_id = sc.id
                                             LEFT JOIN users ca ON r.current_approver_id = ca.id
                                             ORDER BY r.created_at DESC');
                        
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
                            echo '<td>' . htmlspecialchars($request['current_approver_username'] ?? 'N/A') . '</td>';
                            echo '<td>';
                            if ($request['attachment_path']) {
                                echo '<a href="' . htmlspecialchars($request['attachment_path']) . '" target="_blank" class="btn btn-info btn-sm">View</a>';
                            } else {
                                echo 'N/A';
                            }
                            echo '</td>';
                            echo '<td>';
                            
                          /*  // IT HOD (Admin) approval buttons
                            if ($status === 'Approved by Manager' && $request['current_approver_id'] == $it_hod_id) {
                                echo '<form method="POST" action="backend.php" class="d-inline-block me-2">
                                          <input type="hidden" name="id" value="' . htmlspecialchars($request['id']) . '">
                                          <button type="submit" name="approve_request" class="btn btn-success btn-sm">Approve IT</button>
                                      </form>';
                                echo '<form method="POST" action="backend.php" class="d-inline-block">
                                          <input type="hidden" name="id" value="' . htmlspecialchars($request['id']) . '">
                                          <button type="submit" name="reject_request" class="btn btn-danger btn-sm">Reject IT</button>
                                      </form>';
                            }
                                      */
                            // IT HOD (specific role) approval buttons
                            if ($logged_in_user_role === 'it_hod' && $status === 'Approved by Manager'  && $request['current_approver_id'] == $logged_in_user_id) {
                                echo '<form method="POST" action="backend.php" class="d-inline-block me-2">
                                          <input type="hidden" name="id" value="' . htmlspecialchars($request['id']) . '">
                                          <button type="submit" name="approve_request" class="btn btn-success btn-sm">Approve IT</button>
                                      </form>';
                                echo '<form method="POST" action="backend.php" class="d-inline-block">
                                          <input type="hidden" name="id" value="' . htmlspecialchars($request['id']) . '">
                                          <button type="submit" name="reject_request" class="btn btn-danger btn-sm">Reject IT</button>
                                      </form>';
                            }
                             // Admin (original 'admin' role) can always delete any request
                            // IT HOD can also delete any request
                            if ($logged_in_user_role === 'admin' || $logged_in_user_role === 'it_hod') {
                                echo '<form method="POST" action="backend.php" class="d-inline-block ms-2">
                                          <input type="hidden" name="id" value="' . htmlspecialchars($request['id']) . '">
                                          <button type="submit" name="delete_request" class="btn btn-danger btn-sm">Delete</button>
                                      </form>';
                            }
                            echo '</td>';
                            echo '</tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($logged_in_user_role === 'admin'): // Only 'admin' role can manage users, categories, subcategories ?>
        <!-- User Management Card -->
        <div class="card p-4 shadow mb-5">
            <h3 class="mb-3">User Management</h3>
            <form method="POST" action="backend.php" class="mb-4" enctype="multipart/form-data">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="newUsername" class="form-label">Username</label>
                        <input type="text" name="username" id="newUsername" class="form-control" placeholder="New Username" required>
                    </div>
                    <div class="col-md-3">
                        <label for="newPassword" class="form-label">Password</label>
                        <input type="password" name="password" id="newPassword" class="form-control" placeholder="New Password" required>
                    </div>
                    <div class="col-md-2">
                        <label for="newRole" class="form-label">Role</label>
                        <select name="role" id="newRole" class="form-select" required>
                            <option value="user">User</option>
                            <option value="manager">Manager</option>
                            <option value="admin">Admin</option>
                            <option value="it_hod">IT HOD</option> <!-- New role option -->
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="reportingManager" class="form-label">Reporting Manager</label>
                        <select name="reporting_manager_id" id="reportingManager" class="form-select">
                            <option value="">None</option>
                            <?php foreach ($managers_and_it_hods as $manager_or_it_hod): // Use the combined list ?>
                                <option value="<?php echo htmlspecialchars($manager_or_it_hod['id']); ?>"><?php echo htmlspecialchars($manager_or_it_hod['username']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <button type="submit" name="create_user" class="btn btn-primary w-100">Add</button>
                    </div>
                </div>
            </form>
            
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-purple-header">
                        <tr>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Reporting Manager</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $users = $pdo->query('SELECT u.id, u.username, u.role, rm.username AS manager_username 
                                              FROM users u 
                                              LEFT JOIN users rm ON u.reporting_manager_id = rm.id
                                              ORDER BY u.username');
                        foreach ($users as $u) {
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($u['username']) . '</td>';
                            echo '<td>' . htmlspecialchars($u['role']) . '</td>';
                            echo '<td>' . htmlspecialchars($u['manager_username'] ?? 'N/A') . '</td>';
                            echo '<td>';
                            echo '<form method="POST" action="backend.php" class="d-inline-block">
                                      <input type="hidden" name="id" value="' . htmlspecialchars($u['id']) . '">
                                      <button type="submit" name="delete_user" class="btn btn-danger btn-sm">Delete</button>
                                  </form>';
                            echo '</td>';
                            echo '</tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Category Management Card -->
        <div class="card p-4 shadow mb-5">
            <h3 class="mb-3">Category Management</h3>
            <form method="POST" action="backend.php" class="mb-4" enctype="multipart/form-data">
                <div class="row g-3 align-items-end">
                    <div class="col-md-10">
                        <label for="categoryName" class="form-label">New Category Name</label>
                        <input type="text" name="category_name" id="categoryName" class="form-control" placeholder="e.g., Software" required>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" name="create_category" class="btn btn-primary w-100">Add Category</button>
                    </div>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-purple-header">
                        <tr>
                            <th>ID</th>
                            <th>Category Name</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $categories_list = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($categories_list as $cat) {
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($cat['id']) . '</td>';
                            echo '<td>' . htmlspecialchars($cat['name']) . '</td>';
                            echo '<td>';
                            echo '<form method="POST" action="backend.php" class="d-inline-block">
                                      <input type="hidden" name="id" value="' . htmlspecialchars($cat['id']) . '">
                                      <button type="submit" name="delete_category" class="btn btn-danger btn-sm">Delete</button>
                                  </form>';
                            echo '</td>';
                            echo '</tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Subcategory Management Card -->
        <div class="card p-4 shadow">
            <h3 class="mb-3">Subcategory Management</h3>
            <form method="POST" action="backend.php" class="mb-4" enctype="multipart/form-data">
                <div class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label for="parentCategory" class="form-label">Parent Category</label>
                        <select name="parent_category_id" id="parentCategory" class="form-select" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat['id']); ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label for="subcategoryName" class="form-label">New Subcategory Name</label>
                        <input type="text" name="subcategory_name" id="subcategoryName" class="form-control" placeholder="e.g., Wave" required>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" name="create_subcategory" class="btn btn-primary w-100">Add Subcategory</button>
                    </div>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-purple-header">
                        <tr>
                            <th>ID</th>
                            <th>Category</th>
                            <th>Subcategory Name</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $subcategories_list = $pdo->query('SELECT sc.id, sc.name, c.name as category_name 
                                                           FROM subcategories sc 
                                                           JOIN categories c ON sc.category_id = c.id 
                                                           ORDER BY c.name, sc.name')->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($subcategories_list as $subcat) {
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($subcat['id']) . '</td>';
                            echo '<td>' . htmlspecialchars($subcat['category_name']) . '</td>';
                            echo '<td>' . htmlspecialchars($subcat['name']) . '</td>';
                            echo '<td>';
                            echo '<form method="POST" action="backend.php" class="d-inline-block">
                                      <input type="hidden" name="id" value="' . htmlspecialchars($subcat['id']) . '">
                                      <button type="submit" name="delete_subcategory" class="btn btn-danger btn-sm">Delete</button>
                                  </form>';
                            echo '</td>';
                            echo '</tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; // End of admin-only sections ?>
        <!-- Export Requests Button (Admin only) -->
        <div class="card p-4 shadow mt-5">
            <h3 class="mb-3">Export Data</h3>
            <a href="export_requests.php" class="btn btn-success w-25">Export Requests to Excel (CSV)</a>
        </div>
    </div>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
