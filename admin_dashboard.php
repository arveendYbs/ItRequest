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


$logged_in_user_id = $_SESSION['user_id'];
$logged_in_user_role = $_SESSION['role'];

// Fetch all categories for dropdowns
$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

// Fetch all managers and IT HOD for reporting manager dropdown
$managers_and_it_hods = $pdo->query('SELECT id, username FROM users WHERE role IN ("manager", "it_hod") ORDER BY username')->fetchAll(PDO::FETCH_ASSOC);

// Fetch all companies for dropdowns
$companies = $pdo->query('SELECT id, name FROM companies ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

// filtering 
$filter_category_id = $_GET['filter_category'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';
$filter_company_id = $_GET['filter_company'] ?? '';
$filter_department_type_id = $_GET['filter_department_type'] ?? '';

$where_clouses = [];
$params = [];

if ($filter_category_id !== '') {
    $where_clouses[] = 'r.category_id = ?';
    $params[] = $filter_category_id;
}
if ($filter_status !== '') {
    $where_clouses[] = 'r.status = ?';
    $params[] = $filter_status;
}
if ($filter_company_id !== '') {
    $where_clouses[] = 'u.company_id = ?';
    $params[] = $filter_company_id;

}
if ($filter_department_type_id !== '') {
    $where_clouses[] = 'u.department_type_id = ?';
    $params[] = $filter_department_type_id;
}

$where_sql = '';
if (!empty($where_clouses)) {
    $where_sql = ' WHERE ' . implode( ' AND ', $where_clouses);
}
// fetch dept types for filter dropdown 
$filter_department_type = [];
if ($filter_company_id !== '') {
    $stmt_filter_dept_types = $pdo->prepare('SELECT id, name FROM department_types WHERE company_id = ? ORDER BY name');
    $stmt_filter_dept_types->execute([$filter_company_id]);
    $filter_department_types = $stmt_filter_dept_types->fetchAll(PDO::FETCH_ASSOC);
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
                    $stmt_it_hod_pending = $pdo->prepare('SELECT COUNT(*) FROM requests WHERE current_approver_id = ? AND status = "Approved by Manager" OR status = "Pending IT HOD"');
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
                <div class="col-md-3"> 
                    <label for="filterCompany" class="form-label">Company</label>
                    <select name="filter_company" id="filterCompany" class="form-select">
                        <option value="">All Companies</option>
                        <?php foreach ($companies as $comp): ?>
                            <option value="<?php echo htmlspecialchars($comp['id']); ?>" <?php echo ($filter_company_id == $comp['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($comp['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3"> 
                    <label for="filterDepartmentType" class="form-label">Department Type</label>
                    <select name="filter_department_type" id="filterDepartmentType" class="form-select" <?php echo empty($filter_department_types) ? 'disabled' : ''; ?>>
                        <option value="">All Dept types</option>
                        <?php foreach ($filter_departement_types as $dept_type): ?>
                            <option value="<?php echo htmlspecialchars($dept_type['id']); ?>" <?php echo ($filter_department_type_id == $dept_type['id']) ? 'selected' : '';?>>
                            <?php echo htmlspecialchars($dept_type['name']); ?>
                            </option>
                            <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 d-flex justify-content-end">
                    <button type="submit" class="btn btn-purple">Apply Filter</button>
                    <a href="admin_dashboard.php" class="btn btn-secondary ms-2">Clear Filters</a>

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
                            <th>Company</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Current Approver</th>
                            <th>Attachment</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $pdo->prepare('SELECT r.*, u.username, cpn.name as company_name, dt.name as department_type_name, cat.name as category_name, sc.name as subcategory_name, ca.username as current_approver_username
                                             FROM requests r 
                                             JOIN users u ON r.user_id = u.id 
                                             LEFT JOIN companies cpn ON u.company_id = cpn.id
                                             LEFT JOIN department_types dt ON u.department_type_id = dt.id
                                             LEFT JOIN categories cat ON r.category_id = cat.id
                                             LEFT JOIN subcategories sc ON r.subcategory_id = sc.id
                                             LEFT JOIN users ca ON r.current_approver_id = ca.id
                                             ' . $where_sql . ' ORDER BY r.created_at DESC');
                        $stmt->execute($params);
                        
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
                            echo '<td>' . htmlspecialchars($request['company_name'] ?? 'N/A') . '</td>';
                            echo '<td>' . htmlspecialchars($request['department_type_name'] ?? 'N/A') . '</td>';

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
                            if ($logged_in_user_role === 'it_hod' && ($status === 'Approved by Manager' || $status === 'Pending IT HOD') && $request['current_approver_id'] == $logged_in_user_id) {
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
                    <div class="col-md-2"> 
                        <label for="newCompany" class="form-label">Company</label>
                        <select name="company_id" id="newCompany" class="form-select">
                            <option value="">None</option>
                            <?php foreach ($companies as $comp): ?>
                                <option value="<?php echo htmlspecialchars($comp['id']); ?>"><?php echo htmlspecialchars($comp['name']); ?></option>
                            <?php endforeach; ?>

                        </select>

                    </div>
                    <div class="col-md-2">
                        <label for="newDepartmentType" class="form-label">Department Type</label>
                        <select name="department_type_id" id="newDepartmentType" class="form-select" disabled>
                            <option value="">None</option>
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
                            <th>ID</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th>Company</th>
                            <th>Department Type</th>
                            <th>Reporting Manager</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $users = $pdo->query('SELECT u.id, u.username, u.role, cpn.name as company_name, dt.name as department_type_name, rm.username AS manager_username 
                                              FROM users u 
                                              LEFT JOIN companies cpn ON u.company_id = cpn.id
                                              LEFT JOIN department_types dt ON u.department_type_id = dt.id
                                              LEFT JOIN users rm ON u.reporting_manager_id = rm.id
                                              ORDER BY u.username');
                        foreach ($users as $u) {
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($u['id']) . '</td>';

                            echo '<td>' . htmlspecialchars($u['username']) . '</td>';
                            echo '<td>' . htmlspecialchars($u['role']) . '</td>';
                            echo '<td>' . htmlspecialchars($u['company_name'] ?? 'N/A') . '</td>';
                            echo '<td>' . htmlspecialchars($u['department_type_name'] ?? 'N/A')  . '</td>';

                            echo '<td>' . htmlspecialchars($u['manager_username'] ?? 'N/A') . '</td>';
                            echo '<td>';
                            // Add Edit User button
                            echo '<button type="button" class="btn btn-secondary btn-sm me-2 edit-user-btn" 
                                      data-bs-toggle="modal" data-bs-target="#editUserModal"
                                      data-id="' . htmlspecialchars($u['id']) . '"
                                      data-username="' . htmlspecialchars($u['username']) . '"
                                      data-role="' . htmlspecialchars($u['role']) . '"
                                      data-company-id="' . htmlspecialchars($u['company_id'] ?? '') . '"
                                      data-department-type-id="' . htmlspecialchars($u['department_type_id'] ?? '') . '"
                                      data-reporting-manager-id="' . htmlspecialchars($u['reporting_manager_id'] ?? '') . '">Edit</button>';
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

        
        <!-- Edit User Modal -->
        <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="backend.php">
                        <div class="modal-body">
                            <input type="hidden" name="user_id" id="editUserId">
                            <div class="mb-3">
                                <label for="editUsername" class="form-label">Username</label>
                                <input type="text" name="username" id="editUsername" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label for="editPassword" class="form-label">New Password (leave blank to keep current)</label>
                                <input type="password" name="password" id="editPassword" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label for="editRole" class="form-label">Role</label>
                                <select name="role" id="editRole" class="form-select" required>
                                    <option value="user">User</option>
                                    <option value="manager">Manager</option>
                                    <option value="admin">Admin</option>
                                    <option value="it_hod">IT HOD</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="editCompany" class="form-label">Company</label>
                                <select name="company_id" id="editCompany" class="form-select">
                                    <option value="">None</option>
                                    <?php foreach ($companies as $comp): // Changed from $divisions ?>
                                        <option value="<?php echo htmlspecialchars($comp['id']); ?>"><?php echo htmlspecialchars($comp['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="editDepartmentType" class="form-label">Department Type</label>
                                <select name="department_type_id" id="editDepartmentType" class="form-select" disabled>
                                    <option value="">None</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="editReportingManager" class="form-label">Reporting Manager</label>
                                <select name="reporting_manager_id" id="editReportingManager" class="form-select">
                                    <option value="">None</option>
                                    <?php foreach ($managers_and_it_hods as $manager_or_it_hod): ?>
                                        <option value="<?php echo htmlspecialchars($manager_or_it_hod['id']); ?>"><?php echo htmlspecialchars($manager_or_it_hod['username']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" name="update_user" class="btn btn-primary">Save changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>


        
        <!-- Company Management Card (Changed from Division Management) -->
        <div class="card p-4 shadow mb-5">
            <h3 class="mb-3">Company Management</h3>
            <form method="POST" action="backend.php" class="mb-4" enctype="multipart/form-data">
                <div class="row g-3 align-items-end">
                    <div class="col-md-10">
                        <label for="companyName" class="form-label">New Company Name</label>
                        <input type="text" name="company_name" id="companyName" class="form-control" placeholder="e.g., Facebook" required>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" name="create_company" class="btn btn-primary w-100">Add Company</button>
                    </div>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-purple-header">
                        <tr>
                            <th>ID</th>
                            <th>Company Name</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $companies_list = $pdo->query('SELECT id, name FROM companies ORDER BY name')->fetchAll(PDO::FETCH_ASSOC); // Changed table name
                        foreach ($companies_list as $comp) {
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($comp['id']) . '</td>';
                            echo '<td>' . htmlspecialchars($comp['name']) . '</td>';
                            echo '<td>';
                            echo '<form method="POST" action="backend.php" class="d-inline-block">
                                      <input type="hidden" name="id" value="' . htmlspecialchars($comp['id']) . '">
                                      <button type="submit" name="delete_company" class="btn btn-danger btn-sm">Delete</button>
                                  </form>';
                            echo '</td>';
                            echo '</tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Department Type Management Card (Changed from Department Management) -->
        <div class="card p-4 shadow mb-5">
            <h3 class="mb-3">Department Type Management</h3>
            <form method="POST" action="backend.php" class="mb-4" enctype="multipart/form-data">
                <div class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label for="parentCompany" class="form-label">Parent Company</label>
                        <select name="parent_company_id" id="parentCompany" class="form-select" required>
                            <option value="">Select Company</option>
                            <?php foreach ($companies as $comp): // Changed from $divisions ?>
                                <option value="<?php echo htmlspecialchars($comp['id']); ?>"><?php echo htmlspecialchars($comp['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label for="departmentTypeName" class="form-label">New Department Type Name</label>
                        <input type="text" name="department_type_name" id="departmentTypeName" class="form-control" placeholder="e.g., IT" required>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" name="create_department_type" class="btn btn-primary w-100">Add Department Type</button>
                    </div>
                </div>
            </form>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-purple-header">
                        <tr>
                            <th>ID</th>
                            <th>Company</th>
                            <th>Department Type Name</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $department_types_list = $pdo->query('SELECT dt.id, dt.name, cpn.name as company_name 
                                                           FROM department_types dt 
                                                           JOIN companies cpn ON dt.company_id = cpn.id 
                                                           ORDER BY cpn.name, dt.name')->fetchAll(PDO::FETCH_ASSOC); // Changed tables/columns
                        foreach ($department_types_list as $dt) {
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($dt['id']) . '</td>';
                            echo '<td>' . htmlspecialchars($dt['company_name']) . '</td>';
                            echo '<td>' . htmlspecialchars($dt['name']) . '</td>';
                            echo '<td>';
                            echo '<form method="POST" action="backend.php" class="d-inline-block">
                                      <input type="hidden" name="id" value="' . htmlspecialchars($dt['id']) . '">
                                      <button type="submit" name="delete_department_type" class="btn btn-danger btn-sm">Delete</button>
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
 <script>
        // Dynamic Department Type dropdown for User Creation/Edit forms
        document.addEventListener('DOMContentLoaded', function() {
            const newCompanySelect = document.getElementById('newCompany');
            const newDepartmentTypeSelect = document.getElementById('newDepartmentType');
            const editCompanySelect = document.getElementById('editCompany');
            const editDepartmentTypeSelect = document.getElementById('editDepartmentType');

            function loadDepartmentTypes(companySelect, departmentTypeSelect, selectedDepartmentTypeId = null) {
                console.log('--- loadDepartmentTypes Called ---');
                console.log('Company Select Element:', companySelect.id);
                console.log('Department Type Select Element:', departmentTypeSelect.id);
                console.log('Selected Department Type ID (initial):', selectedDepartmentTypeId, typeof selectedDepartmentTypeId);

                const companyId = companySelect.value;
                console.log('Company ID (from select.value):', companyId, typeof companyId);

                departmentTypeSelect.innerHTML = '<option value="">None</option>';
                departmentTypeSelect.disabled = true;

                if (companyId) {
                    fetch(`backend.php?action=get_department_types&company_id=${companyId}`)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP error! status: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            console.log('Fetched Department Types data:', data);
                            departmentTypeSelect.innerHTML = '<option value="">None</option>'; // Re-clear for new options
                            if (data.length > 0) {
                                data.forEach(dept_type => {
                                    const option = document.createElement('option');
                                    option.value = dept_type.id;
                                    option.textContent = dept_type.name;
                                    if (selectedDepartmentTypeId && String(dept_type.id) === String(selectedDepartmentTypeId)) { // Ensure string comparison
                                        option.selected = true;
                                        console.log('Pre-selected Department Type:', dept_type.name, 'with ID:', dept_type.id);
                                    }
                                    departmentTypeSelect.appendChild(option);
                                });
                                departmentTypeSelect.disabled = false;
                            } else {
                                departmentTypeSelect.innerHTML = '<option value="">No Department Types Found</option>';
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching department types:', error);
                            departmentTypeSelect.innerHTML = '<option value="">Error loading department types</option>';
                        });
                } else {
                    console.log('No Company ID selected, Department Type dropdown cleared and disabled.');
                    departmentTypeSelect.innerHTML = '<option value="">None</option>';
                    departmentTypeSelect.disabled = true;
                }
            }

            // Event listeners for New User form
            if (newCompanySelect && newDepartmentTypeSelect) {
                newCompanySelect.addEventListener('change', function() {
                    loadDepartmentTypes(newCompanySelect, newDepartmentTypeSelect);
                });
            }

            // Event listeners for Edit User modal
            if (editCompanySelect && editDepartmentTypeSelect) {
                editCompanySelect.addEventListener('change', function() {
                    // When company changes in edit modal, clear and reload department types
                    loadDepartmentTypes(editCompanySelect, editDepartmentTypeSelect);
                });
            }

            // Populate Edit User Modal
            const editUserModal = document.getElementById('editUserModal');
            if (editUserModal) {
                editUserModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    const userId = button.getAttribute('data-id');
                    const username = button.getAttribute('data-username');
                    const role = button.getAttribute('data-role');
                    const companyId = button.getAttribute('data-company-id');
                    const departmentTypeId = button.getAttribute('data-department-type-id');
                    const reportingManagerId = button.getAttribute('data-reporting-manager-id');

                    console.log('--- Edit User Modal Data (from data attributes) ---');
                    console.log('User ID:', userId);
                    console.log('Username:', username);
                    console.log('Role:', role);
                    console.log('Company ID:', companyId, typeof companyId); // Should be string or ""
                    console.log('Department Type ID:', departmentTypeId, typeof departmentTypeId); // Should be string or ""
                    console.log('Reporting Manager ID:', reportingManagerId, typeof reportingManagerId); // Should be string or ""

                    const modalUserId = editUserModal.querySelector('#editUserId');
                    const modalUsername = editUserModal.querySelector('#editUsername');
                    const modalRole = editUserModal.querySelector('#editRole');
                    const modalCompany = editUserModal.querySelector('#editCompany');
                    const modalDepartmentType = editUserModal.querySelector('#editDepartmentType');
                    const modalReportingManager = editUserModal.querySelector('#editReportingManager');

                    modalUserId.value = userId;
                    modalUsername.value = username;
                    
                    // Set Role dropdown value directly (usually reliable)
                    modalRole.value = role;

                    // Explicitly set selected option for Company dropdown
                    // Ensure that the 'None' option (value="") is also correctly handled
                    Array.from(modalCompany.options).forEach(option => {
                        option.selected = (String(option.value) === String(companyId)); // Robust string comparison
                        if (option.selected) {
                            console.log('Company: Selected option value:', option.value, 'text:', option.textContent);
                        }
                    });

                    // Explicitly set selected option for Reporting Manager dropdown
                    // Ensure that the 'None' option (value="") is also correctly handled
                    Array.from(modalReportingManager.options).forEach(option => {
                        option.selected = (String(option.value) === String(reportingManagerId)); // Robust string comparison
                        if (option.selected) {
                            console.log('Reporting Manager: Selected option value:', option.value, 'text:', option.textContent);
                        }
                    });

                    // Load department types for the edit modal, with existing department type selected
                    // This call relies on modalCompany.value being correctly set by the above loop
                    if (companyId) {
                        loadDepartmentTypes(modalCompany, modalDepartmentType, departmentTypeId);
                    } else {
                         // If no company is selected, clear and disable department type dropdown
                         modalDepartmentType.innerHTML = '<option value="">None</option>';
                         modalDepartmentType.disabled = true;
                         console.log('No company ID for edit user, Department Type dropdown cleared and disabled.');
                    }
                });
            }

            // Dynamic Subcategory dropdown for Request forms (kept for completeness, no changes here)
            const createCategorySelect = document.getElementById('categorySelect');
            const createSubcategorySelect = document.getElementById('subcategorySelect');
            
            function loadSubcategories(categorySelectElement, subcategorySelectElement, selectedSubcategoryId = null) {
                const categoryId = categorySelectElement.value;
                subcategorySelectElement.innerHTML = '<option value="">Loading...</option>';
                subcategorySelectElement.disabled = true;

                if (categoryId) {
                    fetch(`backend.php?action=get_subcategories&category_id=${categoryId}`)
                        .then(response => response.json())
                        .then(data => {
                            subcategorySelectElement.innerHTML = '<option value="">Select Subcategory</option>';
                            if (data.length > 0) {
                                data.forEach(subcat => {
                                    const option = document.createElement('option');
                                    option.value = subcat.id;
                                    option.textContent = subcat.name;
                                    if (selectedSubcategoryId && String(subcat.id) === String(selectedSubcategoryId)) {
                                        option.selected = true;
                                    }
                                    subcategorySelectElement.appendChild(option);
                                });
                                subcategorySelectElement.disabled = false;
                            } else {
                                subcategorySelectElement.innerHTML = '<option value="">No Subcategories Found</option>';
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching subcategories:', error);
                            subcategorySelectElement.innerHTML = '<option value="">Error loading subcategories</option>';
                        });
                } else {
                    subcategorySelectElement.innerHTML = '<option value="">Select Subcategory</option>';
                    subcategorySelectElement.disabled = true;
                }
            }

            if (createCategorySelect && createSubcategorySelect) {
                createCategorySelect.addEventListener('change', function() {
                    loadSubcategories(createCategorySelect, createSubcategorySelect);
                });
            }

            // Event listener for filter form company change (for dynamic department type filtering)
            const filterCompanySelect = document.getElementById('filterCompany');
            const filterDepartmentTypeSelect = document.getElementById('filterDepartmentType');
            if (filterCompanySelect && filterDepartmentTypeSelect) {
                 filterCompanySelect.addEventListener('change', function() {
                    loadDepartmentTypes(filterCompanySelect, filterDepartmentTypeSelect);
                });
                // Load department types on page load if a filter company is already selected
                const initialFilterCompanyId = filterCompanySelect.value;
                const initialFilterDepartmentTypeId = "<?php echo htmlspecialchars($filter_department_type_id); ?>";
                if (initialFilterCompanyId) {
                    loadDepartmentTypes(filterCompanySelect, filterDepartmentTypeSelect, initialFilterDepartmentTypeId);
                }
            }
        });
    </script>
</body>
</html>
