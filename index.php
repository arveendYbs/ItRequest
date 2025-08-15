<?php
session_start();
require_once 'config.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$logged_in_user_id = $_SESSION['user_id'];
$logged_in_user_role = $_SESSION['role'];

//fetch all categrories fro the dropdown 
$categories = $pdo->query(
    'SELECT id, name FROM categories ORDER BY name')->fetchALL(PDO::FETCH_ASSOC);
$stmt_user_info = $pdo->prepare('SELECT c.name as company_name, dt.name as department_type_name
                                FROM users u 
                                LEFT JOIN companies c ON u.company_id = c.id
                                LEFT JOIN department_types dt ON u.department_type_id = dt.id
                                WHERE u.id = ?');
$stmt_user_info-> execute([$logged_in_user_id]);
$user_info = $stmt_user_info->fetch(PDO::FETCH_ASSOC);

$user_company = $user_info['company_name'] ?? 'N/A';
$user_department_type = $user_info['department_type_name'] ?? 'N/A';

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

$where_sql = ' WHERE ' . implode(' AND ', $where_clouses);
?>

<!DOCTYPE html>
<html>
<head>
    <title>IT Request System</title>
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
                        <a class="nav-link active" aria-current="page" href="index.php">Home</a>
                    </li>
                    <?php if (isset($_SESSION['role'])): ?>
                        <?php if ($_SESSION['role'] === 'manager'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="manager_dashboard.php">Manager Dashboard</a>
                            </li>
                        <?php endif; ?>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="admin_dashboard.php">Admin Dashboard</a>
                            </li>
                        <?php endif; ?>
                        <?php if ($_SESSION['role'] === 'it_hod'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="admin_dashboard.php">IT HOD Dashboard</a>
                            </li>
                        <?php endif; ?>
                    <?php endif; ?>
                </ul>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="btn btn-primary" href="logout.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
     <div class="container mt-5">
        <h1 class="mb-4">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?></h1>
        <p><strong>Role:</strong> <?php echo htmlspecialchars(ucfirst($logged_in_user_role)); ?></p>
        <p><strong>Company:</strong> <?php echo htmlspecialchars($user_company); ?></p>
        <p><strong>Department Type:</strong> <?php echo htmlspecialchars($user_department_type); ?></p>

       


        <!-- Create New Request Card -->
        <div class="card p-4 shadow mb-5">
            <h2 class="h4 text-dark mb-3">Create a New Request</h2>
            <form method="POST" action="backend.php" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="requestTitle" class="form-label">Request Title</label>
                    <input type="text" name="title" id="requestTitle" class="form-control" placeholder="Request Title" required>
                </div>
                <div class="mb-3">
                    <label for="requestDescription" class="form-label">Request Description</label>
                    <textarea name="description" id="requestDescription" class="form-control" placeholder="Request Description" rows="3" required></textarea>
                </div>
                <div class="mb-3">
                    <label for="categorySelect" class="form-label">Category</label>
                    <select name="category_id" id="categorySelect" class="form-select" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat['id']); ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="subcategorySelect" class="form-label">Subcategory</label>
                    <select name="subcategory_id" id="subcategorySelect" class="form-select" required disabled>
                        <option value="">Select Subcategory</option>
                    </select>
                </div>

                <!-- Priority drop down -->
                 <div class="mb-3">
                    <label for="requestPriority" class="form-label">Priority</label>
                    <select name="priority" id="requestPriority" class="form-select" required>
                        <option value="Low">Low</option>
                        <option value="Medium">Medium</option>
                        <option value="High">High</option>
                    </select>
                 </div>


                <div class="mb-3">
                    <label for="attachment" class="form-label">Attachment (Optional)</label>
                    <input type="file" name="attachment" id="attachment" class="form-control">
                    <small class="form-text text-muted">Max file size: 2MB. Allowed types: jpg, png, pdf.</small>
                </div>
                <button type="submit" name="create_request" class="btn btn-primary">Create Request</button>
            </form>
        </div>


                 <!-- filter form-->
        <div class="card p-4 shadow mb-4">
            <h2 class="h4 text-dark mb-3">Filter Requests</h2>
            <form method="GET" action="index.php" class="row g-3">
                <div class="col-md-4">
                    <label for="filterCategory" class="form-label">Category</label>
                    <select name="filter_category" id="fitlerCategory" class="form-select">
                        <option value="">All Category</option>
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
                        <option value="">All Statuses</option>
                        <option value="Pending Manager" <?php echo ($filter_status == 'Pending Manager') ? 'selected' : ''; ?>>Pending Manager</option>
                        <option value="Approved by Manager" <?php echo ($filter_status == 'Approved by Manager') ? 'selected' : ''; ?>>Approved by Manager</option>
                        <option value="Pending IT HOD" <?php echo ($filter_status == 'Pending IT HOD') ? 'selected' : ''; ?>>Pending IT HOD</option>
                        <option value="Approved" <?php echo ($filter_status == 'Approved') ? 'selected' : ''; ?>>Approved</option>
                        <option value="Rejected" <?php echo ($filter_status == 'Rejected') ? 'selected' : ''; ?>>Rejected</option>

                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-purple">Apply Filters</button>
                    <a href="index.php" class="btn btn-secondary ms-2">Clear Filters</a>
                </div>
            </form>
        </div>


        <!-- Your Requests Card -->
        <div class="card p-4 shadow">
            <h2 class="h4 text-dark mb-3">Your Requests</h2>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-purple-header">
                        <tr>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Category</th>
                            <th>Subcategory</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Attachment</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        //$stmt = $pdo->prepare('SELECT * FROM requests WHERE user_id = ? ORDER BY created_at DESC');
                       
                       /*$stmt = $pdo->prepare('SELECT r.*, c.name as category_name, sc.name as subcategory_name
                                            FROM requests r
                                            LEFT JOIN categories c ON r.category_id = c.id 
                                            LEFT JOIN subcategories sc ON r.subcategory_id = sc.id
                                            WHERE r.user_id = ? ORDER BY r.created_at DESC');
                         $stmt->execute([$_SESSION['user_id']]);
                         */
                         $stmt = $pdo->prepare('SELECT r.*, c.name as category_name, sc.name as subcategory_name, ca.username as current_approver_username 
                                             FROM requests r 
                                             LEFT JOIN categories c ON r.category_id = c.id
                                             LEFT JOIN subcategories sc ON r.subcategory_id = sc.id
                                             LEFT JOIN users ca ON r.current_approver_id = ca.id
                                             ' . $where_sql . ' ORDER BY r.created_at DESC');
                        $stmt->execute($params);
                        
                        while ($request = $stmt->fetch()) {
                            $status = trim($request['status']); 
                            $status_class = '';

                            switch ($request['status']) {
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
                                case 'Approved By Manager':
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
                            echo '<td><span class="badge ' . $status_class . '">' . htmlspecialchars($status) . '</span></td>'; 
                            echo '<td>' . htmlspecialchars($request['priority']) . '</td>';
                            echo '<td>' . htmlspecialchars($request['current-approver-username'] ?? 'N/A') . '</td>';
                            echo '<td>';
                              if ($request['attachment_path']) {
                                echo '<a href="' . htmlspecialchars($request['attachment_path']) . '" target="_blank" class="btn btn-info btn-sm">View</a>';
                            } else {
                                echo 'N/A';
                            }
                            echo '</td>';
                            echo '<td>';
                            // Show Edit and Delete buttons only if status is Pending
                            if ($status === 'Pending Manager' || $status === 'Pending IT HOD') {
                                echo '<a href="edit_request.php?id=' . htmlspecialchars($request['id']) . '" class="btn btn-secondary btn-sm me-2">Edit</a>';
                                echo '<form method="POST" action="backend.php" class="d-inline-block">
                                          <input type="hidden" name="id" value="' . htmlspecialchars($request['id']) . '">
                                          <button type="submit" name="delete_request" class="btn btn-danger btn-sm">Delete</button>
                                      </form>';
                            } else {
                                echo 'No actions';
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
    <!-- Bootstrap JS (optional, for some components like dropdowns) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
        // JavaScript for dynamic subcategory dropdown
        document.addEventListener('DOMContentLoaded', function() {
            const categorySelect = document.getElementById('categorySelect');
            const subcategorySelect = document.getElementById('subcategorySelect');

            categorySelect.addEventListener('change', function() {
                const categoryId = this.value;
                subcategorySelect.innerHTML = '<option value="">Loading...</option>'; // Clear and show loading
                subcategorySelect.disabled = true;

                if (categoryId) {
                    fetch(`backend.php?action=get_subcategories&category_id=${categoryId}`)
                        .then(response => response.json())
                        .then(data => {
                            subcategorySelect.innerHTML = '<option value="">Select Subcategory</option>';
                            if (data.length > 0) {
                                data.forEach(subcat => {
                                    const option = document.createElement('option');
                                    option.value = subcat.id;
                                    option.textContent = subcat.name;
                                    subcategorySelect.appendChild(option);
                                });
                                subcategorySelect.disabled = false;
                            } else {
                                subcategorySelect.innerHTML = '<option value="">No Subcategories Found</option>';
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching subcategories:', error);
                            subcategorySelect.innerHTML = '<option value="">Error loading subcategories</option>';
                        });
                } else {
                    subcategorySelect.innerHTML = '<option value="">Select Subcategory</option>';
                    subcategorySelect.disabled = true;
                }
            });
        });
    </script>

</body>
</html>
