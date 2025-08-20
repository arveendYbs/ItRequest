<?php


session_start();
require_once 'config.php';


// Access control: only managers, admins, IT HODs can view this page
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['manager', 'admin', 'it_hod'])) {
    header('Location: login.php');
    exit();
}


$manager_id = $_SESSION['user_id'];
$logged_in_user_role = $_SESSION['role'];


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

// fetch all categories for the dropdown filters
$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

//fetch all companies for filters
$companies = $pdo->query('SELECT id, name FROM companies ORDER BY name')->fetchALL(PDO::FETCH_ASSOC);

// filtering logic
// filters by category, status, company, status = pending , approve , rejected or pending it hod, approved by manager 
$filter_category_id = $_GET['filter_category'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';
$filter_company_id = $_GET['filter_company'] ?? '';
$filter_department_type_id = $_GET['filter_department_type'] ?? '';

// base where clause for "req for my approvals
$my_approval_where_clauses = ['r.current_approver_id = ?', 'r.status = "Pending Manager"'];
$my_approval_params = [$manager_id];

if ($filter_category_id !== '') {
    $my_approval_where_clauses[] = 'r.category_id = ?';
    $my_approval_params[] = $filter_category_id;
}
if ($filter_company_id !== '') {
    $my_approval_where_clauses[] = 'u.company_id = ?';
    $my_approval_params[] = $filter_company_id;
}
if ($filter_department_type_id !== '') {
    $my_approval_where_clauses[] = 'u.department_type_id = ?';
    $my_approval_params[] = $filter_department_type_id;
}

$my_approval_where_sql = '';
if (!empty($my_approval_where_clauses)) {
    $my_approval_where_sql = ' WHERE ' . implode(' AND ', $my_approval_where_clauses);
}


$my_approval_where_sql = '';
if (!empty($my_approval_where_clauses)) {
    $my_approval_where_sql = ' WHERE ' . implode(' AND ', $my_approval_where_clauses);
}

// for all subordinates req for tracking table 
$tracking_where_clauses = ['u.reporting_manager_id = ?'];
$tracking_params = [$manager_id];

if($filter_category_id !== '') {
    $tracking_where_clauses[] = 'r.category_id = ?';
    $tracking_params[] = $filter_category_id;

}
if ($filter_status !== '') {
    $tracking_where_clauses[] = 'r.status = ?';
    $tracking_params[] = $filter_status;

}
if ($filter_company_id !== '') {
    $tracking_where_clauses[] = 'u.company_id = ?';
    $tracking_params[] = $filter_company_id;

}
if ($filter_department_type_id !== '') {
    $tracking_where_clauses[] = 'u.department_type_id = ?';
    $tracking_params[] = $filter_department_type_id;
        
}


$tracking_where_sql = '';
if (!empty($tracking_where_clauses)) {
    $tracking_where_sql = ' WHERE ' . implode(' AND ', $tracking_where_clauses);
}
//fetch dept type for filter dropdown 
$filter_department_types = [];
if ($filter_company_id !== ''){
    $stmt_filter_dept_types = $pdo->prepare('SELECT id, name FROM department_types WHERE company_id = ? ORDER BY name');
    $stmt_filter_dept_types->execute([$filter_company_id]);
    $filter_department_types = $stmt_filter_dept_types->fetchAll(PDO::FETCH_ASSOC);
}
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
                    <?php if (in_array($logged_in_user_role, ['admin', 'it_hod'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="admin_dashboard.php">Admin Dashboard</a>
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
        <h1 class="mb-4">Welcome, Manager <?php echo htmlspecialchars($_SESSION['username']); ?></h1>

        <!-- Summary Card -->
        <div class="card p-4 shadow mb-5">
            <h2 class="h4 text-dark mb-0">Requests Pending My Approval: <span class="badge bg-primary"><?php echo $pendingRequestsForMe; ?></span></h2>
            <p class="mt-3 mb-0">IT HOD: <?php echo htmlspecialchars($it_hod_username); ?> (ID: <?php echo htmlspecialchars($it_hod_id); ?>)</p>
        </div>

        <!-- Requests Pending My Approval Table Card -->
        <div class="card p-4 shadow mb-5">
            <h3 class="mb-3">Requests for My Approval (Pending Manager)</h3>
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
                            <th>Department Type</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Current Approver</th>
                            <th>Attachment</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                       // Fetch requests where THIS manager is the current approver AND status is Pending Manager
                        $stmt = $pdo->prepare('SELECT r.*, u.username, cpn.name as company_name, dt.name as department_type_name, cat.name as category_name, sc.name as subcategory_name, ca.username as current_approver_username
                                             FROM requests r 
                                             JOIN users u ON r.user_id = u.id 
                                             LEFT JOIN companies cpn ON u.company_id = cpn.id
                                             LEFT JOIN department_types dt ON u.department_type_id = dt.id
                                             LEFT JOIN categories cat ON r.category_id = cat.id
                                             LEFT JOIN subcategories sc ON r.subcategory_id = sc.id
                                             LEFT JOIN users ca ON r.current_approver_id = ca.id 
                                             ' . $my_approval_where_sql . ' ORDER BY r.created_at DESC'); // Using my_approval_where_sql
                        $stmt->execute($my_approval_params); // Using my_approval_params
                        
                        
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
                            
                         /*   // Buttons logic for manager: Only approve/reject if it's pending their approval
                            if ($status === 'Pending Manager' && $request['current_approver_id'] == $manager_id) {
                                echo '<form method="POST" action="backend.php" class="d-inline-block me-2">
                                          <input type="hidden" name="id" value="' . htmlspecialchars($request['id']) . '">
                                          <button type="submit" name="approve_request" class="btn btn-success btn-sm">Approve</button>
                                      </form>';
                                echo '<form method="POST" action="backend.php" class="d-inline-block">
                                          <input type="hidden" name="id" value="' . htmlspecialchars($request['id']) . '">
                                          <button type="submit" name="reject_request" class="btn btn-danger btn-sm">Reject</button>
                                      </form>';
                                echo '<form method="POST" action="backend.php" class="d-inline-block ms-2">
                                          <input type="hidden" name="id" value="' . htmlspecialchars($request['id']) . '">
                                          <button type="submit" name="delete_request" class="btn btn-danger btn-sm">Delete</button>
                                      </form>';
                            } else {
                                echo 'No actions required'; 
                            }
                                */
                            // Actions now link to view_request.php
                            echo '<a href="view_request.php?id=' . htmlspecialchars($request['id']) . '" class="btn btn-primary btn-sm">View</a>';
                            echo '</td>';                            echo '</tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
        

        <!-- filter form -->
         <div class="card p-4 shadow mb-4">
            <h2 class="h4 text-dark mb-3">Filter Requests</h2>
            <form method="GET" action="manager_dashboard.php" class="row g-3">
                <div class="col-md-3">
                    <label for="filterCategory" class="form-label">Category</label>
                    <select name="filter_category" id="filterCategory" class="form-select"> 
                        <option value=""> All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['id']); ?>" 
                            <?php echo ($filter_category_id == $cat['id']) ? 'selected' : ''; ''; ?>>
                            <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="filterStatus" class="form-label">Status</label>
                    <select name="filter_status" id="filterStatus" class="form-select"> 
                        <option value="">All Statuses</option>
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
                        <option value="">All Department Types</option>
                        <?php foreach ($filter_department_types as $dept_type): ?>
                            <option value="<?php echo htmlspecialchars($dept_type['id']); ?>" <?php echo ($filter_department_type_id == $dept_type['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept_type['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 d-flex justify-content-end">
                    <button type="submit" class="btn btn-purple">Apply Filters</button>
                    <a href="manager_dashboard.php" class="btn btn-secondary ms-2">Clear Filters</a>
                </div>
            </form>
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
                            <th>Company</th>
                            <th>Department</th>
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
                        // Fetch all requests where the user's reporting_manager_id matches the current manager's ID
                        $stmt_all_subordinates = $pdo->prepare('SELECT r.*, u.username, cpn.name as company_name, dt.name as department_type_name, cat.name as category_name, sc.name as subcategory_name, ca.username as current_approver_username
                                             FROM requests r 
                                             JOIN users u ON r.user_id = u.id 
                                             LEFT JOIN companies cpn ON u.company_id = cpn.id
                                             LEFT JOIN department_types dt ON u.department_type_id = dt.id
                                             LEFT JOIN categories cat ON r.category_id = cat.id
                                             LEFT JOIN subcategories sc ON r.subcategory_id = sc.id
                                             LEFT JOIN users ca ON r.current_approver_id = ca.id
                                             ' . $tracking_where_sql . ' ORDER BY r.created_at DESC'); // Using tracking_where_sql
                        $stmt_all_subordinates->execute($tracking_params); // Using tracking_params
                           
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
                            // No approval/reject actions here, this is a read-only tracking table.
                            // Only allow delete if the manager is allowed to delete this type of request.
                           /* if ($status === 'Pending Manager' && $request['current_approver_id'] == $manager_id) {
                                echo '<form method="POST" action="backend.php" class="d-inline-block">
                                          <input type="hidden" name="id" value="' . htmlspecialchars($request['id']) . '">
                                          <button type="submit" name="delete_request" class="btn btn-danger btn-sm">Delete</button>
                                      </form>';
                            } else {
                                echo 'N/A';
                            } */
                              // Actions now link to view_request.php
                            echo '<a href="view_request.php?id=' . htmlspecialchars($request['id']) . '" class="btn btn-primary btn-sm">View</a>';
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
<script>
        // JavaScript for dynamic department type dropdown in filters
        document.addEventListener('DOMContentLoaded', function() {
            const filterCompanySelect = document.getElementById('filterCompany');
            const filterDepartmentTypeSelect = document.getElementById('filterDepartmentType');

            function loadDepartmentTypes(companySelect, departmentTypeSelect, selectedDepartmentTypeId = null) {
                const companyId = companySelect.value;
                departmentTypeSelect.innerHTML = '<option value="">None</option>';
                departmentTypeSelect.disabled = true;

                if (companyId) {
                    fetch(`backend.php?action=get_department_types&company_id=${companyId}`) // Updated action and parameter
                        .then(response => response.json())
                        .then(data => {
                            if (data.length > 0) {
                                data.forEach(dept_type => {
                                    const option = document.createElement('option');
                                    option.value = dept_type.id;
                                    option.textContent = dept_type.name;
                                    if (selectedDepartmentTypeId && dept_type.id == selectedDepartmentTypeId) {
                                        option.selected = true;
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
                }
            }

            // Event listener for filter form company change
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

</body>
</html>
