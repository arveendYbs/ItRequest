<?php
session_start();
require_once 'config.php';

// Access control: only admins can view this page
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit();
}


$totalRequests = $pdo->query('SELECT COUNT(*) FROM requests')->fetchColumn();
// Fetch all categories for dropdowns
$categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

// Fetch all managers for dropdown
$managers = $pdo->query('SELECT id, username FROM users WHERE role = "manager" ORDER BY username')->fetchAll(PDO::FETCH_ASSOC);


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
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" aria-current="page" href="admin_dashboard.php">Admin Dashboard</a>
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
        <h1 class="mb-4">Welcome, Admin <?php echo htmlspecialchars($_SESSION['username']); ?></h1>

        <!-- Total Requests Card -->
        <div class="card p-4 shadow mb-5">
            <h2 class="h4 text-dark mb-0">Total Requests: <span class="badge bg-primary"><?php echo $totalRequests; ?></span></h2>
        </div>

        <!-- All Requests Table Card -->
        <div class="card p-4 shadow mb-5">
            <h3 class="mb-3">All Requests</h3>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-purple-header">
                        <tr>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Category</th>
                            <th>SubCategory</th>
                            <th>User</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Attachment</th>

                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $stmt = $pdo->query('SELECT r.*, u.username, c.name as category_name, sc.name as subcategory_name
                        FROM requests r 
                        JOIN users u ON r.user_id = u.id 
                        LEFT JOIN categories c ON r.category_id = c.id
                        LEFT JOIN subcategories sc ON r.subcategory_id = sc.id
                        ORDER BY r.created_at DESC');
                        
                        while ($request = $stmt->fetch()) {
                            $status_class = '';
                            switch ($request['status']) {
                                case 'Approved':
                                    $status_class = 'bg-success';
                                    break;
                                case 'Pending':
                                    $status_class = 'bg-warning text-dark';
                                    break;
                                case 'Rejected':
                                    $status_class = 'bg-danger';
                                    break;
                                default:
                                    $status_class = 'bg-secondary';
                                    break;
                            }
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($request['title']) . '</td>';
                            echo '<td>' . htmlspecialchars($request['description']) . '</td>';
                            echo '<td>' . htmlspecialchars($request['category_name'] ?? 'N/A' ) . '</td>';
                            echo '<td>' . htmlspecialchars($request['subcategory_name'] ?? 'N/A' ) .  '</td>';

                            echo '<td>' . htmlspecialchars($request['username']) . '</td>';
                            echo '<td><span class="badge ' . $status_class . '">' . htmlspecialchars($request['status']) . '</span></td>';
                            echo '<td>' . htmlspecialchars($request['priority']) . '</td>';

                            echo '<td>';
                              if ($request['attachment_path']) {
                                echo '<a href="' . htmlspecialchars($request['attachment_path']) . '" target="_blank" class="btn btn-info btn-sm">View</a>';
                            } else {
                                echo 'N/A';
                            }

                            echo '</td>';
                            echo '<td>';
                            // Admin can always delete
                            echo '<form method="POST" action="backend.php" class="d-inline-block">
                                      <input type="hidden" name="id" value="' . htmlspecialchars($request['id']) . '">
                                      <button type="submit" name="delete_request" class="btn btn-danger btn-sm">Delete</button>
                                  </form>';
                            echo '</td>';
                            echo '</tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- User Management Card -->
        <div class="card p-4 shadow">
            <h3 class="mb-3">User Management</h3>
            <form method="POST" action="backend.php" class="mb-4">
                <div class="row g-3">
                    <div class="col-md-3">
                        <input type="text" name="username" class="form-control" placeholder="New Username" required>
                    </div>
                    <div class="col-md-3">
                        <input type="password" name="password" class="form-control" placeholder="New Password" required>
                    </div>
                    <div class="col-md-2">
                        <label for="newRole" class="form-label">Role</label>
                        <select name="role" id="newRole" class="form-select" required>
                            <option value="user">User</option>
                            <option value="manager">Manager</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="reportingManager" class="form-label">Reporting Manager</label>
                        <select name="reporting_manager_id" id="reportingManager" class="form-select">
                            <option value="">None</option>
                            <?php foreach ($managers as $manager): ?>
                                <option value="<?php echo htmlspecialchars($manager['id']); ?>"><?php echo htmlspecialchars($manager['username']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <button type="submit" name="create_user" class="btn btn-primary w-100">Create User</button>
                    </div>
                </div>
            </form>
            
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-purple-thead">
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
 echo '<tr>';               echo '<tr>';
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
        <div class="card p-4 shadow ">
            <h3 class="mb-3">Category Management</h3>
            <form method="POST" action="backend.php" class="mb-4">
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
<br> </br>

        <!-- Subcategory Management Card -->
        <div class="card p-4 shadow">
            <h3 class="mb-3">Subcategory Management</h3>
            <form method="POST" action="backend.php" class="mb-4">
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
    </div>
    <!-- Bootstrap JS (optional, for some components like dropdowns) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
    <footer>
        <p>&copy; 2025 IT Request System. by ArveendPhraseart.</p>
    </footer>
</html>
